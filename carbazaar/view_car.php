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
    $_SESSION['error'] = "No car specified.";
    header("Location: index.php");
    exit();
}

$car_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$stmt = $conn->prepare("SELECT cars.*, users.id AS seller_id, users.username AS seller_name, users.phone AS seller_phone, users.email AS seller_email, users.profile_pic AS seller_profile_pic 
                        FROM cars 
                        JOIN users ON cars.seller_id = users.id 
                        WHERE cars.id = ?");
if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $car_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error'] = "Car not found.";
    header("Location: index.php");
    exit();
}
$car = $result->fetch_assoc();
$stmt->close();

// Handle favorite toggle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_favorite']) && isset($_SESSION['user_id'])) {
    try {
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND car_id = ?");
        $stmt->bind_param("ii", $user_id, $car_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND car_id = ?");
            $stmt->bind_param("ii", $user_id, $car_id);
            $action = "removed from";
        } else {
            $stmt = $conn->prepare("INSERT INTO favorites (user_id, car_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $user_id, $car_id);
            $action = "added to";
        }
        if (!$stmt->execute()) {
            throw new Exception("Failed to update favorites: " . $stmt->error);
        }
        $_SESSION['message'] = "Car $action favorites!";
        header("Location: view_car.php?id=$car_id");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    $stmt->close();
}

// Check if car is in favorites
$is_favorite = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND car_id = ?");
    $stmt->bind_param("ii", $_SESSION['user_id'], $car_id);
    $stmt->execute();
    $is_favorite = $stmt->get_result()->num_rows > 0;
    $stmt->close();
}

// Count unread messages
$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT COUNT(*) as unread FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $unread_count = $result->fetch_assoc()['unread'];
    $stmt->close();
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?> - CarBazaar</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --accent: #4361ee;
            --dark: #1b263b;
            --light: #f8f9fa;
            --success: #4cc9f0;
            --warning: #f8961e;
            --danger: #f72585;
            --gray: #6c757d;
            --light-gray: #e9ecef;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: var(--light);
            color: var(--dark);
        }

        .container {
            display: flex;
            max-width: 1200px;
            margin: 20px auto;
            padding: 10px;
            gap: 20px;
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
            max-width: 1200px;
            margin: 0 auto;
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
            position: relative;
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

        nav ul li .badge {
            position: absolute;
            top: -8px;
            right: -10px;
            background-color: var(--danger);
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .user-actions {
            display: flex;
            align-items: center;
        }

        .login-margin {
            margin-right: 10px;
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

        /* Left Scroll Section */
        .left-section {
            flex: 2;
            overflow-y: auto;
            padding-right: 15px;
        }

        .car-image-container {
            position: relative;
        }

        .car-image {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            border-radius: 10px;
            transition: opacity 0.3s ease;
        }

        .sold-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            padding: 8px 15px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            background: var(--danger);
            color: white;
        }

        .gallery-nav {
            position: absolute;
            top: 50%;
            width: 100%;
            display: flex;
            justify-content: space-between;
            transform: translateY(-50%);
            z-index: 10;
        }

        .gallery-nav button {
            background-color: rgba(0, 0, 0, 0.5);
            border: none;
            color: white;
            font-size: 20px;
            padding: 10px;
            cursor: pointer;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }

        .gallery-nav button:hover {
            background-color: var(--primary);
            transform: scale(1.1);
        }

        .gallery-nav button:disabled {
            background-color: rgba(0, 0, 0, 0.3);
            cursor: not-allowed;
        }

        .car-gallery {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .car-gallery img {
            width: 100px;
            height: 75px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: transform 0.3s ease, border 0.3s ease;
        }

        .car-gallery img:hover,
        .car-gallery img.active {
            transform: scale(1.05);
            border: 2px solid var(--primary);
        }

        .tabs {
            display: flex;
            margin-top: 15px;
            border-bottom: 2px solid var(--light-gray);
        }

        .tab {
            margin-right: 20px;
            padding: 10px;
            cursor: pointer;
            font-weight: 600;
            color: var(--gray);
            transition: color 0.3s ease;
        }

        .tab.active {
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
        }

        .tab-content {
            display: none;
            margin-top: 20px;
        }

        .tab-content.active {
            display: block;
        }

        .car-overview, .car-gallery-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .car-overview h3, .car-gallery-section h3 {
            margin-bottom: 15px;
            color: var(--dark);
        }

        .overview-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .overview-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
        }

        .overview-item i {
            margin-right: 8px;
            color: var(--accent);
        }

        .car-description {
            margin-top: 20px;
            font-size: 14px;
            color: var(--gray);
            line-height: 1.6;
        }

        .gallery-content img {
            width: 100%;
            max-height: 500px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 10px;
            cursor: pointer;
        }

        .car-gallery-section img {
            width: 100px;
            height: 75px;
            object-fit: cover;
            border-radius: 8px;
            margin: 5px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .car-gallery-section img:hover {
            transform: scale(1.05);
        }

        /* Full-Screen Image View */
        .fullscreen-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .fullscreen-image {
            max-width: 90%;
            max-height: 80%;
            object-fit: contain;
            border-radius: 10px;
        }

        .fullscreen-nav {
            position: absolute;
            top: 50%;
            width: 100%;
            display: flex;
            justify-content: space-between;
            transform: translateY(-50%);
        }

        .fullscreen-nav button {
            background-color: rgba(0, 0, 0, 0.5);
            border: none;
            color: white;
            font-size: 24px;
            padding: 15px;
            cursor: pointer;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease;
        }

        .fullscreen-nav button:hover {
            background-color: var(--primary);
        }

        .fullscreen-nav button:disabled {
            background-color: rgba(0, 0, 0, 0.3);
            cursor: not-allowed;
        }

        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--danger);
            color: white;
            border: none;
            font-size: 18px;
            padding: 10px;
            border-radius: 50%;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .close-btn:hover {
            background: #d1145a;
        }

        /* Right Fixed Section */
        .right-section {
            flex: 1;
            position: sticky;
            top: 20px;
            height: fit-content;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .car-title-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .right-section h2 {
            font-size: 24px;
            color: var(--dark);
            font-weight: 600;
        }

        .price {
            font-size: 28px;
            color: var(--primary);
            font-weight: bold;
        }

        .car-info {
            font-size: 14px;
            color: var(--gray);
            line-height: 1.6;
        }

        .car-info hr {
            border: 0;
            border-top: 1px solid var(--light-gray);
            margin: 10px 0;
        }

        .location {
            font-size: 14px;
            color: var(--gray);
        }

        .seller-container {
            background: var(--light);
            padding: 15px;
            border-radius: 8px;
        }

        .seller-container h3 {
            font-size: 16px;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .seller-container p {
            font-size: 14px;
            color: var(--gray);
            margin: 5px 0;
        }

        .seller-container a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .seller-container a:hover {
            text-decoration: underline;
        }

        .car-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .favorite-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 20px;
            color: var(--gray);
            transition: color 0.3s ease;
        }

        .favorite-btn.active {
            color: var(--danger);
        }

        .favorite-btn:hover {
            color: var(--danger);
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            .right-section {
                position: static;
            }
            .car-image {
                max-height: 300px;
            }
            .car-gallery img, .car-gallery-section img {
                width: 80px;
                height: 60px;
            }
            .overview-grid {
                grid-template-columns: 1fr;
            }
            .fullscreen-image {
                max-width: 95%;
                max-height: 70%;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-container">
            <a href="index.php" class="logo">
                <div class="logo-icon"><i class="fas fa-car"></i></div>
                <div class="logo-text">Car<span>Bazaar</span></div>
            </a>
            <nav>
                <ul>
                    <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="index.php#cars"><i class="fas fa-car"></i> Cars</a></li>
                    <li><a href="index.php#about"><i class="fas fa-info-circle"></i> About</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                        <li><a href="favorites.php"><i class="fas fa-heart"></i> Favorites</a></li>
                        <li>
                            <a href="messages.php"><i class="fas fa-envelope"></i> Messages
                                <?php if ($unread_count > 0): ?>
                                    <span class="badge"><?php echo $unread_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php if ($_SESSION['user_type'] == 'admin'): ?>
                            <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="user-actions">
                <?php if (isset($_SESSION['username'])): ?>
                    <div class="user-greeting">Welcome, <span><?php echo htmlspecialchars($_SESSION['username']); ?></span></div>
                    <a href="index.php?logout" class="btn btn-outline"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline login-margin"><i class="fas fa-sign-in-alt"></i> Login</a>
                    <a href="register.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Register</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- LEFT SECTION -->
        <div class="left-section">
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

            <div class="car-image-container">
                <?php if ($car['is_sold']): ?>
                    <div class="sold-badge">SOLD</div>
                <?php endif; ?>
                <img src="<?php echo htmlspecialchars($car['main_image']); ?>" alt="Car Image" class="car-image" id="main-image">
                <div class="gallery-nav">
                    <button onclick="prevImage()" class="prev-btn"><i class="fas fa-chevron-left"></i></button>
                    <button onclick="nextImage()" class="next-btn"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
            <div class="car-gallery">
                <img src="<?php echo htmlspecialchars($car['main_image']); ?>" alt="Main Image" class="sub-image active" onclick="openFullscreen(0)">
                <?php 
                $images = [$car['main_image']];
                $index = 1;
                foreach (['sub_image1', 'sub_image2', 'sub_image3'] as $img_field): 
                    if ($car[$img_field]): 
                        $images[] = $car[$img_field];
                ?>
                    <img src="<?php echo htmlspecialchars($car[$img_field]); ?>" alt="Car Image" class="sub-image" onclick="openFullscreen(<?php echo $index; ?>)">
                <?php 
                    $index++;
                    endif; 
                endforeach; 
                ?>
            </div>

            <div class="tabs">
                <div class="tab active" onclick="showTab('overview')">OVERVIEW</div>
                <div class="tab" onclick="showTab('gallery')">GALLERY</div>
            </div>

            <div id="overview" class="tab-content active">
                <div class="car-overview">
                    <h3>Car Overview</h3>
                    <div class="overview-grid">
                        <div class="overview-item"><span><i class="fas fa-calendar-alt"></i> Year</span><span><?php echo htmlspecialchars($car['year']); ?></span></div>
                        <div class="overview-item"><span><i class="fas fa-tachometer-alt"></i> Kms Driven</span><span><?php echo formatIndianPrice($car['km_driven']); ?> km</span></div>
                        <div class="overview-item"><span><i class="fas fa-gas-pump"></i> Fuel Type</span><span><?php echo htmlspecialchars($car['fuel_type']); ?></span></div>
                        <div class="overview-item"><span><i class="fas fa-cog"></i> Transmission</span><span><?php echo htmlspecialchars($car['transmission']); ?></span></div>
                        <div class="overview-item"><span><i class="fas fa-map-marker-alt"></i> Location</span><span><?php echo htmlspecialchars($car['location']); ?></span></div>
                        <div class="overview-item"><span><i class="fas fa-user"></i> Ownership</span><span><?php echo htmlspecialchars($car['ownership']); ?> Owner</span></div>
                        <div class="overview-item"><span><i class="fas fa-shield-alt"></i> Insurance</span><span><?php echo htmlspecialchars($car['insurance_status']); ?></span></div>
                    </div>
                    <div class="car-description">
                        <h3>Description</h3>
                        <p><?php echo nl2br(htmlspecialchars($car['description'])); ?></p>
                    </div>
                </div>
                <div class="car-gallery-section">
                    <h3>Car Gallery</h3>
                    <div class="car-gallery">
                        <?php foreach ($images as $idx => $img): ?>
                            <img src="<?php echo htmlspecialchars($img); ?>" alt="Car Image" onclick="openFullscreen(<?php echo $idx; ?>)">
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div id="gallery" class="tab-content">
                <?php foreach ($images as $idx => $img): ?>
                    <img src="<?php echo htmlspecialchars($img); ?>" alt="Car Image" onclick="openFullscreen(<?php echo $idx; ?>)">
                <?php endforeach; ?>
            </div>
        </div>

        <!-- RIGHT SECTION -->
        <div class="right-section">
            <div class="car-title-container">
                <h2><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h2>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                        <button type="submit" name="toggle_favorite" class="favorite-btn <?php echo $is_favorite ? 'active' : ''; ?>">
                            <i class="fas fa-heart"></i>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <p class="price">₹<?php echo formatIndianPrice($car['price']); ?></p>
            <p class="car-info">
                <?php echo formatIndianPrice($car['km_driven']); ?> kms • <?php echo htmlspecialchars($car['fuel_type']); ?> • <?php echo htmlspecialchars($car['transmission']); ?> • <?php echo htmlspecialchars($car['ownership']); ?> Owner
                <hr>
                <span class="location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($car['location']); ?></span>
            </p>
            <div class="seller-container">
                <h3>Seller Information</h3>
                <p><a href="seller_profile.php?id=<?php echo $car['seller_id']; ?>"><i class="fas fa-user"></i> <?php echo htmlspecialchars($car['seller_name']); ?></a></p>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($car['seller_email']); ?></p>
                <?php if ($car['seller_phone']): ?>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($car['seller_phone']); ?></p>
                <?php endif; ?>
            </div>
            <div class="car-actions">
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $car['seller_id']): ?>
                    <a href="messages.php?car_id=<?php echo $car['id']; ?>&seller_id=<?php echo $car['seller_id']; ?>" class="btn btn-primary">
                        <i class="fas fa-envelope"></i> Chat with Seller
                    </a>
                    <a href="userreport.php?car_id=<?php echo $car['id']; ?>" class="btn btn-danger">
                        <i class="fas fa-flag"></i> Report
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Full-Screen Image Overlay -->
    <div class="fullscreen-overlay" id="fullscreen-overlay">
        <img src="" alt="Full-Screen Image" class="fullscreen-image" id="fullscreen-image">
        <div class="fullscreen-nav">
            <button onclick="prevFullscreen()" id="fullscreen-prev"><i class="fas fa-chevron-left"></i></button>
            <button onclick="nextFullscreen()" id="fullscreen-next"><i class="fas fa-chevron-right"></i></button>
        </div>
        <button class="close-btn" onclick="closeFullscreen()"><i class="fas fa-times"></i></button>
    </div>

    <script>
        const images = [
            "<?php echo htmlspecialchars($car['main_image']); ?>",
            <?php foreach (['sub_image1', 'sub_image2', 'sub_image3'] as $img_field): ?>
                <?php if ($car[$img_field]): ?>
                    "<?php echo htmlspecialchars($car[$img_field]); ?>",
                <?php endif; ?>
            <?php endforeach; ?>
        ];
        let currentIndex = 0;

        function changeMainImage(src, index) {
            const mainImage = document.getElementById('main-image');
            mainImage.style.opacity = '0';
            setTimeout(() => {
                mainImage.src = src;
                mainImage.style.opacity = '1';
            }, 300);
            currentIndex = index;
            updateActiveImage();
            updateNavButtons();
        }

        function updateActiveImage() {
            const galleryImages = document.querySelectorAll('.car-gallery img');
            galleryImages.forEach((img, index) => {
                img.classList.toggle('active', index === currentIndex);
            });
        }

        function updateNavButtons() {
            const prevBtn = document.querySelector('.prev-btn');
            const nextBtn = document.querySelector('.next-btn');
            prevBtn.disabled = currentIndex === 0;
            nextBtn.disabled = currentIndex === images.length - 1;
        }

        function prevImage() {
            if (currentIndex > 0) {
                currentIndex--;
                changeMainImage(images[currentIndex], currentIndex);
            }
        }

        function nextImage() {
            if (currentIndex < images.length - 1) {
                currentIndex++;
                changeMainImage(images[currentIndex], currentIndex);
            }
        }

        function openFullscreen(index) {
            currentIndex = index;
            const overlay = document.getElementById('fullscreen-overlay');
            const fullscreenImage = document.getElementById('fullscreen-image');
            const prevBtn = document.getElementById('fullscreen-prev');
            const nextBtn = document.getElementById('fullscreen-next');
            fullscreenImage.src = images[currentIndex];
            overlay.style.display = 'flex';
            prevBtn.disabled = currentIndex === 0;
            nextBtn.disabled = currentIndex === images.length - 1;
        }

        function closeFullscreen() {
            document.getElementById('fullscreen-overlay').style.display = 'none';
        }

        function prevFullscreen() {
            if (currentIndex > 0) {
                currentIndex--;
                document.getElementById('fullscreen-image').src = images[currentIndex];
                document.getElementById('fullscreen-prev').disabled = currentIndex === 0;
                document.getElementById('fullscreen-next').disabled = false;
            }
        }

        function nextFullscreen() {
            if (currentIndex < images.length - 1) {
                currentIndex++;
                document.getElementById('fullscreen-image').src = images[currentIndex];
                document.getElementById('fullscreen-next').disabled = currentIndex === images.length - 1;
                document.getElementById('fullscreen-prev').disabled = false;
            }
        }

        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            document.querySelector(`.tab[onclick="showTab('${tabId}')"]`).classList.add('active');
        }

        updateActiveImage();
        updateNavButtons();
    </script>
</body>
</html>