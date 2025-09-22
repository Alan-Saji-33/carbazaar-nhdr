<?php
// Ensure session is started (if not already started by the including page)
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure' => false,
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

// Custom sanitization function for strings
function sanitize_string($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Database connection
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
    error_log("Database connection error: " . $e->getMessage());
    die("An error occurred. Please try again later.");
}

// Fetch distinct locations from cars table
$locations = $conn->query("SELECT DISTINCT location FROM cars WHERE is_sold = FALSE ORDER BY location")->fetch_all(MYSQLI_ASSOC);

// Handle location selection
if (isset($_GET['selected_location']) && !empty($_GET['selected_location'])) {
    $_SESSION['selected_location'] = sanitize_string($_GET['selected_location']);
}

// Get user data if logged in
$user = null;
if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username, profile_pic, user_type FROM users WHERE id = ?");
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        die("An error occurred while preparing the query.");
    }
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
    } else {
        error_log("Query execution failed: " . $stmt->error);
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

        header {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }

        .left-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .logo-icon i {
            font-size: 24px;
            color: var(--primary);
            margin-right: 8px;
        }

        .logo-text {
            font-size: 20px;
            font-weight: 600;
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
            gap: 30px;
        }

        nav a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            transition: color 0.3s ease;
            font-size: 16px;
        }

        nav a:hover {
            color: var(--primary);
        }

        /* Dropdown Button */
        .dropdown {
            position: relative;
        }

        .dropdown-btn {
            background: transparent;
            border: 1px solid var(--light-gray);
            border-radius: 25px;
            color: var(--dark);
            padding: 8px 16px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .dropdown-btn:hover {
            background: var(--light);
        }

        .dropdown-btn i {
            font-size: 12px;
        }

        /* Dropdown Menu */
        .dropdown-menu {
            position: absolute;
            top: 110%;
            left: 0;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            min-width: 150px;
            border: 1px solid var(--light-gray);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            opacity: 0;
            transform: translateY(-10px);
            pointer-events: none;
            transition: all 0.3s ease;
            z-index: 99;
        }

        .dropdown-menu.active {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .dropdown-menu li {
            list-style: none;
            padding: 10px 15px;
            color: var(--dark);
            cursor: pointer;
            transition: background 0.2s;
            font-size: 14px;
        }

        .dropdown-menu li:hover {
            background: var(--light);
        }

        /* Search Box */
        .search-box {
            display: flex;
            align-items: center;
            border: 1px solid var(--light-gray);
            border-radius: 25px;
            padding: 8px 15px;
            background: transparent;
        }

        .search-box input {
            background: transparent;
            border: none;
            outline: none;
            color: var(--dark);
            font-size: 14px;
            width: 200px;
        }

        .search-box input::placeholder {
            color: var(--gray);
        }

        .search-box button {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--primary);
            font-size: 16px;
        }

        .profile-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .profile-action-icon {
            color: var(--primary);
            font-size: 22px;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .profile-action-icon:hover {
            color: var(--secondary);
        }

        .profile-dropdown {
            position: relative;
            display: inline-block;
        }

        .profile-dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .profile-dropdown-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }

        .profile-dropdown-toggle i {
            color: var(--primary);
            font-size: 12px;
            transition: transform 0.3s ease;
        }

        .profile-dropdown-toggle.active i {
            transform: rotate(180deg);
        }

        .dropdown.profile-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            width: 280px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            display: none;
            flex-direction: column;
            border: 1px solid var(--light-gray);
            margin-top: 5px;
            opacity: 0;
            transform: translateY(-5px);
            transition: opacity 0.2s ease, transform 0.2s ease;
            z-index: 1000;
        }

        .dropdown.profile-menu.show {
            display: flex;
            opacity: 1;
            transform: translateY(0);
        }

        .profile-section {
            padding: 12px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid var(--light-gray);
        }

        .profile-section img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            margin-right: 12px;
        }

        .profile-section .name {
            font-weight: 500;
            font-size: 15px;
            color: var(--dark);
        }

        .menu-item {
            padding: 12px 15px;
            display: flex;
            align-items: center;
            cursor: pointer;
            font-size: 14px;
            color: var(--dark);
            text-decoration: none;
            transition: background 0.2s ease;
        }

        .menu-item:hover {
            background: var(--light);
        }

        .menu-item i {
            margin-right: 12px;
            color: var(--primary);
            font-size: 16px;
        }

        @media (max-width: 768px) {
            .profile-dropdown-img {
                width: 32px;
                height: 32px;
            }
            .profile-action-icon {
                font-size: 18px;
            }
            .dropdown.profile-menu {
                width: 250px;
                right: -20px;
            }
            .left-section {
                gap: 10px;
                flex-wrap: wrap;
            }
            nav ul {
                gap: 20px;
            }
            nav a {
                font-size: 14px;
            }
            .search-box input {
                width: 150px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container header-container">
            <div class="left-section">
                <a href="index.php" class="logo">
                    <div class="logo-icon"><i class="fas fa-car"></i></div>
                    <div class="logo-text">Car<span>Bazaar</span></div>
                </a>
                <!-- Location Dropdown -->
                <div class="dropdown">
                    <button class="dropdown-btn" id="dropdownBtn">
                        <?php echo htmlspecialchars($_SESSION['selected_location'] ?? 'Select Location'); ?> <i class="fas fa-chevron-down"></i>
                    </button>
                    <ul class="dropdown-menu" id="dropdownMenu">
                        <?php foreach ($locations as $loc): ?>
                            <li onclick="selectLocation('<?php echo htmlspecialchars($loc['location']); ?>')">
                                <?php echo htmlspecialchars($loc['location']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <!-- Search Box -->
                <div class="search-box">
                    <form id="searchForm" method="GET" action="advanced_search.php">
                        <input type="text" name="search" placeholder="Search by model" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <input type="hidden" name="location" value="<?php echo htmlspecialchars($_SESSION['selected_location'] ?? ''); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                </div>
                <nav>
                    <ul>
                        <li><a href="#cars">Cars</a></li>
                        <li><a href="about.php">About</a></li>
                    </ul>
                </nav>
            </div>
            <?php if (isset($_SESSION['user_id']) && $user): ?>
                <div class="profile-actions">
                    <a href="messages.php" class="profile-action-icon" title="Messages"><i class="fa-solid fa-comments"></i></a>
                    <a href="favorites.php" class="profile-action-icon" title="Favorites"><i class="fa-solid fa-heart"></i></a>
                    <div class="profile-dropdown">
                        <div class="profile-dropdown-toggle" id="profileToggle">
                            <img src="<?php echo htmlspecialchars($user['profile_pic'] ?? 'Uploads/profiles/default.jpg'); ?>" alt="Profile Picture" class="profile-dropdown-img">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="dropdown profile-menu" id="profileMenu">
                            <div class="profile-section">
                                <img src="<?php echo htmlspecialchars($user['profile_pic'] ?? 'Uploads/profiles/default.jpg'); ?>" alt="Profile Picture">
                                <div class="name"><?php echo htmlspecialchars($user['username'] ?? 'User'); ?></div>
                            </div>
                            <a href="profile.php" class="menu-item"><i class="fas fa-edit"></i> Edit Profile</a>
                            <?php 
                            $dashboard_link = '';
                            if (isset($user['user_type'])) {
                                if ($user['user_type'] == 'admin') {
                                    $dashboard_link = 'admin_dashboard.php';
                                } elseif ($user['user_type'] == 'seller') {
                                    $dashboard_link = 'seller_dashboard.php';
                                } elseif ($user['user_type'] == 'buyer') {
                                    $dashboard_link = 'buyer_dashboard.php';
                                }
                            }
                            if ($dashboard_link): ?>
                                <a href="<?php echo htmlspecialchars($dashboard_link); ?>" class="menu-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                            <?php endif; ?>
                            <a href="index.php?logout" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="profile-actions">
                    <a href="login.php" class="btn btn-primary">Login</a>
                    <a href="signup.php" class="btn btn-outline">Sign Up</a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const dropdownBtn = document.getElementById('dropdownBtn');
            const dropdownMenu = document.getElementById('dropdownMenu');
            const profileToggle = document.getElementById('profileToggle');
            const profileMenu = document.getElementById('profileMenu');

            if (dropdownBtn && dropdownMenu) {
                dropdownBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    dropdownMenu.classList.toggle('active');
                });

                window.selectLocation = function (city) {
                    dropdownBtn.innerHTML = city + ' <i class="fas fa-chevron-down"></i>';
                    dropdownMenu.classList.remove('active');
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.set('selected_location', city);
                    window.location.href = currentUrl.toString();
                };

                document.addEventListener('click', function (e) {
                    if (!dropdownBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
                        dropdownMenu.classList.remove('active');
                    }
                });
            }

            if (profileToggle && profileMenu) {
                profileToggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    profileMenu.classList.toggle('show');
                    profileToggle.classList.toggle('active');
                });

                document.addEventListener('click', function (e) {
                    if (!profileToggle.contains(e.target) && !profileMenu.contains(e.target)) {
                        profileMenu.classList.remove('show');
                        profileToggle.classList.remove('active');
                    }
                });

                profileMenu.querySelectorAll('.menu-item').forEach(item => {
                    item.addEventListener('click', function () {
                        profileMenu.classList.remove('show');
                        profileToggle.classList.remove('active');
                    });
                });
            }
        });
    </script>
</body>
</html>