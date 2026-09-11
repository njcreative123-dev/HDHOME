<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../src/ai-agent/AIAgent.php';

Auth::startSession();
Security::secureHeaders();

Auth::requireAdmin();
?>
<?php require_once __DIR__ . '/../../templates/AdminTemplate.php'; ?>
<?= AdminTemplate::renderHeader('System Monitor') ?>

<div class="dashboard-grid">
    <div class="dashboard-header">
        <h2>System Monitor</h2>
        <div class="header-actions">
            <button id="btn-run-maintenance" class="btn btn-primary">
                Run AI Maintenance
            </button>
        </div>
    </div>

    <?php echo AdminTemplate::renderFlashMessages(); ?>

    <?php
    $agent = new AIAgent();
    $report = $agent->getHealthReport();
    ?>

    <div class="dashboard-cards">
        <div class="card">
            <div class="card-body text-center">
                <div class="stat-icon">🗄️</div>
                <h3>Database</h3>
                <p class="status-<?= $report['uptime_status'] ?>"><?= ucfirst($report['uptime_status']) ?></p>
                <p class="text-muted">Connected: <?= $report['system']['db_connected'] ? 'Yes' : 'No' ?></p>
            </div>
        </div>
        <div class="card">
            <div class="card-body text-center">
                <div class="stat-icon">💾</div>
                <h3>Cache</h3>
                <p class="stat-value"><?= $report['cache']['count'] ?></p>
                <p class="text-muted"><?= $report['cache']['size_human'] ?> stored</p>
            </div>
        </div>
        <div class="card">
            <div class="card-body text-center">
                <div class="stat-icon">🤖</div>
                <h3>AI Agent</h3>
                <p class="stat-value"><?= $report['stats']['ai_messages_today'] ?? 0 ?></p>
                <p class="text-muted">Messages today</p>
            </div>
        </div>
        <div class="card">
            <div class="card-body text-center">
                <div class="stat-icon">📊</div>
                <h3>Channels</h3>
                <p class="stat-value"><?= $report['stats']['active_channels'] ?? 0 ?></p>
                <p class="text-muted">Active channels</p>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Stream Health</h3>
        </div>
        <div class="card-body" id="stream-health">
            <div class="loading">Checking stream health...</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Recent Errors & Warnings</h3>
        </div>
        <div class="card-body">
            <div id="recent-errors">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Source</th>
                            <th>Message</th>
                            <th>Resolved</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $logs = Database::fetchAll(
                            "SELECT type, source, message, is_resolved, created_at
                             FROM maintenance_logs
                             WHERE type IN ('error', 'warning')
                             ORDER BY created_at DESC
                             LIMIT 20"
                        );
                        if (empty($logs)):
                            echo '<tr><td colspan="5" class="text-center text-muted">No errors found</td></tr>';
                        else:
                            foreach ($logs as $log):
                            ?>
                            <tr class="log-row log-<?= $log['type'] ?>">
                                <td><?= $log['created_at'] ?></td>
                                <td><?= $log['type'] ?></td>
                                <td><?= htmlspecialchars($log['source']) ?></td>
                                <td><?= htmlspecialchars(substr($log['message'], 0, 120)) ?></td>
                                <td><?= $log['is_resolved'] ? '✓' : '—' ?></td>
                            </tr>
                            <?php endforeach;
                        endif;
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btn-run-maintenance').addEventListener('click', function() {
    this.disabled = true;
    this.textContent = 'Running...';

    fetch('/src/ai-agent/AIAgent.php?maintenance=run&key=<?= addslashes(SECRET_KEY) ?>')
    .then(r => r.json())
    .then(data => {
        alert('Maintenance completed successfully!');
        location.reload();
    })
    .catch(err => {
        alert('Maintenance failed. Check logs for details.');
        this.disabled = false;
        this.textContent = 'Run AI Maintenance';
    });
});

// Check stream health
fetch('/api/admin.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'get_health_report' })
})
.then(r => r.json())
.then(data => {
    if (data.status === 'success' && data.report) {
        const report = data.report;
        const healthHtml = `
            <div class="health-grid">
                <div>Uptime Status: <strong class="status-${report.uptime_status}">${report.uptime_status}</strong></div>
                <div>PHP Version: <strong>${report.system.php_version}</strong></div>
                <div>Memory Limit: <strong>${report.system.memory_limit}</strong></div>
                <div>Cache: <strong>${report.cache.size_human}</strong> (${report.cache.count} files)</div>
                <div>Active Channels: <strong>${report.stats.active_channels}</strong></div>
                <div>Active Users: <strong>${report.stats.active_users}</strong></div>
                <div>Server Time: <strong>${report.server_time}</strong></div>
            </div>
        `;
        document.getElementById('stream-health').innerHTML = healthHtml;
    }
});
</script>

<?= AdminTemplate::renderFooter() ?>
