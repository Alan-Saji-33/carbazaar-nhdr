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

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'seller') {
    $_SESSION['error'] = "Please log in as a seller to access messages.";
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch all conversations for the seller
$stmt = $conn->prepare("SELECT DISTINCT m.car_id, c.brand, c.model, u.username AS buyer_name, u.id AS buyer_id, u.profile_pic AS buyer_profile_pic,
                        (SELECT message FROM messages m2 WHERE m2.car_id = m.car_id AND ((m2.sender_id = ? AND m2.receiver_id = u.id) OR (m2.sender_id = u.id AND m2.receiver_id = ?)) ORDER BY m2.created_at DESC LIMIT 1) AS last_message,
                        (SELECT COUNT(*) FROM messages m3 WHERE m3.car_id = m.car_id AND m3.receiver_id = ? AND m3.is_read = 0) AS unread_count
                        FROM messages m
                        JOIN cars c ON m.car_id = c.id
                        JOIN users u ON (m.sender_id = u.id OR m.receiver_id = u.id) AND u.id != ?
                        WHERE m.sender_id = ? OR m.receiver_id = ?
                        ORDER BY (SELECT MAX(created_at) FROM messages m2 WHERE m2.car_id = m.car_id AND ((m2.sender_id = ? AND m2.receiver_id = u.id) OR (m2.sender_id = u.id AND m2.receiver_id = ?))) DESC");
$stmt->bind_param("iiiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle sending a message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message']) && isset($_POST['buyer_id']) && isset($_POST['car_id'])) {
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $buyer_id = filter_input(INPUT_POST, 'buyer_id', FILTER_SANITIZE_NUMBER_INT);
    $car_id = filter_input(INPUT_POST, 'car_id', FILTER_SANITIZE_NUMBER_INT);
    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, car_id, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $user_id, $buyer_id, $car_id, $message);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Message sent successfully!";
        } else {
            $_SESSION['error'] = "Failed to send message.";
        }
        $stmt->close();
        header("Location: seller_messages.php?buyer_id=$buyer_id&car_id=$car_id");
        exit();
    } else {
        $_SESSION['error'] = "Message cannot be empty.";
    }
}

// Mark messages as read if specific conversation is selected
if (isset($_GET['buyer_id']) && isset($_GET['car_id'])) {
    $buyer_id = filter_input(INPUT_GET, 'buyer_id', FILTER_SANITIZE_NUMBER_INT);
    $car_id = filter_input(INPUT_GET, 'car_id', FILTER_SANITIZE_NUMBER_INT);
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND car_id = ? AND sender_id = ?");
    $stmt->bind_param("iii", $user_id, $car_id, $buyer_id);
    $stmt->execute();
    $stmt->close();

    // Fetch buyer and car details for the selected conversation
    $stmt = $conn->prepare("SELECT username, profile_pic FROM users WHERE id = ?");
    $stmt->bind_param("i", $buyer_id);
    $stmt->execute();
    $buyer = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("SELECT brand, model FROM cars WHERE id = ?");
    $stmt->bind_param("i", $car_id);
    $stmt->execute();
    $car = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Messages - CarBazaar</title>
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

        /* Messaging Styles */
        .messaging-container {
            display: flex;
            max-width: 1200px;
            margin: 50px auto;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            animation: slideIn 0.5s ease;
            min-height: 500px;
        }

        .chat-list {
            flex: 0 0 300px;
            border-right: 1px solid var(--light-gray);
            overflow-y: auto;
        }

        .chat-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid var(--light-gray);
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .chat-item:hover,
        .chat-item.active {
            background-color: var(--light);
        }

        .chat-item img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }

        .chat-item-content {
            flex: 1;
        }

        .chat-item-content h4 {
            font-size: 16px;
            color: var(--dark);
            margin: 0 0 5px;
        }

        .chat-item-content p {
            font-size: 12px;
            color: var(--gray);
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-item .unread-badge {
            background-color: var(--danger);
            color: white;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 10px;
        }

        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .chat-header {
            padding: 15px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chat-header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .chat-header h3 {
            font-size: 18px;
            color: var(--dark);
            margin: 0;
        }

        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            max-height: 400px;
        }

        .message {
            margin-bottom: 15px;
            max-width: 70%;
        }

        .message.sent {
            margin-left: auto;
            text-align: right;
        }

        .message.received {
            margin-right: auto;
        }

        .message-content {
            padding: 10px 15px;
            border-radius: 12px;
            font-size: 14px;
        }

        .message.sent .message-content {
            background-color: var(--primary);
            color: white;
        }

        .message.received .message-content {
            background-color: var(--light-gray);
            color: var(--dark);
        }

        .message-time {
            font-size: 12px;
            color: var(--gray);
            margin-top: 5px;
        }

        .message-form {
            padding: 15px;
            border-top: 1px solid var(--light-gray);
            display: flex;
            gap: 10px;
        }

        .message-form textarea {
            flex: 1;
            padding: 10px;
            border: 2px solid var(--light-gray);
            border-radius: 8px;
            resize: none;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .message-form textarea:focus {
            border-color: var(--primary);
            outline: none;
        }

        .message-form button {
            padding: 10px 20px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .alert-success {
            background-color: var(--success);
            color: white;
        }

        .alert-error {
            background-color: var(--danger);
            color: white;
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
            .messaging-container {
                flex-direction: column;
                margin: 20px auto;
                padding: 20px;
            }
            .chat-list {
                flex: none;
                border-right: none;
                border-bottom: 1px solid var(--light-gray);
                max-height: 200px;
            }
            .chat-item {
                padding: 10px;
            }
            .chat-header h3 {
                font-size: 16px;
            }
            .chat-messages {
                max-height: 300px;
            }
            .message-form textarea {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container header-container">
            <a href="index.php" class="logo">
                <div class="logo-icon"><i class="fas fa-car"></i></div>
                <div class="logo-text">Car<span>Bazaar</span></div>
            </a>
            <nav>
                <ul>
                    <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="index.php#cars"><i class="fas fa-car"></i> Cars</a></li>
                    <li><a href="index.php#about"><i class="fas fa-info-circle"></i> About</a></li>
                    <li><a href="index.php#contact"><i class="fas fa-phone-alt"></i> Contact</a></li>
                    <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="favorites.php"><i class="fas fa-heart"></i> Favorites</a></li>
                    <li><a href="seller_messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
                </ul>
            </nav>
            <div class="user-actions">
                <div class="user-greeting">Welcome, <span><?php echo htmlspecialchars($_SESSION['username']); ?></span></div>
                <a href="index.php?logout" class="btn btn-outline"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </header>

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

        <div class="messaging-container">
            <div class="chat-list">
                <?php foreach ($conversations as $conv): ?>
                    <div class="chat-item <?php echo (isset($_GET['buyer_id']) && isset($_GET['car_id']) && $conv['buyer_id'] == $_GET['buyer_id'] && $conv['car_id'] == $_GET['car_id']) ? 'active' : ''; ?>" 
                         onclick="window.location.href='seller_messages.php?buyer_id=<?php echo $conv['buyer_id']; ?>&car_id=<?php echo $conv['car_id']; ?>'">
                        <img src="<?php echo htmlspecialchars($conv['buyer_profile_pic'] ?: 'Uploads/profiles/default.jpg'); ?>" alt="<?php echo htmlspecialchars($conv['buyer_name']); ?>">
                        <div class="chat-item-content">
                            <h4><?php echo htmlspecialchars($conv['buyer_name']); ?> - <?php echo htmlspecialchars($conv['brand'] . ' ' . $conv['model']); ?></h4>
                            <p><?php echo htmlspecialchars($conv['last_message'] ?: 'No messages yet'); ?></p>
                        </div>
                        <?php if ($conv['unread_count'] > 0): ?>
                            <span class="unread-badge"><?php echo $conv['unread_count']; ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="chat-area">
                <?php if (isset($buyer) && isset($car)): ?>
                    <div class="chat-header">
                        <img src="<?php echo htmlspecialchars($buyer['profile_pic'] ?: 'Uploads/profiles/default.jpg'); ?>" alt="<?php echo htmlspecialchars($buyer['username']); ?>">
                        <h3><?php echo htmlspecialchars($buyer['username']); ?> - <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h3>
                    </div>
                    <div class="chat-messages" id="chat-messages">
                        <!-- Messages will be loaded via AJAX -->
                    </div>
                    <form class="message-form" method="POST">
                        <input type="hidden" name="buyer_id" value="<?php echo $buyer_id; ?>">
                        <input type="hidden" name="car_id" value="<?php echo $car_id; ?>">
                        <textarea name="message" rows="3" placeholder="Type your message..." required></textarea>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send</button>
                    </form>
                <?php else: ?>
                    <div class="chat-header">
                        <h3>Select a conversation to start messaging</h3>
                    </div>
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
                        <a href="#"><i class="fab fa-linkedin-in"></a>
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

    <?php if (isset($buyer) && isset($car)): ?>
    <script>
        function fetchMessages() {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'fetch_messages.php?other_user_id=<?php echo $buyer_id; ?>&car_id=<?php echo $car_id; ?>', true);
            xhr.onload = function () {
                if (xhr.status === 200) {
                    document.getElementById('chat-messages').innerHTML = xhr.responseText;
                    const chatMessages = document.getElementById('chat-messages');
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }
            };
            xhr.send();
        }

        // Initial fetch
        fetchMessages();

        // Poll every 5 seconds
        setInterval(fetchMessages, 5000);
    </script>
    <?php endif; ?>
</body>
</html>