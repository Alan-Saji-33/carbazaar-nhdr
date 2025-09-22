<?php
// Start output buffering to prevent headers issues
ob_start();

// Ensure this file is included in admin_dashboard.php
if (!defined('IN_ADMIN_DASHBOARD')) {
    define('IN_ADMIN_DASHBOARD', true);
}

$search_query = isset($_GET['search']) ? filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) : '';
$where_clause = $search_query ? "WHERE (brand LIKE ? OR model LIKE ?)" : "";
$params = $search_query ? ["%$search_query%", "%$search_query%"] : [];
$param_types = $search_query ? "ss" : "";

$stmt = $conn->prepare("SELECT id, brand, model, price, is_sold, main_image FROM cars $where_clause ORDER BY created_at DESC");
if ($stmt === false) {
    $_SESSION['error'] = "Prepare failed: " . htmlspecialchars($conn->error);
    header("Location: admin_dashboard.php?section=cars" . ($search_query ? "&search=" . urlencode($search_query) : ""));
    exit();
}
if ($search_query) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$cars_result = $stmt->get_result();
?>

<div class="table-container">
    <h3>Manage Cars</h3>
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
                                <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>
                            </div>
                        </td>
                        <td style="text-align: right;">₹<?php echo number_format($car['price'], 0, '', ','); ?></td>
                        <td style="text-align: center;"><?php echo $car['is_sold'] ? 'Sold' : 'Available'; ?></td>
                        <td class="action-buttons">
                            <a href="view_car.php?id=<?php echo $car['id']; ?>" class="btn btn-outline"><i class="fas fa-eye"></i> View</a>
                            <a href="delete_car.php?id=<?php echo $car['id']; ?>" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No cars found.</p>
    <?php endif; ?>
</div>

<?php
// End output buffering
ob_end_flush();
?>