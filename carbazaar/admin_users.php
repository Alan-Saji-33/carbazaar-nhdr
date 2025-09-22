<?php
// Ensure this file is included in admin_dashboard.php
if (!defined('IN_ADMIN_DASHBOARD')) {
    define('IN_ADMIN_DASHBOARD', true);
}

// Database connection (assumed to be available from admin_dashboard.php)
global $conn;

$search_query = isset($_GET['search']) ? filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) : '';
$where_clause = $search_query ? "WHERE username LIKE ? OR email LIKE ?" : "";
$params = $search_query ? ["%$search_query%", "%$search_query%"] : [];
$param_types = $search_query ? "ss" : "";

$stmt = $conn->prepare("SELECT id, username, email, user_type, profile_pic FROM users $where_clause ORDER BY created_at DESC");
if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}
if ($search_query) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$users_result = $stmt->get_result();
?>

<div class="table-container">
    <h3>Manage Users</h3>
    <?php if ($users_result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Type</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $users_result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="<?php echo htmlspecialchars($user['profile_pic'] ?: 'Uploads/profiles/default.jpg'); ?>" alt="<?php echo htmlspecialchars($user['username']); ?>" class="user-image">
                                <?php echo htmlspecialchars($user['username']); ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td style="text-align: center;"><?php echo ucfirst($user['user_type']); ?></td>
                        <td class="action-buttons">
                            <a href="view_user.php?id=<?php echo $user['id']; ?>" class="btn btn-outline"><i class="fas fa-eye"></i> View</a>
                            <?php if ($user['user_type'] !== 'admin'): ?>
                                <form method="POST" action="delete_user.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                                    <button type="submit" name="delete_user" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No users found.</p>
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

    .btn-danger {
        background-color: var(--danger);
        color: white;
    }

    .btn-danger:hover {
        background-color: #d1145a;
        transform: translateY(-2px);
    }
</style>