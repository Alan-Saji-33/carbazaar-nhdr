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
$stmt = $conn->prepare("SELECT username, email, phone, user_type, location, profile_pic, aadhaar_status, aadhaar_path, aadhaar_rejection_reason, created_at FROM users WHERE id = ?");
if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->num_rows > 0 ? $user_result->fetch_assoc() : null;
$stmt->close();

if (!$user) {
    $_SESSION['error'] = "User not found.";
    header("Location: admin_dashboard.php?section=users");
    exit();
}

// Fetch user's cars
$stmt = $conn->prepare("SELECT id, brand, model, price, is_sold, main_image FROM cars WHERE seller_id = ? ORDER BY created_at DESC");
if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cars_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User - CarBazaar</title>
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

        /* User Details Section */
        .user-details-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .user-details-header {
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

        .user-details-header h2 {
            font-size: 24px;
            color: var(--dark);
            margin: 0;
        }

        .user-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .user-detail {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .user-detail label {
            font-weight: 500;
            color: var(--gray);
            font-size: 14px;
        }

        .user-detail span, .user-detail a {
            font-size: 16px;
            color: var(--dark);
        }

        .user-detail a {
            color: var(--primary);
            text-decoration: none;
        }

        .user-detail a:hover {
            text-decoration: underline;
        }

        /* Cars Section */
        .table-container {
            background-color: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
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
            width: 60px;
            height: 40px;
            object-fit: cover;
            border-radius: 8px;
            vertical-align: middle;
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
            .user-details-grid {
                grid-template-columns: 1fr;
            }
            .user-details-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .action-buttons {
                flex-direction: column;
                align-items: flex-end;
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

        <div class="section-header">
            <h2 class="section-title">User Details</h2>
        </div>

        <div class="user-details-container">
            <div class="user-details-header">
                <img src="<?php echo htmlspecialchars($user['profile_pic'] ?: 'Uploads/profiles/default.jpg'); ?>" alt="<?php echo htmlspecialchars($user['username']); ?>" class="user-profile-pic">
                <h2><?php echo htmlspecialchars($user['username']); ?></h2>
            </div>
            <div class="user-details-grid">
                <div class="user-detail">
                    <label>Email</label>
                    <span><?php echo htmlspecialchars($user['email']); ?></span>
                </div>
                <div class="user-detail">
                    <label>Phone</label>
                    <span><?php echo htmlspecialchars($user['phone'] ?: 'Not provided'); ?></span>
                </div>
                <div class="user-detail">
                    <label>User Type</label>
                    <span><?php echo ucfirst(htmlspecialchars($user['user_type'])); ?></span>
                </div>
                <div class="user-detail">
                    <label>Location</label>
                    <span><?php echo htmlspecialchars($user['location'] ?: 'Not provided'); ?></span>
                </div>
                <div class="user-detail">
                    <label>Aadhaar Status</label>
                    <span><?php echo ucfirst(htmlspecialchars($user['aadhaar_status'])); ?></span>
                </div>
                <div class="user-detail">
                    <label>Aadhaar Document</label>
                    <?php if ($user['aadhaar_path']): ?>
                        <a href="<?php echo htmlspecialchars($user['aadhaar_path']); ?>" target="_blank">View Aadhaar</a>
                    <?php else: ?>
                        <span>Not uploaded</span>
                    <?php endif; ?>
                </div>
                <?php if ($user['aadhaar_status'] === 'rejected' && $user['aadhaar_rejection_reason']): ?>
                    <div class="user-detail">
                        <label>Rejection Reason</label>
                        <span><?php echo htmlspecialchars($user['aadhaar_rejection_reason']); ?></span>
                    </div>
                <?php endif; ?>
                <div class="user-detail">
                    <label>Joined On</label>
                    <span><?php echo date('F j, Y, g:i a', strtotime($user['created_at'])); ?></span>
                </div>
            </div>
        </div>

        <div class="table-container">
            <h3>Listed Cars</h3>
            <?php if ($cars_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Car</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($car = $cars_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <img src="<?php echo htmlspecialchars($car['main_image'] ?: 'Uploads/cars/default.jpg'); ?>" alt="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>" class="car-image">
                                        <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>
                                    </div>
                                </td>
                                <td style="text-align: right;">₹<?php echo number_format($car['price'], 0, '', ','); ?></td>
                                <td style="text-align: center;"><?php echo $car['is_sold'] ? 'Sold' : 'Available'; ?></td>
                                <td class="action-buttons">
                                    <a href="view_car.php?id=<?php echo $car['id']; ?>" class="btn btn-outline"><i class="fas fa-eye"></i> View</a>
                                    <a href="edit_car.php?id=<?php echo $car['id']; ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Edit</a>
                                    <?php if (!$car['is_sold']): ?>
                                        <form method="POST" action="admin_cars.php" style="display: inline;">
                                            <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                                            <button type="submit" name="mark_sold" class="btn btn-warning"><i class="fas fa-check-circle"></i> Mark Sold</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No cars listed by this user.</p>
            <?php endif; ?>
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
</body>
</html>