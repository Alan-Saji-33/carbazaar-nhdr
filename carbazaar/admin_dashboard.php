<?php
ob_start();
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = "Please login as an admin to access the dashboard.";
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

// Fetch admin's profile picture
$stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$admin_result = $stmt->get_result();
$admin = $admin_result->num_rows > 0 ? $admin_result->fetch_assoc() : ['profile_pic' => 'Uploads/profiles/default.jpg'];
$stmt->close();

// Fetch statistics
$stats = [
    'total_users' => $conn->query("SELECT COUNT(*) FROM users WHERE user_type != 'admin'")->fetch_row()[0],
    'total_cars' => $conn->query("SELECT COUNT(*) FROM cars")->fetch_row()[0],
    'pending_aadhaar' => $conn->query("SELECT COUNT(*) FROM users WHERE aadhaar_status = 'pending'")->fetch_row()[0],
    'sold_cars' => $conn->query("SELECT COUNT(*) FROM cars WHERE is_sold = TRUE")->fetch_row()[0]
];

// Determine which section to display
$section = isset($_GET['section']) ? filter_input(INPUT_GET, 'section', FILTER_SANITIZE_STRING) : 'dashboard';

// Fetch reports for the Reports section
if ($section === 'reports') {
    $stmt = $conn->prepare("
        SELECT r.id, r.case_id, r.category, r.created_at, 
               " . ($conn->query("SHOW COLUMNS FROM reports LIKE 'status'")->num_rows > 0 ? "r.status" : "'New' AS status") . ",
               c.brand, c.model, u.username AS seller_username
        FROM reports r
        JOIN cars c ON r.car_id = c.id
        JOIN users u ON c.seller_id = u.id
        WHERE r.status = 'New'
        ORDER BY r.created_at DESC
    ");
    $stmt->execute();
    $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<?php include 'header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CarBazaar</title>
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

        .btn-danger {
            background-color: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background-color: #d1145a;
            transform: translateY(-2px);
        }

        .btn-success {
            background-color: var(--success);
            color: white;
        }

        .btn-success:hover {
            background-color: #0ea5e9;
            transform: translateY(-2px);
        }

        .btn-warning {
            background-color: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background-color: #e07b00;
            transform: translateY(-2px);
        }

        .dashboard-container {
            display: flex;
            gap: 30px;
            margin-top: 30px;
        }

        .sidebar {
            width: 250px;
            background-color: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 20px;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar li {
            margin-bottom: 10px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            color: var(--gray);
            text-decoration: none;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .sidebar a:hover, .sidebar a.active {
            background-color: var(--primary);
            color: white;
            transform: translateX(5px);
        }

        .sidebar a i {
            font-size: 18px;
        }

        .content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .dashboard-header {
            background: linear-gradient(to right, var(--primary), var(--secondary));
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .dashboard-header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .dashboard-header h2 {
            font-size: 28px;
            margin: 0;
        }

        .dashboard-header p {
            font-size: 16px;
            margin: 0;
            opacity: 0.9;
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, var(--primary), var(--accent));
        }

        .stat-card h3 {
            font-size: 28px;
            color: var(--primary);
            margin: 10px 0;
        }

        .stat-card p {
            color: var(--gray);
            font-size: 16px;
            margin: 0;
        }

        .stat-card i {
            font-size: 24px;
            color: var(--accent);
            margin-bottom: 10px;
        }

        .table-container {
            background-color: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .table-container h3 {
            margin: 0 0 20px;
            font-size: 22px;
            color: var(--dark);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
        }

        th {
            background-color: var(--light);
            font-weight: 600;
            color: var(--dark);
        }

        tr:hover {
            background-color: var(--light);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .car-image, .user-image {
            width: 70px;
            height: 40px;
            object-fit: cover;
            border-radius: 10%;
            vertical-align: middle;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
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
            .dashboard-container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                position: static;
            }
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
            .action-buttons {
                flex-direction: column;
                align-items: flex-end;
            }
            .dashboard-header {
                flex-direction: column;
                gap: 10px;
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

        <div class="section-header">
            <h2 class="section-title">Admin Dashboard</h2>
        </div>

        <div class="dashboard-container">
            <div class="sidebar">
                <ul>
                    <li><a href="admin_dashboard.php?section=dashboard" <?php echo $section === 'dashboard' ? 'class="active"' : ''; ?>><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="admin_dashboard.php?section=verifications" <?php echo $section === 'verifications' ? 'class="active"' : ''; ?>><i class="fas fa-id-card"></i> Verifications</a></li>
                    <li><a href="admin_dashboard.php?section=users" <?php echo $section === 'users' ? 'class="active"' : ''; ?>><i class="fas fa-users"></i> Users</a></li>
                    <li><a href="admin_dashboard.php?section=cars" <?php echo $section === 'cars' ? 'class="active"' : ''; ?>><i class="fas fa-car"></i> Cars</a></li>
                    <li><a href="admin_dashboard.php?section=reports" <?php echo $section === 'reports' ? 'class="active"' : ''; ?>><i class="fas fa-flag"></i> Reports</a></li>
                </ul>
            </div>
            <div class="content">
                <?php
                switch ($section) {
                    case 'verifications':
                        include 'admin_verifications.php'; 
                        break;
                    case 'users':
                        include 'admin_users.php';
                        break;
                    case 'cars':
                        include 'admin_cars.php';
                        break;
                    case 'reports':
                        ?>
                        <div class="table-container">
                            <h3>Reports</h3>
                            <?php if (empty($reports)): ?>
                                <p>No reports available.</p>
                            <?php else: ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Case ID</th>
                                            <th>Car Title</th>
                                            <th>Seller Name</th>
                                            <th>Category</th>
                                            <th>Status</th>
                                            <th>Date Reported</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reports as $report): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($report['case_id']); ?></td>
                                                <td><?php echo htmlspecialchars($report['brand'] . ' ' . $report['model']); ?></td>
                                                <td><?php echo htmlspecialchars($report['seller_username']); ?></td>
                                                <td><?php echo htmlspecialchars($report['category']); ?></td>
                                                <td><?php echo htmlspecialchars($report['status']); ?></td>
                                                <td><?php echo date('M j, Y, h:i A', strtotime($report['created_at'])); ?></td>
                                                <td class="action-buttons">
                                                    <a href="view_report.php?id=<?php echo $report['id']; ?>" class="btn btn-primary"><i class="fas fa-eye"></i> View Case</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                        <?php
                        break;
                    default:
                        ?>
                        <div class="dashboard-header">
                            <img src="<?php echo htmlspecialchars($admin['profile_pic']); ?>" alt="<?php echo htmlspecialchars($_SESSION['username']); ?>">
                            <div>
                                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
                                <p>Manage your platform efficiently from this dashboard.</p>
                            </div>
                        </div>
                        <div class="dashboard-stats">
                            <a href="admin_dashboard.php?section=users" class="stat-card">
                                <i class="fas fa-users"></i>
                                <h3><?php echo $stats['total_users']; ?></h3>
                                <p>Total Users</p>
                            </a>
                            <a href="admin_dashboard.php?section=cars" class="stat-card">
                                <i class="fas fa-car"></i>
                                <h3><?php echo $stats['total_cars']; ?></h3>
                                <p>Total Cars</p>
                            </a>
                            <div class="stat-card">
                                <i class="fas fa-id-card"></i>
                                <h3><?php echo $stats['pending_aadhaar']; ?></h3>
                                <p>Pending Aadhaar</p>
                            </div>
                            <div class="stat-card">
                                <i class="fas fa-check-circle"></i>
                                <h3><?php echo $stats['sold_cars']; ?></h3>
                                <p>Sold Cars</p>
                            </div>
                        </div>
                        <div class="table-container">
                            <h3>Quick Actions</h3>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                                <a href="add_car.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Car</a>
                                <a href="admin_dashboard.php?section=verifications" class="btn btn-outline"><i class="fas fa-id-card"></i> Review Aadhaar Verifications</a>
                                <a href="admin_dashboard.php?section=users" class="btn btn-outline"><i class="fas fa-users"></i> Manage Users</a>
                                <a href="admin_dashboard.php?section=cars" class="btn btn-outline"><i class="fas fa-car"></i> Manage Cars</a>
                            </div>
                        </div>
                        <?php
                        break;
                }
                ?>
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
<?php
ob_end_flush();
?>