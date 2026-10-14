<?php
/**
 * Shubh Labh CMS - Website Sections Editor
 */

require_once dirname(__DIR__, 2) . '/config/Config.php';
require_once dirname(__DIR__, 2) . '/app/Logger.php';
require_once dirname(__DIR__, 2) . '/app/Database.php';
require_once dirname(__DIR__, 2) . '/app/Session.php';
require_once dirname(__DIR__, 2) . '/app/CSRF.php';
require_once dirname(__DIR__, 2) . '/app/Sanitizer.php';
require_once dirname(__DIR__, 2) . '/app/AuditLogger.php';
require_once dirname(__DIR__, 2) . '/app/Models/SiteSection.php';
require_once __DIR__ . '/layout.php';

use ShubhLabh\App\AuditLogger;
use ShubhLabh\App\CSRF;
use ShubhLabh\App\Database;
use ShubhLabh\App\Sanitizer;
use ShubhLabh\App\Session;
use ShubhLabh\App\Models\SiteSection;
use function ShubhLabh\Admin\renderAdminHeader;
use function ShubhLabh\Admin\renderAdminFooter;

Session::requireAuth('/admin/login.php');

$editKey = $_GET['edit'] ?? null;
$action = $_GET['action'] ?? 'list';

// Handle POST save or publish toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate()) {
        Session::setFlash('error', 'Security validation failed (Invalid CSRF token). Please try again.');
        header('Location: /admin/sections.php' . ($editKey ? "?edit={$editKey}" : ''));
        exit;
    }

    $formAction = $_POST['form_action'] ?? 'save';
    $sectionKey = trim($_POST['section_key'] ?? '');

    // Validate section key is one of the approved sections
    $approvedSections = ['hero', 'trust_strip', 'about', 'dealer'];
    if (!in_array($sectionKey, $approvedSections, true)) {
        Session::setFlash('error', 'Invalid section identifier provided.');
        header('Location: /admin/sections.php');
        exit;
    }

    try {
        $pdo = Database::getConnection();

        if ($formAction === 'toggle_publish') {
            $newStatus = (int)($_POST['is_published'] ?? 1);
            $stmt = $pdo->prepare('UPDATE site_sections SET is_published = :status, updated_at = NOW() WHERE section_key = :key');
            $stmt->execute(['status' => $newStatus, 'key' => $sectionKey]);

            AuditLogger::log($newStatus ? 'PUBLISH' : 'UNPUBLISH', 'site_sections', null, ['section' => $sectionKey]);
            Session::setFlash('success', "Section '{$sectionKey}' publication status updated.");
            header('Location: /admin/sections.php');
            exit;
        }

        // Save content
        $title = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $eyebrow = trim($_POST['eyebrow'] ?? '');
        $leadText = trim($_POST['lead_text'] ?? '');
        $bodyText = trim($_POST['body_text'] ?? '');
        $primaryCtaText = trim($_POST['primary_cta_text'] ?? '');
        $primaryCtaUrl = Sanitizer::cleanUrl($_POST['primary_cta_url'] ?? null);
        $secondaryCtaText = trim($_POST['secondary_cta_text'] ?? '');
        $secondaryCtaUrl = Sanitizer::cleanUrl($_POST['secondary_cta_url'] ?? null);
        $isPublished = isset($_POST['is_published']) ? 1 : 0;

        if (empty($title)) {
            Session::setFlash('error', 'Section title is required.');
            header("Location: /admin/sections.php?edit={$sectionKey}");
            exit;
        }

        // Process additional JSON data if provided
        $additionalData = null;
        if (!empty($_POST['additional_data_json'])) {
            $decoded = json_decode($_POST['additional_data_json'], true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                Session::setFlash('error', 'Additional Data contains invalid JSON formatting.');
                header("Location: /admin/sections.php?edit={$sectionKey}");
                exit;
            }
            $additionalData = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        // Validate media_id if provided
        $mediaId = !empty($_POST['media_id']) ? (int)$_POST['media_id'] : null;
        if ($mediaId !== null) {
            $imgCheck = $pdo->prepare('SELECT id FROM media_assets WHERE id = :id LIMIT 1');
            $imgCheck->execute(['id' => $mediaId]);
            if (!$imgCheck->fetch()) {
                $mediaId = null;
            }
        }

        $stmt = $pdo->prepare('
            UPDATE site_sections 
            SET title = :title, subtitle = :subtitle, eyebrow = :eyebrow, lead_text = :lead_text,
                body_text = :body_text, primary_cta_text = :p_text, primary_cta_url = :p_url,
                secondary_cta_text = :s_text, secondary_cta_url = :s_url,
                media_id = :media_id,
                additional_data = :add_data, is_published = :published, updated_at = NOW()
            WHERE section_key = :key
        ');

        $stmt->execute([
            'title'      => $title,
            'subtitle'   => !empty($subtitle) ? $subtitle : null,
            'eyebrow'    => !empty($eyebrow) ? $eyebrow : null,
            'lead_text'  => !empty($leadText) ? $leadText : null,
            'body_text'  => !empty($bodyText) ? $bodyText : null,
            'p_text'     => !empty($primaryCtaText) ? $primaryCtaText : null,
            'p_url'      => $primaryCtaUrl,
            's_text'     => !empty($secondaryCtaText) ? $secondaryCtaText : null,
            's_url'      => $secondaryCtaUrl,
            'media_id'   => $mediaId,
            'add_data'   => $additionalData,
            'published'  => $isPublished,
            'key'        => $sectionKey,
        ]);

        AuditLogger::log('UPDATE', 'site_sections', null, ['section' => $sectionKey, 'is_published' => $isPublished, 'media_id' => $mediaId]);
        Session::setFlash('success', "Section '{$sectionKey}' updated successfully.");
        header('Location: /admin/sections.php');
        exit;

    } catch (\Throwable $e) {
        Session::setFlash('error', 'Failed to update section: Database operation failed.');
        header("Location: /admin/sections.php?edit={$sectionKey}");
        exit;
    }
}

// Fetch sections for display
$allSections = SiteSection::getAllPublished();
// If editing a specific section, find it
$currentSection = null;
if ($editKey) {
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM site_sections WHERE section_key = :key LIMIT 1');
        $stmt->execute(['key' => $editKey]);
        $currentSection = $stmt->fetch();
        if ($currentSection && !empty($currentSection['additional_data'])) {
            $currentSection['additional_data_formatted'] = json_encode(json_decode($currentSection['additional_data'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    } catch (\Throwable) {
        $fallbacks = SiteSection::getFallbackSections();
        $currentSection = $fallbacks[$editKey] ?? null;
        if ($currentSection && isset($currentSection['additional_data'])) {
            $currentSection['additional_data_formatted'] = json_encode($currentSection['additional_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
// Fetch media assets for section image selector
$mediaAssets = [];
try {
    $pdo = Database::getConnection();
    $mStmt = $pdo->query('SELECT id, file_name, file_path, alt_text FROM media_assets ORDER BY id DESC');
    $mediaAssets = $mStmt->fetchAll();
} catch (\Throwable) {
    $mediaAssets = [];
}

renderAdminHeader('Website Sections', 'sections');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Website Sections Editor</h1>
        <p class="page-subtitle">Manage hero messaging, about narrative, trust strip metrics, and dealer invitations.</p>
    </div>
    <?php if ($editKey): ?>
        <a href="/admin/sections.php" class="btn-secondary">← Back to All Sections</a>
    <?php endif; ?>
</div>

<?php if ($editKey && $currentSection): ?>
    <div class="card">
        <h2 style="font-size: 1.25rem; font-family: 'Manrope', sans-serif; font-weight: 700; margin-bottom: 1.5rem; color: var(--forest-900);">
            Editing Section: <?= htmlspecialchars(strtoupper($currentSection['section_key']), ENT_QUOTES, 'UTF-8') ?>
        </h2>

        <form method="POST" action="/admin/sections.php">
            <?= CSRF::getInputField() ?>
            <input type="hidden" name="form_action" value="save">
            <input type="hidden" name="section_key" value="<?= htmlspecialchars($currentSection['section_key'], ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">
                <label for="title" class="form-label">Headline / Section Title *</label>
                <input type="text" id="title" name="title" class="form-input" required value="<?= htmlspecialchars($currentSection['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="eyebrow" class="form-label">Eyebrow / Badge Text</label>
                    <input type="text" id="eyebrow" name="eyebrow" class="form-input" value="<?= htmlspecialchars($currentSection['eyebrow'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group">
                    <label for="subtitle" class="form-label">Subtitle / Supporting Tagline</label>
                    <input type="text" id="subtitle" name="subtitle" class="form-input" value="<?= htmlspecialchars($currentSection['subtitle'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <?php if (in_array($currentSection['section_key'], ['about', 'dealer'])): ?>
                <div class="form-group">
                    <label for="lead_text" class="form-label">Lead Paragraph</label>
                    <textarea id="lead_text" name="lead_text" class="form-textarea" style="min-height: 80px;"><?= htmlspecialchars($currentSection['lead_text'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="body_text" class="form-label">Main Body Narrative</label>
                    <textarea id="body_text" name="body_text" class="form-textarea"><?= htmlspecialchars($currentSection['body_text'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
            <?php endif; ?>

            <?php if (in_array($currentSection['section_key'], ['hero', 'dealer'])): ?>
                <div class="grid-2">
                    <div class="form-group">
                        <label for="primary_cta_text" class="form-label">Primary CTA Label</label>
                        <input type="text" id="primary_cta_text" name="primary_cta_text" class="form-input" value="<?= htmlspecialchars($currentSection['primary_cta_text'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label for="primary_cta_url" class="form-label">Primary CTA Destination (e.g. #products)</label>
                        <input type="text" id="primary_cta_url" name="primary_cta_url" class="form-input" value="<?= htmlspecialchars($currentSection['primary_cta_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label for="secondary_cta_text" class="form-label">Secondary CTA Label</label>
                        <input type="text" id="secondary_cta_text" name="secondary_cta_text" class="form-input" value="<?= htmlspecialchars($currentSection['secondary_cta_text'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label for="secondary_cta_url" class="form-label">Secondary CTA Destination</label>
                        <input type="text" id="secondary_cta_url" name="secondary_cta_url" class="form-input" value="<?= htmlspecialchars($currentSection['secondary_cta_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="media_id" class="form-label">Featured Section Image (Select from Media Library)</label>
                <select id="media_id" name="media_id" class="form-select">
                    <option value="">-- No Image (Use default layout) --</option>
                    <?php foreach ($mediaAssets as $media): ?>
                        <option value="<?= (int)$media['id'] ?>" <?= (isset($currentSection['media_id']) && (int)$currentSection['media_id'] === (int)$media['id']) ? 'selected' : '' ?>>
                            #<?= (int)$media['id'] ?>: <?= htmlspecialchars($media['file_name'], ENT_QUOTES, 'UTF-8') ?> <?= !empty($media['alt_text']) ? '— ' . htmlspecialchars($media['alt_text'], ENT_QUOTES, 'UTF-8') : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div style="margin-top: 0.375rem; display: flex; justify-content: space-between; align-items: center;">
                    <span class="form-help" style="margin: 0;">Optional graphic or banner image associated with this section.</span>
                    <a href="/admin/media.php" target="_blank" style="font-size: 0.8125rem; color: var(--forest-600); text-decoration: none; font-weight: 600;">Open Media Library ↗</a>
                </div>
            </div>

            <div class="form-group">
                <label for="additional_data_json" class="form-label">Structured Section Data (JSON)</label>
                <textarea id="additional_data_json" name="additional_data_json" class="form-textarea" style="font-family: monospace; font-size: 0.85rem;"><?= htmlspecialchars($currentSection['additional_data_formatted'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                <p class="form-help">JSON data for custom cards, pillars, or indicator texts.</p>
            </div>

            <div class="form-group" style="margin-top: 1.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600; cursor: pointer;">
                    <input type="checkbox" name="is_published" value="1" <?= (!isset($currentSection['is_published']) || $currentSection['is_published']) ? 'checked' : '' ?>>
                    <span>Publish section immediately to public website</span>
                </label>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" class="btn-primary" onclick="return confirm('Confirm saving changes to this section?');">Save Changes</button>
                <a href="/admin/sections.php" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

<?php else: ?>

    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Section Key</th>
                        <th>Title / Heading</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sectionDefinitions = [
                        'hero'        => 'Hero Banner & Kicker',
                        'trust_strip' => 'Core Trust Pillars',
                        'about'       => 'About Shubh Labh Story',
                        'dealer'      => 'Dealership Enquiry Pitch',
                    ];
                    foreach ($sectionDefinitions as $secKey => $secDesc): 
                        $secData = $allSections[$secKey] ?? null;
                    ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars(strtoupper($secKey), ENT_QUOTES, 'UTF-8') ?></strong>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($secDesc, ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td>
                                <?= htmlspecialchars($secData['title'] ?? 'Verified Baseline Copy', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <span class="status-badge status-published">Published</span>
                            </td>
                            <td>
                                <a href="/admin/sections.php?edit=<?= htmlspecialchars($secKey, ENT_QUOTES, 'UTF-8') ?>" class="btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.8125rem;">
                                    Edit Content ✏️
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<?php renderAdminFooter(); ?>
