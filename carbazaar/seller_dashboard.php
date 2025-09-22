<?php
ob_start();
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'seller') {
    $_SESSION['error'] = "Please login as a seller to access the dashboard.";
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

// Handle Mark as Sold
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_sold'])) {
    $car_id = filter_input(INPUT_POST, 'car_id', FILTER_SANITIZE_NUMBER_INT);
    $stmt = $conn->prepare("UPDATE cars SET is_sold = 1 WHERE id = ? AND seller_id = ?");
    $stmt->bind_param("ii", $car_id, $_SESSION['user_id']);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Car marked as sold successfully!";
    } else {
        $_SESSION['error'] = "Failed to mark car as sold.";
    }
    $stmt->close();
    header("Location: seller_dashboard.php?section=active");
    exit();
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_car'])) {
    $car_id = filter_input(INPUT_POST, 'car_id', FILTER_SANITIZE_NUMBER_INT);
    $stmt = $conn->prepare("DELETE FROM cars WHERE id = ? AND seller_id = ?");
    $stmt->bind_param("ii", $car_id, $_SESSION['user_id']);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Car deleted successfully!";
    } else {
        $_SESSION['error'] = "Failed to delete car.";
    }
    $stmt->close();
    header("Location: seller_dashboard.php?section=active");
    exit();
}

// Handle Mark as Unsold
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_unsold'])) {
    $car_id = filter_input(INPUT_POST, 'car_id', FILTER_SANITIZE_NUMBER_INT);
    $stmt = $conn->prepare("UPDATE cars SET is_sold = 0 WHERE id = ? AND seller_id = ?");
    $stmt->bind_param("ii", $car_id, $_SESSION['user_id']);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Car marked as unsold successfully!";
    } else {
        $_SESSION['error'] = "Failed to mark car as unsold.";
    }
    $stmt->close();
    header("Location: seller_dashboard.php?section=sold");
    exit();
}

// Fetch seller's profile picture
$stmt = $conn->prepare("SELECT profile_pic, aadhaar_status, aadhaar_rejection_reason FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$seller_result = $stmt->get_result();
$seller = $seller_result->num_rows > 0 ? $seller_result->fetch_assoc() : ['profile_pic' => 'Uploads/profiles/default.jpg', 'aadhaar_status' => 'pending', 'aadhaar_rejection_reason' => ''];
$stmt->close();

// Fetch statistics
$stats = [
    'active_listings' => $conn->query("SELECT COUNT(*) FROM cars WHERE seller_id = {$_SESSION['user_id']} AND is_sold = 0 AND is_hidden = 0")->fetch_row()[0],
    'sold_listings' => $conn->query("SELECT COUNT(*) FROM cars WHERE seller_id = {$_SESSION['user_id']} AND is_sold = 1")->fetch_row()[0],
    'reported_listings' => $conn->query("SELECT COUNT(*) FROM reports WHERE car_id IN (SELECT id FROM cars WHERE seller_id = {$_SESSION['user_id']})")->fetch_row()[0]
];

// Determine which section to display
$section = isset($_GET['section']) ? filter_input(INPUT_GET, 'section', FILTER_SANITIZE_STRING) : 'dashboard';

// Fetch active listings
if ($section === 'active') {
    $stmt = $conn->prepare("SELECT id, brand, model, main_image, created_at FROM cars WHERE seller_id = ? AND is_sold = 0 AND is_hidden = 0 ORDER BY created_at DESC");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $active_listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Fetch sold listings
if ($section === 'sold') {
    $stmt = $conn->prepare("SELECT id, brand, model, main_image, created_at FROM cars WHERE seller_id = ? AND is_sold = 1 ORDER BY created_at DESC");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $sold_listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Fetch reports for seller's cars
if ($section === 'reports') {
    $stmt = $conn->prepare("
        SELECT r.id, r.case_id, r.category, r.created_at, 
               " . ($conn->query("SHOW COLUMNS FROM reports LIKE 'status'")->num_rows > 0 ? "r.status" : "'New' AS status") . ",
               c.brand, c.model
        FROM reports r
        JOIN cars c ON r.car_id = c.id
        WHERE c.seller_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
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
    <title>Seller Dashboard - CarBazaar</title>
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

        .car-image {
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
            <h2 class="section-title">Seller Dashboard</h2>
        </div>

        <div class="dashboard-container">
            <div class="sidebar">
                <ul>
                    <li><a href="seller_dashboard.php?section=dashboard" <?php echo $section === 'dashboard' ? 'class="active"' : ''; ?>><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="seller_dashboard.php?section=active" <?php echo $section === 'active' ? 'class="active"' : ''; ?>><i class="fas fa-car"></i> Active Listings</a></li>
                    <li><a href="seller_dashboard.php?section=sold" <?php echo $section === 'sold' ? 'class="active"' : ''; ?>><i class="fas fa-check-circle"></i> Sold Listings</a></li>
                    <li><a href="seller_dashboard.php?section=reports" <?php echo $section === 'reports' ? 'class="active"' : ''; ?>><i class="fas fa-flag"></i> Reports</a></li>
                </ul>
            </div>
            <div class="content">
                <?php
                switch ($section) {
                    case 'active':
                        ?>
                        <div class="table-container">
                            <h3>Active Listings</h3>
                            <?php if (empty($active_listings)): ?>
                                <p>No active listings available.</p>
                            <?php else: ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Image</th>
                                            <th>Car Title</th>
                                            <th>Date Listed</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($active_listings as $car): ?>
                                            <tr>
                                                <td><img src="<?php echo htmlspecialchars($car['main_image'] ?: 'Uploads/cars/default.jpg'); ?>" class="car-image" alt="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>"></td>
                                                <td><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></td>
                                                <td><?php echo date('M j, Y, h:i A', strtotime($car['created_at'])); ?></td>
                                                <td class="action-buttons">
                                                    <a href="view_car.php?id=<?php echo $car['id']; ?>" class="btn btn-primary"><i class="fas fa-eye"></i> View</a>
                                                    <a href="edit_car.php?id=<?php echo $car['id']; ?>" class="btn btn-outline"><i class="fas fa-edit"></i> Edit</a>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                                                        <button type="submit" name="mark_sold" class="btn btn-success"><i class="fas fa-check"></i> Mark as Sold</button>
                                                    </form>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                                                        <button type="submit" name="delete_car" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                        <?php
                        break;
                    case 'sold':
                        ?>
                        <div class="table-container">
                            <h3>Sold Listings</h3>
                            <?php if (empty($sold_listings)): ?>
                                <p>No sold listings available.</p>
                            <?php else: ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Image</th>
                                            <th>Car Title</th>
                                            <th>Date Listed</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sold_listings as $car): ?>
                                            <tr>
                                                <td><img src="<?php echo htmlspecialchars($car['main_image'] ?: 'Uploads/cars/default.jpg'); ?>" class="car-image" alt="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>"></td>
                                                <td><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></td>
                                                <td><?php echo date('M j, Y, h:i A', strtotime($car['created_at'])); ?></td>
                                                <td class="action-buttons">
                                                    <a href="view_car.php?id=<?php echo $car['id']; ?>" class="btn btn-primary"><i class="fas fa-eye"></i> View</a>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                                                        <button type="submit" name="mark_unsold" class="btn btn-success"><i class="fas fa-undo"></i> Mark as Unsold</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                        <?php
                        break;
                    case 'reports':
                        ?>
                        <div class="table-container">
                            <h3>Reports on Your Listings</h3>
                            <?php if (empty($reports)): ?>
                                <p>No reports on your listings.</p>
                            <?php else: ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Case ID</th>
                                            <th>Car Title</th>
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
                                                <td><?php echo htmlspecialchars($report['category']); ?></td>
                                                <td><?php echo htmlspecialchars($report['status']); ?></td>
                                                <td><?php echo date('M j, Y, h:i A', strtotime($report['created_at'])); ?></td>
                                                <td class="action-buttons">
                                                    <a href="seller_view_report.php?id=<?php echo $report['id']; ?>" class="btn btn-primary"><i class="fas fa-eye"></i> View Report</a>
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
                            <img src="<?php echo htmlspecialchars($seller['profile_pic']); ?>" alt="<?php echo htmlspecialchars($_SESSION['username']); ?>">
                            <div>
                                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
                                <p>Manage your listings efficiently from this dashboard.</p>
                            </div>
                        </div>
                        <div class="dashboard-stats">
                            <div class="stat-card">
                                <i class="fas fa-car"></i>
                                <h3><?php echo $stats['active_listings']; ?></h3>
                                <p>Active Listings</p>
                            </div>
                            <div class="stat-card">
                                <i class="fas fa-check-circle"></i>
                                <h3><?php echo $stats['sold_listings']; ?></h3>
                                <p>Sold Listings</p>
                            </div>
                            <div class="stat-card">
                                <i class="fas fa-flag"></i>
                                <h3><?php echo $stats['reported_listings']; ?></h3>
                                <p>Reported Listings</p>
                            </div>
                        </div>
                        <div class="table-container">
                            <h3>Quick Actions</h3>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                                <a href="add_car.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Car</a>
                                <a href="seller_dashboard.php?section=active" class="btn btn-outline"><i class="fas fa-car"></i> View Active Listings</a>
                                <a href="seller_dashboard.php?section=sold" class="btn btn-outline"><i class="fas fa-check-circle"></i> View Sold Listings</a>
                                <a href="seller_dashboard.php?section=reports" class="btn btn-outline"><i class="fas fa-flag"></i> View Reports</a>
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