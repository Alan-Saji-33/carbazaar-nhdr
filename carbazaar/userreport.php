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
    $_SESSION['error'] = "Please log in to report a listing.";
    header("Location: login.php");
    exit();
}

if (!isset($_GET['car_id'])) {
    $_SESSION['error'] = "No car specified.";
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$car_id = filter_input(INPUT_GET, 'car_id', FILTER_SANITIZE_NUMBER_INT);

// Verify car exists and fetch car details with seller username
$stmt = $conn->prepare("SELECT c.brand, c.model, c.main_image, c.seller_id, u.username AS seller_username 
                        FROM cars c 
                        JOIN users u ON c.seller_id = u.id 
                        WHERE c.id = ?");
$stmt->bind_param("i", $car_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    $_SESSION['error'] = "Car not found.";
    header("Location: index.php");
    exit();
}
$car = $result->fetch_assoc();
$stmt->close();

// Handle report submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_report'])) {
    $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $contact_info = filter_input(INPUT_POST, 'contact_info', FILTER_SANITIZE_STRING);
    
    // Validate inputs
    $valid_categories = ['Fraud', 'Incorrect Info', 'Stolen', 'Inappropriate Images', 'Other'];
    if (!in_array($category, $valid_categories) || empty($description)) {
        $_SESSION['error'] = "Invalid report details.";
        header("Location: userreport.php?car_id=$car_id");
        exit();
    }

    // Handle file upload
    $evidence_file = null;
    if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] == UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $file_type = $_FILES['evidence']['type'];
        $file_size = $_FILES['evidence']['size'];
        
        if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
            $upload_dir = 'Uploads/reports/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_ext = pathinfo($_FILES['evidence']['name'], PATHINFO_EXTENSION);
            $evidence_file = $upload_dir . 'report_' . time() . '_' . uniqid() . '.' . $file_ext;
            if (!move_uploaded_file($_FILES['evidence']['tmp_name'], $evidence_file)) {
                $_SESSION['error'] = "Failed to upload evidence.";
                header("Location: userreport.php?car_id=$car_id");
                exit();
            }
        } else {
            $_SESSION['error'] = "Invalid file type or size. Allowed: JPEG, PNG, GIF (max 5MB).";
            header("Location: userreport.php?car_id=$car_id");
            exit();
        }
    }

    // Generate unique Case ID (e.g., RP20250919-0456-CBRP2025001)
    $date_part = date('Ymd-His');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['count'] + 1;
    $stmt->close();
    $case_id = sprintf("RP%s-CBRP%03d", $date_part, $count);

    // Insert report into database
    $stmt = $conn->prepare("INSERT INTO reports (car_id, user_id, category, description, evidence_file, contact_info, case_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssss", $car_id, $user_id, $category, $description, $evidence_file, $contact_info, $case_id);
    if ($stmt->execute()) {
        $_SESSION['report_case_id'] = $case_id; // Store for modal display
    } else {
        $_SESSION['error'] = "Failed to submit report.";
    }
    $stmt->close();
    header("Location: userreport.php?car_id=$car_id");
    exit();
}
?>

<?php include 'header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Listing - CarBazaar</title>
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

        /* Report Form Styles */
        .report-container {
            max-width: 600px;
            margin: 50px auto;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
            animation: slideIn 0.5s ease;
        }

        .report-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .report-header img {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid var(--primary);
        }

        .report-header h2 {
            font-size: 24px;
            color: var(--dark);
            margin: 0;
        }

        .report-header p {
            font-size: 14px;
            color: var(--gray);
            margin: 5px 0 0;
        }

        .report-header h2::after {
            content: '';
            display: block;
            width: 50px;
            height: 3px;
            background: var(--primary);
            margin: 10px 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .form-group select,
        .form-group textarea,
        .form-group input[type="text"],
        .form-group input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group select:focus,
        .form-group textarea:focus,
        .form-group input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 5px rgba(67, 97, 238, 0.3);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }

        .alert-success {
            background-color: var(--success);
            color: white;
        }

        .alert-error {
            background-color: var(--danger);
            color: white;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            display: flex; /* Changed to flex for centering */
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            border-radius: 15px;
            padding: 20px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease;
        }

        .modal-content h3 {
            font-size: 20px;
            color: var(--dark);
            margin: 0 0 15px;
        }

        .modal-content p {
            font-size: 14px;
            color: var(--gray);
            margin: 0 0 20px;
        }

        .modal-content .btn {
            padding: 10px 30px;
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
            .report-container {
                margin: 20px auto;
                padding: 20px;
            }

            .report-header h2 {
                font-size: 20px;
            }

            .report-header img {
                width: 40px;
                height: 40px;
            }

            .modal-content {
                width: 95%;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="report-container">
            <div class="report-header">
                <img src="<?php echo htmlspecialchars($car['main_image'] ?: 'Uploads/cars/default.jpg'); ?>" alt="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>">
                <div>
                    <h2><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h2>
                    <p>Seller: <?php echo htmlspecialchars($car['seller_username']); ?></p>
                </div>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="category">Report Category</label>
                    <select name="category" id="category" required>
                        <option value="">Select a category</option>
                        <option value="Fraud">Fraud</option>
                        <option value="Incorrect Info">Incorrect Info</option>
                        <option value="Stolen">Stolen</option>
                        <option value="Inappropriate Images">Inappropriate Images</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" placeholder="Describe the issue (e.g., 'Seller is asking for advance payment; looks suspicious.')" required></textarea>
                </div>
                <div class="form-group">
                    <label for="evidence">Upload Evidence (Optional)</label>
                    <input type="file" name="evidence" id="evidence" accept="image/jpeg,image/png,image/gif">
                    <p style="font-size: 12px; color: var(--gray); margin-top: 5px;">Accepted formats: JPEG, PNG, GIF (max 5MB)</p>
                </div>
                <div class="form-group">
                    <label for="contact_info">Contact Information (Optional)</label>
                    <input type="text" name="contact_info" id="contact_info" placeholder="Email or phone number">
                </div>
                <button type="submit" name="submit_report" class="btn btn-primary"><i class="fas fa-flag"></i> Submit Report</button>
                <a href="view_car.php?id=<?php echo $car_id; ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Listing</a>
            </form>
        </div>

        <!-- Modal for confirmation -->
        <?php if (isset($_SESSION['report_case_id'])): ?>
            <div class="modal" id="reportModal" style="display: flex;">
                <div class="modal-content">
                    <h3>Thank You!</h3>
                    <p>Your report has been submitted (Case ID: <?php echo htmlspecialchars($_SESSION['report_case_id']); ?>). Our team will review it shortly.</p>
                    <a href="view_car.php?id=<?php echo $car_id; ?>" class="btn btn-primary">Close</a>
                </div>
            </div>
            <?php unset($_SESSION['report_case_id']); ?>
        <?php endif; ?>
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

    <script>
        // Close modal on click
        document.querySelectorAll('.modal .btn').forEach(button => {
            button.addEventListener('click', () => {
                document.getElementById('reportModal').style.display = 'none';
            });
        });
    </script>
</body>
</html>