<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::startSession();
Security::secureHeaders();

if (!Auth::isLoggedIn() || !Auth::isAdmin()) {
    redirectTo('/admin/login.php');
}

$categories = getCategories(false);
$error = $_GET['msg'] ?? '';

if (isset($_POST['add_category'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (Security::validateToken($csrfToken, 'admin_category')) {
        $name = Security::sanitizeInput($_POST['name'] ?? '');
        $description = Security::sanitizeInput($_POST['description'] ?? '');
        $icon = Security::sanitizeInput($_POST['icon'] ?? '');

        if (empty($name)) {
            $error = 'Category name is required.';
        } else {
            $slug = generateSlug($name);
            try {
                Database::insert('categories', [
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $description,
                    'icon' => $icon,
                ]);
                Cache::getInstance()->delete('categories_active');
                Cache::getInstance()->delete('categories_all');
                $_SESSION['flash_success'] = 'Category added successfully!';
                redirectTo('/admin/categories.php');
            } catch (PDOException $e) {
                $error = 'Category slug may already exist. Choose a different name.';
            }
        }
    } else {
        $error = 'Invalid security token.';
    }
}

$categoryToken = Security::generateToken('admin_category');
?>
<?php require_once __DIR__ . '/../../templates/AdminTemplate.php'; ?>
<?= AdminTemplate::renderHeader('Categories') ?>

<div class="dashboard-grid">
    <div class="dashboard-header">
        <h2>Categories</h2>
        <a href="/admin/categories.php?action=add" class="btn btn-primary">Add Category</a>
    </div>

    <?php echo AdminTemplate::renderFlashMessages(); ?>

    <?php if (isset($_GET['action']) && $_GET['action'] === 'add'): ?>
        <div class="card">
            <div class="card-header">
                <h3>Add New Category</h3>
            </div>
            <div class="card-body">
                <form method="POST" class="admin-form">
                    <input type="hidden" name="csrf_token" value="<?= $categoryToken ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Name *</label>
                            <input type="text" id="name" name="name" required class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="icon">Icon</label>
                            <input type="text" id="icon" name="icon" class="form-control" placeholder="📺">
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3" class="form-control"></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="add_category" class="btn btn-success">Save</button>
                        <a href="/admin/categories.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Icon</th>
                            <th>Description</th>
                            <th>Channels</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): 
                            $count = Database::fetch("SELECT COUNT(*) as total FROM channels WHERE category_id = ? AND is_active = 1", [$cat['id']]);
                        ?>
                        <tr>
                            <td><?= $cat['id'] ?></td>
                            <td><?= htmlspecialchars($cat['name']) ?></td>
                            <td><?= htmlspecialchars($cat['slug']) ?></td>
                            <td><?= $cat['icon'] ?></td>
                            <td><?= htmlspecialchars(substr($cat['description'] ?? '', 0, 80)) ?></td>
                            <td><?= $count['total'] ?? 0 ?></td>
                            <td>
                                <a href="/admin/categories.php?action=edit&id=<?= $cat['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                <a href="/admin/categories.php?action=delete&id=<?= $cat['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this category?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?= AdminTemplate::renderFooter() ?>
