<?php
/**
 * Shubh Labh CMS - Audit History Log Viewer
 * Read-only administrative activity stream.
 */

require_once dirname(__DIR__, 2) . '/config/Config.php';
require_once dirname(__DIR__, 2) . '/app/Logger.php';
require_once dirname(__DIR__, 2) . '/app/Database.php';
require_once dirname(__DIR__, 2) . '/app/Session.php';
require_once dirname(__DIR__, 2) . '/app/AuditLogger.php';
require_once __DIR__ . '/layout.php';

use ShubhLabh\App\AuditLogger;
use ShubhLabh\App\Session;
use function ShubhLabh\Admin\renderAdminHeader;
use function ShubhLabh\Admin\renderAdminFooter;

Session::requireAuth('/admin/login.php');

$logs = AuditLogger::getRecent(100);

renderAdminHeader('Audit History', 'audit');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Administrative Audit Trail</h1>
        <p class="page-subtitle">Read-only immutable record of content mutations, publishing events, and system changes.</p>
    </div>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Action</th>
                    <th>Entity Type</th>
                    <th>Record ID</th>
                    <th>Operator</th>
                    <th>IP Address</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 3rem;">
                            No audit activity recorded yet. Events will appear as administrators make changes.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($log['created_at'], ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td>
                                <strong style="color: var(--forest-900);"><?= htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8') ?></strong>
                            </td>
                            <td><span style="font-size: 0.8125rem; text-transform: uppercase;"><?= htmlspecialchars($log['entity_type'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= $log['entity_id'] ? '#' . (int)$log['entity_id'] : '—' ?></td>
                            <td><?= htmlspecialchars($log['username'] ?? 'System', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><code><?= htmlspecialchars($log['ip_address'], ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td>
                                <?php if (!empty($log['details'])): ?>
                                    <pre style="font-size: 0.75rem; background: #f8fafc; padding: 0.35rem 0.5rem; border-radius: 4px; max-width: 300px; overflow-x: auto;"><?= htmlspecialchars(json_encode($log['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderAdminFooter(); ?>
