<?php
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = "Please login as an admin to approve Aadhaar verifications.";
    header("Location: login.php");
    exit();
}

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

if (!isset($_GET['user_id'])) {
    $_SESSION['error'] = "No user specified.";
    header("Location: admin_dashboard.php");
    exit();
}

$user_id = filter_input(INPUT_GET, 'user_id', FILTER_SANITIZE_NUMBER_INT);
$stmt = $conn->prepare("SELECT username, aadhaar_path, aadhaar_status FROM users WHERE id = ?");
if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || $user['aadhaar_status'] !== 'pending') {
    $_SESSION['error'] = "No pending Aadhaar verification found for this user.";
    header("Location: admin_dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
        $rejection_reason = $action === 'reject' ? filter_input(INPUT_POST, 'rejection_reason', FILTER_SANITIZE_STRING) : null;

        $stmt = $conn->prepare("UPDATE users SET aadhaar_status = ?, aadhaar_rejection_reason = ? WHERE id = ?");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt->bind_param("ssi", $status, $rejection_reason, $user_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Aadhaar verification " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully!";
            header("Location: admin_dashboard.php");
            exit();
        } else {
            throw new Exception("Failed to update Aadhaar status: " . $stmt->error);
        }
        $stmt->close();
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
    <title>Approve Aadhaar - CarBazaar</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .aadhaar-preview { max-width: 100%; border-radius: 8px; margin-bottom: 20px; }
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
                    <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
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

        <div class="auth-container">
            <div class="auth-image"></div>
            <div class="auth-form-container">
                <div class="auth-header">
                    <h2>Review Aadhaar Verification</h2>
                    <p>Review Aadhaar for <?php echo htmlspecialchars($user['username']); ?></p>
                </div>
                <div class="form-group">
                    <h4>Aadhaar Document</h4>
                    <?php if ($user['aadhaar_path']): ?>
                        <?php if (pathinfo($user['aadhaar_path'], PATHINFO_EXTENSION) === 'pdf'): ?>
                            <a href="<?php echo htmlspecialchars($user['aadhaar_path']); ?>" target="_blank" class="btn btn-outline"><i class="fas fa-file-pdf"></i> View PDF</a>
                        <?php else: ?>
                            <img src="<?php echo htmlspecialchars($user['aadhaar_path']); ?>" alt="Aadhaar Document" class="aadhaar-preview">
                        <?php endif; ?>
                    <?php else: ?>
                        <p>No document uploaded.</p>
                    <?php endif; ?>
                </div>
                <form method="POST">
                    <div class="form-group">
                        <label for="rejection_reason"><i class="fas fa-comment"></i> Rejection Reason (Required if rejecting)</label>
                        <textarea id="rejection_reason" name="rejection_reason" class="form-control" rows="4" placeholder="Enter reason for rejection (if applicable)"></textarea>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="action" value="approve" class="btn btn-primary" style="width: 48%; margin-right: 4%;">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button type="submit" name="action" value="reject" class="btn btn-danger" style="width: 48%;">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    </div>
                    <div class="form-footer">
                        <p><a href="admin_dashboard.php">Back to Dashboard</a></p>
                    </div>
                </form>
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
