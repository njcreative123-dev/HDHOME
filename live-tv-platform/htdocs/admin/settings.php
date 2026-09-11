<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::startSession();
Security::secureHeaders();

Auth::requireAdmin();
$user = Auth::getCurrentUser();

// Handle settings update
if (isset($_POST['update_settings'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (Security::validateToken($csrfToken, 'admin_settings')) {
        $updated = [];
        foreach (['site_title', 'site_description', 'items_per_page', 'stream_buffer', 'maintenance_mode', 'allow_registration', 'ai_chat_enabled', 'log_retention_days'] as $key) {
            if (isset($_POST[$key])) {
                setSystemSetting($key, $_POST[$key]);
                $updated[] = $key;
            }
        }
        $_SESSION['flash_success'] = 'Settings updated: ' . implode(', ', $updated);
        redirectTo('/admin/settings.php');
    }
}

$settings = Database::fetchAll("SELECT setting_key, setting_value FROM system_settings WHERE is_editable = 1 ORDER BY setting_key");
$settingsMap = [];
foreach ($settings as $s) {
    $settingsMap[$s['setting_key']] = $s['setting_value'];
}
$csrfToken = Security::generateToken('admin_settings');
?>
<?php require_once __DIR__ . '/../../templates/AdminTemplate.php'; ?>
<?= AdminTemplate::renderHeader('Settings') ?>

<div class="dashboard-grid">
    <div class="dashboard-header">
        <h2>System Settings</h2>
    </div>

    <?php echo AdminTemplate::renderFlashMessages(); ?>

    <div class="card">
        <div class="card-header">
            <h3>Site Configuration</h3>
        </div>
        <div class="card-body">
            <form method="POST" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="site_title">Site Title</label>
                        <input type="text" id="site_title" name="site_title" class="form-control" value="<?= htmlspecialchars($settingsMap['site_title'] ?? APP_NAME) ?>">
                    </div>
                    <div class="form-group">
                        <label for="site_description">Meta Description</label>
                        <input type="text" id="site_description" name="site_description" class="form-control" value="<?= htmlspecialchars($settingsMap['site_description'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="items_per_page">Channels Per Page</label>
                        <input type="number" id="items_per_page" name="items_per_page" class="form-control" value="<?= $settingsMap['items_per_page'] ?? 24 ?>" min="6" max="100">
                    </div>
                    <div class="form-group">
                        <label for="stream_buffer">Stream Buffer (seconds)</label>
                        <input type="number" id="stream_buffer" name="stream_buffer" class="form-control" value="<?= $settingsMap['stream_buffer'] ?? STREAM_BUFFER_SIZE ?>" min="10" max="3000">
                    </div>
                    <div class="form-group">
                        <label for="log_retention_days">Log Retention (days)</label>
                        <input type="number" id="log_retention_days" name="log_retention_days" class="form-control" value="<?= $settingsMap['log_retention_days'] ?? LOG_RETENTION_DAYS ?>" min="1" max="90">
                    </div>
                </div>
                <div class="form-group checkbox-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="maintenance_mode" value="1" <?= ($settingsMap['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>> Enable Maintenance Mode
                    </label>
                </div>
                <div class="form-group checkbox-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="allow_registration" value="1" <?= ($settingsMap['allow_registration'] ?? '1') === '1' ? 'checked' : '' ?>> Allow User Registration
                    </label>
                </div>
                <div class="form-group checkbox-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="ai_chat_enabled" value="1" <?= ($settingsMap['ai_chat_enabled'] ?? '1') === '1' ? 'checked' : '' ?>> Enable AI Chat
                    </label>
                </div>
                <div class="form-actions">
                    <button type="submit" name="update_settings" class="btn btn-success">Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= AdminTemplate::renderFooter() ?>
