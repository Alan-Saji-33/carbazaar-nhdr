<?php
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'seller' && $_SESSION['user_type'] !== 'admin')) {
    $_SESSION['error'] = "Please login as a seller to add a car.";
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

// Check if user is suspended
$is_suspended = false;
$stmt = $conn->prepare("SELECT is_suspended FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if ($user && $user['is_suspended'] == 1) {
    $is_suspended = true;
}
$stmt->close();

// Check Aadhaar verification
$stmt = $conn->prepare("SELECT aadhaar_status FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user['aadhaar_status'] !== 'approved' && $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = "Your Aadhaar verification is pending or rejected. Please verify to add cars.";
    header("Location: verify_aadhaar.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $stmt = $conn->prepare("INSERT INTO cars (seller_id, model, brand, year, price, km_driven, fuel_type, transmission, main_image, sub_image1, sub_image2, sub_image3, location, ownership, insurance_status, description) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $seller_id = $_SESSION['user_id'];
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
                $file_name = $seller_id . '_' . time() . '_' . $field . '_' . basename($file['name']);
                $file_path = $upload_dir . $file_name;
                if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                    throw new Exception("Failed to upload $field.");
                }
                $image_paths[$field] = $file_path;
            } else {
                $image_paths[$field] = null;
            }
        }

        if (!$image_paths['main_image']) {
            throw new Exception("Main image is required.");
        }

        $stmt->bind_param("issiiissssssssss", 
            $seller_id, $model, $brand, $year, $price, $km_driven, $fuel_type, $transmission, 
            $image_paths['main_image'], $image_paths['sub_image1'], $image_paths['sub_image2'], 
            $image_paths['sub_image3'], $location, $ownership, $insurance_status, $description
        );

        if ($stmt->execute()) {
            $_SESSION['message'] = "Car added successfully!";
            header("Location: index.php");
            exit();
        } else {
            throw new Exception("Failed to add car: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}
?>

<?php include 'header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Car - CarBazaar</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .error-message {
            color: red;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            max-width: 500px;
            width: 90%;
            text-align: center;
        }
        .modal-content h2 {
            margin-bottom: 15px;
            color: #dc3545;
        }
        .modal-content p {
            margin-bottom: 20px;
        }
        .modal-content .btn {
            padding: 10px 20px;
            background-color: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
        }
        .modal-content .btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Suspension Modal -->
        <?php if ($is_suspended): ?>
            <div class="modal" id="suspensionModal" style="display: flex;">
                <div class="modal-content">
                    <h2>Account Suspended</h2>
                    <p>Your account is suspended due to multiple allegations against you found to be true.</p>
                    <a href="index.php?logout" class="btn">OK</a>
                </div>
            </div>
        <?php endif; ?>

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

        <div class="auth-form-container">
            <div class="auth-header">
                <h2>Add New Car</h2>
                <p>List your car for sale on CarBazaar</p>
            </div>
            <form method="POST" enctype="multipart/form-data" id="addCarForm">
                <div class="form-group">
                    <label for="brand"><i class="fas fa-car"></i> Brand</label>
                    <input type="text" id="brand" name="brand" class="form-control" placeholder="e.g. Toyota" required>
                </div>
                <div class="form-group">
                    <label for="model"><i class="fas fa-car-side"></i> Model</label>
                    <input type="text" id="model" name="model" class="form-control" placeholder="e.g. Camry" required>
                </div>
                <div class="form-group">
                    <label for="year"><i class="fas fa-calendar-alt"></i> Year</label>
                    <input type="number" id="year" name="year" class="form-control" min="1900" max="2025" required>
                    <div id="year-error" class="error-message"></div>
                </div>
                <div class="form-group">
                    <label for="price"><i class="fas fa-rupee-sign"></i> Price (₹)</label>
                    <input type="number" id="price" name="price" class="form-control" min="0" required>                    <div id="price-error" class="error-message"></div>
                </div>
                <div class="form-group">
                    <label for="km_driven"><i class="fas fa-tachometer-alt"></i> Kilometers Driven</label>
                    <input type="number" id="km_driven" name="km_driven" class="form-control" min="0" required>
                    <div id="km_driven-error" class="error-message"></div>
                </div>
                <div class="form-group">
                    <label for="fuel_type"><i class="fas fa-gas-pump"></i> Fuel Type</label>
                    <select id="fuel_type" name="fuel_type" class="form-control" required>
                        <option value="Petrol">Petrol</option>
                        <option value="Diesel">Diesel</option>
                        <option value="Electric">Electric</option>
                        <option value="Hybrid">Hybrid</option>
                        <option value="CNG">CNG</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="transmission"><i class="fas fa-cog"></i> Transmission</label>
                    <select id="transmission" name="transmission" class="form-control" required>
                        <option value="Automatic">Automatic</option>
                        <option value="Manual">Manual</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="location"><i class="fas fa-map-marker-alt"></i> Location</label>
                    <input type="text" id="location" name="location" class="form-control" placeholder="e.g. Mumbai" required>
                </div>
                <div class="form-group">
                    <label for="ownership"><i class="fas fa-user"></i> Ownership</label>
                    <select id="ownership" name="ownership" class="form-control" required>
                        <option value="First">First</option>
                        <option value="Second">Second</option>
                        <option value="Third">Third</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="insurance_status"><i class="fas fa-shield-alt"></i> Insurance Status</label>
                    <select id="insurance_status" name="insurance_status" class="form-control" required>
                        <option value="Valid">Valid</option>
                        <option value="Expired">Expired</option>
                        <option value="None">None</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="main_image"><i class="fas fa-image"></i> Main Image</label>
                    <input type="file" id="main_image" name="main_image" class="form-control" accept="image/jpeg,image/png" required>
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
                    <textarea id="description" name="description" class="form-control" rows="5" placeholder="Describe the car's condition, features, etc."></textarea>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-plus"></i> Add Car
                    </button>
                </div>
            </form>
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

    <script>
        document.getElementById('addCarForm').addEventListener('submit', function(event) {
            // Get form fields and error divs
            const yearInput = document.getElementById('year');
            const priceInput = document.getElementById('price');
            const kmDrivenInput = document.getElementById('km_driven');
            const yearError = document.getElementById('year-error');
            const priceError = document.getElementById('price-error');
            const kmDrivenError = document.getElementById('km_driven-error');

            // Reset error messages
            yearError.style.display = 'none';
            priceError.style.display = 'none';
            kmDrivenError.style.display = 'none';

            // Get values
            const year = parseInt(yearInput.value);
            const price = parseInt(priceInput.value);
            const kmDriven = parseInt(kmDrivenInput.value);

            // Year validation
            if (isNaN(year) || year > 2025 || year < 1900 || year.toString().startsWith('0') || year === 0) {
                event.preventDefault();
                yearError.textContent = 'Year must be between 1900 and 2025, and cannot start with 0 or be invalid.';
                yearError.style.display = 'block';
                yearInput.focus();
                return;
            }

            // Price validation
            if (isNaN(price) || price < 20000 || price === 0) {
                event.preventDefault();
                priceError.textContent = 'Price must be at least ₹20,000 and cannot be 0.';
                priceError.style.display = 'block';
                priceInput.focus();
                return;
            }

            // Kilometers driven validation
            if (isNaN(kmDriven) || kmDriven < 100 || kmDriven === 0) {
                event.preventDefault();
                kmDrivenError.textContent = 'Kilometers driven must be at least 100 and cannot be 0.';
                kmDrivenError.style.display = 'block';
                kmDrivenInput.focus();
                return;
            }
        });

        // Show modal if user is suspended
        <?php if ($is_suspended): ?>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('suspensionModal').style.display = 'flex';
            });
        <?php endif; ?>
    </script>
</body>
</html>