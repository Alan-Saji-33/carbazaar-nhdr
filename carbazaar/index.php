<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "car_rental_db";

// Initialize session with secure settings
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

// Database connection with error handling
try {
    // First connect without database to create it if needed
    $temp_conn = new mysqli($db_host, $db_user, $db_pass);
    if ($temp_conn->connect_error) {
        throw new Exception("Initial connection failed: " . $temp_conn->connect_error);
    }

    // Create database if not exists
    $create_db = "CREATE DATABASE IF NOT EXISTS $db_name";
    if (!$temp_conn->query($create_db)) {
        throw new Exception("Error creating database: " . $temp_conn->error);
    }
    $temp_conn->close();

    // Connect with database selected
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die($e->getMessage());
}

// Create tables if they don't exist
$tables = [
    "users" => "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        phone VARCHAR(15) NULL,
        user_type ENUM('admin', 'seller', 'buyer') NOT NULL,
        location VARCHAR(100) NULL,
        profile_pic VARCHAR(255) NULL,
        aadhaar_status ENUM('not_submitted', 'pending', 'approved', 'rejected') DEFAULT 'not_submitted',
        aadhaar_path VARCHAR(255) NULL,
        aadhaar_rejection_reason TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    "cars" => "CREATE TABLE IF NOT EXISTS cars (
        id INT AUTO_INCREMENT PRIMARY KEY,
        seller_id INT NOT NULL,
        model VARCHAR(100) NOT NULL,
        brand VARCHAR(50) NOT NULL,
        year INT NOT NULL,
        price INT NOT NULL,
        km_driven INT NOT NULL,
        fuel_type ENUM('Petrol', 'Diesel', 'Electric', 'Hybrid', 'CNG') NOT NULL,
        transmission ENUM('Automatic', 'Manual') NOT NULL,
        main_image VARCHAR(255) NOT NULL,
        sub_image1 VARCHAR(255) NULL,
        sub_image2 VARCHAR(255) NULL,
        sub_image3 VARCHAR(255) NULL,
        location VARCHAR(100) NOT NULL,
        ownership ENUM('First', 'Second', 'Third', 'Other') NOT NULL,
        insurance_status ENUM('Valid', 'Expired', 'None') NOT NULL,
        description TEXT,
        is_sold BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    "favorites" => "CREATE TABLE IF NOT EXISTS favorites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        car_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE,
        UNIQUE KEY unique_favorite (user_id, car_id)
    )"
];

foreach ($tables as $table_name => $sql) {
    if (!$conn->query($sql)) {
        die("Error creating table $table_name: " . $conn->error);
    }
}

// Handle favorite toggle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_favorite']) && isset($_SESSION['user_id'])) {
    $car_id = filter_input(INPUT_POST, 'car_id', FILTER_SANITIZE_NUMBER_INT);
    $user_id = $_SESSION['user_id'];

    // Check if the car is already favorited
    $stmt = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND car_id = ?");
    $stmt->bind_param("ii", $user_id, $car_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Remove from favorites
        $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND car_id = ?");
        $stmt->bind_param("ii", $user_id, $car_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Removed from favorites.";
        } else {
            $_SESSION['error'] = "Failed to remove from favorites.";
        }
    } else {
        // Add to favorites
        $stmt = $conn->prepare("INSERT INTO favorites (user_id, car_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $car_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Added to favorites.";
        } else {
            $_SESSION['error'] = "Failed to add to favorites.";
        }
    }
    $stmt->close();
    header("Location: index.php" . (isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit();
}

// Get cars for listing with prepared statements
$search_where = "WHERE is_sold = FALSE";
$search_params = [];
$param_types = "";

// Check if any search parameters are provided
$search = trim(filter_input(INPUT_GET, 'search', FILTER_UNSAFE_RAW) ?: '');
$min_price = filter_input(INPUT_GET, 'min_price', FILTER_SANITIZE_NUMBER_INT) ?: 0;
$max_price = filter_input(INPUT_GET, 'max_price', FILTER_SANITIZE_NUMBER_INT) ?: 10000000;
$fuel_type = trim(filter_input(INPUT_GET, 'fuel_type', FILTER_UNSAFE_RAW) ?: '');
$transmission = trim(filter_input(INPUT_GET, 'transmission', FILTER_UNSAFE_RAW) ?: '');
$location = trim(filter_input(INPUT_GET, 'location', FILTER_UNSAFE_RAW) ?: '');

$conditions = [];

if (!empty($search)) {
    $conditions[] = "(model LIKE ? OR brand LIKE ? OR description LIKE ?)";
    $search_params = array_merge($search_params, ["%$search%", "%$search%", "%$search%"]);
    $param_types .= "sss";
}

if ($min_price > 0 || $max_price < 10000000) {
    $conditions[] = "price BETWEEN ? AND ?";
    $search_params[] = $min_price;
    $search_params[] = $max_price;
    $param_types .= "ii";
}

if (!empty($fuel_type)) {
    $conditions[] = "fuel_type = ?";
    $search_params[] = $fuel_type;
    $param_types .= "s";
}

if (!empty($transmission)) {
    $conditions[] = "transmission = ?";
    $search_params[] = $transmission;
    $param_types .= "s";
}

if (!empty($location)) {
    $conditions[] = "cars.location LIKE ?";
    $search_params[] = "%$location%";
    $param_types .= "s";
}

if (!empty($conditions)) {
    $search_where .= " AND " . implode(" AND ", $conditions);
}

$sql_cars_list = "SELECT cars.*, users.username AS seller_name, users.phone AS seller_phone, users.email AS seller_email, users.profile_pic AS seller_profile_pic 
                  FROM cars 
                  JOIN users ON cars.seller_id = users.id 
                  $search_where 
                  ORDER BY created_at DESC 
                  LIMIT 12";

$stmt = $conn->prepare($sql_cars_list);
if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}

if (!empty($search_params)) {
    $stmt->bind_param($param_types, ...$search_params);
}

if (!$stmt->execute()) {
    die("Execute failed: " . htmlspecialchars($stmt->error));
}
$cars_result = $stmt->get_result();

// Get favorite cars
$favorites = [];
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT car_id FROM favorites WHERE user_id = ?");
    if ($stmt === false) {
        die("Prepare failed: " . htmlspecialchars($conn->error));
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $favorites_result = $stmt->get_result();
    
    while ($row = $favorites_result->fetch_assoc()) {
        $favorites[] = $row['car_id'];
    }
    $stmt->close();
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Function to format price in Indian number system
function formatIndianPrice($number) {
    $number = (int)$number;
    if ($number < 1000) {
        return $number;
    }
    $number_str = (string)$number;
    $len = strlen($number_str);
    $last_three = substr($number_str, -3);
    $remaining = substr($number_str, 0, $len - 3);
    $formatted = $last_three;
    if ($remaining) {
        $formatted = substr($remaining, -2) . ',' . $formatted;
        $remaining = substr($remaining, 0, -2);
        while ($remaining) {
            $formatted = substr($remaining, -2, 2) . ',' . $formatted;
            $remaining = substr($remaining, 0, -2);
        }
        $formatted = ltrim($formatted, '0,');
    }
    return $formatted;
}
?>

<?php include 'header.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CarBazaar - Used Car Selling Platform</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<style>
.login-margin{
margin-right:10px;
}
</style>
<body>
    <style>
        /* Internal CSS to ensure smooth scrolling */
        html, body {
            scroll-behavior: smooth !important;
        }
    </style>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Find Your Perfect Used Car</h1>
            <p>Buy and sell quality used cars from trusted sellers across India</p>
            <div class="hero-buttons">
                <a href="#cars" class="btn btn-primary">
                    <i class="fas fa-car"></i> Browse Cars
                </a>
                <?php if (isset($_SESSION['user_type']) && ($_SESSION['user_type'] == 'seller' || $_SESSION['user_type'] == 'admin')): ?>
                    <a href="add_car.php" class="btn btn-outline">
                        <i class="fas fa-plus"></i> Add Car
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Search Section -->
    <div class="container">
        <div class="search-section" id="search">
            <div class="search-title">
                <h2>Find Your Dream Car</h2>
                <p>Search through our extensive inventory of quality used cars</p>
            </div>
            
            <form method="GET" class="search-form" action="index.php#cars">
                <div class="form-group">
                    <label for="search"><i class="fas fa-search"></i> Keywords</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Toyota, Honda, SUV..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="min_price"><i class="fas fa-rupee-sign"></i> Min Price</label>
                    <input type="number" id="min_price" name="min_price" class="form-control" min="0" placeholder="₹10,000" value="<?php echo isset($_GET['min_price']) ? htmlspecialchars($_GET['min_price']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="max_price"><i class="fas fa-rupee-sign"></i> Max Price</label>
                    <input type="number" id="max_price" name="max_price" class="form-control" min="0" placeholder="₹50,00,000" value="<?php echo isset($_GET['max_price']) ? htmlspecialchars($_GET['max_price']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="fuel_type"><i class="fas fa-gas-pump"></i> Fuel Type</label>
                    <select id="fuel_type" name="fuel_type" class="form-control">
                        <option value="">Any Fuel Type</option>
                        <option value="Petrol" <?php echo (isset($_GET['fuel_type']) && $_GET['fuel_type'] == 'Petrol') ? 'selected' : ''; ?>>Petrol</option>
                        <option value="Diesel" <?php echo (isset($_GET['fuel_type']) && $_GET['fuel_type'] == 'Diesel') ? 'selected' : ''; ?>>Diesel</option>
                        <option value="Electric" <?php echo (isset($_GET['fuel_type']) && $_GET['fuel_type'] == 'Electric') ? 'selected' : ''; ?>>Electric</option>
                        <option value="Hybrid" <?php echo (isset($_GET['fuel_type']) && $_GET['fuel_type'] == 'Hybrid') ? 'selected' : ''; ?>>Hybrid</option>
                        <option value="CNG" <?php echo (isset($_GET['fuel_type']) && $_GET['fuel_type'] == 'CNG') ? 'selected' : ''; ?>>CNG</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="transmission"><i class="fas fa-cog"></i> Transmission</label>
                    <select id="transmission" name="transmission" class="form-control">
                        <option value="">Any Transmission</option>
                        <option value="Automatic" <?php echo (isset($_GET['transmission']) && $_GET['transmission'] == 'Automatic') ? 'selected' : ''; ?>>Automatic</option>
                        <option value="Manual" <?php echo (isset($_GET['transmission']) && $_GET['transmission'] == 'Manual') ? 'selected' : ''; ?>>Manual</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="location"><i class="fas fa-map-marker-alt"></i> Location</label>
                    <input type="text" id="location" name="location" class="form-control" placeholder="e.g. Kottayam" value="<?php echo isset($_GET['location']) ? htmlspecialchars($_GET['location']) : ''; ?>">
                </div>
                
                <div class="form-group form-actions" >
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search Cars
                    </button>
                     <a href="#" onclick="resetAndScrollToTop()" class="btn btn-outline">
                        <i class="fas fa-sync-alt"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Content -->
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

        <!-- Cars Section -->
        <section id="cars">
            <div class="section-header">
                <h2 class="section-title">Available Cars</h2>
                <?php if (isset($_SESSION['user_type']) && ($_SESSION['user_type'] == 'seller' || $_SESSION['user_type'] == 'admin')): ?>
                    <a href="add_car.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Car
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="cars-grid">
                <?php if ($cars_result->num_rows > 0): ?>
                    <?php while ($car = $cars_result->fetch_assoc()): ?>
                        <div class="car-card">
                            <?php if ($car['is_sold']): ?>
                                <div class="sold-badge">SOLD</div>
                            <?php else: ?>
                                <div class="car-badge">NEW</div>
                            <?php endif; ?>
                            
                            <div class="car-image">
                                <img src="<?php echo htmlspecialchars($car['main_image']); ?>" alt="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>">
                            </div>
                            
                            <div class="car-details">
                                <h3 class="car-title"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h3>
                                <div class="car-price">₹<?php echo formatIndianPrice($car['price']); ?></div>
                                
                                <div class="car-specs">
                                    <span class="car-spec"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($car['year']); ?></span>
                                    <span class="car-spec"><i class="fas fa-tachometer-alt"></i> <?php echo formatIndianPrice($car['km_driven']); ?> km</span>
                                    <span class="car-spec"><i class="fas fa-gas-pump"></i> <?php echo htmlspecialchars($car['fuel_type']); ?></span>
                                    <span class="car-spec"><i class="fas fa-cog"></i> <?php echo htmlspecialchars($car['transmission']); ?></span>
                                    <span class="car-spec"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($car['location']); ?></span>
                                </div>
                                
                                <p class="car-description"><?php echo htmlspecialchars(substr($car['description'], 0, 100)); ?>...</p>
                                
                                <div class="car-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                                        <button type="submit" name="toggle_favorite" class="favorite-btn <?php echo in_array($car['id'], $favorites) ? 'active' : ''; ?>">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                    </form>
                                    <a href="view_car.php?id=<?php echo $car['id']; ?>" class="btn btn-outline">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                        <i class="fas fa-car" style="font-size: 60px; color: var(--light-gray); margin-bottom: 20px;"></i>
                        <h3 style="color: var(--gray);">No cars found matching your criteria</h3>
                        <p>Try adjusting your search filters or check back later for new listings</p>
                        <a href="#" onclick="resetAndScrollToTop()" class="btn btn-primary" style="margin-top: 20px;">
    <i class="fas fa-sync-alt"></i> Reset Search
</a>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer>
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
                        <li><a href="#cars">Browse Cars</a></li>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="#contact">Contact</a></li>
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
                        <li><i class="fas fa-map-marker-alt"></i>  Changanacherry</li>
                        <li><i class="fas fa-phone-alt"></i> +91 9876543210</li>
                        <li><i class="fas fa-envelope"></i> support@carbazaar.com</li>
                    
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>© <?php echo date('Y'); ?> CarBazaar. All Rights Reserved. </p>
            </div>
        </div>
    </footer>

<script>
    // Prevent browser's default hash jump for #cars
    let initialScrollPosition = window.scrollY;
    if (window.location.hash) {
        const hash = window.location.hash;
        window.location.hash = '';
        window.scrollTo({ top: initialScrollPosition, behavior: 'instant' });
        setTimeout(() => {
            window.location.hash = hash;
        }, 0);
    }

    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });

    // Smooth scrolling for Home link
    document.querySelectorAll('a[href="index.php"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            // If already on index.php, scroll to top; otherwise, navigate
            if (window.location.pathname.endsWith('index.php')) {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            } else {
                window.location.href = 'index.php';
            }
        });
    });

    // Function for Reset button to clear form and reload page
    function resetAndScrollToTop() {
        // Clear form fields
        const form = document.querySelector('.search-form');
        if (form) {
            form.reset();
        }
        // Reload the page without query parameters and scroll to cars section
        window.location.href = 'index.php#cars';
    }

    // Smooth scrolling on page load for URL hash (e.g., #cars from form submission)
    document.addEventListener('DOMContentLoaded', () => {
        const hash = window.location.hash;
        if (hash && hash !== '#') {
            const targetElement = document.querySelector(hash);
            if (targetElement) {
                const rect = targetElement.getBoundingClientRect();
                const isInView = rect.top >= 0 && rect.top <= window.innerHeight;
                if (!isInView) {
                    setTimeout(() => {
                        targetElement.scrollIntoView({
                            behavior: 'smooth'
                        });
                    }, 600); // Delay for dynamic content
                }
            }
        }
    });
</script>
</body>
</html>