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

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = "Access denied. Admins only.";
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No report specified.";
    header("Location: admin_dashboard.php?section=reports");
    exit();
}

$report_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

// Handle report deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_report'])) {
    $stmt_delete = $conn->prepare("DELETE FROM reports WHERE id = ?");
    if ($stmt_delete === false) {
        $_SESSION['error'] = "Failed to prepare delete query: " . htmlspecialchars($conn->error);
        header("Location: view_report.php?id=$report_id");
        exit();
    }
    $stmt_delete->bind_param("i", $report_id);
    if ($stmt_delete->execute()) {
        $_SESSION['message'] = "Report deleted successfully.";
        header("Location: admin_dashboard.php?section=reports");
        exit();
    } else {
        $_SESSION['error'] = "Failed to delete report: " . htmlspecialchars($conn->error);
    }
    $stmt_delete->close();
    header("Location: view_report.php?id=$report_id");
    exit();
}

// Check for column existence
$aadhaar_verified_exists = $conn->query("SHOW COLUMNS FROM users LIKE 'aadhaar_verified'")->num_rows > 0;
$select_aadhaar = $aadhaar_verified_exists ? "u.aadhaar_verified" : "0 AS aadhaar_verified";

// Fetch report details
$stmt = $conn->prepare("
    SELECT r.case_id, r.category, r.description, r.evidence_file, r.contact_info, r.created_at, 
           " . ($conn->query("SHOW COLUMNS FROM reports LIKE 'status'")->num_rows > 0 ? "r.status" : "'New' AS status") . ",
           c.id AS car_id, c.brand, c.model, c.main_image, c.seller_id,
           u.username AS seller_username, u.email AS seller_email, u.phone AS seller_phone, $select_aadhaar,
           ru.username AS reporter_username
    FROM reports r
    JOIN cars c ON r.car_id = c.id
    JOIN users u ON c.seller_id = u.id
    LEFT JOIN users ru ON r.user_id = ru.id
    WHERE r.id = ?
");
if ($stmt === false) {
    $_SESSION['error'] = "Database query failed: " . htmlspecialchars($conn->error);
    header("Location: admin_dashboard.php?section=reports");
    exit();
}
$stmt->bind_param("i", $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$report) {
    $_SESSION['error'] = "Report not found.";
    header("Location: admin_dashboard.php?section=reports");
    exit();
}

// Fetch seller stats
$is_suspended_exists = $conn->query("SHOW COLUMNS FROM users LIKE 'is_suspended'")->num_rows > 0;
$is_hidden_exists = $conn->query("SHOW COLUMNS FROM cars LIKE 'is_hidden'")->num_rows > 0;

$stmt = $conn->prepare("SELECT COUNT(*) as listing_count FROM cars WHERE seller_id = ?");
$stmt->bind_param("i", $report['seller_id']);
$stmt->execute();
$seller_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as report_count FROM reports WHERE car_id IN (SELECT id FROM cars WHERE seller_id = ?)");
$stmt->bind_param("i", $report['seller_id']);
$stmt->execute();
$seller_stats['report_count'] = $stmt->get_result()->fetch_assoc()['report_count'];
$stmt->close();

// Fetch report history (group reports for the same car)
$stmt = $conn->prepare("
    SELECT r.id, r.case_id, r.category, r.description, r.created_at, 
           " . ($conn->query("SHOW COLUMNS FROM reports LIKE 'status'")->num_rows > 0 ? "r.status" : "'New' AS status") . ",
           u.username AS reporter_username
    FROM reports r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.car_id = ? AND r.id != ?
    ORDER BY r.created_at DESC
");
$stmt->bind_param("ii", $report['car_id'], $report_id);
$stmt->execute();
$history_reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch action history
$stmt = $conn->prepare("
    SELECT ra.action_type, ra.status, ra.comments, ra.notification_to_seller, ra.notification_to_reporter, ra.created_at, u.username AS admin_username
    FROM report_actions ra
    LEFT JOIN users u ON ra.admin_id = u.id
    WHERE ra.report_id = ?
    ORDER BY ra.created_at DESC
");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$action_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_type']) && !isset($_POST['confirm_action'])) {
    $action_type = filter_input(INPUT_POST, 'action_type', FILTER_SANITIZE_STRING);
    $comments = filter_input(INPUT_POST, 'comments', FILTER_SANITIZE_STRING);

    $valid_actions = ['Dismiss', 'Delete Listing', 'Suspend Seller'];
    if (!in_array($action_type, $valid_actions)) {
        $_SESSION['error'] = "Invalid action.";
        header("Location: view_report.php?id=$report_id");
        exit();
    }

    if ($action_type === 'Delete Listing') {
        // Show modal via JavaScript
        echo "<script>document.getElementById('delete-listing-modal').style.display = 'flex';</script>";
        exit();
    } elseif ($action_type === 'Suspend Seller') {
        // Show confirmation prompt via JavaScript
        echo "<script>
            if (confirm('Are you sure you want to suspend this seller?')) {
                document.getElementById('confirm_action').value = 'true';
                document.getElementById('action-form').submit();
            } else {
                document.getElementById('action_type').value = '';
            }
        </script>";
        exit();
    }
}

// Process confirmed actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_type']) && isset($_POST['confirm_action'])) {
    $action_type = filter_input(INPUT_POST, 'action_type', FILTER_SANITIZE_STRING);
    $comments = filter_input(INPUT_POST, 'comments', FILTER_SANITIZE_STRING);
    $admin_id = $_SESSION['user_id'];

    $valid_actions = ['Dismiss', 'Delete Listing', 'Suspend Seller'];
    if (!in_array($action_type, $valid_actions)) {
        $_SESSION['error'] = "Invalid action.";
        header("Location: view_report.php?id=$report_id");
        exit();
    }

    // Set status and notifications based on action
    $status = $action_type === 'Dismiss' ? 'Dismissed' : 'Actioned';
    $notification_to_seller = '';
    $notification_to_reporter = '';

    // Insert action into report_actions before any deletions
    $stmt_insert = $conn->prepare("INSERT INTO report_actions (report_id, action_type, status, admin_id, comments, notification_to_seller, notification_to_reporter) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt_insert === false) {
        $_SESSION['error'] = "Failed to prepare insert query: " . htmlspecialchars($conn->error);
        header("Location: view_report.php?id=$report_id");
        exit();
    }

    if ($action_type === 'Dismiss') {
        $notification_to_reporter = "Your report (Case ID: {$report['case_id']}) was dismissed as the listing was found to be valid.";
    } elseif ($action_type === 'Delete Listing') {
        $notification_to_seller = "Your listing '{$report['brand']} {$report['model']}' has been deleted due to a violation.";
        $notification_to_reporter = "Your report (Case ID: {$report['case_id']}) was validated, and the listing has been deleted.";
    } elseif ($action_type === 'Suspend Seller') {
        $notification_to_seller = "Your account has been suspended due to a serious violation involving your listing '{$report['brand']} {$report['model']}'.";
        $notification_to_reporter = "Your report (Case ID: {$report['case_id']}) was validated, and the seller's account has been suspended.";
    }

    // Insert the action
    $stmt_insert->bind_param("ississs", $report_id, $action_type, $status, $admin_id, $comments, $notification_to_seller, $notification_to_reporter);
    if (!$stmt_insert->execute()) {
        $_SESSION['error'] = "Failed to record action: " . htmlspecialchars($conn->error);
        $stmt_insert->close();
        header("Location: view_report.php?id=$report_id");
        exit();
    }
    $stmt_insert->close();

    // Update report status if column exists
    if ($conn->query("SHOW COLUMNS FROM reports LIKE 'status'")->num_rows > 0) {
        $stmt_update = $conn->prepare("UPDATE reports SET status = ? WHERE id = ?");
        if ($stmt_update === false) {
            $_SESSION['error'] = "Failed to prepare update query: " . htmlspecialchars($conn->error);
            header("Location: view_report.php?id=$report_id");
            exit();
        }
        $stmt_update->bind_param("si", $status, $report_id);
        $stmt_update->execute();
        $stmt_update->close();
    }

    // Perform additional actions for Delete Listing or Suspend Seller
    if ($action_type === 'Delete Listing') {
        $stmt_delete_car = $conn->prepare("DELETE FROM cars WHERE id = ?");
        $stmt_delete_car->bind_param("i", $report['car_id']);
        if (!$stmt_delete_car->execute()) {
            $_SESSION['error'] = "Failed to delete listing: " . htmlspecialchars($conn->error);
            $stmt_delete_car->close();
            header("Location: view_report.php?id=$report_id");
            exit();
        }
        $stmt_delete_car->close();
    } elseif ($action_type === 'Suspend Seller') {
        if ($is_suspended_exists) {
            $stmt_suspend = $conn->prepare("UPDATE users SET is_suspended = 1 WHERE id = ?");
            $stmt_suspend->bind_param("i", $report['seller_id']);
            $stmt_suspend->execute();
            $stmt_suspend->close();
        }
        if ($is_hidden_exists) {
            $stmt_hide_all = $conn->prepare("UPDATE cars SET is_hidden = 1 WHERE seller_id = ?");
            $stmt_hide_all->bind_param("i", $report['seller_id']);
            $stmt_hide_all->execute();
            $stmt_hide_all->close();
        }
    }

    $_SESSION['message'] = "Action recorded successfully.";
    header("Location: view_report.php?id=$report_id");
    exit();
}
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

        .btn-success {
            background-color: var(--success);
            color: white;
        }

        .btn-success:hover {
            background-color: #3ba6d8;
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

        .action-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }

        .action-form .form-group {
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 400px;
        }

        .action-form label {
            font-size: 14px;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .action-form select {
            padding: 12px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            background-color: var(--light);
            color: var(--dark);
            transition: border-color 0.3s ease;
        }

        .action-form select:focus {
            border-color: var(--primary);
            outline: none;
        }

        .action-form textarea {
            padding: 12px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            resize: vertical;
            min-height: 100px;
            background-color: var(--light);
            color: var(--dark);
            transition: border-color 0.3s ease;
        }

        .action-form textarea:focus {
            border-color: var(--primary);
            outline: none;
        }

        .action-form .button-group {
            display: flex;
            gap: 10px;
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

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .modal-content img {
            max-width: 200px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .modal-content p {
            font-size: 16px;
            color: var(--dark);
            margin-bottom: 20px;
        }

        .modal-content .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .modal-content .btn {
            padding: 10px 20px;
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

            .action-form .form-group {
                max-width: 100%;
            }

            .action-form select,
            .action-form textarea {
                width: 100%;
            }

            .action-form .button-group {
                flex-direction: column;
                gap: 10px;
            }

            .section img {
                max-width: 150px;
            }

            .history-table table {
                font-size: 12px;
            }

            .modal-content {
                width: 95%;
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

        <div class="report-container">
            <div class="report-header">
                <img src="<?php echo htmlspecialchars($report['main_image'] ?: 'Uploads/cars/default.jpg'); ?>" alt="<?php echo htmlspecialchars($report['brand'] . ' ' . $report['model']); ?>">
                <div>
                    <h2>Case ID: <?php echo htmlspecialchars($report['case_id']); ?></h2>
                    <p><?php echo htmlspecialchars($report['brand'] . ' ' . $report['model']); ?> - Seller: <?php echo htmlspecialchars($report['seller_username']); ?></p>
                </div>
            </div>

            <div class="section">
                <h3>Car Details</h3>
                <p><strong>Car:</strong> <?php echo htmlspecialchars($report['brand'] . ' ' . $report['model']); ?></p>
                <img src="<?php echo htmlspecialchars($report['main_image'] ?: 'Uploads/cars/default.jpg'); ?>" alt="Car Image">
                <p><a href="view_car.php?id=<?php echo $report['car_id']; ?>" class="btn btn-outline"><i class="fas fa-eye"></i> View Listing</a></p>
            </div>

            <div class="section">
                <h3>Seller Profile</h3>
                <p><strong>Username:</strong> <?php echo htmlspecialchars($report['seller_username']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($report['seller_email']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($report['seller_phone'] ?: 'Not provided'); ?></p>
                <p><strong>Aadhaar Verified:</strong> <?php echo $report['aadhaar_verified'] ? 'Yes' : 'No'; ?></p>
            </div>

            <div class="section">
                <h3>Reporter Details</h3>
                <p><strong>Username:</strong> <?php echo htmlspecialchars($report['reporter_username'] ?: 'Anonymous'); ?></p>
                <p><strong>Contact Info:</strong> <?php echo htmlspecialchars($report['contact_info'] ?: 'Not provided'); ?></p>
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
                <h3>History Log (Other Reports for This Car)</h3>
                <?php if (empty($history_reports)): ?>
                    <p>No other reports for this car.</p>
                <?php else: ?>
                    <div class="history-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Case ID</th>
                                    <th>Category</th>
                                    <th>Reporter</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Date Reported</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history_reports as $hr): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($hr['case_id']); ?></td>
                                        <td><?php echo htmlspecialchars($hr['category']); ?></td>
                                        <td><?php echo htmlspecialchars($hr['reporter_username'] ?: 'Anonymous'); ?></td>
                                        <td><?php echo htmlspecialchars($hr['description']); ?></td>
                                        <td><?php echo htmlspecialchars($hr['status']); ?></td>
                                        <td><?php echo date('M j, Y, h:i A', strtotime($hr['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="section">
                <h3>Admin Actions</h3>
                <form method="POST" class="action-form" id="action-form">
                    <div class="form-group">
                        <label for="action_type">Select Action</label>
                        <select name="action_type" id="action_type" required>
                            <option value="">Choose an action</option>
                            <option value="Dismiss">Approve Listing (Dismiss Report)</option>
                            <option value="Delete Listing">Delete Listing</option>
                            <option value="Suspend Seller">Suspend Seller Account</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="comments">Comments (Optional)</label>
                        <textarea name="comments" id="comments" placeholder="Enter comments about the action..."></textarea>
                    </div>
                    <input type="hidden" name="confirm_action" id="confirm_action" value="">
                    <div class="button-group">
                        <button type="submit" class="btn btn-primary" id="submit-action"><i class="fas fa-save"></i> Submit Action</button>
                        <button type="submit" name="delete_report" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this report? This action cannot be undone.');"><i class="fas fa-trash"></i> Delete Report</button>
                    </div>
                </form>
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
                                    <th>Notifications</th>
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
                                        <td>
                                            <?php if ($action['notification_to_seller']): ?>
                                                <p><strong>Seller:</strong> <?php echo htmlspecialchars($action['notification_to_seller']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($action['notification_to_reporter']): ?>
                                                <p><strong>Reporter:</strong> <?php echo htmlspecialchars($action['notification_to_reporter']); ?></p>
                                            <?php endif; ?>
                                        </td>
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

    <!-- Modal for Delete Listing Confirmation -->
    <div class="modal" id="delete-listing-modal">
        <div class="modal-content">
            <img src="<?php echo htmlspecialchars($report['main_image'] ?: 'Uploads/cars/default.jpg'); ?>" alt="<?php echo htmlspecialchars($report['brand'] . ' ' . $report['model']); ?>">
            <p>Are you sure you want to delete this listing: <strong><?php echo htmlspecialchars($report['brand'] . ' ' . $report['model']); ?></strong>?</p>
            <div class="modal-buttons">
                <button class="btn btn-primary" onclick="confirmDelete()">Confirm</button>
                <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
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
        document.getElementById('action-form').addEventListener('submit', function(e) {
            const actionType = document.getElementById('action_type').value;
            if (actionType === 'Delete Listing' && !document.getElementById('confirm_action').value) {
                e.preventDefault();
                document.getElementById('delete-listing-modal').style.display = 'flex';
            } else if (actionType === 'Suspend Seller' && !document.getElementById('confirm_action').value) {
                e.preventDefault();
                if (confirm('Are you sure you want to suspend this seller?')) {
                    document.getElementById('confirm_action').value = 'true';
                    this.submit();
                }
            }
        });

        function confirmDelete() {
            document.getElementById('confirm_action').value = 'true';
            document.getElementById('action-form').submit();
        }

        function closeModal() {
            document.getElementById('delete-listing-modal').style.display = 'none';
            document.getElementById('action_type').value = '';
            document.getElementById('confirm_action').value = '';
        }
    </script>
</body>
</html>