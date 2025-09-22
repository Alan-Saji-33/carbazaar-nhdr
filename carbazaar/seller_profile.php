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

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No seller specified.";
    header("Location: index.php");
    exit();
}

$seller_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$stmt = $conn->prepare("SELECT username, location, created_at, profile_pic FROM users WHERE id = ?");
if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error'] = "Seller not found.";
    header("Location: index.php");
    exit();
}
$seller = $result->fetch_assoc();
$stmt->close();

// Fetch seller's cars
$stmt = $conn->prepare("SELECT id, brand, model, price, main_image, is_sold FROM cars WHERE seller_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$cars_result = $stmt->get_result();
$stmt->close();
?>

<?php include 'header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($seller['username']); ?>'s Profile - CarBazaar</title>
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

        /* Header Styles (Unchanged from Provided) */
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
    
    .login-margin{
    margin-right:10px;
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

        /* Seller Profile Styles */
        .seller-profile-container {
            max-width: 1200px;
            margin: 50px auto;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
            animation: slideIn 0.5s ease;
        }

        .seller-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--light-gray);
        }

        .seller-header img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }

        .seller-header h2 {
            font-size: 28px;
            color: var(--dark);
            margin: 0;
        }

        .seller-header p {
            font-size: 14px;
            color: var(--gray);
            margin: 5px 0 0;
        }

        .seller-cars {
            margin-top: 20px;
        }

        .seller-cars h3 {
            font-size: 22px;
            color: var(--dark);
            margin: 0 0 20px;
        }

        .car-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .car-card {
            background-color: var(--light);
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }

        .car-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }

        .car-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-bottom: 1px solid var(--light-gray);
        }

        .car-card .sold-badge {
            position: absolute;
            top: 10px;
            left: 10px;
        width:55px;
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            background: linear-gradient(to right, var(--danger), #d1145a);
            color: white;
        }

        .car-card-content {
            padding: 15px;
        }

        .car-card h4 {
            font-size: 18px;
            color: var(--dark);
            margin: 0 0 10px;
        }

        .car-card p {
            font-size: 14px;
            color: var(--gray);
            margin: 0 0 10px;
        }

        .car-card a {
            display: inline-flex;
            align-items: center;
            padding: 8px 15px;
            background-color: var(--primary);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }

        .car-card a:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
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

        /* Footer Styles (Matching index.php and view_car.php) */
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
            .seller-profile-container {
                margin: 20px auto;
                padding: 20px;
            }
            .seller-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .seller-header img {
                width: 60px;
                height: 60px;
            }
            .seller-header h2 {
                font-size: 24px;
            }
            .car-grid {
                grid-template-columns: 1fr;
            }
            .car-card img {
                height: 120px;
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

        <div class="seller-profile-container">
            <div class="seller-header">
                <img src="<?php echo htmlspecialchars($seller['profile_pic'] ?: 'Uploads/profiles/default.jpg'); ?>" alt="<?php echo htmlspecialchars($seller['username']); ?>">
                <div>
                    <h2><?php echo htmlspecialchars($seller['username']); ?></h2>
                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($seller['location'] ?: 'Not specified'); ?></p>
                    <p><i class="fas fa-calendar-alt"></i> Joined: <?php echo date('F j, Y', strtotime($seller['created_at'])); ?></p>
                </div>
            </div>
            <div class="seller-cars">
                <h3>Cars Listed by <?php echo htmlspecialchars($seller['username']); ?></h3>
                <?php if ($cars_result->num_rows > 0): ?>
                    <div class="car-grid">
                        <?php while ($car = $cars_result->fetch_assoc()): ?>
                            <div class="car-card">
                                <?php if ($car['is_sold']): ?>
                                    <div class="sold-badge">SOLD</div>
                                <?php endif; ?>
                                <img src="<?php echo htmlspecialchars($car['main_image'] ?: 'Uploads/cars/default.jpg'); ?>" alt="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>">
                                <div class="car-card-content">
                                    <h4><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h4>
                                    <p>₹<?php echo number_format($car['price'], 0, '', ','); ?></p>
                                    <a href="view_car.php?id=<?php echo $car['id']; ?>" class="btn"><i class="fas fa-eye"></i> View Details</a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p>No cars listed by this seller.</p>
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