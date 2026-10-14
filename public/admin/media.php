<?php
/**
 * Shubh Labh CMS - Secure Media Library
 */

require_once dirname(__DIR__, 2) . '/config/Config.php';
require_once dirname(__DIR__, 2) . '/app/Logger.php';
require_once dirname(__DIR__, 2) . '/app/Database.php';
require_once dirname(__DIR__, 2) . '/app/Session.php';
require_once dirname(__DIR__, 2) . '/app/CSRF.php';
require_once dirname(__DIR__, 2) . '/app/AuditLogger.php';
require_once dirname(__DIR__, 2) . '/app/MediaService.php';
require_once __DIR__ . '/layout.php';

use ShubhLabh\App\CSRF;
use ShubhLabh\App\Database;
use ShubhLabh\App\MediaService;
use ShubhLabh\App\Session;
use function ShubhLabh\Admin\renderAdminHeader;
use function ShubhLabh\Admin\renderAdminFooter;

Session::requireAuth('/admin/login.php');

$searchQuery = trim($_GET['q'] ?? '');

// Handle POST actions: upload, update_alt, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate()) {
        Session::setFlash('error', 'Security validation failed (Invalid CSRF token). Please try again.');
        header('Location: /admin/media.php');
        exit;
    }

    $action = $_POST['form_action'] ?? '';

    // 1. Upload
    if ($action === 'upload') {
        if (!isset($_FILES['image']) || empty($_FILES['image']['name'])) {
            Session::setFlash('error', 'Please select an image file to upload.');
            header('Location: /admin/media.php');
            exit;
        }

        $altText = trim($_POST['alt_text'] ?? '');
        if (empty($altText)) {
            Session::setFlash('error', 'Alternative text (Alt text) is required for accessibility.');
            header('Location: /admin/media.php');
            exit;
        }

        try {
            $created = MediaService::upload($_FILES['image'], $altText);
            Session::setFlash('success', "Image '{$created['file_name']}' uploaded successfully.");
        } catch (\Throwable $e) {
            Session::setFlash('error', 'Upload failed: ' . $e->getMessage());
        }
        header('Location: /admin/media.php');
        exit;
    }

    // 2. Update Alt Text
    if ($action === 'update_alt') {
        $id = (int)($_POST['id'] ?? 0);
        $altText = trim($_POST['alt_text'] ?? '');

        if (!$id || empty($altText)) {
            Session::setFlash('error', 'Valid image ID and Alt text are required.');
            header('Location: /admin/media.php');
            exit;
        }

        try {
            MediaService::updateAltText($id, $altText);
            Session::setFlash('success', 'Alternative text updated.');
        } catch (\Throwable $e) {
            Session::setFlash('error', 'Update failed: ' . $e->getMessage());
        }
        header('Location: /admin/media.php');
        exit;
    }

    // 3. Delete
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            Session::setFlash('error', 'Invalid media ID.');
            header('Location: /admin/media.php');
            exit;
        }

        try {
            MediaService::delete($id);
            Session::setFlash('success', 'Image deleted permanently.');
        } catch (\Throwable $e) {
            Session::setFlash('error', $e->getMessage());
        }
        header('Location: /admin/media.php');
        exit;
    }
}

// Retrieve assets
$assets = MediaService::getAll($searchQuery);

renderAdminHeader('Media Library', 'media');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Media & Asset Library</h1>
        <p class="page-subtitle">Upload and manage product packshots, logos, and article photography.</p>
    </div>
</div>

<!-- Upload Box Card -->
<div class="card" style="border: 2px dashed var(--border-color); background: #fafaf9;">
    <h2 style="font-size: 1.15rem; font-family: 'Manrope', sans-serif; font-weight: 700; margin-bottom: 0.5rem; color: var(--forest-900);">
        📤 Upload New Media Asset
    </h2>
    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.25rem;">
        Supported formats: <strong>JPEG, PNG, WebP</strong> &bull; Max file size: <strong>5 MB</strong> &bull; Max dimensions: <strong>4096 &times; 4096 px</strong>.
    </p>

    <form method="POST" action="/admin/media.php" enctype="multipart/form-data">
        <?= CSRF::getInputField() ?>
        <input type="hidden" name="form_action" value="upload">

        <div class="grid-2">
            <div class="form-group">
                <label for="image" class="form-label">Select Image File *</label>
                <input type="file" id="image" name="image" class="form-input" required accept="image/jpeg,image/png,image/webp">
            </div>

            <div class="form-group">
                <label for="alt_text" class="form-label">Accessibility Alternative Text (Alt Text) *</label>
                <input type="text" id="alt_text" name="alt_text" class="form-input" required placeholder="Describe the image content concisely...">
            </div>
        </div>

        <div style="margin-top: 0.5rem;">
            <button type="submit" class="btn-primary">Upload to Media Library</button>
        </div>
    </form>
</div>

<!-- Search & Filter Bar -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap;">
    <form method="GET" action="/admin/media.php" style="display: flex; gap: 0.5rem; flex: 1; max-width: 450px;">
        <input type="text" name="q" class="form-input" placeholder="Search by filename or alt text..." value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="btn-secondary">Search</button>
        <?php if (!empty($searchQuery)): ?>
            <a href="/admin/media.php" class="btn-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <div style="font-size: 0.875rem; color: var(--text-muted);">
        Total Assets: <strong><?= count($assets) ?></strong>
    </div>
</div>

<!-- Image Grid -->
<?php if (empty($assets)): ?>
    <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
        <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📷</div>
        <h3 style="color: var(--forest-900); font-size: 1.15rem; margin-bottom: 0.5rem;">No Media Assets Found</h3>
        <p style="color: var(--text-muted); font-size: 0.875rem;">
            <?= !empty($searchQuery) ? 'No images matched your search criteria.' : 'Upload your first cattle feed packshot or educational image above.' ?>
        </p>
    </div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;">
        <?php foreach ($assets as $asset): 
            $sizeKb = round((int)$asset['file_size'] / 1024, 1);
            $sizeDisplay = $sizeKb > 1024 ? round($sizeKb / 1024, 2) . ' MB' : $sizeKb . ' KB';
            $dimensions = (!empty($asset['width']) && !empty($asset['height'])) ? "{$asset['width']} &times; {$asset['height']} px" : 'Unknown';
            $isUsed = !empty($asset['references']);
        ?>
            <div class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between;">
                <!-- Thumbnail Preview -->
                <div style="height: 180px; background: #f1f5f9; display: flex; justify-content: center; align-items: center; overflow: hidden; border-bottom: 1px solid var(--border-color); position: relative;">
                    <img 
                        src="<?= htmlspecialchars($asset['file_path'], ENT_QUOTES, 'UTF-8') ?>" 
                        alt="<?= htmlspecialchars($asset['alt_text'], ENT_QUOTES, 'UTF-8') ?>"
                        style="max-width: 100%; max-height: 100%; object-fit: contain;"
                        loading="lazy"
                    >
                    <span style="position: absolute; top: 0.5rem; right: 0.5rem; font-size: 0.6875rem; font-weight: 700; padding: 0.2rem 0.45rem; border-radius: 4px; text-transform: uppercase; <?= $isUsed ? 'background: #dbeafe; color: #1e40af;' : 'background: #f1f5f9; color: #64748b;' ?>">
                        <?= $isUsed ? 'In Use' : 'Unused' ?>
                    </span>
                </div>

                <!-- Info Body -->
                <div style="padding: 1rem; flex: 1;">
                    <strong style="font-size: 0.875rem; color: var(--text-dark); display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($asset['file_name'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($asset['file_name'], ENT_QUOTES, 'UTF-8') ?>
                    </strong>

                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; display: flex; justify-content: space-between;">
                        <span><?= $dimensions ?></span>
                        <span><?= $sizeDisplay ?></span>
                    </div>

                    <!-- Alt Text Editor -->
                    <form method="POST" action="/admin/media.php" style="margin-top: 0.75rem;">
                        <?= CSRF::getInputField() ?>
                        <input type="hidden" name="form_action" value="update_alt">
                        <input type="hidden" name="id" value="<?= (int)$asset['id'] ?>">
                        <label style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted);">Alt Text:</label>
                        <div style="display: flex; gap: 0.25rem; margin-top: 0.2rem;">
                            <input type="text" name="alt_text" class="form-input" style="padding: 0.35rem 0.5rem; font-size: 0.8rem;" value="<?= htmlspecialchars($asset['alt_text'], ENT_QUOTES, 'UTF-8') ?>" required>
                            <button type="submit" class="btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" title="Save Alt Text">💾</button>
                        </div>
                    </form>

                    <!-- Reference List if in use -->
                    <?php if ($isUsed): ?>
                        <div style="margin-top: 0.75rem; padding: 0.5rem; background: #f8fafc; border-radius: 4px; font-size: 0.75rem; border: 1px solid var(--border-color);">
                            <span style="font-weight: 600; color: var(--forest-900);">Referenced in:</span>
                            <ul style="padding-left: 1rem; margin-top: 0.25rem; color: var(--text-muted);">
                                <?php foreach ($asset['references'] as $ref): ?>
                                    <li>
                                        <a href="<?= htmlspecialchars($ref['url'], ENT_QUOTES, 'UTF-8') ?>" style="color: var(--brand-green); text-decoration: none;">
                                            <?= htmlspecialchars($ref['type'] . ': ' . $ref['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Action Footer -->
                <div style="padding: 0.75rem 1rem; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                    <button type="button" class="btn-secondary" style="padding: 0.3rem 0.6rem; font-size: 0.75rem;" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($asset['file_path'], ENT_QUOTES, 'UTF-8') ?>'); alert('Copied path: <?= htmlspecialchars($asset['file_path'], ENT_QUOTES, 'UTF-8') ?>');">
                        Copy Path 📋
                    </button>

                    <?php if ($isUsed): ?>
                        <button type="button" class="btn-secondary" style="padding: 0.3rem 0.6rem; font-size: 0.75rem; color: #94a3b8; cursor: not-allowed;" title="Cannot delete image while referenced by active content">
                            Locked 🔒
                        </button>
                    <?php else: ?>
                        <form method="POST" action="/admin/media.php" style="display: inline;" onsubmit="return confirm('Delete this image permanently from disk and database?');">
                            <?= CSRF::getInputField() ?>
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$asset['id'] ?>">
                            <button type="submit" style="background: none; border: none; color: #dc2626; font-size: 0.75rem; font-weight: 600; cursor: pointer;">
                                Delete 🗑️
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php renderAdminFooter(); ?>
