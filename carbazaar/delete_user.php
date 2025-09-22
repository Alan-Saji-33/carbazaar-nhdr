<?php
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = "Please login as an admin to access this action.";
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
    $_SESSION['error'] = $e->getMessage();
    header("Location: admin_dashboard.php?section=users");
    exit();
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    try {
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
        $search_query = filter_input(INPUT_POST, 'search', FILTER_SANITIZE_STRING) ?? '';
        
        // Prevent deletion of admin user
        $stmt = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_type = $stmt->get_result()->fetch_assoc()['user_type'];
        $stmt->close();
        
        if ($user_type === 'admin') {
            throw new Exception("Cannot delete admin user.");
        }

        // Delete user (cascading deletes will handle related cars and favorites due to foreign key constraints)
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "User deleted successfully!";
        } else {
            throw new Exception("Failed to delete user: " . $stmt->error);
        }
        $stmt->close();
        header("Location: admin_dashboard.php?section=users" . ($search_query ? "&search=" . urlencode($search_query) : ""));
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: admin_dashboard.php?section=users" . ($search_query ? "&search=" . urlencode($search_query) : ""));
        exit();
    }
} else {
    $_SESSION['error'] = "Invalid request.";
    header("Location: admin_dashboard.php?section=users");
    exit();
}
?>