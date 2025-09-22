<?php
// Start output buffering
ob_start();

session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'seller' && $_SESSION['user_type'] !== 'admin')) {
    $_SESSION['error'] = "Please login as a seller or admin to edit a car.";
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

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No car specified.";
    header("Location: index.php");
    exit();
}

$car_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$stmt = $conn->prepare("SELECT * FROM cars WHERE id = ? AND seller_id = ?");
if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}
$stmt->bind_param("ii", $car_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0 && $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = "Car not found or you don't have permission to edit it.";
    header("Location: index.php");
    exit();
}
$car = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $stmt = $conn->prepare("UPDATE cars SET model = ?, brand = ?, year = ?, price = ?, km_driven = ?, fuel_type = ?, transmission = ?, location = ?, ownership = ?, insurance_status = ?, description = ?, main_image = COALESCE(?, main_image), sub_image1 = COALESCE(?, sub_image1), sub_image2 = COALESCE(?, sub_image2), sub_image3 = COALESCE(?, sub_image3) WHERE id = ?");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $model = filter_input(INPUT_POST, 'model', FILTER_SANITIZE_STRING);
        $brand = filter_input(INPUT_POST, 'brand', FILTER_SANITIZE_STRING);
        $year = filter_input(INPUT_POST, 'year', FILTER_SANITIZE_NUMBER_INT);
        $price = filter_input(INPUT_POST, 'price', FILTER_SANITIZE_NUMBER_INT);
        $km_driven = filter_input(INPUT_POST, 'km_driven', FILTER_SANITIZE_NUMBER_INT);
        $fuel_type = filter_input(INPUT_POST, 'fuel_type', FILTER_SANITIZE_STRING);
        $transmission = filter_input(INPUT_POST, 'transmission', FILTER_SANITIZE_STRING);
        $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING);
        $ownership = filter_input(INPUT_POST, 'ownership', FILTER_SANITIZE_STRING);
        $insurance_status = filter_input(INPUT_POST, 'insurance_status', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);

        $upload_dir = 'Uploads/cars/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $image_fields = ['main_image', 'sub_image1', 'sub_image2', 'sub_image3'];
        $image_paths = [];

        foreach ($image_fields as $field) {
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$field];
                if (!in_array($file['type'], $allowed_types)) {
                    throw new Exception("Invalid file type for $field. Only JPEG or PNG allowed.");
                }
                if ($file['size'] > $max_size) {
                    throw new Exception("File size for $field exceeds 5MB limit.");
                }
                $file_name = $_SESSION['user_id'] . '_' . time() . '_' . $field . '_' . basename($file['name']);
                $file_path = $upload_dir . $file_name;
                if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                    throw new Exception("Failed to upload $field.");
                }
                $image_paths[$field] = $file_path;
            } else {
                $image_paths[$field] = null;
            }
        }

        $stmt->bind_param("ssiiissssssssssi", 
            $model, $brand, $year, $price, $km_driven, $fuel_type, $transmission, 
            $location, $ownership, $insurance_status, $description, 
            $image_paths['main_image'], $image_paths['sub_image1'], $image_paths['sub_image2'], 
            $image_paths['sub_image3'], $car_id
        );

        if ($stmt->execute()) {
            $_SESSION['message'] = "Car updated successfully!";
            header("Location: profile.php");
            exit();
        } else {
            throw new Exception("Failed to update car: " . $stmt->error);
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
    <title>Edit Car - CarBazaar</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
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
                    <li><a href="#cars"><i class="fas fa-car"></i> Cars</a></li>
                    <li><a href="about.php"><i class="fas fa-info-circle"></i> About</a></li>
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                        <li><a href="favorites.php"><i class="fas fa-heart"></i> Favorites</a></li>
                        <li>
                            <a href="messages.php"><i class="fas fa-envelope"></i> Messages</a>
                        </li>
                        <?php if ($_SESSION['user_type'] == 'admin'): ?>
                            <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
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

        <div class="auth-container">
            <div class="auth-form-container">
                <div class="auth-header">
                    <h2>Edit Car</h2>
                    <p>Update your car listing details</p>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="brand"><i class="fas fa-car"></i> Brand</label>
                        <input type="text" id="brand" name="brand" class="form-control" value="<?php echo htmlspecialchars($car['brand']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="model"><i class="fas fa-car-side"></i> Model</label>
                        <input type="text" id="model" name="model" class="form-control" value="<?php echo htmlspecialchars($car['model']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="year"><i class="fas fa-calendar-alt"></i> Year</label>
                        <input type="number" id="year" name="year" class="form-control" min="1900" max="<?php echo date('Y'); ?>" value="<?php echo htmlspecialchars($car['year']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="price"><i class="fas fa-rupee-sign"></i> Price (₹)</label>
                        <input type="number" id="price" name="price" class="form-control" min="0" value="<?php echo htmlspecialchars($car['price']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="km_driven"><i class="fas fa-tachometer-alt"></i> Kilometers Driven</label>
                        <input type="number" id="km_driven" name="km_driven" class="form-control" min="0" value="<?php echo htmlspecialchars($car['km_driven']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="fuel_type"><i class="fas fa-gas-pump"></i> Fuel Type</label>
                        <select id="fuel_type" name="fuel_type" class="form-control" required>
                            <option value="Petrol" <?php echo $car['fuel_type'] == 'Petrol' ? 'selected' : ''; ?>>Petrol</option>
                            <option value="Diesel" <?php echo $car['fuel_type'] == 'Diesel' ? 'selected' : ''; ?>>Diesel</option>
                            <option value="Electric" <?php echo $car['fuel_type'] == 'Electric' ? 'selected' : ''; ?>>Electric</option>
                            <option value="Hybrid" <?php echo $car['fuel_type'] == 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                            <option value="CNG" <?php echo $car['fuel_type'] == 'CNG' ? 'selected' : ''; ?>>CNG</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="transmission"><i class="fas fa-cog"></i> Transmission</label>
                        <select id="transmission" name="transmission" class="form-control" required>
                            <option value="Automatic" <?php echo $car['transmission'] == 'Automatic' ? 'selected' : ''; ?>>Automatic</option>
                            <option value="Manual" <?php echo $car['transmission'] == 'Manual' ? 'selected' : ''; ?>>Manual</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="location"><i class="fas fa-map-marker-alt"></i> Location</label>
                        <input type="text" id="location" name="location" class="form-control" value="<?php echo htmlspecialchars($car['location']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="ownership"><i class="fas fa-user"></i> Ownership</label>
                        <select id="ownership" name="ownership" class="form-control" required>
                            <option value="First" <?php echo $car['ownership'] == 'First' ? 'selected' : ''; ?>>First</option>
                            <option value="Second" <?php echo $car['ownership'] == 'Second' ? 'selected' : ''; ?>>Second</option>
                            <option value="Third" <?php echo $car['ownership'] == 'Third' ? 'selected' : ''; ?>>Third</option>
                            <option value="Other" <?php echo $car['ownership'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="insurance_status"><i class="fas fa-shield-alt"></i> Insurance Status</label>
                        <select id="insurance_status" name="insurance_status" class="form-control" required>
                            <option value="Valid" <?php echo $car['insurance_status'] == 'Valid' ? 'selected' : ''; ?>>Valid</option>
                            <option value="Expired" <?php echo $car['insurance_status'] == 'Expired' ? 'selected' : ''; ?>>Expired</option>
                            <option value="None" <?php echo $car['insurance_status'] == 'None' ? 'selected' : ''; ?>>None</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="main_image"><i class="fas fa-image"></i> Main Image (Leave empty to keep current)</label>
                        <input type="file" id="main_image" name="main_image" class="form-control" accept="image/jpeg,image/png">
                    </div>
                    <div class="form-group">
                        <label for="sub_image1"><i class="fas fa-image"></i> Additional Image 1</label>
                        <input type="file" id="sub_image1" name="sub_image1" class="form-control" accept="image/jpeg,image/png">
                    </div>
                    <div class="form-group">
                        <label for="sub_image2"><i class="fas fa-image"></i> Additional Image 2</label>
                        <input type="file" id="sub_image2" name="sub_image2" class="form-control" accept="image/jpeg,image/png">
                    </div>
                    <div class="form-group">
                        <label for="sub_image3"><i class="fas fa-image"></i> Additional Image 3</label>
                        <input type="file" id="sub_image3" name="sub_image3" class="form-control" accept="image/jpeg,image/png">
                    </div>
                    <div class="form-group">
                        <label for="description"><i class="fas fa-info-circle"></i> Description</label>
                        <textarea id="description" name="description" class="form-control" rows="5"><?php echo htmlspecialchars($car['description']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-save"></i> Update Car
                        </button>
                    </div>
                </form>
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
// End output buffering and flush the output
ob_end_flush();
?>