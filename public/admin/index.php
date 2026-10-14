<?php
/**
 * Shubh Labh CMS - Admin Dashboard
 */

require_once dirname(__DIR__, 2) . '/config/Config.php';
require_once dirname(__DIR__, 2) . '/app/Logger.php';
require_once dirname(__DIR__, 2) . '/app/Database.php';
require_once dirname(__DIR__, 2) . '/app/Session.php';
require_once dirname(__DIR__, 2) . '/app/CSRF.php';
require_once dirname(__DIR__, 2) . '/app/AuditLogger.php';
require_once __DIR__ . '/layout.php';

use ShubhLabh\App\AuditLogger;
use ShubhLabh\App\Database;
use ShubhLabh\App\Session;
use function ShubhLabh\Admin\renderAdminHeader;
use function ShubhLabh\Admin\renderAdminFooter;

Session::requireAuth('/admin/login.php');

$dbAvailable = false;
$productCounts = ['total' => 0, 'published' => 0, 'draft' => 0, 'archived' => 0];
$articleCounts = ['total' => 0, 'published' => 0, 'draft' => 0];
$sectionCount = 0;
$recentAudits = [];

try {
    $pdo = Database::getConnection();
    $dbAvailable = true;

    // Fetch real metrics from DB
    $stmt = $pdo->query('
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = "published" THEN 1 ELSE 0 END) as published,
            SUM(CASE WHEN status = "draft" THEN 1 ELSE 0 END) as draft,
            SUM(CASE WHEN status = "archived" THEN 1 ELSE 0 END) as archived
        FROM products
    ');
    $pRow = $stmt->fetch();
    if ($pRow) {
        $productCounts = [
            'total'     => (int)$pRow['total'],
            'published' => (int)$pRow['published'],
            'draft'     => (int)$pRow['draft'],
            'archived'  => (int)$pRow['archived'],
        ];
    }

    $stmt = $pdo->query('
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = "published" THEN 1 ELSE 0 END) as published,
            SUM(CASE WHEN status = "draft" THEN 1 ELSE 0 END) as draft
        FROM knowledge_articles
    ');
    $aRow = $stmt->fetch();
    if ($aRow) {
        $articleCounts = [
            'total'     => (int)$aRow['total'],
            'published' => (int)$aRow['published'],
            'draft'     => (int)$aRow['draft'],
        ];
    }

    $stmt = $pdo->query('SELECT COUNT(*) FROM site_sections');
    $sectionCount = (int)$stmt->fetchColumn();

    $recentAudits = AuditLogger::getRecent(5);

} catch (\Throwable) {
    $dbAvailable = false;
}

renderAdminHeader('Admin Dashboard', 'dashboard');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Operational Dashboard</h1>
        <p class="page-subtitle">Real-time content inventory and administrative activity log.</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="/admin/products.php?action=create" class="btn-primary">+ Add Product</a>
        <a href="/admin/articles.php?action=create" class="btn-secondary">+ New Article</a>
    </div>
</div>

<?php if (!$dbAvailable): ?>
    <div class="card" style="border-left: 4px solid var(--gold-500); background: #fffbeb;">
        <h3 style="color: #92400e; font-size: 1.1rem; margin-bottom: 0.5rem;">Database Connection Notice</h3>
        <p style="color: #78350f; font-size: 0.875rem; line-height: 1.5;">
            The CMS is currently running in local verification mode. Live database metrics will populate once connected to a running MySQL instance and migrations are executed.
        </p>
    </div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.8125rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Published Feeds</div>
            <div style="font-size: 2rem; font-weight: 800; color: var(--brand-green); margin: 0.25rem 0;"><?= $productCounts['published'] ?></div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">
                <?= $productCounts['draft'] ?> draft &bull; <?= $productCounts['archived'] ?> archived
            </div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.8125rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Knowledge Articles</div>
            <div style="font-size: 2rem; font-weight: 800; color: var(--forest-900); margin: 0.25rem 0;"><?= $articleCounts['published'] ?></div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">
                <?= $articleCounts['draft'] ?> draft
            </div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.8125rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Website Sections</div>
            <div style="font-size: 2rem; font-weight: 800; color: var(--text-dark); margin: 0.25rem 0;"><?= $sectionCount ?></div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">Hero, About, Trust, Dealer</div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.8125rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">CRM Destination</div>
            <div style="font-size: 1.2rem; font-weight: 700; color: var(--forest-900); margin: 0.65rem 0 0.25rem;">Active &bull; Locked</div>
            <div style="font-size: 0.75rem; color: var(--text-muted); text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                <a href="https://shubhlabh-crm.vercel.app/" target="_blank" rel="noopener noreferrer" style="color: var(--brand-green); text-decoration: none;">https://shubhlabh-crm.vercel.app/ ↗</a>
            </div>
        </div>

    </div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h2 style="font-size: 1.15rem; font-family: 'Manrope', sans-serif; font-weight: 700; margin-bottom: 1rem; color: var(--forest-900);">
            Quick Content Shortcuts
        </h2>
        <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.75rem;">
            <li>
                <a href="/admin/sections.php" style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 6px; text-decoration: none; color: var(--text-dark); font-weight: 500;">
                    <span>📑 Edit Homepage Hero & Section Narratives</span>
                    <span style="color: var(--brand-green);">→</span>
                </a>
            </li>
            <li>
                <a href="/admin/products.php" style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 6px; text-decoration: none; color: var(--text-dark); font-weight: 500;">
                    <span>🌾 Manage Cattle Feed Catalog</span>
                    <span style="color: var(--brand-green);">→</span>
                </a>
            </li>
            <li>
                <a href="/admin/articles.php" style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 6px; text-decoration: none; color: var(--text-dark); font-weight: 500;">
                    <span>📖 Publish Farmer Education Articles</span>
                    <span style="color: var(--brand-green);">→</span>
                </a>
            </li>
            <li>
                <a href="/admin/settings.php" style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 6px; text-decoration: none; color: var(--text-dark); font-weight: 500;">
                    <span>⚙️ Update Contact & Social Settings</span>
                    <span style="color: var(--brand-green);">→</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="font-size: 1.15rem; font-family: 'Manrope', sans-serif; font-weight: 700; color: var(--forest-900);">
                Recent Audit Trail
            </h2>
            <a href="/admin/audit.php" style="font-size: 0.8125rem; color: var(--brand-green); text-decoration: none; font-weight: 600;">View Full Log →</a>
        </div>

        <?php if (empty($recentAudits)): ?>
            <p style="color: var(--text-muted); font-size: 0.875rem;">No recent administrative activity recorded yet.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <?php foreach ($recentAudits as $log): ?>
                    <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; font-size: 0.8125rem;">
                        <div style="display: flex; justify-content: space-between;">
                            <strong style="color: var(--forest-900);"><?= htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <span style="color: var(--text-muted);"><?= htmlspecialchars($log['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div style="color: var(--text-dark); margin-top: 0.2rem;">
                            <?= htmlspecialchars(ucfirst($log['entity_type']), ENT_QUOTES, 'UTF-8') ?> #<?= (int)$log['entity_id'] ?> by <?= htmlspecialchars($log['username'] ?? 'System', ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php renderAdminFooter(); ?>
