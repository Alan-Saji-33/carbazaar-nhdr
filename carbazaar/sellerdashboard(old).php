<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering
ob_start();

// Log errors to a file for debugging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// Start session
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

// Restrict access to sellers and admins
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'seller' && $_SESSION['user_type'] !== 'admin')) {
    $_SESSION['error'] = "Please login as a seller or admin to access the dashboard.";
    header("Location: login.php");
    exit();
}

// Database connection
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "car_rental_db";

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    die("An error occurred. Please try again later.");
}

// Fetch user profile details for dropdown and profile section
$stmt = $conn->prepare("SELECT username, email, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$profile_pic = $user['profile_pic'] ?: 'Uploads/profiles/default.jpg';
$username = $user['username'];
$email = $user['email'];
$stmt->close();

// Handle car deletion (Listings section)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_car'])) {
    try {
        $car_id = filter_input(INPUT_POST, 'car_id', FILTER_SANITIZE_NUMBER_INT);
        if (!$car_id) {
            throw new Exception("Invalid car ID.");
        }
        $stmt = $conn->prepare("DELETE FROM cars WHERE id = ? AND seller_id = ?");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        if ($_SESSION['user_type'] === 'admin') {
            $stmt = $conn->prepare("DELETE FROM cars WHERE id = ?");
            $stmt->bind_param("i", $car_id);
        } else {
            $stmt->bind_param("ii", $car_id, $_SESSION['user_id']);
        }
        if ($stmt->execute()) {
            $_SESSION['message'] = "Car deleted successfully!";
        } else {
            throw new Exception("Failed to delete car: " . $stmt->error);
        }
        $stmt->close();
        header("Location: sellerdashboard.php?section=listings");
        exit();
    } catch (Exception $e) {
        error_log("Delete car error: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
    }
}

// Handle mark as sold/unmark (Listings and Sold Cars sections)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['mark_sold']) || isset($_POST['unmark_sold']))) {
    try {
        $car_id = filter_input(INPUT_POST, 'car_id', FILTER_SANITIZE_NUMBER_INT);
        if (!$car_id) {
            throw new Exception("Invalid car ID.");
        }
        $is_sold = isset($_POST['mark_sold']) ? 1 : 0;
        $stmt = $conn->prepare("UPDATE cars SET is_sold = ? WHERE id = ? AND seller_id = ?");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        if ($_SESSION['user_type'] === 'admin') {
            $stmt = $conn->prepare("UPDATE cars SET is_sold = ? WHERE id = ?");
            $stmt->bind_param("ii", $is_sold, $car_id);
        } else {
            $stmt->bind_param("iii", $is_sold, $car_id, $_SESSION['user_id']);
        }
        if ($stmt->execute()) {
            $_SESSION['message'] = $is_sold ? "Car marked as sold!" : "Car unmarked as sold!";
        } else {
            throw new Exception("Failed to update car status: " . $stmt->error);
        }
        $stmt->close();
        header("Location: sellerdashboard.php?section=" . ($is_sold ? 'sold_cars' : 'listings'));
        exit();
    } catch (Exception $e) {
        error_log("Mark/Unmark sold error: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
    }
}

// Handle remove from favorites (Favorites section)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_favorite'])) {
    try {
        $car_id = filter_input(INPUT_POST, 'car_id', FILTER_SANITIZE_NUMBER_INT);
        if (!$car_id) {
            throw new Exception("Invalid car ID.");
        }
        $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND car_id = ?");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ii", $_SESSION['user_id'], $car_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Car removed from favorites!";
        } else {
            throw new Exception("Failed to remove car from favorites: " . $stmt->error);
        }
        $stmt->close();
        header("Location: sellerdashboard.php?section=favorites");
        exit();
    } catch (Exception $e) {
        error_log("Remove favorite error: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
    }
}

// Fetch data based on section
$section = isset($_GET['section']) ? $_GET['section'] : 'listings';
if ($section === 'listings') {
    $query = $_SESSION['user_type'] === 'admin'
        ? "SELECT id, brand, model, year, price, main_image, is_sold FROM cars WHERE is_sold = 0 ORDER BY created_at DESC"
        : "SELECT id, brand, model, year, price, main_image, is_sold FROM cars WHERE seller_id = ? AND is_sold = 0 ORDER BY created_at DESC";
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        error_log("Prepare query failed: " . $conn->error);
        die("An error occurred. Please try again later.");
    }
    if ($_SESSION['user_type'] !== 'admin') {
        $stmt->bind_param("i", $_SESSION['user_id']);
    }
    $stmt->execute();
    $cars_result = $stmt->get_result();
    $stmt->close();
} elseif ($section === 'sold_cars') {
    $query = $_SESSION['user_type'] === 'admin'
        ? "SELECT id, brand, model, year, price, main_image, is_sold FROM cars WHERE is_sold = 1 ORDER BY created_at DESC"
        : "SELECT id, brand, model, year, price, main_image, is_sold FROM cars WHERE seller_id = ? AND is_sold = 1 ORDER BY created_at DESC";
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        error_log("Prepare query failed: " . $conn->error);
        die("An error occurred. Please try again later.");
    }
    if ($_SESSION['user_type'] !== 'admin') {
        $stmt->bind_param("i", $_SESSION['user_id']);
    }
    $stmt->execute();
    $sold_cars_result = $stmt->get_result();
    $stmt->close();
} elseif ($section === 'favorites') {
    $query = "SELECT c.id, c.brand, c.model, c.year, c.price, c.main_image, c.is_sold 
              FROM cars c 
              INNER JOIN favorites f ON c.id = f.car_id 
              WHERE f.user_id = ? 
              ORDER BY f.created_at DESC";
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        error_log("Prepare query failed: " . $conn->error);
        die("An error occurred. Please try again later.");
    }
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $favorites_result = $stmt->get_result();
    $stmt->close();
}
?>

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
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background-color: #f4f4f4;
        }
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 60px);
            margin-top: 60px;
        }
        .sidebar {
            width: 250px;
            background-color: white;
            border-right: 1px solid var(--light-gray);
            padding: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar ul li {
            margin-bottom: 10px;
        }
        .sidebar ul li a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: var(--gray);
            text-decoration: none;
            font-size: 14px;
            border-radius: 8px;
            transition: background 0.2s;
        }
        .sidebar ul li a:hover,
        .sidebar ul li a.active {
            background-color: var(--light-gray);
            color: var(--dark);
        }
        .sidebar ul li a i {
            margin-right: 12px;
            font-size: 16px;
            color: var(--primary);
        }
        .main-content {
            flex: 1;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .table-container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .table-container h3 {
            font-size: 24px;
            color: var(--dark);
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
        }
        th {
            background-color: var(--light-gray);
            font-weight: 600;
            color: var(--dark);
        }
        td {
            color: var(--gray);
        }
        .car-image {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            vertical-align: middle;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .btn {
            padding: 8px 16px;
            font-size: 12px;
        }
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background-color: var(--secondary);
        }
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        .btn-danger:hover {
            background-color: #d1145a;
        }
        .btn-outline {
            background-color: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }
        .btn-outline:hover {
            background-color: var(--primary);
            color: white;
        }
        .btn-success {
            background-color: var(--success);
            color: white;
            width: 120px;
            text-align: center;
        }
        .btn-success:hover {
            background-color: #3ba6d3;
        }
        .status-available {
            color: green;
            font-weight: 600;
        }
        .status-sold {
            color: red;
            font-weight: 600;
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
        .add-car-btn {
            margin-bottom: 20px;
            display: inline-block;
        }
        /* Profile section */
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .profile-container h3 {
            font-size: 24px;
            color: var(--dark);
            margin-bottom: 20px;
        }
        .profile-container img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
        }
        .profile-container p {
            font-size: 14px;
            color: var(--gray);
            margin: 10px 0;
        }
        /* Header styles */
        header {
            position: fixed;
            top: 0;
            width: 100%;
            background: white;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 1200px;
            margin: 0 auto;
            padding: 15px;
        }
        nav ul {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
            justify-content: center;
            flex: 1;
        }
        nav ul li {
            margin: 0 15px;
        }
        nav ul li a {
            color: var(--gray);
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        nav ul li a i {
            margin-right: 8px;
            color: var(--primary);
        }
        nav ul li a:hover {
            color: var(--primary);
        }
        .header-icons {
            display: flex;
            align-items: center;
            gap: 15px;
            position: absolute;
            right: 15px;
            top: 15px;
        }
        .header-icon {
            font-size: 24px;
            color: var(--primary);
            cursor: pointer;
            transition: color 0.2s;
        }
        .header-icon:hover {
            color: var(--secondary);
        }
        .profile-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid var(--light-gray);
        }
        .profile-btn img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .dropdown {
            position: fixed;
            top: 60px;
            right: 15px;
            background: white;
            width: 280px;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            display: none;
            flex-direction: column;
            animation: fadeIn 0.2s ease;
            z-index: 1000;
        }
        .dropdown.show {
            display: flex;
        }
        .dropdown .profile-section {
            padding: 12px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid var(--light-gray);
            color: var(--dark);
        }
        .dropdown .profile-section img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            margin-right: 12px;
        }
        .dropdown .profile-section .name {
            font-weight: 600;
            font-size: 15px;
        }
        .menu-item {
            padding: 12px 15px;
            display: flex;
            align-items: center;
            cursor: pointer;
            font-size: 14px;
            color: var(--gray);
            transition: background 0.2s;
        }
        .menu-item:hover {
            background: var(--light-gray);
            color: var(--dark);
        }
        .menu-item i {
            margin-right: 12px;
            font-size: 16px;
            color: var(--primary);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
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
                    <?php if ($_SESSION['user_type'] === 'admin'): ?>
                        <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <div class="header-icons">
            <a href="favorites.php" class="header-icon"><i class="fas fa-heart"></i></a>
            <div class="profile-btn" id="profileBtn">
                <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile">
            </div>
        </div>
        <div class="dropdown" id="dropdownMenu">
            <div class="profile-section">
                <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile">
                <div class="name"><?php echo htmlspecialchars($username); ?></div>
            </div>
            <a href="profile.php" class="menu-item"><i class="fas fa-user"></i> View/Edit Your Profile</a>
            <a href="sellerdashboard.php?section=listings" class="menu-item"><i class="fas fa-car"></i> Manage Your Listing</a>
            <a href="index.php?logout" class="menu-item"><i class="fas fa-sign-out-alt"></i> Log out</a>
        </div>
    </header>

    <div class="dashboard-container">
        <div class="sidebar">
            <ul>
                <li><a href="?section=listings" class="<?php echo $section === 'listings' ? 'active' : ''; ?>"><i class="fas fa-car"></i> Listings</a></li>
                <li><a href="add_car.php"><i class="fas fa-plus"></i> Add New Car</a></li>
                <li><a href="?section=sold_cars" class="<?php echo $section === 'sold_cars' ? 'active' : ''; ?>"><i class="fas fa-check-circle"></i> Sold Cars</a></li>
                <li><a href="?section=profile" class="<?php echo $section === 'profile' ? 'active' : ''; ?>"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="?section=favorites" class="<?php echo $section === 'favorites' ? 'active' : ''; ?>"><i class="fas fa-heart"></i> Favorites</a></li>
            </ul>
        </div>
        <div class="main-content">
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

            <?php if ($section === 'listings'): ?>
                <div class="table-container">
                    <h3>Your Car Listings</h3>
                    <a href="add_car.php" class="btn btn-primary add-car-btn"><i class="fas fa-plus"></i> Add New Car</a>
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
                                                <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' (' . $car['year'] . ')'); ?>
                                            </div>
                                        </td>
                                        <td>₹<?php echo number_format($car['price'], 2); ?></td>
                                        <td class="<?php echo $car['is_sold'] ? 'status-sold' : 'status-available'; ?>">
                                            <?php echo $car['is_sold'] ? 'Sold' : 'Available'; ?>
                                        </td>
                                        <td class="action-buttons">
                                            <a href="view_car.php?id=<?php echo $car['id']; ?>" class="btn btn-outline"><i class="fas fa-eye"></i> View</a>
                                            <a href="edit_car.php?id=<?php echo $car['id']; ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Edit</a>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this car?');">
                                                <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                                                <button type="submit" name="delete_car" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
                                            </form>
                                            <?php if ($car['is_sold']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                                                    <button type="submit" name="unmark_sold" class="btn btn-success"><i class="fas fa-undo"></i> Unmark Sold</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                                                    <button type="submit" name="mark_sold" class="btn btn-success"><i class="fas fa-check"></i> Mark Sold</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No car listings found.</p>
                    <?php endif; ?>
                </div>
            <?php elseif ($section === 'sold_cars'): ?>
                <div class="table-container">
                    <h3>Sold Cars</h3>
                    <?php if ($sold_cars_result->num_rows > 0): ?>
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
                                <?php while ($car = $sold_cars_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <img src="<?php echo htmlspecialchars($car['main_image'] ?: 'Uploads/cars/default.jpg'); ?>" alt="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>" class="car-image">
                                                <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' (' . $car['year'] . ')'); ?>
                                            </div>
                                        </td>
                                        <td>₹<?php echo number_format($car['price'], 2); ?></td>
                                        <td class="status-sold">Sold</td>
                                        <td class="action-buttons">
                                            <a href="view_car.php?id=<?php echo $car['id']; ?>" class="btn btn-outline"><i class="fas fa-eye"></i> View</a>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                                                <button type="submit" name="unmark_sold" class="btn btn-success"><i class="fas fa-undo"></i> Unmark Sold</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No sold cars found.</p>
                    <?php endif; ?>
                </div>
            <?php elseif ($section === 'profile'): ?>
                <div class="profile-container">
                    <h3>Your Profile</h3>
                    <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture">
                    <p><strong>Username:</strong> <?php echo htmlspecialchars($username); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
                    <a href="profile.php" class="btn btn-primary"><i class="fas fa-edit"></i> Edit Profile</a>
                </div>
            <?php elseif ($section === 'favorites'): ?>
                <div class="table-container">
                    <h3>Your Favorite Cars</h3>
                    <?php if ($favorites_result->num_rows > 0): ?>
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
                                <?php while ($car = $favorites_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <img src="<?php echo htmlspecialchars($car['main_image'] ?: 'Uploads/cars/default.jpg'); ?>" alt="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>" class="car-image">
                                                <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' (' . $car['year'] . ')'); ?>
                                            </div>
                                        </td>
                                        <td>₹<?php echo number_format($car['price'], 2); ?></td>
                                        <td class="<?php echo $car['is_sold'] ? 'status-sold' : 'status-available'; ?>">
                                            <?php echo $car['is_sold'] ? 'Sold' : 'Available'; ?>
                                        </td>
                                        <td class="action-buttons">
                                            <a href="view_car.php?id=<?php echo $car['id']; ?>" class="btn btn-outline"><i class="fas fa-eye"></i> View</a>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this car from favorites?');">
                                                <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                                                <button type="submit" name="remove_favorite" class="btn btn-danger"><i class="fas fa-heart-broken"></i> Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No favorite cars found.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <h3>Welcome to Your Dashboard</h3>
                    <p>Select an option from the sidebar to manage your listings or profile.</p>
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
        const profileBtn = document.getElementById("profileBtn");
        const dropdownMenu = document.getElementById("dropdownMenu");

        profileBtn.addEventListener("click", () => {
            dropdownMenu.classList.toggle("show");
        });

        document.addEventListener("click", (e) => {
            if (!profileBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
                dropdownMenu.classList.remove("show");
            }
        });
    </script>
</body>
</html>

<?php
// End output buffering
ob_end_flush();
?>