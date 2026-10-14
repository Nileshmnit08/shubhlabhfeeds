<?php
/**
 * Shubh Labh CMS - Navigation Menu Management
 */

require_once dirname(__DIR__, 2) . '/config/Config.php';
require_once dirname(__DIR__, 2) . '/app/Logger.php';
require_once dirname(__DIR__, 2) . '/app/Database.php';
require_once dirname(__DIR__, 2) . '/app/Session.php';
require_once dirname(__DIR__, 2) . '/app/CSRF.php';
require_once dirname(__DIR__, 2) . '/app/Sanitizer.php';
require_once dirname(__DIR__, 2) . '/app/AuditLogger.php';
require_once dirname(__DIR__, 2) . '/app/Models/NavigationItem.php';
require_once __DIR__ . '/layout.php';

use ShubhLabh\App\AuditLogger;
use ShubhLabh\App\CSRF;
use ShubhLabh\App\Database;
use ShubhLabh\App\Sanitizer;
use ShubhLabh\App\Session;
use ShubhLabh\App\Models\NavigationItem;
use function ShubhLabh\Admin\renderAdminHeader;
use function ShubhLabh\Admin\renderAdminFooter;

Session::requireAuth('/admin/login.php');

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;

// Handle POST actions: save, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate()) {
        Session::setFlash('error', 'Security validation failed (Invalid CSRF token).');
        header('Location: /admin/navigation.php');
        exit;
    }

    $formAction = $_POST['form_action'] ?? 'save';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;

    try {
        $pdo = Database::getConnection();

        if ($formAction === 'delete' && $id) {
            $stmt = $pdo->prepare('DELETE FROM navigation_items WHERE id = :id');
            $stmt->execute(['id' => $id]);
            AuditLogger::log('DELETE', 'navigation_items', $id);
            Session::setFlash('success', 'Navigation item deleted.');
            header('Location: /admin/navigation.php');
            exit;
        }

        // Save (insert or update)
        $label = trim($_POST['label'] ?? '');
        $rawUrl = trim($_POST['url'] ?? '');
        $cleanUrl = Sanitizer::cleanUrl($rawUrl);
        $location = in_array($_POST['location'] ?? '', ['header', 'footer'], true) ? $_POST['location'] : 'header';
        $target = in_array($_POST['target'] ?? '', ['_self', '_blank'], true) ? $_POST['target'] : '_self';
        $order = (int)($_POST['display_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($label)) {
            Session::setFlash('error', 'Navigation label is required.');
            header('Location: /admin/navigation.php');
            exit;
        }

        if (empty($cleanUrl)) {
            Session::setFlash('error', 'Invalid URL destination. Only valid URLs or internal anchors (#...) are accepted.');
            header('Location: /admin/navigation.php');
            exit;
        }

        if ($id) {
            $stmt = $pdo->prepare('
                UPDATE navigation_items 
                SET location = :loc, label = :label, url = :url, target = :target, display_order = :order, is_active = :active
                WHERE id = :id
            ');
            $stmt->execute([
                'loc'    => $location,
                'label'  => $label,
                'url'    => $cleanUrl,
                'target' => $target,
                'order'  => $order,
                'active' => $isActive,
                'id'     => $id,
            ]);
            AuditLogger::log('UPDATE', 'navigation_items', $id, ['label' => $label, 'url' => $cleanUrl]);
            Session::setFlash('success', "Navigation item '{$label}' updated.");
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO navigation_items (location, label, url, target, display_order, is_active)
                VALUES (:loc, :label, :url, :target, :order, :active)
            ');
            $stmt->execute([
                'loc'    => $location,
                'label'  => $label,
                'url'    => $cleanUrl,
                'target' => $target,
                'order'  => $order,
                'active' => $isActive,
            ]);
            $newId = (int)$pdo->lastInsertId();
            AuditLogger::log('CREATE', 'navigation_items', $newId, ['label' => $label, 'url' => $cleanUrl]);
            Session::setFlash('success', "Navigation item '{$label}' created.");
        }

        header('Location: /admin/navigation.php');
        exit;

    } catch (\Throwable $e) {
        Session::setFlash('error', 'Database operation failed: ' . $e->getMessage());
        header('Location: /admin/navigation.php');
        exit;
    }
}

// Fetch item being edited if requested
$editItem = null;
if ($editId) {
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM navigation_items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $editId]);
        $editItem = $stmt->fetch();
    } catch (\Throwable) {
        $editItem = null;
    }
}

// Fetch all navigation items
$navItems = [];
try {
    $pdo = Database::getConnection();
    $stmt = $pdo->query('SELECT * FROM navigation_items ORDER BY location ASC, display_order ASC');
    $navItems = $stmt->fetchAll();
} catch (\Throwable) {
    $navItems = NavigationItem::getFallbackNavItems('header');
}

renderAdminHeader('Navigation Menus', 'navigation');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Navigation Menu Management</h1>
        <p class="page-subtitle">Configure header menu links and footer navigation items.</p>
    </div>
</div>

<div class="grid-2">
    <!-- Form Card -->
    <div class="card">
        <h2 style="font-size: 1.15rem; font-family: 'Manrope', sans-serif; font-weight: 700; margin-bottom: 1.25rem; color: var(--forest-900);">
            <?= $editItem ? 'Edit Navigation Link' : 'Add New Navigation Link' ?>
        </h2>

        <form method="POST" action="/admin/navigation.php">
            <?= CSRF::getInputField() ?>
            <input type="hidden" name="form_action" value="save">
            <?php if ($editItem): ?>
                <input type="hidden" name="id" value="<?= (int)$editItem['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="label" class="form-label">Link Label *</label>
                <input type="text" id="label" name="label" class="form-input" required value="<?= htmlspecialchars($editItem['label'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. Products">
            </div>

            <div class="form-group">
                <label for="url" class="form-label">URL / Anchor Target *</label>
                <input type="text" id="url" name="url" class="form-input" required value="<?= htmlspecialchars($editItem['url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. #products or https://...">
                <p class="form-help">Safe anchor tags (e.g. #about) or valid HTTP/HTTPS URLs.</p>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="location" class="form-label">Menu Location</label>
                    <select id="location" name="location" class="form-select">
                        <option value="header" <?= (!isset($editItem['location']) || $editItem['location'] === 'header') ? 'selected' : '' ?>>Header Navigation</option>
                        <option value="footer" <?= (isset($editItem['location']) && $editItem['location'] === 'footer') ? 'selected' : '' ?>>Footer Links</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="target" class="form-label">Window Target</label>
                    <select id="target" name="target" class="form-select">
                        <option value="_self" <?= (!isset($editItem['target']) || $editItem['target'] === '_self') ? 'selected' : '' ?>>Same Tab (_self)</option>
                        <option value="_blank" <?= (isset($editItem['target']) && $editItem['target'] === '_blank') ? 'selected' : '' ?>>New Tab (_blank)</option>
                    </select>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="display_order" class="form-label">Display Order</label>
                    <input type="number" id="display_order" name="display_order" class="form-input" value="<?= (int)($editItem['display_order'] ?? 1) ?>" min="0">
                </div>

                <div class="form-group" style="display: flex; align-items: center; margin-top: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600; cursor: pointer;">
                        <input type="checkbox" name="is_active" value="1" <?= (!isset($editItem['is_active']) || $editItem['is_active']) ? 'checked' : '' ?>>
                        <span>Active Link</span>
                    </label>
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="submit" class="btn-primary"><?= $editItem ? 'Save Changes' : 'Add Link' ?></button>
                <?php if ($editItem): ?>
                    <a href="/admin/navigation.php" class="btn-secondary">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Table Card -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Label</th>
                        <th>Destination</th>
                        <th>Location</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($navItems)): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">No navigation links configured.</td></tr>
                    <?php else: ?>
                        <?php foreach ($navItems as $item): ?>
                            <tr>
                                <td><?= (int)($item['display_order'] ?? 0) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                </td>
                                <td><code><?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td><span style="font-size: 0.75rem; text-transform: uppercase;"><?= htmlspecialchars($item['location'] ?? 'header', ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <a href="/admin/navigation.php?edit=<?= (int)$item['id'] ?>" class="btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                            Edit
                                        </a>
                                        <form method="POST" action="/admin/navigation.php" style="display: inline;" onsubmit="return confirm('Delete this navigation link?');">
                                            <?= CSRF::getInputField() ?>
                                            <input type="hidden" name="form_action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                            <button type="submit" style="background: none; border: none; color: #dc2626; cursor: pointer; font-size: 0.75rem; padding: 0.25rem;">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php renderAdminFooter(); ?>
