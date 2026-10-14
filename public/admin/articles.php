<?php
/**
 * Shubh Labh CMS - Knowledge & Nutrition Articles Management
 */

require_once dirname(__DIR__, 2) . '/config/Config.php';
require_once dirname(__DIR__, 2) . '/app/Logger.php';
require_once dirname(__DIR__, 2) . '/app/Database.php';
require_once dirname(__DIR__, 2) . '/app/Session.php';
require_once dirname(__DIR__, 2) . '/app/CSRF.php';
require_once dirname(__DIR__, 2) . '/app/Sanitizer.php';
require_once dirname(__DIR__, 2) . '/app/AuditLogger.php';
require_once dirname(__DIR__, 2) . '/app/Models/KnowledgeArticle.php';
require_once __DIR__ . '/layout.php';

use ShubhLabh\App\AuditLogger;
use ShubhLabh\App\CSRF;
use ShubhLabh\App\Database;
use ShubhLabh\App\Sanitizer;
use ShubhLabh\App\Session;
use ShubhLabh\App\Models\KnowledgeArticle;
use function ShubhLabh\Admin\renderAdminHeader;
use function ShubhLabh\Admin\renderAdminFooter;

Session::requireAuth('/admin/login.php');

$action = $_GET['action'] ?? 'list';
$articleId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Handle POST actions: create, update, archive
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate()) {
        Session::setFlash('error', 'Security validation failed (Invalid CSRF token). Please try again.');
        header('Location: /admin/articles.php');
        exit;
    }

    $formAction = $_POST['form_action'] ?? 'save';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;

    try {
        $pdo = Database::getConnection();

        // 1. Archive action
        if ($formAction === 'archive' && $id) {
            $stmt = $pdo->prepare("UPDATE knowledge_articles SET status = 'archived', updated_at = NOW() WHERE id = :id");
            $stmt->execute(['id' => $id]);

            AuditLogger::log('ARCHIVE', 'knowledge_articles', $id);
            Session::setFlash('success', 'Article archived and hidden from the public website.');
            header('Location: /admin/articles.php');
            exit;
        }

        // 2. Create or Edit Article
        $title = trim($_POST['title'] ?? '');
        $rawSlug = trim($_POST['slug'] ?? '');
        $slug = Sanitizer::slug(!empty($rawSlug) ? $rawSlug : $title);
        $category = trim($_POST['category'] ?? 'Dairy Nutrition');
        $summary = trim($_POST['summary'] ?? '');
        $rawBody = $_POST['content_body'] ?? '';
        $cleanBody = Sanitizer::sanitizeHtml($rawBody);
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $status = $_POST['status'] ?? 'draft';
        $publishDate = !empty($_POST['published_at']) ? trim($_POST['published_at']) : null;

        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            $status = 'draft';
        }

        // Validate required fields
        if (empty($title)) {
            Session::setFlash('error', 'Article title is required.');
            header('Location: /admin/articles.php' . ($id ? "?action=edit&id={$id}" : '?action=create'));
            exit;
        }

        if (empty($summary)) {
            Session::setFlash('error', 'Article summary is required.');
            header('Location: /admin/articles.php' . ($id ? "?action=edit&id={$id}" : '?action=create'));
            exit;
        }

        if (empty($cleanBody)) {
            Session::setFlash('error', 'Article content body cannot be empty.');
            header('Location: /admin/articles.php' . ($id ? "?action=edit&id={$id}" : '?action=create'));
            exit;
        }

        // Check slug uniqueness
        $slugSql = 'SELECT id FROM knowledge_articles WHERE slug = :slug';
        $slugParams = ['slug' => $slug];
        if ($id) {
            $slugSql .= ' AND id != :id';
            $slugParams['id'] = $id;
        }
        $slugStmt = $pdo->prepare($slugSql . ' LIMIT 1');
        $slugStmt->execute($slugParams);
        if ($slugStmt->fetch()) {
            Session::setFlash('error', "The slug '{$slug}' is already in use by another article.");
            header('Location: /admin/articles.php' . ($id ? "?action=edit&id={$id}" : '?action=create'));
            exit;
        }

        // Validate cover_image_id if provided
        $coverImageId = !empty($_POST['cover_image_id']) ? (int)$_POST['cover_image_id'] : null;
        if ($coverImageId !== null) {
            $imgCheck = $pdo->prepare('SELECT id FROM media_assets WHERE id = :id LIMIT 1');
            $imgCheck->execute(['id' => $coverImageId]);
            if (!$imgCheck->fetch()) {
                $coverImageId = null;
            }
        }

        if ($id) {
            // Update
            $updateStmt = $pdo->prepare('
                UPDATE knowledge_articles 
                SET title = :title, slug = :slug, category = :category,
                    summary = :summary, content_body = :body, cover_image_id = :cover_id,
                    display_order = :order, status = :status, published_at = :pub_at, updated_at = NOW()
                WHERE id = :id
            ');
            $updateStmt->execute([
                'title'    => $title,
                'slug'     => $slug,
                'category' => $category,
                'summary'  => $summary,
                'body'     => $cleanBody,
                'cover_id' => $coverImageId,
                'order'    => $displayOrder,
                'status'   => $status,
                'pub_at'   => $publishDate,
                'id'       => $id,
            ]);

            AuditLogger::log('UPDATE', 'knowledge_articles', $id, ['title' => $title, 'status' => $status, 'cover_image_id' => $coverImageId]);
            Session::setFlash('success', "Article '{$title}' updated successfully.");
        } else {
            // Insert
            $insertStmt = $pdo->prepare('
                INSERT INTO knowledge_articles (title, slug, category, summary, content_body, cover_image_id, display_order, status, published_at, created_at)
                VALUES (:title, :slug, :category, :summary, :body, :cover_id, :order, :status, :pub_at, NOW())
            ');
            $insertStmt->execute([
                'title'    => $title,
                'slug'     => $slug,
                'category' => $category,
                'summary'  => $summary,
                'body'     => $cleanBody,
                'cover_id' => $coverImageId,
                'order'    => $displayOrder,
                'status'   => $status,
                'pub_at'   => $publishDate ?: date('Y-m-d H:i:s'),
            ]);
            $newId = (int)$pdo->lastInsertId();

            AuditLogger::log('CREATE', 'knowledge_articles', $newId, ['title' => $title, 'status' => $status, 'cover_image_id' => $coverImageId]);
            Session::setFlash('success', "Article '{$title}' created successfully.");
        }

        header('Location: /admin/articles.php');
        exit;

    } catch (\Throwable $e) {
        Session::setFlash('error', 'Database operation failed: ' . $e->getMessage());
        header('Location: /admin/articles.php');
        exit;
    }
}

// Fetch article for edit view
$article = null;
if ($action === 'edit' && $articleId) {
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM knowledge_articles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $articleId]);
        $article = $stmt->fetch();
    } catch (\Throwable) {
        $fallbacks = KnowledgeArticle::getFallbackArticles();
        foreach ($fallbacks as $item) {
            if ($item['id'] === $articleId) {
                $article = $item;
                break;
            }
        }
    }
}

// Fetch media assets for cover image selector
$mediaAssets = [];
try {
    $pdo = Database::getConnection();
    $mStmt = $pdo->query('SELECT id, file_name, file_path, alt_text FROM media_assets ORDER BY id DESC');
    $mediaAssets = $mStmt->fetchAll();
} catch (\Throwable) {
    $mediaAssets = [];
}

// Fetch all articles for list view
$articles = [];
try {
    $pdo = Database::getConnection();
    $stmt = $pdo->query('
        SELECT a.*, m.file_path AS cover_image_url
        FROM knowledge_articles a
        LEFT JOIN media_assets m ON a.cover_image_id = m.id
        ORDER BY a.display_order ASC, a.id DESC
    ');
    $articles = $stmt->fetchAll();
} catch (\Throwable) {
    $articles = KnowledgeArticle::getFallbackArticles();
}

renderAdminHeader('Knowledge Hub Articles', 'articles');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Nutrition & Knowledge Hub</h1>
        <p class="page-subtitle">Publish educational feeding guides, dairy management articles, and transition protocols.</p>
    </div>
    <?php if ($action === 'list'): ?>
        <a href="/admin/articles.php?action=create" class="btn-primary">+ Add New Article</a>
    <?php else: ?>
        <a href="/admin/articles.php" class="btn-secondary">← Back to Articles</a>
    <?php endif; ?>
</div>

<?php if ($action === 'create' || ($action === 'edit' && $article)): ?>

    <div class="card">
        <h2 style="font-size: 1.25rem; font-family: 'Manrope', sans-serif; font-weight: 700; margin-bottom: 1.5rem; color: var(--forest-900);">
            <?= $action === 'create' ? 'Create Knowledge Article' : 'Edit Article: ' . htmlspecialchars($article['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </h2>

        <form method="POST" action="/admin/articles.php">
            <?= CSRF::getInputField() ?>
            <input type="hidden" name="form_action" value="save">
            <?php if ($article): ?>
                <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="title" class="form-label">Article Title *</label>
                <input type="text" id="title" name="title" class="form-input" required value="<?= htmlspecialchars($article['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. Essential Feeding Practices for Peak Milk Production">
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="slug" class="form-label">URL Slug (leave blank to auto-generate)</label>
                    <input type="text" id="slug" name="slug" class="form-input" value="<?= htmlspecialchars($article['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="form-group">
                    <label for="category" class="form-label">Category</label>
                    <input type="text" id="category" name="category" class="form-input" value="<?= htmlspecialchars($article['category'] ?? 'Dairy Nutrition', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="summary" class="form-label">Article Summary / Card Excerpt *</label>
                <textarea id="summary" name="summary" class="form-textarea" style="min-height: 80px;" required><?= htmlspecialchars($article['summary'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="form-group">
                <label for="content_body" class="form-label">Article Content (Safe formatted HTML allowed: &lt;p&gt;, &lt;strong&gt;, &lt;ul&gt;, &lt;li&gt;, &lt;h2&gt;) *</label>
                <textarea id="content_body" name="content_body" class="form-textarea" style="min-height: 220px;" required><?= htmlspecialchars($article['content_body'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                <p class="form-help">All script tags and dangerous event handlers are automatically scrubbed on save.</p>
            </div>

            <div class="form-group">
                <label for="cover_image_id" class="form-label">Cover Image (Select from Media Library)</label>
                <select id="cover_image_id" name="cover_image_id" class="form-select">
                    <option value="">-- No Cover Image (Use clean neutral placeholder) --</option>
                    <?php foreach ($mediaAssets as $media): ?>
                        <option value="<?= (int)$media['id'] ?>" <?= (isset($article['cover_image_id']) && (int)$article['cover_image_id'] === (int)$media['id']) ? 'selected' : '' ?>>
                            #<?= (int)$media['id'] ?>: <?= htmlspecialchars($media['file_name'], ENT_QUOTES, 'UTF-8') ?> <?= !empty($media['alt_text']) ? '— ' . htmlspecialchars($media['alt_text'], ENT_QUOTES, 'UTF-8') : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div style="margin-top: 0.375rem; display: flex; justify-content: space-between; align-items: center;">
                    <span class="form-help" style="margin: 0;">Optional educational header image. Verified farming and cattle care images only.</span>
                    <a href="/admin/media.php" target="_blank" style="font-size: 0.8125rem; color: var(--forest-600); text-decoration: none; font-weight: 600;">Open Media Library ↗</a>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="status" class="form-label">Status *</label>
                    <select id="status" name="status" class="form-select" required>
                        <option value="draft" <?= (!isset($article['status']) || $article['status'] === 'draft') ? 'selected' : '' ?>>Draft</option>
                        <option value="published" <?= (isset($article['status']) && $article['status'] === 'published') ? 'selected' : '' ?>>Published</option>
                        <option value="archived" <?= (isset($article['status']) && $article['status'] === 'archived') ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="published_at" class="form-label">Publication Date & Time (Schedule future publishing)</label>
                    <input type="datetime-local" id="published_at" name="published_at" class="form-input" value="<?= !empty($article['published_at']) ? date('Y-m-d\TH:i', strtotime($article['published_at'])) : '' ?>">
                </div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" class="btn-primary" onclick="return confirm('Confirm saving article?');">Save Article</button>
                <a href="/admin/articles.php" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

<?php else: ?>

    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Cover</th>
                        <th>Title & Slug</th>
                        <th>Category</th>
                        <th>Published Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($articles)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">No knowledge articles found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($articles as $art): ?>
                            <tr>
                                <td style="width: 56px;">
                                    <?php if (!empty($art['cover_image_url'])): ?>
                                        <img src="<?= htmlspecialchars($art['cover_image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="" style="width: 44px; height: 44px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-color);">
                                    <?php else: ?>
                                        <div style="width: 44px; height: 44px; border-radius: 6px; background: var(--sand-100); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; color: var(--text-muted);" title="No cover image assigned">📖</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($art['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($art['slug'], ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td><?= htmlspecialchars($art['category'] ?? 'Dairy Care', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= !empty($art['published_at']) ? htmlspecialchars(date('M j, Y H:i', strtotime($art['published_at'])), ENT_QUOTES, 'UTF-8') : 'Immediate' ?></td>
                                <td>
                                    <span class="status-badge status-<?= htmlspecialchars($art['status'] ?? 'draft', ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars(ucfirst($art['status'] ?? 'draft'), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <a href="/admin/articles.php?action=edit&id=<?= (int)$art['id'] ?>" class="btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.8125rem;">
                                            Edit ✏️
                                        </a>

                                        <?php if (($art['status'] ?? 'draft') !== 'archived'): ?>
                                            <form method="POST" action="/admin/articles.php" style="display: inline;" onsubmit="return confirm('Archive this article? It will no longer be visible to visitors.');">
                                                <?= CSRF::getInputField() ?>
                                                <input type="hidden" name="form_action" value="archive">
                                                <input type="hidden" name="id" value="<?= (int)$art['id'] ?>">
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
