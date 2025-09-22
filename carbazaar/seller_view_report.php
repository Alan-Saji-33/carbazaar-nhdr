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

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'seller') {
    $_SESSION['error'] = "Access denied. Sellers only.";
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No report specified.";
    header("Location: seller_dashboard.php?section=reports");
    exit();
}

$report_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

// Fetch report details and verify seller ownership
$stmt = $conn->prepare("
    SELECT r.case_id, r.category, r.description, r.evidence_file, r.created_at, 
           " . ($conn->query("SHOW COLUMNS FROM reports LIKE 'status'")->num_rows > 0 ? "r.status" : "'New' AS status") . ",
           c.id AS car_id, c.brand, c.model, c.main_image, c.seller_id
    FROM reports r
    JOIN cars c ON r.car_id = c.id
    WHERE r.id = ? AND c.seller_id = ?
");
$stmt->bind_param("ii", $report_id, $_SESSION['user_id']);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$report) {
    $_SESSION['error'] = "Report not found or you don't have permission to view it.";
    header("Location: seller_dashboard.php?section=reports");
    exit();
}

// Fetch action history
$stmt = $conn->prepare("
    SELECT ra.action_type, ra.status, ra.comments, ra.notification_to_seller, ra.created_at, u.username AS admin_username
    FROM report_actions ra
    LEFT JOIN users u ON ra.admin_id = u.id
    WHERE ra.report_id = ?
    ORDER BY ra.created_at DESC
");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$action_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Report - CarBazaar</title>
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

        .report-container {
            max-width: 1200px;
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

        .section {
            margin-bottom: 30px;
        }

        .section h3 {
            font-size: 20px;
            color: var(--dark);
            margin-bottom: 15px;
            position: relative;
        }

        .section h3::after {
            content: '';
            display: block;
            width: 50px;
            height: 3px;
            background: var(--primary);
            margin: 10px 0;
        }

        .section p {
            font-size: 14px;
            color: var(--gray);
            margin: 10px 0;
        }

        .section img {
            max-width: 200px;
            border-radius: 8px;
            margin-top: 10px;
        }

        .history-table {
            margin-top: 20px;
        }

        .history-table table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--light);
            border-radius: 8px;
            overflow: hidden;
        }

        .history-table th, .history-table td {
            padding: 10px;
            border-bottom: 1px solid var(--light-gray);
            text-align: left;
        }

        .history-table th {
            background-color: var(--primary);
            color: white;
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

            .section img {
                max-width: 150px;
            }

            .history-table table {
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
                    <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="favorites.php"><i class="fas fa-heart"></i> Favorites</a></li>
                    <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
                    <li><a href="seller_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
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

        <div class="report-container">
            <div class="report-header">
                <img src="<?php echo htmlspecialchars($report['main_image'] ?: 'Uploads/cars/default.jpg'); ?>" alt="<?php echo htmlspecialchars($report['brand'] . ' ' . $report['model']); ?>">
                <div>
                    <h2>Case ID: <?php echo htmlspecialchars($report['case_id']); ?></h2>
                    <p><?php echo htmlspecialchars($report['brand'] . ' ' . $report['model']); ?></p>
                </div>
            </div>

            <div class="section">
                <h3>Car Details</h3>
                <p><strong>Car:</strong> <?php echo htmlspecialchars($report['brand'] . ' ' . $report['model']); ?></p>
                <p><strong>Listing ID:</strong> <?php echo $report['car_id']; ?></p>
                <img src="<?php echo htmlspecialchars($report['main_image'] ?: 'Uploads/cars/default.jpg'); ?>" alt="Car Image">
                <p><a href="view_car.php?id=<?php echo $report['car_id']; ?>" class="btn btn-outline"><i class="fas fa-eye"></i> View Listing</a></p>
            </div>

            <div class="section">
                <h3>Report Details</h3>
                <p><strong>Category:</strong> <?php echo htmlspecialchars($report['category']); ?></p>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($report['description']); ?></p>
                <p><strong>Status:</strong> <?php echo htmlspecialchars($report['status']); ?></p>
                <p><strong>Date Reported:</strong> <?php echo date('M j, Y, h:i A', strtotime($report['created_at'])); ?></p>
                <?php if ($report['evidence_file']): ?>
                    <p><strong>Evidence:</strong> <a href="<?php echo htmlspecialchars($report['evidence_file']); ?>" target="_blank" class="btn btn-outline"><i class="fas fa-download"></i> View/Download Evidence</a></p>
                <?php else: ?>
                    <p><strong>Evidence:</strong> None provided</p>
                <?php endif; ?>
            </div>

            <div class="section">
                <h3>Action History</h3>
                <?php if (empty($action_history)): ?>
                    <p>No actions taken yet.</p>
                <?php else: ?>
                    <div class="history-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Status</th>
                                    <th>Admin</th>
                                    <th>Comments</th>
                                    <th>Notification to You</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($action_history as $action): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($action['action_type']); ?></td>
                                        <td><?php echo htmlspecialchars($action['status']); ?></td>
                                        <td><?php echo htmlspecialchars($action['admin_username'] ?: 'Deleted Admin'); ?></td>
                                        <td><?php echo htmlspecialchars($action['comments'] ?: 'None'); ?></td>
                                        <td><?php echo htmlspecialchars($action['notification_to_seller'] ?: 'None'); ?></td>
                                        <td><?php echo date('M j, Y, h:i A', strtotime($action['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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