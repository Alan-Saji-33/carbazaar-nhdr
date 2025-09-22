<?php
// Start output buffering to prevent headers already sent errors
ob_start();

session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please login to view your favorites.";
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

// Function to format price in Indian number system
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

// Handle remove favorite
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_favorite'])) {
    try {
        $car_id = filter_input(INPUT_POST, 'car_id', FILTER_SANITIZE_NUMBER_INT);
        $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND car_id = ?");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ii", $_SESSION['user_id'], $car_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Car removed from favorites!";
        } else {
            throw new Exception("Failed to remove favorite: " . $stmt->error);
        }
        $stmt->close();
        header("Location: favorites.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Fetch favorite cars
$stmt = $conn->prepare("SELECT cars.* FROM cars JOIN favorites ON cars.id = favorites.car_id WHERE favorites.user_id = ? ORDER BY favorites.created_at DESC");
if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$favorites_result = $stmt->get_result();
$stmt->close();
?>

<?php include 'header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Favorites - CarBazaar</title>
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
            margin: 0;
            padding: 0;
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

        /* Favorites Section */
        .section-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .section-title {
            font-size: 32px;
            color: var(--dark);
            margin: 0;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 16px;
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

        .cars-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 60px;
        }

        .car-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .car-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .car-image {
            width: 100%;
            height: 200px;
            overflow: hidden;
        }

        .car-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }

        .car-details {
            padding: 15px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .car-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .car-price {
            font-size: 16px;
            font-weight: 500;
            color: var(--primary);
        }

        .car-specs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 14px;
            color: var(--gray);
        }

        .car-spec {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .car-actions {
            display: flex;
            gap: 10px;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
        }

        .favorite-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: var(--danger);
            transition: color 0.3s ease;
        }

        .favorite-btn.active i {
            color: var(--danger);
        }

        .favorite-btn:hover i {
            color: #d1145a;
        }

        .sold-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--danger);
            color: white;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
        }

        .car-card.sold {
            opacity: 0.7;
        }

        .no-favorites {
            text-align: center;
            font-size: 18px;
            color: var(--gray);
            padding: 40px;
            grid-column: 1 / -1;
        }

        .no-favorites i {
            font-size: 60px;
            color: var(--light-gray);
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .cars-grid {
                grid-template-columns: 1fr;
            }
            .car-card {
                max-width: 100%;
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
            <h2 class="section-title">Your Favorite Cars</h2>
        </div>

        <div class="cars-grid">
            <?php if ($favorites_result->num_rows > 0): ?>
                <?php while ($car = $favorites_result->fetch_assoc()): ?>
                    <div class="car-card <?php echo $car['is_sold'] ? 'sold' : ''; ?>">
                        <?php if ($car['is_sold']): ?>
                            <div class="sold-badge">SOLD</div>
                        <?php endif; ?>
                        <div class="car-image">
                            <img src="<?php echo htmlspecialchars($car['main_image'] ?: 'Uploads/cars/default.jpg'); ?>" alt="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>">
                        </div>
                        <div class="car-details">
                            <h3 class="car-title"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h3>
                            <div class="car-price">₹<?php echo formatIndianPrice($car['price']); ?></div>
                            <div class="car-specs">
                                <span class="car-spec"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($car['year']); ?></span>
                                <span class="car-spec"><i class="fas fa-tachometer-alt"></i> <?php echo formatIndianPrice($car['km_driven']); ?> km</span>
                                <span class="car-spec"><i class="fas fa-gas-pump"></i> <?php echo htmlspecialchars($car['fuel_type']); ?></span>
                            </div>
                            <div class="car-actions">
                                <a href="view_car.php?id=<?php echo $car['id']; ?>" class="btn btn-primary"><i class="fas fa-eye"></i> View Details</a>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                                    <button type="submit" name="remove_favorite" class="favorite-btn active"><i class="fas fa-heart"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-favorites">
                    <i class="fas fa-heart"></i>
                    <p>No favorite cars added yet.</p>
                    <a href="index.php#cars" class="btn btn-primary"><i class="fas fa-car"></i> Browse Cars</a>
                </div>
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
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="profile.php">Profile</a></li>
                        <li><a href="favorites.php">Favorites</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Help & Support</h3>
                    <ul>
                        <li><a href="about.php#faq">FAQ</a></li>
                        <li><a href="about.php#privacy-policy">Privacy Policy</a></li>
                        <li><a href="about.php#terms-conditions">Terms & Conditions</a></li>
                        <li><a href="about.php#how-to-sell">How to Sell</a></li>
                        <li><a href="about.php#buyer-guide">Buyer Guide</a></li>
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
// End output buffering
ob_end_flush();
?>