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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Validate email format
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }
        
        // Validate phone number
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $phone = preg_replace('/[^0-9]/', '', $phone); // Remove non-numeric characters
        
        // Check if phone number is exactly 10 digits and not a simple pattern
        if (strlen($phone) !== 10) {
            throw new Exception("Phone number must be exactly 10 digits.");
        }
        
        // Check for simple patterns (like all same digits or simple sequences)
        if (preg_match('/^(\d)\1{9}$/', $phone)) { // All same digits
            throw new Exception("Invalid phone number format.");
        }
        
        // Check for simple sequences (like 1234567890)
        $is_sequence = true;
        for ($i = 0; $i < 9; $i++) {
            if ((int)$phone[$i+1] !== (int)$phone[$i] + 1) {
                $is_sequence = false;
                break;
            }
        }
        if ($is_sequence) {
            throw new Exception("Invalid phone number format.");
        }
        
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, phone, location, user_type, profile_pic) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $user_type = 'seller';
        $profile_pic = 'https://ui-avatars.com/api/?name=' . urlencode($username);
        
        if (!$username || !$email || !$password || !$location) {
            throw new Exception("Invalid input data.");
        }

        $stmt->bind_param("sssssss", $username, $password, $email, $phone, $location, $user_type, $profile_pic);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Registration successful! Please login.";
            header("Location: login.php");
            exit();
        } else {
            throw new Exception("Registration failed: " . $conn->error);
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
    <title>Register as Seller - CarBazaar</title>
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

        /* Auth Container Styles (Matching register.php) */
        .auth-container {
            display: flex;
            max-width: 900px;
            margin: 50px auto;
            background-color: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            animation: slideIn 0.5s ease;
        }

        .auth-image {
            flex: 1;
            background: url('https://images.unsplash.com/photo-1541348263662-e068662d82af?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80') no-repeat center;
            background-size: cover;
            min-height: 500px;
            position: relative;
        }

        .auth-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
           
            z-index: 1;
        }

        .auth-form-container {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            justify-content: center;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .auth-header h2 {
            font-size: 32px;
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
            margin: 10px auto 0;
        }

        .auth-header p {
            color: var(--gray);
            font-size: 16px;
            margin: 0;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 1px;
	    margin-bottom:14px;
            position: relative;
        }

        .form-group label {
            font-weight: 500;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .form-control {
            padding: 12px 15px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 14px;
            width: 100%;
            box-sizing: border-box;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 8px rgba(67, 97, 238, 0.2);
            outline: none;
        }

        .form-group button {
            font-size: 16px;
            padding: 12px 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .form-group button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.2);
        }

        .form-footer {
            text-align: center;
            font-size: 14px;
            color: var(--gray);
        }

        .form-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
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
        
        .error-message {
            color: var(--danger);
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }
        
        .form-control.error {
            border-color: var(--danger);
        }

        /* Animation for Auth Container */
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .auth-container {
                flex-direction: column;
                margin: 20px auto;
                padding: 20px;
            }
            .auth-image {
                min-height: 200px;
                width: 100%;
            }
            .auth-form-container {
                padding: 20px;
            }
            .auth-header h2 {
                font-size: 24px;
            }
            .auth-header p {
                font-size: 14px;
            }
            .form-control {
                padding: 10px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
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
                    <li><a href="index.php#about"><i class="fas fa-info-circle"></i> About</a></li>
                                  </ul>
            </nav>
            
            <div class="user-actions">
                <a href="login.php" class="btn btn-primary login-margin">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
                <a href="register.php" class="btn btn-outline">
                    <i class="fas fa-user-plus"></i> Register
                </a>
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
            <div class="auth-image"></div>
            <div class="auth-form-container">
                <div class="auth-header">
                    <h2>Register as Seller</h2>
                    <p>Join CarBazaar to sell your cars</p>
                </div>
                
                <form method="POST" id="registrationForm">
                    <div class="form-group">
                        <label for="username"><i class="fas fa-user"></i> Username</label>
                        <input type="text" id="username" name="username" class="form-control" placeholder="Choose a username" required>
                        <div class="error-message" id="username-error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
                        <div class="error-message" id="email-error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="text" id="phone" name="phone" class="form-control" placeholder="Enter your 10-digit phone number" required>
                        <div class="error-message" id="phone-error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="location"><i class="fas fa-map-marker-alt"></i> Location</label>
                        <input type="text" id="location" name="location" class="form-control" placeholder="e.g. Mumbai, Maharashtra" required>
                        <div class="error-message" id="location-error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Password</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Create a password" required>
                        <div class="error-message" id="password-error"></div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="register" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Register
                        </button>
                    </div>
                    
                    <div class="form-footer">
                        <p>Want to buy instead? <a href="buyer.php">Register as Buyer</a></p>
                        <p>Already have an account? <a href="login.php">Login here</a></p>
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
                        <li><i class="fas fa-map-marker-alt"></i> Changanacherry</li>
                        <li><i class="fas fa-phone-alt"></i> +91 9876543210</li>
                        <li><i class="fas fa-envelope"></i> support@carbazaar.com</li>
                   
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>© <?php echo date('Y'); ?> CarBazaar. All Rights Reserved. Designed with <i class="fas fa-heart"></i> in India.</p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registrationForm');
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Reset previous errors
                clearErrors();
                
                // Validate inputs
                let isValid = true;
                
                // Username validation
                const username = document.getElementById('username');
                if (username.value.trim() === '') {
                    showError(username, 'username-error', 'Username is required');
                    isValid = false;
                }
                
                // Email validation
                const email = document.getElementById('email');
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (email.value.trim() === '') {
                    showError(email, 'email-error', 'Email is required');
                    isValid = false;
                } else if (!emailRegex.test(email.value)) {
                    showError(email, 'email-error', 'Please enter a valid email address');
                    isValid = false;
                }
                
                // Phone validation
                const phone = document.getElementById('phone');
                const phoneRegex = /^[0-9]{10}$/;
                const phoneValue = phone.value.replace(/\D/g, '');
                
                if (phoneValue === '') {
                    showError(phone, 'phone-error', 'Phone number is required');
                    isValid = false;
                } else if (!phoneRegex.test(phoneValue)) {
                    showError(phone, 'phone-error', 'Phone number must be exactly 10 digits');
                    isValid = false;
                } else if (/^(\d)\1{9}$/.test(phoneValue)) {
                    showError(phone, 'phone-error', 'Invalid phone number format');
                    isValid = false;
                } else {
                    // Check for sequential numbers
                    let isSequential = true;
                    for (let i = 0; i < 9; i++) {
                        if (parseInt(phoneValue[i+1]) !== parseInt(phoneValue[i]) + 1) {
                            isSequential = false;
                            break;
                        }
                    }
                    if (isSequential) {
                        showError(phone, 'phone-error', 'Invalid phone number format');
                        isValid = false;
                    }
                }
                
                // Location validation
                const location = document.getElementById('location');
                if (location.value.trim() === '') {
                    showError(location, 'location-error', 'Location is required');
                    isValid = false;
                }
                
                // Password validation
                const password = document.getElementById('password');
                if (password.value === '') {
                    showError(password, 'password-error', 'Password is required');
                    isValid = false;
                } else if (password.value.length < 6) {
                    showError(password, 'password-error', 'Password must be at least 6 characters');
                    isValid = false;
                }
                
                // If all validations pass, submit the form
                if (isValid) {
                    form.submit();
                }
            });
            
            function showError(input, errorId, message) {
                input.classList.add('error');
                const errorElement = document.getElementById(errorId);
                errorElement.textContent = message;
                errorElement.style.display = 'block';
                input.focus();
            }
            
            function clearErrors() {
                const errorMessages = document.querySelectorAll('.error-message');
                errorMessages.forEach(function(error) {
                    error.style.display = 'none';
                    error.textContent = '';
                });
                
                const inputs = document.querySelectorAll('.form-control');
                inputs.forEach(function(input) {
                    input.classList.remove('error');
                });
            }
            
            // Real-time validation for phone number formatting
            const phoneInput = document.getElementById('phone');
            phoneInput.addEventListener('input', function(e) {
                // Remove any non-digit characters
                this.value = this.value.replace(/\D/g, '');
            });
        });
    </script>
</body>
</html>