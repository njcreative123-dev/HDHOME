<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

Auth::startSession();
Security::secureHeaders();

Auth::requireAdmin();
$user = Auth::getCurrentUser();
?>
<?php require_once __DIR__ . '/../../templates/AdminTemplate.php'; ?>
<?= AdminTemplate::renderHeader('Dashboard') ?>

<div class="dashboard-grid">
    <div class="dashboard-header">
        <h2>System Overview</h2>
        <div class="header-actions">
            <button id="btn-run-maintenance" class="btn btn-primary">Run AI Maintenance</button>
        </div>
    </div>

    <?php echo AdminTemplate::renderFlashMessages(); ?>

    <div id="health-report" class="loading">Loading system health...</div>

    <div class="dashboard-cards">
        <div class="card card-stat">
            <div class="stat-icon">📺</div>
            <div class="stat-content">
                <div class="stat-value" id="stat-channels">Loading...</div>
                <div class="stat-label">Active Channels</div>
            </div>
        </div>
        <div class="card card-stat">
            <div class="stat-icon">👥</div>
            <div class="stat-content">
                <div class="stat-value" id="stat-users">Loading...</div>
                <div class="stat-label">Active Users</div>
            </div>
        </div>
        <div class="card card-stat">
            <div class="stat-icon">💬</div>
            <div class="stat-content">
                <div class="stat-value" id="stat-ai-today">Loading...</div>
                <div class="stat-label">AI Messages Today</div>
            </div>
        </div>
        <div class="card card-stat">
            <div class="stat-icon">⚠️</div>
            <div class="stat-content">
                <div class="stat-value" id="stat-errors">Loading...</div>
                <div class="stat-label">Errors Today</div>
            </div>
        </div>
    </div>

    <div class="dashboard-row">
        <div class="card card-full">
            <div class="card-header">
                <h3>System Health</h3>
            </div>
            <div class="card-body" id="system-health-detail">Checking...</div>
        </div>
    </div>

    <div class="dashboard-row">
        <div class="card card-half">
            <div class="card-header">
                <h3>Recent System Logs</h3>
            </div>
            <div class="card-body" id="recent-logs">
                <div class="table-loading">Loading logs...</div>
            </div>
        </div>
        <div class="card card-half">
            <div class="card-header">
                <h3>Quick Actions</h3>
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="/admin/channels.php?action=add" class="btn btn-success">Add Channel</a>
                    <a href="/admin/categories.php?action=add" class="btn btn-info">Add Category</a>
                    <button id="btn-clear-cache" class="btn btn-warning">Clear Cache</button>
                    <button id="btn-clear-logs" class="btn btn-danger">Clean Old Logs</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    fetch('/api/admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get_health_report',
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success' && data.report) {
            const report = data.report;
            document.getElementById('stat-channels').textContent = report.stats.active_channels || 0;
            document.getElementById('stat-users').textContent = report.stats.active_users || 0;
            document.getElementById('stat-ai-today').textContent = report.stats.ai_messages_today || 0;
            document.getElementById('stat-errors').textContent = report.stats.errors_today || 0;
            
            const healthHtml = `
                <div class="health-grid">
                    <div>Uptime Status: <strong class="status-${report.uptime_status}">${report.uptime_status}</strong></div>
                    <div>App Version: <strong>${report.app_version}</strong></div>
                    <div>Server Time: <strong>${report.server_time}</strong></div>
                    <div>PHP Version: <strong>${report.system.php_version}</strong></div>
                    <div>Memory Limit: <strong>${report.system.memory_limit}</strong></div>
                    <div>Cache Size: <strong>${report.cache.size_human}</strong> (${report.cache.count} files)</div>
                </div>
            `;
            document.getElementById('system-health-detail').innerHTML = healthHtml;
            document.getElementById('health-report').classList.remove('loading');
        }
    })
    .catch(err => {
        document.getElementById('health-report').innerHTML = '<div class="error">Failed to load health report</div>';
    });

    // Load recent logs
    fetch('/api/admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_logs', log_type: 'all', limit: 10 })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            if (data.logs.length === 0) {
                document.getElementById('recent-logs').innerHTML = '<div class="no-logs">No recent logs</div>';
            } else {
                let html = '<table class="log-table"><thead><tr><th>Time</th><th>Type</th><th>Message</th></tr></thead><tbody>';
                data.logs.forEach(log => {
                    html += `<tr class="log-row log-${log.type}">
                        <td>${log.created_at}</td>
                        <td>${log.type}</td>
                        <td>${log.message.substring(0, 100)}</td>
                    </tr>`;
                });
                html += '</tbody></table>';
                document.getElementById('recent-logs').innerHTML = html;
            }
        }
    });

    // Run maintenance
    document.getElementById('btn-run-maintenance').addEventListener('click', function() {
        this.disabled = true;
        this.textContent = 'Running...';
        fetch('/src/ai-agent/AIAgent.php?maintenance=run&key=' + encodeURIComponent('<?= addslashes(SECRET_KEY) ?>'), {
            method: 'GET'
        })
        .then(r => r.json())
        .then(data => {
            alert('Maintenance completed:\n' + JSON.stringify(data, null, 2));
        })
        .catch(() => alert('Maintenance failed'))
        .finally(() => {
            this.disabled = false;
            this.textContent = 'Run AI Maintenance';
            location.reload();
        });
    });

    // Clear cache
    document.getElementById('btn-clear-cache').addEventListener('click', function() {
        if (confirm('Clear all cache files?')) {
            fetch('/api/admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'run_maintenance' })
            }).then(() => location.reload());
        }
    });
});
</script>

<?= AdminTemplate::renderFooter() ?>
