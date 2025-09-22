<?php
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "car_rental_db";

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die($e->getMessage());
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please log in to access messages.";
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Count unread messages for the logged-in user
$unread_count = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as unread FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$unread_count = $result->fetch_assoc()['unread'];
$stmt->close();

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $car_id = filter_input(INPUT_POST, 'car_id', FILTER_SANITIZE_NUMBER_INT);
    $receiver_id = filter_input(INPUT_POST, 'receiver_id', FILTER_SANITIZE_NUMBER_INT);
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);

    if ($car_id && $receiver_id && $message && $user_id != $receiver_id) {
        $stmt = $conn->prepare("INSERT INTO messages (car_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $car_id, $user_id, $receiver_id, $message);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Message sent successfully!";
        } else {
            $_SESSION['error'] = "Failed to send message.";
        }
        $stmt->close();
        header("Location: messages.php?car_id=$car_id&seller_id=$receiver_id");
        exit();
    } else {
        $_SESSION['error'] = "Invalid message details.";
    }
}

// Handle chat deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_chat'])) {
    $car_id = filter_input(INPUT_POST, 'car_id', FILTER_SANITIZE_NUMBER_INT);
    $other_user_id = filter_input(INPUT_POST, 'other_user_id', FILTER_SANITIZE_NUMBER_INT);

    if ($car_id && $other_user_id) {
        $stmt = $conn->prepare("DELETE FROM messages WHERE car_id = ? AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))");
        $stmt->bind_param("iiiii", $car_id, $user_id, $other_user_id, $other_user_id, $user_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Chat deleted successfully!";
            header("Location: messages.php");
            exit();
        } else {
            $_SESSION['error'] = "Failed to delete chat.";
        }
        $stmt->close();
    }
}

// Fetch all chats for the user
$chats = [];
$stmt = $conn->prepare("
    SELECT m.car_id, c.brand, c.model, c.main_image, u.id AS other_user_id, u.username AS other_username,
           (SELECT message FROM messages m2 WHERE m2.car_id = m.car_id AND ((m2.sender_id = m.sender_id AND m2.receiver_id = m.receiver_id) OR (m2.sender_id = m.receiver_id AND m2.receiver_id = m.sender_id)) ORDER BY m2.created_at DESC LIMIT 1) AS last_message,
           (SELECT COUNT(*) FROM messages m3 WHERE m3.car_id = m.car_id AND m3.receiver_id = ? AND m3.is_read = 0) AS unread_count
    FROM messages m
    JOIN cars c ON m.car_id = c.id
    JOIN users u ON (u.id = m.sender_id OR u.id = m.receiver_id) AND u.id != ?
    WHERE (m.sender_id = ? OR m.receiver_id = ?)
    GROUP BY m.car_id, u.id
    ORDER BY m.created_at DESC
");
$stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $chats[] = $row;
}
$stmt->close();

// Fetch messages for the selected chat
$messages = [];
$selected_car_id = filter_input(INPUT_GET, 'car_id', FILTER_SANITIZE_NUMBER_INT);
$selected_seller_id = filter_input(INPUT_GET, 'seller_id', FILTER_SANITIZE_NUMBER_INT);
$other_username = '';
$car_details = null;
$is_sold = false;
$seller_username = '';

if ($selected_car_id && $selected_seller_id) {
    // Fetch car details and sold status
    $stmt = $conn->prepare("SELECT c.brand, c.model, c.main_image, c.is_sold, u.username AS seller_username 
                            FROM cars c 
                            JOIN users u ON c.seller_id = u.id 
                            WHERE c.id = ?");
    $stmt->bind_param("i", $selected_car_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $car_details = $result->fetch_assoc();
    if ($car_details) {
        $is_sold = $car_details['is_sold'];
        $seller_username = $car_details['seller_username'];
    }
    $stmt->close();

    // Fetch other user's username
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $selected_seller_id);
    $stmt->execute();
    $other_username = $stmt->get_result()->fetch_assoc()['username'];
    $stmt->close();

    // Fetch messages
    $stmt = $conn->prepare("
        SELECT m.id, m.sender_id, m.message, m.created_at, m.is_read, u.username
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.car_id = ? AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
        ORDER BY m.created_at ASC
    ");
    $stmt->bind_param("iiiii", $selected_car_id, $user_id, $selected_seller_id, $selected_seller_id, $user_id);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Mark messages as read
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE car_id = ? AND receiver_id = ? AND is_read = 0");
    $stmt->bind_param("ii", $selected_car_id, $user_id);
    $stmt->execute();
    $stmt->close();

    // Group messages by date
    $grouped_messages = [];
    foreach ($messages as $message) {
        $date = date('Y-m-d', strtotime($message['created_at']));
        $grouped_messages[$date][] = $message;
    }
}
?>

<?php include 'header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - CarBazaar</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --accent: #4895ef;
            --dark: #1b263b;
            --light: #f8f9fa;
            --success: #4cc9f0;
            --warning: #f8961e;
            --danger: #f72585;
            --gray: #6c757d;
            --light-gray: #e9ecef;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light);
            color: var(--dark);
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header Styles */
        header {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .logo-icon {
            font-size: 28px;
            color: var(--primary);
            margin-right: 10px;
        }

        .logo-text {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
        }

        .logo-text span {
            color: var(--primary);
        }

        nav ul {
            display: flex;
            list-style: none;
        }

        nav ul li {
            margin-left: 25px;
            position: relative;
        }

        nav ul li a {
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            display: flex;
            align-items: center;
        }

        nav ul li a i {
            margin-right: 8px;
            font-size: 18px;
        }

        nav ul li a:hover {
            color: var(--primary);
        }

        nav ul li .badge {
            position: absolute;
            top: -8px;
            right: -10px;
            background-color: var(--danger);
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .user-actions {
            display: flex;
            align-items: center;
        }

        .user-greeting {
            margin-right: 20px;
            font-weight: 500;
            color: var(--dark);
        }

        .user-greeting span {
            color: var(--primary);
            font-weight: 600;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            border: none;
            outline: none;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.2);
        }

        .btn-outline {
            background-color: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-outline:hover {
            background-color: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        .btn-danger {
            background-color: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background-color: #d1145a;
            transform: translateY(-2px);
        }

        /* Messages Container */
        .messages-container {
            display: flex;
            max-width: 1200px;
            margin: 50px auto;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            animation: slideIn 0.5s ease;
            min-height: 500px;
        }

        .chat-sidebar {
            width: 300px;
            border-right: 1px solid var(--light-gray);
            padding: 20px;
            overflow-y: auto;
        }

        .chat-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            position: relative;
            margin-bottom: 10px;
        }

        .chat-item:hover, .chat-item.active {
            background-color: var(--light-gray);
        }

        .chat-item img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }

        .chat-info {
            flex: 1;
        }

        .chat-info h4 {
            font-size: 14px;
            color: var(--dark);
            margin: 0;
            font-weight: 600;
        }

        .chat-info p {
            font-size: 12px;
            color: var(--gray);
            margin: 5px 0 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }

        .chat-item .badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: var(--danger);
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 20px;
        }

        .chat-header {
            display: flex;
            align-items: center;
            gap: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
            margin-bottom: 20px;
        }

        .chat-header img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }

        .chat-header h3 {
            font-size: 18px;
            color: var(--dark);
            margin: 0;
        }

        .chat-header p {
            font-size: 14px;
            color: var(--gray);
            margin: 5px 0 0;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
            max-height: 400px;
        }

        .date-divider {
            text-align: center;
            margin: 10px auto;
            font-size: 12px;
            color: var(--gray);
            background-color: var(--light-gray);
            padding: 5px 15px;
            border-radius: 12px;
            display: block;
            width: fit-content;
            line-height: 1.5;
        }

        .message {
            display: flex;
            margin-bottom: 10px;
            max-width: 70%;
        }

        .message.sent {
            margin-left: auto;
            flex-direction: row-reverse;
        }

        .message.received {
            margin-right: auto;
        }

        .message-content {
            padding: 10px 15px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.5;
            display: flex;
            flex-direction: column;
        }

        .message.sent .message-content {
            background-color: var(--primary);
            color: white;
        }

        .message.received .message-content {
            background-color: var(--light-gray);
            color: var(--dark);
        }

        .message-text {
            margin: 0;
        }

        .message-time {
            font-size: 12px;
            color: var(--gray);
            margin-top: 5px;
            align-self: flex-end;
        }

        .message.sent .message-time {
            color: #d0e1ff;
        }

        .chat-form {
            display: flex;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid var(--light-gray);
        }

        .chat-form input[type="text"] {
            flex: 1;
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 14px;
        }

        .chat-form button {
            padding: 10px 20px;
        }

        .delete-chat-btn {
            margin-left: auto;
        }

        .sold-message {
            font-size: 14px;
            color: var(--gray);
            text-align: center;
            padding: 15px;
            border-top: 1px solid var(--light-gray);
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Footer Styles */
        footer {
            background-color: var(--dark);
            color: white;
            padding: 40px 0;
            margin-top: 40px;
        }

        .footer-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .footer-column h3 {
            font-size: 18px;
            margin-bottom: 15px;
        }

        .footer-column ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-column li {
            margin-bottom: 10px;
        }

        .footer-column a {
            color: var(--light-gray);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-column a:hover {
            color: var(--primary);
        }

        .footer-social {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .footer-social a {
            color: white;
            font-size: 18px;
        }

        .footer-bottom {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--light-gray);
        }

        /* Animation */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .messages-container {
                flex-direction: column;
                margin: 20px auto;
                padding: 15px;
            }
            .chat-sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid var(--light-gray);
                max-height: 200px;
            }
            .chat-item img {
                width: 35px;
                height: 35px;
            }
            .chat-header img {
                width: 40px;
                height: 40px;
            }
            .chat-messages {
                max-height: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="messages-container">
            <div class="chat-sidebar">
                <?php if (empty($chats)): ?>
                    <p>No conversations yet.</p>
                <?php else: ?>
                    <?php foreach ($chats as $chat): ?>
                        <div class="chat-item <?php echo ($selected_car_id == $chat['car_id'] && $selected_seller_id == $chat['other_user_id']) ? 'active' : ''; ?>" 
                             onclick="window.location.href='messages.php?car_id=<?php echo $chat['car_id']; ?>&seller_id=<?php echo $chat['other_user_id']; ?>'">
                            <img src="<?php echo htmlspecialchars($chat['main_image'] ?: 'Uploads/cars/default.jpg'); ?>" alt="<?php echo htmlspecialchars($chat['brand'] . ' ' . $chat['model']); ?>">
                            <div class="chat-info">
                                <h4><?php echo htmlspecialchars($chat['other_username'] . ' - ' . $chat['brand'] . ' ' . $chat['model']); ?></h4>
                                <p><?php echo htmlspecialchars($chat['last_message'] ?: 'No messages'); ?></p>
                            </div>
                            <?php if ($chat['unread_count'] > 0): ?>
                                <span class="badge"><?php echo $chat['unread_count']; ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="chat-main">
                <?php if ($selected_car_id && $selected_seller_id && $car_details): ?>
                    <div class="chat-header">
                        <img src="<?php echo htmlspecialchars($car_details['main_image'] ?: 'Uploads/cars/default.jpg'); ?>" alt="<?php echo htmlspecialchars($car_details['brand'] . ' ' . $car_details['model']); ?>">
                        <div>
                            <h3><?php echo htmlspecialchars($other_username . ' - ' . $car_details['brand'] . ' ' . $car_details['model']); ?></h3>
                            <p><a href="view_car.php?id=<?php echo $selected_car_id; ?>" class="btn btn-outline btn-sm">View Car</a></p>
                        </div>
                        <form method="POST" class="delete-chat-btn">
                            <input type="hidden" name="car_id" value="<?php echo $selected_car_id; ?>">
                            <input type="hidden" name="other_user_id" value="<?php echo $selected_seller_id; ?>">
                            <button type="submit" name="delete_chat" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this chat?');">
                                <i class="fas fa-trash"></i> Delete Chat
                            </button>
                        </form>
                    </div>
                    <div class="chat-messages" id="chat-messages">
                        <?php foreach ($grouped_messages as $date => $date_messages): ?>
                            <div class="date-divider"><?php echo date('d/m/Y', strtotime($date)); ?></div>
                            <?php foreach ($date_messages as $message): ?>
                                <div class="message <?php echo $message['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                                    <div class="message-content">
                                        <div class="message-text"><?php echo htmlspecialchars($message['message']); ?></div>
                                        <div class="message-time"><?php echo date('h:i A', strtotime($message['created_at'])); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($is_sold): ?>
                        <p class="sold-message">This listing was deleted by seller <?php echo htmlspecialchars($seller_username); ?>.</p>
                    <?php else: ?>
                        <form method="POST" class="chat-form">
                            <input type="hidden" name="car_id" value="<?php echo $selected_car_id; ?>">
                            <input type="hidden" name="receiver_id" value="<?php echo $selected_seller_id; ?>">
                            <input type="text" name="message" placeholder="Type your message..." required>
                            <button type="submit" name="send_message" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="wa">Select a conversation from the sidebar to start chatting.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <div class="footer-container">
                <div class="footer-column">
                    <h3>CarBazaar</h3>
                    <p>Your trusted platform for buying and selling quality used cars across India.</p>
                    <div class="footer-social">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="index.php#cars">Browse Cars</a></li>
                        <li><a href="index.php#about">About Us</a></li>
                        <li><a href="index.php#contact">Contact</a></li>
                        <li><a href="favorites.php">Favorites</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Help & Support</h3>
                    <ul>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms & Conditions</a></li>
                        <li><a href="#">How to Sell</a></li>
                        <li><a href="#">Buyer Guide</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Contact Us</h3>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i> 123 Street, Mumbai, Maharashtra, India</li>
                        <li><i class="fas fa-phone-alt"></i> +91 9876543210</li>
                        <li><i class="fas fa-envelope"></i> support@carbazaar.com</li>
                        <li><i class="fas fa-clock"></i> Mon-Fri: 9 AM - 6 PM</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© <?php echo date('Y'); ?> CarBazaar. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        <?php if ($selected_car_id && $selected_seller_id): ?>
        function fetchMessages() {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'fetch_messages.php?car_id=<?php echo $selected_car_id; ?>&seller_id=<?php echo $selected_seller_id; ?>', true);
            xhr.onload = function () {
                if (xhr.status === 200) {
                    const messages = JSON.parse(xhr.responseText);
                    const chatMessages = document.getElementById('chat-messages');
                    chatMessages.innerHTML = '';
                    let currentDate = '';
                    messages.forEach(msg => {
                        // Replace any non-standard date format to ensure compatibility
                        const cleanedDate = msg.created_at.replace(/(\d{4})-(\d{2})-(\d{2}) (\d{2}:\d{2}:\d{2})/, '$1-$2-$3T$4');
                        const dateObj = new Date(cleanedDate);
                        if (isNaN(dateObj)) {
                            console.error('Invalid date:', msg.created_at);
                            return; // Skip invalid dates
                        }
                        const messageDate = dateObj.toISOString().split('T')[0]; // Get YYYY-MM-DD
                        if (messageDate !== currentDate) {
                            const dateDiv = document.createElement('div');
                            dateDiv.className = 'date-divider';
                            // Format date as DD/MM/YYYY
                            const day = String(dateObj.getDate()).padStart(2, '0');
                            const month = String(dateObj.getMonth() + 1).padStart(2, '0');
                            const year = dateObj.getFullYear();
                            dateDiv.textContent = `${day}/${month}/${year}`;
                            chatMessages.appendChild(dateDiv);
                            currentDate = messageDate;
                        }
                        const messageDiv = document.createElement('div');
                        messageDiv.className = `message ${msg.sender_id == <?php echo $user_id; ?> ? 'sent' : 'received'}`;
                        messageDiv.innerHTML = `
                            <div class="message-content">
                                <div class="message-text">${msg.message}</div>
                                <div class="message-time">${dateObj.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })}</div>
                            </div>
                        `;
                        chatMessages.appendChild(messageDiv);
                    });
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }
            };
            xhr.send();
        }

        // Poll every 5 seconds
        setInterval(fetchMessages, 5000);

        // Initial fetch
        fetchMessages();

        // Scroll to bottom on load
        const chatMessages = document.getElementById('chat-messages');
        chatMessages.scrollTop = chatMessages.scrollHeight;
        <?php endif; ?>
    </script>
</body>
</html>