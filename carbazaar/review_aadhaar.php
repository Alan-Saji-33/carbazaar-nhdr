<?php
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

// Restrict access to admins
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = "Please login as an admin to access this page.";
    header("Location: login.php");
    exit();
}

// Database configuration
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

// Get user ID from query parameter
$user_id = isset($_GET['id']) ? filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT) : 0;

// Fetch user details
$stmt = $conn->prepare("SELECT id, username, profile_pic, aadhaar_path, aadhaar_status FROM users WHERE id = ? AND aadhaar_status = 'pending'");
if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->num_rows > 0 ? $user_result->fetch_assoc() : null;
$stmt->close();

if (!$user) {
    $_SESSION['error'] = "No pending Aadhaar verification found for this user.";
    header("Location: admin_dashboard.php?section=verifications");
    exit();
}

// Handle Aadhaar verification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_aadhaar'])) {
    try {
        $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
        $rejection_reason = ($action === 'reject' && isset($_POST['rejection_reason'])) ? filter_input(INPUT_POST, 'rejection_reason', FILTER_SANITIZE_STRING) : null;

        // Validate rejection reason if rejecting
        if ($action === 'reject' && empty($rejection_reason)) {
            throw new Exception("Please provide a rejection reason.");
        }

        $stmt = $conn->prepare("UPDATE users SET aadhaar_status = ?, aadhaar_rejection_reason = ? WHERE id = ?");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt->bind_param("ssi", $status, $rejection_reason, $user_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Aadhaar verification updated!";
        } else {
            throw new Exception("Failed to update verification: " . $stmt->error);
        }
        $stmt->close();
        header("Location: admin_dashboard.php?section=verifications");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Aadhaar - CarBazaar</title>
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

        /* Header Styles (Unchanged) */
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
            min-height: 40px; /* Ensure consistent button height */
            line-height: 1.5; /* Maintain consistent text alignment */
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

        .btn-success {
            background-color: var(--success);
            color: white;
        }

        .btn-success:hover {
            background-color: #0ea5e9;
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

        /* Review Section */
        .review-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .review-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .user-profile-pic {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
        }

        .review-header h2 {
            font-size: 24px;
            color: var(--dark);
            margin: 0;
        }

        .review-details {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .aadhaar-image {
            max-width: 100%;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 100%;
        }

        .form-group label {
            font-weight: 500;
            color: var(--gray);
            font-size: 14px;
        }

        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 14px;
            resize: vertical;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            align-items: center; /* Ensure vertical alignment */
            flex-wrap: wrap; /* Allow wrapping if needed */
        }

        #rejection-form {
            display: none;
            width: 100%;
            margin-top: 15px; /* Space between buttons and form */
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
            max-width: 800px;
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

        .section-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 28px;
            color: var(--dark);
            margin: 0;
        }

        @media (max-width: 768px) {
            .review-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .action-buttons {
                flex-direction: column;
                align-items: flex-end;
            }
            .btn {
                width: 100%; /* Full-width buttons on mobile */
                justify-content: center;
            }
            #rejection-form {
                margin-top: 10px;
            }
        }
    </style>
    <script>
        function toggleRejectionForm() {
            const rejectionForm = document.getElementById('rejection-form');
            rejectionForm.style.display = rejectionForm.style.display === 'none' ? 'block' : 'none';
        }
    </script>
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
                    <li><a href="#cars"><i class="fas fa-car"></i> Cars</a></li>
                    <li><a href="about.php"><i class="fas fa-info-circle"></i> About</a></li>
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                        <li><a href="favorites.php"><i class="fas fa-heart"></i> Favorites</a></li>
                        <li>
                            <a href="messages.php"><i class="fas fa-envelope"></i> Messages</a>
                        </li>
                        <?php if ($_SESSION['user_type'] == 'admin'): ?>
                            <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
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

        <div class="section-header">
            <h2 class="section-title">Review Aadhaar Verification</h2>
        </div>

        <div class="review-container">
            <div class="review-header">
                <img src="<?php echo htmlspecialchars($user['profile_pic'] ?: 'Uploads/profiles/default.jpg'); ?>" alt="<?php echo htmlspecialchars($user['username']); ?>" class="user-profile-pic">
                <h2><?php echo htmlspecialchars($user['username']); ?></h2>
            </div>
            <div class="review-details">
                <a href="<?php echo htmlspecialchars($user['aadhaar_path']); ?>" target="_blank">
                    <img src="<?php echo htmlspecialchars($user['aadhaar_path'] ?: 'Uploads/aadhaar/default.jpg'); ?>" alt="Aadhaar Image" class="aadhaar-image">
                </a>
                <div class="action-buttons">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" name="verify_aadhaar" class="btn btn-success"><i class="fas fa-check"></i> Approve</button>
                    </form>
                    <button type="button" onclick="toggleRejectionForm()" class="btn btn-danger"><i class="fas fa-times"></i> Reject</button>
                    <form method="POST" id="rejection-form">
                        <div class="form-group">
                            <label for="rejection_reason">Rejection Reason (Required)</label>
                            <textarea name="rejection_reason" id="rejection_reason" placeholder="Enter reason for rejection" rows="4" required></textarea>
                        </div>
                        <div class="action-buttons">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" name="verify_aadhaar" class="btn btn-danger"><i class="fas fa-times"></i> Confirm Reject</button>
                        </div>
                    </form>
                </div>
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
                        <li><i class="fas fa-map-marker-alt"></i> Changanacherry</li>
                        <li><i class="fas fa-phone-alt"></i> +91 9876543210</li>
                        <li><i class="fas fa-envelope"></i> support@carbazaar.com</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© <?php echo date('Y'); ?> CarBazaar. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>