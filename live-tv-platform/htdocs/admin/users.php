<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::startSession();
Security::secureHeaders();

Auth::requireAdmin();
$user = Auth::getCurrentUser();
?>
<?php require_once __DIR__ . '/../../templates/AdminTemplate.php'; ?>
<?= AdminTemplate::renderHeader('Users') ?>

<div class="dashboard-grid">
    <div class="dashboard-header">
        <h2>User Management</h2>
    </div>

    <?php echo AdminTemplate::renderFlashMessages(); ?>

    <div class="card">
        <div class="card-header">
            <h3>All Users</h3>
        </div>
        <div class="card-body">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $users = Database::fetchAll(
                        "SELECT id, username, email, role, status, last_login, created_at FROM users ORDER BY created_at DESC"
                    );
                    foreach ($users as $u):
                    ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <select class="role-select" data-user-id="<?= $u['id'] ?>" <?= ($u['role'] === 'admin' && $user['username'] !== $u['username']) ? 'disabled' : '' ?>>
                                <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                <option value="moderator" <?= $u['role'] === 'moderator' ? 'selected' : '' ?>>Moderator</option>
                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </td>
                        <td>
                            <span class="badge badge-<?= $u['status'] === 'active' ? 'success' : ($u['status'] === 'banned' ? 'danger' : 'warning') ?>">
                                <?= ucfirst($u['status']) ?>
                            </span>
                        </td>
                        <td><?= $u['last_login'] ?? '—' ?></td>
                        <td>
                            <button onclick="deleteUser(<?= $u['id'] ?>)" class="btn btn-sm btn-danger">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Role updates
    document.querySelectorAll('.role-select').forEach(select => {
        select.addEventListener('change', function() {
            const userId = this.dataset.userId;
            const newRole = this.value;
            fetch('/api/admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_user_role',
                    user_id: userId,
                    role: newRole
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status !== 'success') {
                    alert('Error: ' + data.error);
                    this.selectedIndex = 0;
                }
            });
        });
    });
});

function deleteUser(id) {
    if (confirm('Delete user #' + id + '? This cannot be undone.')) {
        fetch('/api/admin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_user', user_id: id })
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        });
    }
}
</script>

<?= AdminTemplate::renderFooter() ?>
