<?php
// Ensure this file is included in admin_dashboard.php
if (!defined('IN_ADMIN_DASHBOARD')) {
    define('IN_ADMIN_DASHBOARD', true);
}

// Database connection (assumed to be available from admin_dashboard.php)
global $conn;

$search_query = isset($_GET['search']) ? filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) : '';
$where_clause = $search_query ? "WHERE username LIKE ? AND aadhaar_status = 'pending'" : "WHERE aadhaar_status = 'pending'";
$params = $search_query ? ["%$search_query%"] : [];
$param_types = $search_query ? "s" : "";

$stmt = $conn->prepare("SELECT id, username, profile_pic, aadhaar_path FROM users $where_clause ORDER BY created_at DESC");
if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}
if ($search_query) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$verifications_result = $stmt->get_result();
?>

<div class="table-container">
    <h3>Aadhaar Verifications</h3>
    <?php if ($verifications_result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Aadhaar Image</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $verifications_result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="<?php echo htmlspecialchars($user['profile_pic'] ?: 'Uploads/profiles/default.jpg'); ?>" alt="<?php echo htmlspecialchars($user['username']); ?>" class="user-image">
                                <?php echo htmlspecialchars($user['username']); ?>
                            </div>
                        </td>
                        <td>
                            <a href="<?php echo htmlspecialchars($user['aadhaar_path']); ?>" target="_blank">
                                <img src="<?php echo htmlspecialchars($user['aadhaar_path'] ?: 'Uploads/aadhaar/default.jpg'); ?>" alt="Aadhaar Image" class="car-image">
                            </a>
                        </td>
                        <td class="action-buttons">
                            <a href="review_aadhaar.php?id=<?php echo $user['id']; ?>" class="btn btn-primary"><i class="fas fa-id-card"></i> Review</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No pending Aadhaar verifications.</p>
    <?php endif; ?>
</div>

<style>
    .user-image {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        vertical-align: middle;
    }

    .car-image {
        width: 200px;
        height: 70px;
        object-fit: cover;
        border-radius: 8px;
        vertical-align: middle;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
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
</style>