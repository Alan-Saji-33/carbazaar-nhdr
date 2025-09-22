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
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die($e->getMessage());
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - CarBazaar</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
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

        /* About Hero Section */
        .about-hero {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 80px 0;
            text-align: center;
            border-radius: 12px;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }

        .about-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('https://images.unsplash.com/photo-1502877338535-766e1452684a?ixlib=rb-4.0.3&auto=format&fit=crop&w=1400&q=80') center/cover no-repeat;
            opacity: 0.3;
            z-index: 0;
        }

        .about-hero .container {
            position: relative;
            z-index: 1;
        }

        .about-hero h1 {
            font-size: 36px;
            margin: 0 0 20px;
            font-weight: 700;
        }

        .about-hero p {
            font-size: 18px;
            margin: 0 auto 30px;
            max-width: 800px;
            line-height: 1.6;
        }

        /* About Content */
        .about-content {
            display: flex;
            flex-direction: column;
            gap: 30px;
            max-width: 1000px;
            margin: 0 auto;
        }

        .about-section {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .about-section h2 {
            font-size: 28px;
            color: var(--dark);
            margin: 0 0 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .about-section h2 i {
            color: var(--primary);
            font-size: 24px;
        }

        .about-section p, .about-section ul {
            font-size: 16px;
            color: var(--gray);
            line-height: 1.6;
            margin: 0 auto 20px;
            max-width: 700px;
        }

        .about-section ul {
            list-style: none;
            padding: 0;
        }

        .about-section li {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .about-section li i {
            color: var(--primary);
        }

        /* Why Choose Us */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .feature-card {
            text-align: center;
            padding: 25px;
            border-radius: 8px;
            background-color: var(--light);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(to right, var(--primary), var(--accent));
            border-radius: 8px 8px 0 0;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .feature-card i {
            font-size: 40px;
            color: var(--primary);
            margin-bottom: 15px;
        }

        .feature-card h3 {
            font-size: 20px;
            color: var(--dark);
            margin: 0 0 10px;
        }

        .feature-card p {
            font-size: 14px;
            color: var(--gray);
            margin: 0;
        }

        /* FAQ and Policy Sections */
        .faq-container, .policy-container {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .faq-item {
            margin-bottom: 20px;
        }

        .faq-item h3 {
            font-size: 18px;
            color: var(--dark);
            margin: 0 0 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .faq-item h3 i {
            color: var(--primary);
        }

        .faq-item p {
            font-size: 14px;
            color: var(--gray);
            line-height: 1.6;
            margin: 0;
        }

        .policy-container h2 {
            font-size: 28px;
            color: var(--dark);
            margin: 0 0 20px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .policy-container h2 i {
            color: var(--primary);
        }

        .policy-container p, .policy-container ul {
            font-size: 16px;
            color: var(--gray);
            line-height: 1.6;
            margin: 0 auto 20px;
            max-width: 700px;
        }

        .policy-container ul {
            list-style: none;
            padding: 0;
        }

        .policy-container li {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .policy-container li i {
            color: var(--primary);
        }

        /* CTA Section */
        .cta-section {
            text-align: center;
            padding: 60px 0;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 12px;
            margin: 40px 0;
            position: relative;
            overflow: hidden;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('https://images.unsplash.com/photo-1503376780353-7e6692767b70?ixlib=rb-4.0.3&auto=format&fit=crop&w=1400&q=80') center/cover no-repeat;
            opacity: 0.3;
            z-index: 0;
        }

        .cta-section .container {
            position: relative;
            z-index: 1;
        }

        .cta-section h2 {
            font-size: 32px;
            margin: 0 0 20px;
            font-weight: 600;
        }

        .cta-section p {
            font-size: 16px;
            margin: 0 auto 30px;
            max-width: 600px;
        }

        .hero-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .btn-chatbot {
            background: var(--success);
            color: white;
            padding: 12px 24px;
            font-size: 16px;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .btn-chatbot::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s ease;
            z-index: -1;
        }

        .btn-chatbot:hover::before {
            left: 100%;
        }

        .btn-chatbot:hover {
            background-color: #0ea5e9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        /* Alerts */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 20px auto;
            font-size: 14px;
            text-align: center;
            max-width: 800px;
        }

        .alert-success {
            background-color: var(--success);
            color: white;
        }

        .alert-error {
            background-color: var(--danger);
            color: white;
        }

        /* Footer */
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
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
            transition: color 0.3s ease;
        }

        .footer-social a:hover {
            color: var(--primary);
        }

        .footer-bottom {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--light-gray);
        }

        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            nav ul {
                flex-direction: column;
                gap: 10px;
            }

            .user-actions {
                flex-direction: column;
                gap: 10px;
            }

            .about-hero h1 {
                font-size: 28px;
            }

            .about-hero p {
                font-size: 16px;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .cta-section {
                padding: 40px 20px;
            }

            .hero-buttons {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
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
                    <a href="?logout" class="btn btn-outline">
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

    <!-- About Hero Section -->
    <section class="about-hero" id="about">
        <div class="container">
            <h1>About CarBazaar</h1>
            <p>CarBazaar is your trusted platform for buying and selling quality used cars across India. We're committed to making car trading seamless, secure, and transparent with verified sellers and a user-friendly experience.</p>
        </div>
    </section>

    <!-- About Content -->
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

        <div class="about-content">
            <!-- Our Mission -->
            <section class="about-section">
                <h2><i class="fas fa-bullseye"></i> Our Mission</h2>
                <p>At CarBazaar, our mission is to revolutionize the used car market in India by providing a secure, transparent, and user-friendly platform. We aim to connect buyers with verified sellers, ensuring every transaction is safe, reliable, and hassle-free.</p>
            </section>

            <!-- Why Aadhaar Verification -->
            <section class="about-section">
                <h2><i class="fas fa-id-card"></i> Why Aadhaar Verification?</h2>
                <p>Aadhaar verification is a cornerstone of CarBazaar's commitment to trust and safety. By requiring sellers to verify their identity through Aadhaar, we ensure:</p>
                <ul>
                    <li><i class="fas fa-check-circle"></i> <strong>Authenticity</strong>: Only genuine sellers can list cars, reducing the risk of fraud.</li>
                    <li><i class="fas fa-shield-alt"></i> <strong>Security</strong>: Verified identities protect buyers from scams and ensure accountability.</li>
                    <li><i class="fas fa-user-check"></i> <strong>Transparency</strong>: Buyers can trust that seller information is legitimate, fostering confidence in every transaction.</li>
                    <li><i class="fas fa-lock"></i> <strong>Compliance</strong>: Adhering to regulatory standards, Aadhaar verification aligns with India's digital identity framework for secure transactions.</li>
                </ul>
                <p>Our rigorous verification process, handled by our admin team, ensures that every seller meets our high standards, making CarBazaar a trusted marketplace for all.</p>
            </section>

            <!-- Why Choose Us -->
            <section class="about-section">
                <h2><i class="fas fa-star"></i> Why Choose CarBazaar?</h2>
                <div class="features-grid">
                    <div class="feature-card">
                        <i class="fas fa-user-check"></i>
                        <h3>Verified Sellers</h3>
                        <p>All sellers are Aadhaar-verified, ensuring trust and authenticity in every listing.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-car-alt"></i>
                        <h3>Wide Selection</h3>
                        <p>Browse a diverse range of quality used cars from trusted brands across India.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-shield-alt"></i>
                        <h3>Secure Transactions</h3>
                        <p>Our platform ensures safe and transparent transactions for peace of mind.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-headset"></i>
                        <h3>24/7 Support</h3>
                        <p>Our dedicated team is available round-the-clock to assist with any queries.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-search"></i>
                        <h3>Easy Search</h3>
                        <p>Find your perfect car with our intuitive search and filter options.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-heart"></i>
                        <h3>Favorites</h3>
                        <p>Save your favorite cars to revisit and compare at your convenience.</p>
                    </div>
                </div>
            </section>

            <!-- FAQ Section -->
            <section class="faq-container" id="faq">
                <h2><i class="fas fa-question-circle"></i> Frequently Asked Questions</h2>
                <div class="faq-item">
                    <h3><i class="fas fa-question"></i> How do I register on CarBazaar?</h3>
                    <p>Click the "Register" button, choose to sign up as a buyer or seller, and fill in your details. Sellers require Aadhaar verification for listing cars.</p>
                </div>
                <div class="faq-item">
                    <h3><i class="fas fa-question"></i> Is Aadhaar verification mandatory?</h3>
                    <p>Yes, for sellers, Aadhaar verification is required to ensure trust and security. Buyers can browse without verification.</p>
                </div>
                <div class="faq-item">
                    <h3><i class="fas fa-question"></i> How can I contact a seller?</h3>
                    <p>Once logged in, you can view seller contact details on the car listing page and reach out directly.</p>
                </div>
                <div class="faq-item">
                    <h3><i class="fas fa-question"></i> What happens if my Aadhaar verification is rejected?</h3>
                    <p>You'll receive a rejection reason from our admin team. You can re-upload a valid Aadhaar document to retry verification.</p>
                </div>
            </section>

            <!-- Privacy Policy -->
            <section class="policy-container" id="privacy-policy">
                <h2><i class="fas fa-lock"></i> Privacy Policy</h2>
                <p>At CarBazaar, we prioritize your privacy. We collect personal information (e.g., name, email, Aadhaar details for sellers) only to facilitate secure transactions and verify identities. Your data is:</p>
                <ul>
                    <li><i class="fas fa-check-circle"></i> Stored securely with encryption.</li>
                    <li><i class="fas fa-check-circle"></i> Used solely for platform operations, such as verification and communication.</li>
                    <li><i class="fas fa-check-circle"></i> Never shared with third parties without consent, except as required by law.</li>
                    <li><i class="fas fa-check-circle"></i> Accessible and editable via your profile settings.</li>
                </ul>
                <p>For more details, contact us at support@carbazaar.com.</p>
            </section>

            <!-- Terms & Conditions -->
            <section class="policy-container" id="terms-conditions">
                <h2><i class="fas fa-file-contract"></i> Terms & Conditions</h2>
                <p>By using CarBazaar, you agree to:</p>
                <ul>
                    <li><i class="fas fa-check-circle"></i> Provide accurate information during registration and verification.</li>
                    <li><i class="fas fa-check-circle"></i> Use the platform for lawful purposes only.</li>
                    <li><i class="fas fa-check-circle"></i> Respect the rights of other users and sellers.</li>
                    <li><i class="fas fa-check-circle"></i> Accept that CarBazaar is not liable for disputes arising from transactions between buyers and sellers.</li>
                </ul>
                <p>Failure to comply may result in account suspension. Contact us for the full terms.</p>
            </section>

            <!-- How to Sell -->
            <section class="policy-container" id="how-to-sell">
                <h2><i class="fas fa-store"></i> How to Sell</h2>
                <p>Selling on CarBazaar is simple:</p>
                <ul>
                    <li><i class="fas fa-check-circle"></i> <strong>Register as a Seller</strong>: Sign up and complete Aadhaar verification.</li>
                    <li><i class="fas fa-check-circle"></i> <strong>List Your Car</strong>: Add details like brand, model, price, and upload clear photos.</li>
                    <li><i class="fas fa-check-circle"></i> <strong>Connect with Buyers</strong>: Respond to inquiries via the platform.</li>
                    <li><i class="fas fa-check-circle"></i> <strong>Close the Deal</strong>: Finalize the sale securely with our support.</li>
                </ul>
                <p>Ensure your car details are accurate to attract genuine buyers.</p>
            </section>

            <!-- Buyer Guide -->
            <section class="policy-container" id="buyer-guide">
                <h2><i class="fas fa-shopping-cart"></i> Buyer Guide</h2>
                <p>Buying a car on CarBazaar is easy and secure:</p>
                <ul>
                    <li><i class="fas fa-check-circle"></i> <strong>Browse Listings</strong>: Use filters to find cars by brand, price, or location.</li>
                    <li><i class="fas fa-check-circle"></i> <strong>Check Details</strong>: Review car specs and seller verification status.</li>
                    <li><i class="fas fa-check-circle"></i> <strong>Contact Seller</strong>: Reach out to discuss details or schedule a test drive.</li>
                    <li><i class="fas fa-check-circle"></i> <strong>Secure Payment</strong>: Use trusted payment methods and verify all documents before finalizing.</li>
                </ul>
                <p>Save cars to your favorites for easy comparison.</p>
            </section>

            <!-- Call to Action -->
            <section class="cta-section">
                <div class="container">
                    <h2>Join the CarBazaar Community</h2>
                    <p>Ready to find your dream car or sell your vehicle with ease? Start exploring CarBazaar today!</p>
                    <div class="hero-buttons">
                        <a href="chatbot.php" class="btn btn-chatbot">
                            <i class="fa-solid fa-comments"></i> Chat with Us
                        </a>
                        <a href="register.php" class="btn btn-outline">
                            <i class="fas fa-user-plus"></i> Join Now
                        </a>
                    </div>
                </div>
            </section>
        </div>
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
                        <li><a href="index.php#cars">Browse Cars</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="index.php#contact">Contact</a></li>
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

    <script>
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
    </script>
</body>
</html>