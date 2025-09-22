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
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$car_id = filter_input(INPUT_GET, 'car_id', FILTER_SANITIZE_NUMBER_INT);
$seller_id = filter_input(INPUT_GET, 'seller_id', FILTER_SANITIZE_NUMBER_INT);
$user_id = $_SESSION['user_id'];

if (!$car_id || !$seller_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit();
}

$stmt = $conn->prepare("
    SELECT m.id, m.sender_id, m.message, m.created_at, u.username
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.car_id = ? AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
    ORDER BY m.created_at ASC
");
$stmt->bind_param("iiiii", $car_id, $user_id, $seller_id, $seller_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'sender_id' => $row['sender_id'],
        'message' => htmlspecialchars($row['message']),
        'created_at' => $row['created_at'] // Output raw MySQL datetime (e.g., 2025-07-25 13:58:00)
    ];
}
$stmt->close();

// Mark messages as read
$stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE car_id = ? AND receiver_id = ? AND is_read = 0");
$stmt->bind_param("ii", $car_id, $user_id);
$stmt->execute();
$stmt->close();

header('Content-Type: application/json');
echo json_encode($messages);
exit();
?>