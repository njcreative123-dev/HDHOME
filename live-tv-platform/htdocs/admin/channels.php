<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::startSession();
Security::secureHeaders();

Auth::requireAdmin();

$action = $_GET['action'] ?? 'list';
$categories = getCategories(false);
?>
<?php require_once __DIR__ . '/../../templates/AdminTemplate.php'; ?>
<?= AdminTemplate::renderHeader('Channels') ?>

<div class="dashboard-grid">
    <div class="dashboard-header">
        <h2>Channel Management</h2>
        <div class="header-actions">
            <a href="/admin/channels.php?action=add" class="btn btn-primary">Add Channel</a>
            <button id="btn-bulk-delete" class="btn btn-danger" disabled>Delete Selected</button>
        </div>
    </div>

    <?php echo AdminTemplate::renderFlashMessages(); ?>

    <?php if ($action === 'add'): ?>
        <!-- Add/Edit Channel Form -->
        <div class="card">
            <div class="card-header">
                <h3>Add New Channel</h3>
            </div>
            <div class="card-body">
                <form id="channel-form" class="admin-form">
                    <input type="hidden" name="action" value="add_channel">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Channel Name *</label>
                            <input type="text" id="name" name="name" required class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="slug">Slug (auto-generated)</label>
                            <input type="text" id="slug" name="slug" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label for="category_id">Category *</label>
                            <select id="category_id" name="category_id" required class="form-control">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="stream_url">Stream URL (HLS .m3u8) *</label>
                            <input type="url" id="stream_url" name="stream_url" required class="form-control" placeholder="https://example.com/stream.m3u8">
                        </div>
                        <div class="form-group">
                            <label for="stream_url_backup">Backup Stream URL</label>
                            <input type="url" id="stream_url_backup" name="stream_url_backup" class="form-control" placeholder="https://example.com/backup.m3u8">
                        </div>
                        <div class="form-group">
                            <label for="country">Country</label>
                            <input type="text" id="country" name="country" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="language">Language</label>
                            <input type="text" id="language" name="language" class="form-control" value="English">
                        </div>
                        <div class="form-group">
                            <label for="logo">Logo URL</label>
                            <input type="url" id="logo" name="logo" class="form-control" placeholder="/assets/img/logo/example.png">
                        </div>
                        <div class="form-group">
                            <label for="poster">Poster URL</label>
                            <input type="url" id="poster" name="poster" class="form-control" placeholder="/assets/img/poster/example.jpg">
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3" class="form-control"></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group checkbox-group">
                                <label><input type="checkbox" name="is_active" value="1" checked> Active</label>
                                <label><input type="checkbox" name="is_featured" value="1"> Featured</label>
                            </div>
                            <div class="form-group">
                                <label for="sort_order">Sort Order</label>
                                <input type="number" id="sort_order" name="sort_order" class="form-control" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Save Channel</button>
                        <a href="/admin/channels.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- Channel List -->
        <div class="card">
            <div class="card-header">
                <h3>All Channels</h3>
                <div class="card-actions">
                    <input type="text" id="channel-search" placeholder="Search channels..." class="search-input">
                </div>
            </div>
            <div class="card-body">
                <div id="channels-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all"></th>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Stream URL</th>
                                <th>Views</th>
                                <th>Status</th>
                                <th>Featured</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="channels-tbody">
                            <?php
                            $channels = Database::fetchAll(
                                "SELECT c.id, c.name, c.slug, c.stream_url, c.is_active, c.is_featured, c.view_count, c.sort_order, cat.name as category_name
                                 FROM channels c
                                 LEFT JOIN categories cat ON c.category_id = cat.id
                                 ORDER BY c.sort_order ASC, c.created_at DESC"
                            );
                            foreach ($channels as $ch):
                            ?>
                            <tr data-channel-id="<?= $ch['id'] ?>">
                                <td class="checkbox-cell"><input type="checkbox" class="channel-checkbox" value="<?= $ch['id'] ?>"></td>
                                <td><?= $ch['id'] ?></td>
                                <td><?= htmlspecialchars($ch['name']) ?></td>
                                <td><?= htmlspecialchars($ch['category_name'] ?? '—') ?></td>
                                <td class="url-cell"><?= htmlspecialchars(substr($ch['stream_url'], 0, 50)) ?>...</td>
                                <td><?= $ch['view_count'] ?></td>
                                <td><?= $ch['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-warning">Inactive</span>' ?></td>
                                <td><?= $ch['is_featured'] ? '<span class="badge badge-info">Yes</span>' : '<span class="badge badge-secondary">No</span>' ?></td>
                                <td class="text-right">
                                    <a href="/admin/channels.php?action=edit&id=<?= $ch['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                    <button onclick="deleteChannel(<?= $ch['id'] ?>)" class="btn btn-sm btn-danger">Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?= AdminTemplate::renderModal('delete-modal', 'Confirm Delete', '<p>Are you sure you want to delete this channel?</p>', '<button class="btn btn-danger" id="confirm-delete">Delete</button><button class="btn btn-secondary" id="cancel-delete">Cancel</button>') ?>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-generate slug from name
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    if (nameInput && slugInput) {
        nameInput.addEventListener('input', function() {
            slugInput.value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        });
    }

    // Form submission
    const form = document.getElementById('channel-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            data.action = 'add_channel';

            fetch('/api/admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(r => r.json())
            .then(result => {
                if (result.status === 'success') {
                    alert('Channel saved successfully!');
                    window.location.href = '/admin/channels.php';
                } else {
                    alert('Error: ' + result.error);
                }
            })
            .catch(err => alert('Request failed: ' + err.message));
        });
    }

    // Bulk actions
    const selectAll = document.getElementById('select-all');
    const selectAllCheckbox = selectAll;
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            document.querySelectorAll('.channel-checkbox').forEach(cb => cb.checked = this.checked);
            updateBulkDeleteButton();
        });
    }
    document.querySelectorAll('.channel-checkbox').forEach(cb => {
        cb.addEventListener('change', updateBulkDeleteButton);
    });

    function updateBulkDeleteButton() {
        const checked = document.querySelectorAll('.channel-checkbox:checked');
        document.getElementById('btn-bulk-delete').disabled = checked.length === 0;
    }

    window.deleteChannel = function(id) {
        if (confirm('Delete channel #' + id + '? This cannot be undone.')) {
            fetch('/api/admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_channel', channel_id: id })
            })
            .then(r => r.json())
            .then(result => {
                if (result.status === 'success') {
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            });
        }
    };
});
</script>

<?= AdminTemplate::renderFooter() ?>
