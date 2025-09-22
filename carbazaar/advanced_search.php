<?php
// Start output buffering
ob_start();

// Initialize session with secure settings (matching index.php)
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

// Database configuration
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "car_rental_db";

// Database connection
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

// Handle search query
$search_where = "WHERE is_sold = FALSE";
$search_params = [];
$param_types = "";

// Process search filters
$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?: '';
$min_price = filter_input(INPUT_GET, 'min_price', FILTER_SANITIZE_NUMBER_INT) ?: 0;
$max_price = filter_input(INPUT_GET, 'max_price', FILTER_SANITIZE_NUMBER_INT) ?: 10000000;
$brand = filter_input(INPUT_GET, 'brand', FILTER_SANITIZE_STRING) ?: '';
$model = filter_input(INPUT_GET, 'model', FILTER_SANITIZE_STRING) ?: '';
$min_year = filter_input(INPUT_GET, 'min_year', FILTER_SANITIZE_NUMBER_INT) ?: 0;
$max_year = filter_input(INPUT_GET, 'max_year', FILTER_SANITIZE_NUMBER_INT) ?: date('Y');
$fuel_type = filter_input(INPUT_GET, 'fuel_type', FILTER_SANITIZE_STRING) ?: '';
$transmission = filter_input(INPUT_GET, 'transmission', FILTER_SANITIZE_STRING) ?: '';
$location = filter_input(INPUT_GET, 'location', FILTER_SANITIZE_STRING) ?: '';
$ownership = filter_input(INPUT_GET, 'ownership', FILTER_SANITIZE_STRING) ?: '';
$insurance_status = filter_input(INPUT_GET, 'insurance_status', FILTER_SANITIZE_STRING) ?: '';
$max_km = filter_input(INPUT_GET, 'max_km', FILTER_SANITIZE_NUMBER_INT) ?: 1000000;
$sort = filter_input(INPUT_GET, 'sort', FILTER_SANITIZE_STRING) ?: 'created_at_desc';

$conditions = [];

if (!empty(trim($search))) {
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

if (!empty($brand)) {
    $conditions[] = "brand = ?";
    $search_params[] = $brand;
    $param_types .= "s";
}

if (!empty($model)) {
    $conditions[] = "model = ?";
    $search_params[] = $model;
    $param_types .= "s";
}

if ($min_year > 0 || $max_year < date('Y')) {
    $conditions[] = "year BETWEEN ? AND ?";
    $search_params[] = $min_year;
    $search_params[] = $max_year;
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

if (!empty(trim($location))) {
    $conditions[] = "cars.location LIKE ?";
    $search_params[] = "%$location%";
    $param_types .= "s";
}

if (!empty($ownership)) {
    $conditions[] = "ownership = ?";
    $search_params[] = $ownership;
    $param_types .= "s";
}

if (!empty($insurance_status)) {
    $conditions[] = "insurance_status = ?";
    $search_params[] = $insurance_status;
    $param_types .= "s";
}

if ($max_km < 1000000) {
    $conditions[] = "km_driven <= ?";
    $search_params[] = $max_km;
    $param_types .= "i";
}

// Sort order
$sort_options = [
    'price_asc' => 'price ASC',
    'price_desc' => 'price DESC',
    'year_asc' => 'year ASC',
    'year_desc' => 'year DESC',
    'km_asc' => 'km_driven ASC',
    'km_desc' => 'km_driven DESC',
    'created_at_desc' => 'created_at DESC'
];
$order_by = $sort_options[$sort] ?? 'created_at DESC';

if (!empty($conditions)) {
    $search_where .= " AND " . implode(" AND ", $conditions);
}

$sql_cars = "SELECT cars.*, users.username AS seller_name 
             FROM cars 
             JOIN users ON cars.seller_id = users.id 
             $search_where 
             ORDER BY $order_by 
             LIMIT 12";

$stmt = $conn->prepare($sql_cars);
if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}

if (!empty($search_params)) {
    $stmt->bind_param($param_types, ...$search_params);
}

$stmt->execute();
$cars_result = $stmt->get_result();

// Get favorite cars
$favorites = [];
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT car_id FROM favorites WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $favorites_result = $stmt->get_result();
    while ($row = $favorites_result->fetch_assoc()) {
        $favorites[] = $row['car_id'];
    }
    $stmt->close();
}

// Get distinct brands and models for dropdowns
$brands = $conn->query("SELECT DISTINCT brand FROM cars WHERE is_sold = FALSE ORDER BY brand")->fetch_all(MYSQLI_ASSOC);
$models = $conn->query("SELECT DISTINCT model FROM cars WHERE is_sold = FALSE ORDER BY model")->fetch_all(MYSQLI_ASSOC);
$locations = $conn->query("SELECT DISTINCT location FROM cars WHERE is_sold = FALSE ORDER BY location")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Search - CarBazaar</title>
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
            display: flex;
            flex-direction: row;
            gap: 20px;
        }

        .filter-panel {
            flex: 1;
            max-width: 300px;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            padding: 20px;
            position: sticky;
            top: 20px;
        }

        .filter-header {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 20px;
            text-align: center;
        }

        .filter-group {
            margin-bottom: 20px;
        }

        .filter-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--gray);
            margin-bottom: 8px;
        }

        .filter-group input, .filter-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 14px;
            color: var(--dark);
            background-color: var(--light);
            transition: border-color 0.3s ease, box-shadow 0.3s ease, transform 0.3s ease;
        }

        .filter-group input:focus, .filter-group select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 8px rgba(67, 97, 238, 0.2);
            outline: none;
        }

        .filter-group select {
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg fill="none" stroke="%236c757d" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path></svg>');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 16px;
            cursor: pointer;
        }

        .filter-group select:focus {
            animation: dropdownExpand 0.3s ease forwards;
        }

        @keyframes dropdownExpand {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.02);
            }
            100% {
                transform: scale(1);
            }
        }

        .range-slider {
            position: relative;
            height: 8px;
            margin: 20px 0 30px 0;
            background: var(--light-gray);
            border-radius: 5px;
        }

        .range-slider .slider-fill {
            position: absolute;
            height: 100%;
            background: var(--primary);
            border-radius: 5px;
            transition: all 0.2s ease;
        }

        .range-slider input[type="range"] {
            position: absolute;
            width: 100%;
            height: 8px;
            top: 0;
            margin: 0;
            background: none;
            pointer-events: none;
            -webkit-appearance: none;
            appearance: none;
        }

        .range-slider input[type="range"]::-webkit-slider-thumb {
            pointer-events: all;
            width: 18px;
            height: 18px;
            background: var(--primary);
            border: 2px solid white;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            cursor: pointer;
            -webkit-appearance: none;
            appearance: none;
            transition: transform 0.2s ease;
        }

        .range-slider input[type="range"]::-webkit-slider-thumb:hover {
            transform: scale(1.2);
        }

        .range-slider input[type="range"]::-moz-range-thumb {
            pointer-events: all;
            width: 18px;
            height: 18px;
            background: var(--primary);
            border: 2px solid white;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .range-slider input[type="range"]::-moz-range-thumb:hover {
            transform: scale(1.2);
        }

        .range-slider input[type="range"]::-webkit-slider-runnable-track {
            height: 8px;
            background: transparent;
            border-radius: 5px;
        }

        .range-slider input[type="range"]::-moz-range-track {
            height: 8px;
            background: transparent;
            border-radius: 5px;
        }

        .range-values {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 10px;
        }

        .results-panel {
            flex: 3;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .results-header h2 {
            font-size: 28px;
            font-weight: 600;
            color: var(--dark);
        }

        .sort-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sort-group label {
            font-size: 14px;
            font-weight: 500;
            color: var(--gray);
        }

        .sort-group select {
            padding: 8px 30px 8px 10px;
            border-radius: 8px;
            border: 1px solid var(--light-gray);
            font-size: 14px;
            color: var(--dark);
            background-color: var(--light);
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg fill="none" stroke="%236c757d" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path></svg>');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 16px;
            cursor: pointer;
            transition: border-color 0.3s ease, box-shadow 0.3s ease, transform 0.3s ease;
        }

        .sort-group select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 8px rgba(67, 97, 238, 0.2);
            animation: dropdownExpand 0.3s ease forwards;
        }

        .cars-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .car-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .car-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .car-image {
            width: 100%;
            height: 200px;
            overflow: hidden;
            background-color: var(--light);
        }

        .car-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            border-bottom: 1px solid var(--light-gray);
        }

        .car-details {
            padding: 15px;
        }

        .car-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
            margin: 0 0 10px;
        }

        .car-price {
            font-size: 18px;
            font-weight: 500;
            color: var(--primary);
            margin-bottom: 10px;
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
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
        }

        .btn-outline {
            border: 1px solid var(--primary);
            color: var(--primary);
            background: transparent;
        }

        .btn-outline:hover {
            background-color: var(--primary);
            color: white;
        }

        .favorite-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: var(--gray);
        }

        .favorite-btn.active {
            color: var(--danger);
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

        /* Footer Styles (Matching index.php and register.php) */
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

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .filter-panel {
                max-width: 100%;
                position: static;
            }

            .results-panel {
                width: 100%;
            }

            .cars-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header (Copied from index.php) -->
    <header>
        <div class="container header-container">
            <a href="index.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-car"></i>
                </div>
                <div class="logo-text">Car<span>Bazaar</span></div>
            </a>
            <nav>
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

    <!-- Main Content -->
    <div class="container">
        <!-- Filter Panel (Left) -->
        <div class="filter-panel">
            <h2 class="filter-header">Advanced Search</h2>
            <form method="GET" id="advanced-search-form">
                <div class="filter-group">
                    <label for="search"><i class="fas fa-search"></i> Keywords</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Brand, Model, Description">
                </div>
                <div class="filter-group">
                    <label for="min_price"><i class="fas fa-rupee-sign"></i> Price Range</label>
                    <div class="range-values">
                        <span id="min-price-display">₹0</span>
                        <span id="max-price-display">₹1,00,00,000</span>
                    </div>
                    <div class="range-slider" id="price-slider">
                        <div class="slider-fill" id="price-fill"></div>
                        <input type="range" id="min_price" name="min_price" min="0" max="10000000" value="<?php echo $min_price; ?>">
                        <input type="range" id="max_price" name="max_price" min="0" max="10000000" value="<?php echo $max_price; ?>">
                    </div>
                </div>
                <div class="filter-group">
                    <label for="brand"><i class="fas fa-car"></i> Brand</label>
                    <select id="brand" name="brand">
                        <option value="">Any Brand</option>
                        <?php foreach ($brands as $b): ?>
                            <option value="<?php echo htmlspecialchars($b['brand']); ?>" <?php echo $brand == $b['brand'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b['brand']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="model"><i class="fas fa-car-side"></i> Model</label>
                    <select id="model" name="model">
                        <option value="">Any Model</option>
                        <?php foreach ($models as $m): ?>
                            <option value="<?php echo htmlspecialchars($m['model']); ?>" <?php echo $model == $m['model'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['model']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="min_year"><i class="fas fa-calendar-alt"></i> Year Range</label>
                    <div class="range-values">
                        <span id="min-year-display">2000</span>
                        <span id="max-year-display"><?php echo date('Y'); ?></span>
                    </div>
                    <div class="range-slider" id="year-slider">
                        <div class="slider-fill" id="year-fill"></div>
                        <input type="range" id="min_year" name="min_year" min="2000" max="<?php echo date('Y'); ?>" value="<?php echo $min_year ?: 2000; ?>">
                        <input type="range" id="max_year" name="max_year" min="2000" max="<?php echo date('Y'); ?>" value="<?php echo $max_year ?: date('Y'); ?>">
                    </div>
                </div>
                <div class="filter-group">
                    <label for="fuel_type"><i class="fas fa-gas-pump"></i> Fuel Type</label>
                    <select id="fuel_type" name="fuel_type">
                        <option value="">Any Fuel Type</option>
                        <option value="Petrol" <?php echo $fuel_type == 'Petrol' ? 'selected' : ''; ?>>Petrol</option>
                        <option value="Diesel" <?php echo $fuel_type == 'Diesel' ? 'selected' : ''; ?>>Diesel</option>
                        <option value="Electric" <?php echo $fuel_type == 'Electric' ? 'selected' : ''; ?>>Electric</option>
                        <option value="Hybrid" <?php echo $fuel_type == 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                        <option value="CNG" <?php echo $fuel_type == 'CNG' ? 'selected' : ''; ?>>CNG</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="transmission"><i class="fas fa-cog"></i> Transmission</label>
                    <select id="transmission" name="transmission">
                        <option value="">Any Transmission</option>
                        <option value="Automatic" <?php echo $transmission == 'Automatic' ? 'selected' : ''; ?>>Automatic</option>
                        <option value="Manual" <?php echo $transmission == 'Manual' ? 'selected' : ''; ?>>Manual</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="location"><i class="fas fa-map-marker-alt"></i> Location</label>
                    <select id="location" name="location">
                        <option value="">Any Location</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?php echo htmlspecialchars($loc['location']); ?>" <?php echo $location == $loc['location'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($loc['location']); ?> (<?php
                                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM cars WHERE is_sold = FALSE AND location = ?");
                                    $stmt->bind_param("s", $loc['location']);
                                    $stmt->execute();
                                    $count = $stmt->get_result()->fetch_assoc()['count'];
                                    echo $count . ($count == 1 ? ' car' : ' cars');
                                    $stmt->close();
                                ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="ownership"><i class="fas fa-user"></i> Ownership</label>
                    <select id="ownership" name="ownership">
                        <option value="">Any Ownership</option>
                        <option value="First" <?php echo $ownership == 'First' ? 'selected' : ''; ?>>First</option>
                        <option value="Second" <?php echo $ownership == 'Second' ? 'selected' : ''; ?>>Second</option>
                        <option value="Third" <?php echo $ownership == 'Third' ? 'selected' : ''; ?>>Third</option>
                        <option value="Other" <?php echo $ownership == 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="insurance_status"><i class="fas fa-shield-alt"></i> Insurance Status</label>
                    <select id="insurance_status" name="insurance_status">
                        <option value="">Any Insurance Status</option>
                        <option value="Valid" <?php echo $insurance_status == 'Valid' ? 'selected' : ''; ?>>Valid</option>
                        <option value="Expired" <?php echo $insurance_status == 'Expired' ? 'selected' : ''; ?>>Expired</option>
                        <option value="None" <?php echo $insurance_status == 'None' ? 'selected' : ''; ?>>None</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="max_km"><i class="fas fa-tachometer-alt"></i> Max Kilometers Driven</label>
                    <div class="range-slider" id="km-slider">
                        <div class="slider-fill" id="km-fill" style="left: 0%; width: 100%;"></div>
                        <input type="range" id="max_km" name="max_km" min="0" max="1000000" value="<?php echo $max_km; ?>">
                    </div>
                    <div class="range-values">
                        <span>0 km</span>
                        <span id="max-km-display"><?php echo formatIndianPrice($max_km); ?> km</span>
                    </div>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    <a href="advanced_search.php" class="btn btn-outline"><i class="fas fa-sync-alt"></i> Reset</a>
                </div>
                <input type="hidden" name="sort" id="hidden-sort" value="<?php echo $sort; ?>">
            </form>
        </div>

        <!-- Results Panel (Right) -->
        <div class="results-panel">
            <div class="results-header">
                <h2>Search Results</h2>
                <div class="sort-group">
                    <label for="sort">Sort By:</label>
                    <select id="sort" name="sort" onchange="updateAndSubmitSort()">
                        <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="year_asc" <?php echo $sort == 'year_asc' ? 'selected' : ''; ?>>Year: Oldest to Newest</option>
                        <option value="year_desc" <?php echo $sort == 'year_desc' ? 'selected' : ''; ?>>Year: Newest to Oldest</option>
                        <option value="km_asc" <?php echo $sort == 'km_asc' ? 'selected' : ''; ?>>Kilometers: Low to High</option>
                        <option value="km_desc" <?php echo $sort == 'km_desc' ? 'selected' : ''; ?>>Kilometers: High to Low</option>
                        <option value="created_at_desc" <?php echo $sort == 'created_at_desc' ? 'selected' : ''; ?>>Newest Listings</option>
                    </select>
                </div>
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
                                <div class="car-actions">
                                    <form method="POST" action="index.php" style="display: inline;">
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
                        <a href="advanced_search.php" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Reset Search
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer (Copied from index.php) -->
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
                        <li><a href="index.php#cars">Browse Cars</a></li>
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

    <script>
        // Function to update range slider with collision prevention
        function updateRangeSlider(sliderId, minId, maxId, minDisplayId, maxDisplayId, formatFunc, minVal, maxVal) {
            const slider = document.getElementById(sliderId);
            const minInput = document.getElementById(minId);
            const maxInput = document.getElementById(maxId);
            const fill = slider.querySelector('.slider-fill');
            const minDisplay = document.getElementById(minDisplayId);
            const maxDisplay = document.getElementById(maxDisplayId);
            const minGap = (maxVal - minVal) * 0.01; // 1% of range as minimum gap

            function update() {
                let min = parseInt(minInput.value);
                let max = parseInt(maxInput.value);

                // Prevent collision
                if (max - min < minGap) {
                    if (minInput === document.activeElement) {
                        max = min + minGap;
                        maxInput.value = max;
                    } else {
                        min = max - minGap;
                        minInput.value = min;
                    }
                }

                // Ensure values stay within bounds
                min = Math.max(minVal, Math.min(min, maxVal - minGap));
                max = Math.min(maxVal, Math.max(max, minVal + minGap));
                minInput.value = min;
                maxInput.value = max;

                const minPercent = ((min - minVal) / (maxVal - minVal)) * 100;
                const maxPercent = ((max - minVal) / (maxVal - minVal)) * 100;
                fill.style.left = minPercent + '%';
                fill.style.width = (maxPercent - minPercent) + '%';
                minDisplay.textContent = formatFunc(min);
                maxDisplay.textContent = formatFunc(max);
            }

            minInput.addEventListener('input', update);
            maxInput.addEventListener('input', update);
            update();
        }

        // Function to update single slider for km
        function updateKmSlider() {
            const maxInput = document.getElementById('max_km');
            const fill = document.getElementById('km-fill');
            const maxDisplay = document.getElementById('max-km-display');
            const minVal = 0;
            const maxVal = 1000000;

            function update() {
                const value = parseInt(maxInput.value);
                const percent = ((value - minVal) / (maxVal - minVal)) * 100;
                fill.style.width = percent + '%';
                maxDisplay.textContent = formatIndianPrice(value) + ' km';
            }

            maxInput.addEventListener('input', update);
            update();
        }

        // Format Indian price in JavaScript
        function formatIndianPrice(number) {
            number = parseInt(number);
            if (number < 1000) return number;
            let lastThree = number.toString().slice(-3);
            let remaining = number.toString().slice(0, -3);
            let formatted = '';
            if (remaining.length > 2) {
                formatted = remaining.slice(-2) + ',' + lastThree;
                remaining = remaining.slice(0, -2);
            } else {
                formatted = remaining + ',' + lastThree;
                remaining = '';
            }
            while (remaining) {
                if (remaining.length > 2) {
                    formatted = remaining.slice(-2) + ',' + formatted;
                    remaining = remaining.slice(0, -2);
                } else {
                    formatted = remaining + ',' + formatted;
                    remaining = '';
                }
            }
            return formatted.replace(/^,/, '');
        }

        function formatPrice(val) {
            return '₹' + formatIndianPrice(val);
        }

        function formatYear(val) {
            return val;
        }

        // Initialize sliders
        updateRangeSlider('price-slider', 'min_price', 'max_price', 'min-price-display', 'max-price-display', formatPrice, 0, 10000000);
        updateRangeSlider('year-slider', 'min_year', 'max_year', 'min-year-display', 'max-year-display', formatYear, 2000, <?php echo date('Y'); ?>);
        updateKmSlider();

        // Update sort and submit form
        function updateAndSubmitSort() {
            const sortValue = document.getElementById('sort').value;
            document.getElementById('hidden-sort').value = sortValue;
            document.getElementById('advanced-search-form').submit();
        }
    </script>
</body>
</html>
<?php
ob_end_flush();
?>