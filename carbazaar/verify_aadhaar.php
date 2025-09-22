<?php
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'seller') {
    $_SESSION['error'] = "Please login as a seller to verify Aadhaar.";
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

$user_id = $_SESSION['user_id'];

// Fetch current Aadhaar status
$stmt = $conn->prepare("SELECT aadhaar_status, aadhaar_path, aadhaar_rejection_reason FROM users WHERE id = ?");
if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Handle Aadhaar upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['aadhaar_image'])) {
    try {
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $file = $_FILES['aadhaar_image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error: " . $file['error']);
        }

        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception("Invalid file type. Only JPEG, PNG, or PDF allowed.");
        }

        if ($file['size'] > $max_size) {
            throw new Exception("File size exceeds 5MB limit.");
        }

        $upload_dir = 'uploads/aadhaar/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_name = $user_id . '_' . time() . '_' . basename($file['name']);
        $file_path = $upload_dir . $file_name;

        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception("Failed to move uploaded file.");
        }

        // Update user record
        $stmt = $conn->prepare("UPDATE users SET aadhaar_path = ?, aadhaar_status = 'pending' WHERE id = ?");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("si", $file_path, $user_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update Aadhaar status: " . $stmt->error);
        }
        $stmt->close();

        $_SESSION['success_modal'] = true; // Flag to show success modal
        header("Location: verify_aadhaar.php");
        exit();
    } catch (Exception $e) {
        // Silently log errors
        error_log($e->getMessage(), 3, 'errors.log');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aadhaar Verification - CarBazaar</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Modal Styles */
        .modal {
            display: <?php echo ($user['aadhaar_status'] !== 'approved' && (!$user['aadhaar_path'] || $user['aadhaar_status'] == 'rejected') && !isset($_SESSION['success_modal'])) ? 'flex' : 'none'; ?>;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease-out;
        }

        .pending-modal {
            display: <?php echo ($user['aadhaar_status'] == 'pending' && $user['aadhaar_path'] && !isset($_SESSION['success_modal'])) ? 'flex' : 'none'; ?>;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease-out;
        }

        .success-modal {
            display: <?php echo isset($_SESSION['success_modal']) ? 'flex' : 'none'; ?>;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease-out;
        }

        .modal-content {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            animation: scaleIn 0.3s ease-out;
            position: relative;
        }

        .modal-content h2 {
            color: var(--primary);
            font-size: 24px;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .modal-content p {
            color: var(--gray);
            font-size: 16px;
            margin-bottom: 20px;
        }

        .modal-content .not-verified {
            color: var(--danger);
            font-weight: 500;
        }

        .modal-content .btn-primary {
            background: var(--primary);
            color: #fff;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            transition: background 0.3s ease, transform 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .modal-content .btn-primary:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }

        .modal-content .btn-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 20px;
            color: var(--gray);
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .modal-content .btn-close:hover {
            color: var(--danger);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes scaleIn {
            from {
                transform: scale(0.8);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        /* Existing Styles */
        .form-footer {
            margin-top: 10px;
            text-align: center;
            font-size: 14px;
            color: var(--gray);
        }

        .form-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .form-footer a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }

        .btn-primary {
            margin-top: 20px;
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
                    <li><a href="#cars"><i class="fas fa-car"></i> Cars</a></li>
                    <li><a href="about.php"><i class="fas fa-info-circle"></i> About</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                        <li><a href="favorites.php"><i class="fas fa-heart"></i> Favorites</a></li>
                        <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
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
        <!-- Not Verified Modal -->
        <div class="modal" id="aadhaarModal">
            <div class="modal-content">
                <button class="btn-close" onclick="closeModal('aadhaarModal')"><i class="fas fa-times"></i></button>
                <h2>Aadhaar Verification Required</h2>
                <p>Your Aadhaar is <span class="not-verified">not verified</span>. Please upload your Aadhaar details to list cars for sale.</p>
                <button class="btn-primary" onclick="closeModal('aadhaarModal')">Verify Now</button>
            </div>
        </div>

        <!-- Pending Modal -->
        <div class="pending-modal" id="pendingModal">
            <div class="modal-content">
                <button class="btn-close" onclick="closeModal('pendingModal')"><i class="fas fa-times"></i></button>
                <h2>Aadhaar Verification Pending</h2>
                <p>You have already uploaded. Wait till admin approves it.</p>
                <button class="btn-primary" onclick="closeModal('pendingModal')">OK</button>
            </div>
        </div>

        <!-- Success Modal -->
        <div class="success-modal" id="successModal">
            <div class="modal-content">
                <button class="btn-close" onclick="closeModal('successModal')"><i class="fas fa-times"></i></button>
                <h2>Aadhaar Uploaded Successfully</h2>
                <p>You can sell cars when approved by admin.</p>
                <button class="btn-primary" onclick="closeModal('successModal')">OK</button>
            </div>
        </div>

        <div class="auth-container">
            <div class="auth-image"></div>
            <div class="auth-form-container">
                <div class="auth-header">
                    <h2>Aadhaar Verification</h2>
                    <p>Verify your identity to list cars for sale</p>
                </div>

                <div class="form-group">
                    <h3>Current Status: <span style="color: <?php echo $user['aadhaar_status'] == 'approved' ? 'green' : ($user['aadhaar_status'] == 'rejected' ? 'red' : 'orange'); ?>">
                        <?php echo ucfirst($user['aadhaar_status'] ?? 'not uploaded'); ?>
                    </span></h3>
                    <?php if ($user['aadhaar_status'] == 'rejected' && $user['aadhaar_rejection_reason']): ?>
                        <p style="color: var(--danger);"><strong>Reason for Rejection:</strong> <?php echo htmlspecialchars($user['aadhaar_rejection_reason']); ?></p>
                    <?php endif; ?>
                </div>
                <br>
                <?php if ($user['aadhaar_path']): ?>
                    <div class="form-group">
                        <h4>Uploaded Aadhaar Preview</h4>
                        <?php if (pathinfo($user['aadhaar_path'], PATHINFO_EXTENSION) === 'pdf'): ?>
                            <a href="<?php echo htmlspecialchars($user['aadhaar_path']); ?>" target="_blank" class="btn btn-outline"><i class="fas fa-file-pdf"></i> View PDF</a>
                        <?php else: ?>
                            <img src="<?php echo htmlspecialchars($user['aadhaar_path']); ?>" alt="Aadhaar Preview" style="max-width: 100%; border-radius: 8px; margin-top: 10px;">
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($user['aadhaar_status'] != 'approved' && (!$user['aadhaar_path'] || $user['aadhaar_status'] == 'rejected')): ?>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="aadhaar_image"><i class="fas fa-id-card"></i> Upload Aadhaar (JPEG, PNG, or PDF)</label>
                            <input type="file" id="aadhaar_image" name="aadhaar_image" class="form-control" accept="image/jpeg,image/png,application/pdf" required>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-upload"></i> Submit for Verification
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="form-footer">
                    <p>Return to <a href="profile.php">Profile</a></p>
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

    <script>
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            <?php if (isset($_SESSION['success_modal'])): ?>
                <?php unset($_SESSION['success_modal']); ?>
            <?php endif; ?>
        }
    </script>
</body>
</html>