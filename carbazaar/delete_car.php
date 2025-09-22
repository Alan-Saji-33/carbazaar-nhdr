<?php
// Start output buffering to prevent headers issues
ob_start();

// Initialize session with secure settings (matching index.php)
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

// Database configuration (matching index.php)
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "car_rental_db";

// Database connection with error handling
try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: index.php");
    exit();
}

// Check if user is logged in and has correct permissions
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'seller' && $_SESSION['user_type'] !== 'admin')) {
    $_SESSION['error'] = "Please login as a seller or admin to delete a car.";
    header("Location: login.php");
    exit();
}

// Check if car ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No car specified.";
    header("Location: index.php");
    exit();
}

// Function to format price in Indian number system (copied from index.php)
function formatIndianPrice($number) {
    $number = (int)$number;
    if ($number < 1000) {
        return $number;
    }
    $last_three = substr($number, -3);
    $remaining = substr($number, 0, -3);
    $formatted = '';
    if (strlen($remaining) > 2) {
        $formatted = substr($remaining, -2) . ',' . $last_three;
        $remaining = substr($remaining, 0, -2);
    } else {
        $formatted = $remaining . ',' . $last_three;
        $remaining = '';
    }
    while ($remaining) {
        if (strlen($remaining) > 2) {
            $formatted = substr($remaining, -2) . ',' . $formatted;
            $remaining = substr($remaining, 0, -2);
        } else {
            $formatted = $remaining . ',' . $formatted;
            $remaining = '';
        }
    }
    return rtrim($formatted, ',');
}

// Fetch car details, allowing admins to delete any car
$car_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if ($_SESSION['user_type'] == 'admin') {
    $stmt = $conn->prepare("SELECT * FROM cars WHERE id = ?");
    if ($stmt === false) {
        $_SESSION['error'] = "Prepare failed: " . htmlspecialchars($conn->error);
        header("Location: index.php");
        exit();
    }
    $stmt->bind_param("i", $car_id);
} else {
    $stmt = $conn->prepare("SELECT * FROM cars WHERE id = ? AND seller_id = ?");
    if ($stmt === false) {
        $_SESSION['error'] = "Prepare failed: " . htmlspecialchars($conn->error);
        header("Location: index.php");
        exit();
    }
    $stmt->bind_param("ii", $car_id, $_SESSION['user_id']);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error'] = "Car not found or you don't have permission to delete it.";
    header("Location: profile.php");
    exit();
}
$car = $result->fetch_assoc();
$stmt->close();

// Handle car deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $stmt = $conn->prepare("DELETE FROM cars WHERE id = ?");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $car_id);
        if ($stmt->execute()) {
            // Delete associated images
            foreach (['main_image', 'sub_image1', 'sub_image2', 'sub_image3'] as $img_field) {
                if ($car[$img_field] && file_exists($car[$img_field])) {
                    unlink($car[$img_field]);
                }
            }
            $_SESSION['message'] = "Car deleted successfully!";
            header("Location: profile.php");
            exit();
        } else {
            throw new Exception("Failed to delete car: " . $stmt->error);
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
    <title>Delete Car - CarBazaar</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Inline CSS retained for main content (unchanged) */
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --accent: #4895ef;
            --dark: #1b263b;
            --light: #f8f9fa;
            --success: #4cc9f0;
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .auth-container {
            max-width: 600px;
            width: 100%;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            padding: 30px;
            margin: 40px 0;
            animation: slideIn 0.5s ease;
        }

        .auth-form-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .auth-header h2 {
            font-size: 28px;
            font-weight: 600;
            color: var(--dark);
            margin: 0 0 10px;
            position: relative;
        }

        .auth-header h2::after {
            content: '';
            display: block;
            width: 50px;
            height: 3px;
            background: var(--primary);
            margin: 10px auto;
        }

        .auth-header p {
            font-size: 16px;
            color: var(--gray);
            margin: 0;
        }

        .car-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .car-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .car-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark);
            margin: 0 0 15px;
            text-align: center;
        }

        .car-main-image {
            width: 100%;
            max-width: 400px;
            height: 250px;
            object-fit: contain;
            border-radius: 10px;
            margin-bottom: 15px;
            border: 1px solid var(--light-gray);
        }

        .car-price {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .form-group {
            width: 100%;
            max-width: 400px;
            margin-bottom: 20px;
            text-align: center;
        }

        .btn-danger {
            background-color: var(--danger);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
            width: 100%;
            border: none;
        }

        .btn-danger:hover {
            background-color: #d1145a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(247, 37, 133, 0.2);
        }

        .form-footer {
            text-align: center;
            margin-top: 20px;
        }

        .form-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            font-size: 16px;
            transition: color 0.3s ease;
        }

        .form-footer a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
            max-width: 600px;
            width: 100%;
        }

        .alert-success {
            background-color: var(--success);
            color: white;
        }

        .alert-error {
            background-color: var(--danger);
            color: white;
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
            .container {
                padding: 15px;
            }

            .auth-container {
                margin: 20px;
                padding: 20px;
            }

            .car-main-image {
                max-width: 300px;
                height: 200px;
            }

            .car-card {
                padding: 15px;
                max-width: 100%;
            }

            .auth-header h2 {
                font-size: 24px;
            }

            .auth-header p {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <!-- Header (Copied exactly from index.php, with alignment fix) -->
    <header class="header">
        <div class="container header-container">
            <a href="index.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-car"></i>
                </div>
                <div class="logo-text">Car<span>Bazaar</span></div>
            </a>
            
            <nav class="nav">
                <ul>
                    <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="index.php#cars"><i class="fas fa-car"></i> Cars</a></li>
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
                <?php if (isset($_SESSION['username'])): ?>
                    <div class="user-greeting">
                        Welcome, <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </div>
                    <a href="index.php?logout" class="btn btn-outline">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                    <a href="register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main content (unchanged) -->
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

        <?php if ($car): ?>
            <div class="auth-container">
                <div class="auth-form-container">
                    <div class="auth-header">
                        <h2>Delete Car</h2>
                        <p>Are you sure you want to delete this car listing?</p>
                    </div>
                    <div class="car-card">
                        <h3 class="car-title"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h3>
                        <img src="<?php echo htmlspecialchars($car['main_image'] ?: 'Uploads/cars/default.jpg'); ?>" alt="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>" class="car-main-image">
                        <div class="car-price">₹<?php echo formatIndianPrice($car['price']); ?></div>
                    </div>
                    <form method="POST">
                        <div class="form-group">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Confirm Delete
                            </button>
                        </div>
                        <div class="form-footer">
                            <p><a href="profile.php">Cancel</a></p>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer (Copied exactly from index.php, with alignment fix) -->
    <footer class="footer">
        <div class="container">
            <div class="footer-container">
                <div class="footer-column">
                    <h3>CarBazaar</h3>
                    <p>Your trusted platform for buying and selling quality used cars across India. Explore a wide range of vehicles with verified sellers.</p>
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
                <p>© <?php echo date('Y'); ?> CarBazaar. All Rights Reserved. Designed with <i class="fas fa-heart"></i> in India.</p>
            </div>
        </div>
    </footer>
</body>
</html>
<?php
ob_end_flush();
?>