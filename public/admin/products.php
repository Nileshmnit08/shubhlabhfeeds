<?php
/**
 * Shubh Labh CMS - Product Catalog Management
 */

require_once dirname(__DIR__, 2) . '/config/Config.php';
require_once dirname(__DIR__, 2) . '/app/Logger.php';
require_once dirname(__DIR__, 2) . '/app/Database.php';
require_once dirname(__DIR__, 2) . '/app/Session.php';
require_once dirname(__DIR__, 2) . '/app/CSRF.php';
require_once dirname(__DIR__, 2) . '/app/Sanitizer.php';
require_once dirname(__DIR__, 2) . '/app/AuditLogger.php';
require_once dirname(__DIR__, 2) . '/app/Models/Product.php';
require_once __DIR__ . '/layout.php';

use ShubhLabh\App\AuditLogger;
use ShubhLabh\App\CSRF;
use ShubhLabh\App\Database;
use ShubhLabh\App\Sanitizer;
use ShubhLabh\App\Session;
use ShubhLabh\App\Models\Product;
use function ShubhLabh\Admin\renderAdminHeader;
use function ShubhLabh\Admin\renderAdminFooter;

Session::requireAuth('/admin/login.php');

$action = $_GET['action'] ?? 'list';
$productId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Handle POST actions: create, update, archive, publish
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate()) {
        Session::setFlash('error', 'Security validation failed (Invalid CSRF token). Please try again.');
        header('Location: /admin/products.php');
        exit;
    }

    $formAction = $_POST['form_action'] ?? 'save';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;

    try {
        $pdo = Database::getConnection();

        // 1. Archive action
        if ($formAction === 'archive' && $id) {
            $stmt = $pdo->prepare("UPDATE products SET status = 'archived', updated_at = NOW() WHERE id = :id");
            $stmt->execute(['id' => $id]);

            AuditLogger::log('ARCHIVE', 'products', $id);
            Session::setFlash('success', 'Product moved to archive. It is now hidden from the public website.');
            header('Location: /admin/products.php');
            exit;
        }

        // 2. Publish / Unpublish Toggle
        if ($formAction === 'set_status' && $id) {
            $newStatus = $_POST['status'] ?? 'draft';
            if (!in_array($newStatus, ['draft', 'published', 'archived'], true)) {
                $newStatus = 'draft';
            }

            $stmt = $pdo->prepare('UPDATE products SET status = :status, updated_at = NOW() WHERE id = :id');
            $stmt->execute(['status' => $newStatus, 'id' => $id]);

            AuditLogger::log(strtoupper($newStatus), 'products', $id);
            Session::setFlash('success', "Product status changed to '{$newStatus}'.");
            header('Location: /admin/products.php');
            exit;
        }

        // 3. Create or Edit Product
        $name = trim($_POST['name'] ?? '');
        $rawSlug = trim($_POST['slug'] ?? '');
        $slug = Sanitizer::slug(!empty($rawSlug) ? $rawSlug : $name);
        $categoryId = (int)($_POST['category_id'] ?? 1);
        $shortDesc = trim($_POST['short_description'] ?? '');
        $fullDesc = trim($_POST['full_description'] ?? '');
        $packaging = trim($_POST['packaging_desc'] ?? '');
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $status = $_POST['status'] ?? 'draft';

        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            $status = 'draft';
        }

        // Validation
        if (empty($name)) {
            Session::setFlash('error', 'Product name is required.');
            header('Location: /admin/products.php' . ($id ? "?action=edit&id={$id}" : '?action=create'));
            exit;
        }

        if (empty($shortDesc)) {
            Session::setFlash('error', 'Short description is required.');
            header('Location: /admin/products.php' . ($id ? "?action=edit&id={$id}" : '?action=create'));
            exit;
        }

        // Check category exists
        $catStmt = $pdo->prepare('SELECT id FROM product_categories WHERE id = :cid LIMIT 1');
        $catStmt->execute(['cid' => $categoryId]);
        if (!$catStmt->fetch()) {
            Session::setFlash('error', 'Selected category does not exist.');
            header('Location: /admin/products.php' . ($id ? "?action=edit&id={$id}" : '?action=create'));
            exit;
        }

        // Check slug uniqueness (excluding current product)
        $slugSql = 'SELECT id FROM products WHERE slug = :slug';
        $slugParams = ['slug' => $slug];
        if ($id) {
            $slugSql .= ' AND id != :id';
            $slugParams['id'] = $id;
        }
        $slugStmt = $pdo->prepare($slugSql . ' LIMIT 1');
        $slugStmt->execute($slugParams);
        if ($slugStmt->fetch()) {
            Session::setFlash('error', "The slug '{$slug}' is already in use by another product. Please choose a unique slug.");
            header('Location: /admin/products.php' . ($id ? "?action=edit&id={$id}" : '?action=create'));
            exit;
        }

        // Validate main_image_id if provided
        $mainImageId = !empty($_POST['main_image_id']) ? (int)$_POST['main_image_id'] : null;
        if ($mainImageId !== null) {
            $imgCheck = $pdo->prepare('SELECT id FROM media_assets WHERE id = :id LIMIT 1');
            $imgCheck->execute(['id' => $mainImageId]);
            if (!$imgCheck->fetch()) {
                $mainImageId = null;
            }
        }

        if ($id) {
            // Update existing
            $updateStmt = $pdo->prepare('
                UPDATE products 
                SET category_id = :cid, name = :name, slug = :slug,
                    short_description = :short_desc, full_description = :full_desc,
                    packaging_desc = :packaging, main_image_id = :img_id, display_order = :order,
                    status = :status, updated_at = NOW()
                WHERE id = :id
            ');
            $updateStmt->execute([
                'cid'        => $categoryId,
                'name'       => $name,
                'slug'       => $slug,
                'short_desc' => $shortDesc,
                'full_desc'  => !empty($fullDesc) ? $fullDesc : null,
                'packaging'  => !empty($packaging) ? $packaging : null,
                'img_id'     => $mainImageId,
                'order'      => $displayOrder,
                'status'     => $status,
                'id'         => $id,
            ]);

            AuditLogger::log('UPDATE', 'products', $id, ['name' => $name, 'status' => $status, 'main_image_id' => $mainImageId]);
            Session::setFlash('success', "Product '{$name}' updated successfully.");
        } else {
            // Create new
            $insertStmt = $pdo->prepare('
                INSERT INTO products (category_id, name, slug, short_description, full_description, packaging_desc, main_image_id, display_order, status, created_at)
                VALUES (:cid, :name, :slug, :short_desc, :full_desc, :packaging, :img_id, :order, :status, NOW())
            ');
            $insertStmt->execute([
                'cid'        => $categoryId,
                'name'       => $name,
                'slug'       => $slug,
                'short_desc' => $shortDesc,
                'full_desc'  => !empty($fullDesc) ? $fullDesc : null,
                'packaging'  => !empty($packaging) ? $packaging : null,
                'img_id'     => $mainImageId,
                'order'      => $displayOrder,
                'status'     => $status,
            ]);
            $newId = (int)$pdo->lastInsertId();

            AuditLogger::log('CREATE', 'products', $newId, ['name' => $name, 'status' => $status, 'main_image_id' => $mainImageId]);
            Session::setFlash('success', "New product '{$name}' created successfully.");
        }

        header('Location: /admin/products.php');
        exit;

    } catch (\Throwable $e) {
        Session::setFlash('error', 'Database operation failed: ' . $e->getMessage());
        header('Location: /admin/products.php');
        exit;
    }
}

// Fetch categories for form select
$categories = [];
try {
    $pdo = Database::getConnection();
    $catStmt = $pdo->query('SELECT id, name, slug FROM product_categories ORDER BY display_order ASC');
    $categories = $catStmt->fetchAll();
} catch (\Throwable) {
    $categories = [
        ['id' => 1, 'name' => 'Cattle Feed', 'slug' => 'cattle-feed'],
        ['id' => 2, 'name' => 'Nutrition Support', 'slug' => 'nutrition-support'],
    ];
}

// Fetch editing product if action is edit
$product = null;
if ($action === 'edit' && $productId) {
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $productId]);
        $product = $stmt->fetch();
    } catch (\Throwable) {
        $fallbacks = Product::getFallbackProducts();
        foreach ($fallbacks as $item) {
            if ($item['id'] === $productId) {
                $product = $item;
                $product['category_id'] = ($item['category_slug'] === 'cattle-feed') ? 1 : 2;
                break;
            }
        }
    }
}

// Fetch media assets for image selector
$mediaAssets = [];
try {
    $pdo = Database::getConnection();
    $mStmt = $pdo->query('SELECT id, file_name, file_path, alt_text FROM media_assets ORDER BY id DESC');
    $mediaAssets = $mStmt->fetchAll();
} catch (\Throwable) {
    $mediaAssets = [];
}

// Fetch all products for list view
$products = [];
try {
    $pdo = Database::getConnection();
    $stmt = $pdo->query('
        SELECT p.*, c.name AS category_name, m.file_path AS image_url
        FROM products p
        LEFT JOIN product_categories c ON p.category_id = c.id
        LEFT JOIN media_assets m ON p.main_image_id = m.id
        ORDER BY p.display_order ASC, p.id DESC
    ');
    $products = $stmt->fetchAll();
} catch (\Throwable) {
    $products = Product::getFallbackProducts();
}

renderAdminHeader('Products Catalog', 'products');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Products Management</h1>
        <p class="page-subtitle">Add, edit, publish, and archive cattle feeds and nutritional premixes.</p>
    </div>
    <?php if ($action === 'list'): ?>
        <a href="/admin/products.php?action=create" class="btn-primary">+ Add New Product</a>
    <?php else: ?>
        <a href="/admin/products.php" class="btn-secondary">← Back to Catalog</a>
    <?php endif; ?>
</div>

<?php if ($action === 'create' || ($action === 'edit' && $product)): ?>

    <div class="card">
        <h2 style="font-size: 1.25rem; font-family: 'Manrope', sans-serif; font-weight: 700; margin-bottom: 1.5rem; color: var(--forest-900);">
            <?= $action === 'create' ? 'Add New Product' : 'Edit Product: ' . htmlspecialchars($product['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </h2>

        <form method="POST" action="/admin/products.php">
            <?= CSRF::getInputField() ?>
            <input type="hidden" name="form_action" value="save">
            <?php if ($product): ?>
                <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
            <?php endif; ?>

            <div class="grid-2">
                <div class="form-group">
                    <label for="name" class="form-label">Product Name *</label>
                    <input type="text" id="name" name="name" class="form-input" required value="<?= htmlspecialchars($product['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. Shubh Labh Dairy Special">
                </div>

                <div class="form-group">
                    <label for="slug" class="form-label">URL Slug (leave blank to auto-generate)</label>
                    <input type="text" id="slug" name="slug" class="form-input" value="<?= htmlspecialchars($product['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. dairy-special">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="category_id" class="form-label">Category *</label>
                    <select id="category_id" name="category_id" class="form-select" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= (isset($product['category_id']) && $product['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status" class="form-label">Publication Status *</label>
                    <select id="status" name="status" class="form-select" required>
                        <option value="draft" <?= (!isset($product['status']) || $product['status'] === 'draft') ? 'selected' : '' ?>>Draft (Hidden from public)</option>
                        <option value="published" <?= (isset($product['status']) && $product['status'] === 'published') ? 'selected' : '' ?>>Published (Live on public website)</option>
                        <option value="archived" <?= (isset($product['status']) && $product['status'] === 'archived') ? 'selected' : '' ?>>Archived (Catalog history only)</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="short_description" class="form-label">Short Description (for product card grid) *</label>
                <textarea id="short_description" name="short_description" class="form-textarea" style="min-height: 80px;" required><?= htmlspecialchars($product['short_description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="form-group">
                <label for="full_description" class="form-label">Full Description / Nutritional Guidance (Optional)</label>
                <textarea id="full_description" name="full_description" class="form-textarea"><?= htmlspecialchars($product['full_description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                <p class="form-help">Leave blank if lab specifications have not yet been officially provided.</p>
            </div>

            <div class="form-group">
                <label for="main_image_id" class="form-label">Product Image (Select from Media Library)</label>
                <div style="display: flex; gap: 1rem; align-items: flex-start;">
                    <div style="flex: 1;">
                        <select id="main_image_id" name="main_image_id" class="form-select">
                            <option value="">-- No Image (Use clean neutral placeholder) --</option>
                            <?php foreach ($mediaAssets as $media): ?>
                                <option value="<?= (int)$media['id'] ?>" <?= (isset($product['main_image_id']) && (int)$product['main_image_id'] === (int)$media['id']) ? 'selected' : '' ?>>
                                    #<?= (int)$media['id'] ?>: <?= htmlspecialchars($media['file_name'], ENT_QUOTES, 'UTF-8') ?> <?= !empty($media['alt_text']) ? '— ' . htmlspecialchars($media['alt_text'], ENT_QUOTES, 'UTF-8') : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div style="margin-top: 0.375rem; display: flex; justify-content: space-between; align-items: center;">
                            <span class="form-help" style="margin: 0;">Select verified packaging artwork only. Do not assign unverified packshots.</span>
                            <a href="/admin/media.php" target="_blank" style="font-size: 0.8125rem; color: var(--forest-600); text-decoration: none; font-weight: 600;">Open Media Library ↗</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="packaging_desc" class="form-label">Packaging Options</label>
                    <input type="text" id="packaging_desc" name="packaging_desc" class="form-input" value="<?= htmlspecialchars($product['packaging_desc'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. Available in 25 kg & 50 kg bags">
                </div>

                <div class="form-group">
                    <label for="display_order" class="form-label">Display Order (lower numbers appear first)</label>
                    <input type="number" id="display_order" name="display_order" class="form-input" value="<?= (int)($product['display_order'] ?? 1) ?>" min="0">
                </div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" class="btn-primary" onclick="return confirm('Confirm saving product?');">Save Product</button>
                <a href="/admin/products.php" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

<?php else: ?>

    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Order</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Packaging</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">No products found in catalog.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td style="width: 56px;">
                                    <?php if (!empty($p['image_url'])): ?>
                                        <img src="<?= htmlspecialchars($p['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="" style="width: 44px; height: 44px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-color);">
                                    <?php else: ?>
                                        <div style="width: 44px; height: 44px; border-radius: 6px; background: var(--sand-100); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; color: var(--text-muted);" title="No image assigned">📦</div>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int)($p['display_order'] ?? 0) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($p['slug'], ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td><?= htmlspecialchars($p['category_name'] ?? 'Cattle Feed', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($p['packaging_desc'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="status-badge status-<?= htmlspecialchars($p['status'] ?? 'draft', ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars(ucfirst($p['status'] ?? 'draft'), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <a href="/admin/products.php?action=edit&id=<?= (int)$p['id'] ?>" class="btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.8125rem;">
                                            Edit ✏️
                                        </a>

                                        <?php if (($p['status'] ?? 'draft') !== 'archived'): ?>
                                            <form method="POST" action="/admin/products.php" style="display: inline;" onsubmit="return confirm('Archive this product? It will be hidden from the public website.');">
                                                <?= CSRF::getInputField() ?>
                                                <input type="hidden" name="form_action" value="archive">
                                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                                <button type="submit" class="btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.8125rem; color: #dc2626;">
                                                    Archive 📦
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<?php renderAdminFooter(); ?>
