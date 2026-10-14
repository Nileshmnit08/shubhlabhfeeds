<?php
/**
 * Shubh Labh CMS - Site Settings & Contact Management
 */

require_once dirname(__DIR__, 2) . '/config/Config.php';
require_once dirname(__DIR__, 2) . '/app/Logger.php';
require_once dirname(__DIR__, 2) . '/app/Database.php';
require_once dirname(__DIR__, 2) . '/app/Session.php';
require_once dirname(__DIR__, 2) . '/app/CSRF.php';
require_once dirname(__DIR__, 2) . '/app/AuditLogger.php';
require_once dirname(__DIR__, 2) . '/app/Models/SiteSetting.php';
require_once __DIR__ . '/layout.php';

use ShubhLabh\App\AuditLogger;
use ShubhLabh\App\CSRF;
use ShubhLabh\App\Database;
use ShubhLabh\App\Session;
use ShubhLabh\App\Models\SiteSetting;
use function ShubhLabh\Admin\renderAdminHeader;
use function ShubhLabh\Admin\renderAdminFooter;

Session::requireAuth('/admin/login.php');

// Handle POST save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate()) {
        Session::setFlash('error', 'Security validation failed (Invalid CSRF token).');
        header('Location: /admin/settings.php');
        exit;
    }

    // STRICT SERVER-SIDE IMMUTABILITY CHECK FOR CRM LOGIN URL
    if (isset($_POST['crm_login_url']) && $_POST['crm_login_url'] !== 'https://shubhlabh-crm.vercel.app/') {
        Session::setFlash('error', 'Security Violation: The CRM Login URL is system-locked and cannot be modified.');
        AuditLogger::log('VIOLATION_ATTEMPT', 'site_settings', null, ['attempted_crm_url' => $_POST['crm_login_url']]);
        header('Location: /admin/settings.php');
        exit;
    }

    // Whitelist approved editable settings
    $editableKeys = [
        'brand_name',
        'brand_tagline',
        'announcement_text',
        'announcement_active',
        'phone_number',
        'whatsapp_number',
        'public_email',
        'postal_address',
        'meta_title',
        'meta_description',
        'footer_text',
    ];

    try {
        $pdo = Database::getConnection();

        $updatedCount = 0;
        foreach ($editableKeys as $key) {
            if (isset($_POST[$key])) {
                $val = trim($_POST[$key]);

                // Handle email validation
                if ($key === 'public_email' && !empty($val) && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                    Session::setFlash('error', "Invalid email address format: '{$val}'.");
                    header('Location: /admin/settings.php');
                    exit;
                }

                // If empty, store as NULL
                $dbVal = !empty($val) ? $val : null;

                $stmt = $pdo->prepare('
                    UPDATE site_settings 
                    SET setting_value = :val, updated_at = NOW() 
                    WHERE setting_key = :key AND is_system_locked = 0
                ');
                $stmt->execute(['val' => $dbVal, 'key' => $key]);
                $updatedCount++;
            }
        }

        AuditLogger::log('SETTINGS_CHANGE', 'site_settings', null, ['fields_updated' => $updatedCount]);
        Session::setFlash('success', 'Site settings updated successfully.');
        header('Location: /admin/settings.php');
        exit;

    } catch (\Throwable $e) {
        Session::setFlash('error', 'Database operation failed: ' . $e->getMessage());
        header('Location: /admin/settings.php');
        exit;
    }
}

// Fetch current settings
$settings = SiteSetting::getAllPublic();

renderAdminHeader('Site Settings', 'settings');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Site Settings & Contact Channels</h1>
        <p class="page-subtitle">Manage company branding, public communication channels, and global SEO metadata.</p>
    </div>
</div>

<form method="POST" action="/admin/settings.php">
    <?= CSRF::getInputField() ?>

    <div class="grid-2">
        <!-- Brand & Announcement -->
        <div class="card">
            <h2 style="font-size: 1.15rem; font-family: 'Manrope', sans-serif; font-weight: 700; margin-bottom: 1.25rem; color: var(--forest-900);">
                Brand Identity & Announcement Banner
            </h2>

            <div class="form-group">
                <label for="brand_name" class="form-label">Brand Name</label>
                <input type="text" id="brand_name" name="brand_name" class="form-input" value="<?= htmlspecialchars($settings['brand_name'] ?? 'SHUBH LABH', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-group">
                <label for="brand_tagline" class="form-label">Brand Tagline</label>
                <input type="text" id="brand_tagline" name="brand_tagline" class="form-input" value="<?= htmlspecialchars($settings['brand_tagline'] ?? 'DAIRY NUTRITION', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-group">
                <label for="announcement_text" class="form-label">Top Announcement Banner Text</label>
                <textarea id="announcement_text" name="announcement_text" class="form-textarea" style="min-height: 80px;"><?= htmlspecialchars($settings['announcement_text'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600; cursor: pointer;">
                    <input type="checkbox" name="announcement_active" value="1" <?= (!empty($settings['announcement_active']) && $settings['announcement_active'] == '1') ? 'checked' : '' ?>>
                    <span>Display announcement banner on website</span>
                </label>
            </div>
        </div>

        <!-- System Integrations (Locked CRM) -->
        <div class="card">
            <h2 style="font-size: 1.15rem; font-family: 'Manrope', sans-serif; font-weight: 700; margin-bottom: 1.25rem; color: var(--forest-900);">
                System Integrations (Immutable)
            </h2>

            <div class="form-group">
                <label class="form-label">CRM Portal Destination URL <span style="color: var(--danger-text); font-size: 0.75rem;">(System Locked)</span></label>
                <input type="text" class="form-input" readonly value="<?= htmlspecialchars($settings['crm_login_url'] ?? 'https://shubhlabh-crm.vercel.app/', ENT_QUOTES, 'UTF-8') ?>" style="background: #f1f5f9; color: var(--text-muted); cursor: not-allowed;">
                <p class="form-help">The CRM Login target is strictly immutable and protected by server-side security policies.</p>
            </div>

            <div class="form-group">
                <label for="footer_text" class="form-label">Footer Bio / Corporate Narrative</label>
                <textarea id="footer_text" name="footer_text" class="form-textarea" style="min-height: 100px;"><?= htmlspecialchars($settings['footer_text'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
        </div>
    </div>

    <!-- Contact Details Card -->
    <div class="card">
        <h2 style="font-size: 1.15rem; font-family: 'Manrope', sans-serif; font-weight: 700; margin-bottom: 0.5rem; color: var(--forest-900);">
            Official Customer Communication Channels
        </h2>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">
            Notice: Missing or unconfirmed contact information must remain blank. Any field left blank is automatically marked <em>"Unconfigured"</em> and hidden from website visitors.
        </p>

        <div class="grid-2">
            <div class="form-group">
                <label for="phone_number" class="form-label">
                    Official Customer Care Telephone
                    <?php if (empty($settings['phone_number'])): ?>
                        <span style="font-size: 0.75rem; color: #b45309; font-weight: normal;">(Unconfigured)</span>
                    <?php endif; ?>
                </label>
                <input type="text" id="phone_number" name="phone_number" class="form-input" value="<?= htmlspecialchars($settings['phone_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. +91 98765 43210">
            </div>

            <div class="form-group">
                <label for="whatsapp_number" class="form-label">
                    WhatsApp Business Number
                    <?php if (empty($settings['whatsapp_number'])): ?>
                        <span style="font-size: 0.75rem; color: #b45309; font-weight: normal;">(Unconfigured)</span>
                    <?php endif; ?>
                </label>
                <input type="text" id="whatsapp_number" name="whatsapp_number" class="form-input" value="<?= htmlspecialchars($settings['whatsapp_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. +919876543210">
            </div>
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label for="public_email" class="form-label">
                    Official Support Email
                    <?php if (empty($settings['public_email'])): ?>
                        <span style="font-size: 0.75rem; color: #b45309; font-weight: normal;">(Unconfigured)</span>
                    <?php endif; ?>
                </label>
                <input type="email" id="public_email" name="public_email" class="form-input" value="<?= htmlspecialchars($settings['public_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. contact@shubhlabhfeeds.com">
            </div>

            <div class="form-group">
                <label for="postal_address" class="form-label">
                    Registered Factory / Office Postal Address
                    <?php if (empty($settings['postal_address'])): ?>
                        <span style="font-size: 0.75rem; color: #b45309; font-weight: normal;">(Unconfigured)</span>
                    <?php endif; ?>
                </label>
                <textarea id="postal_address" name="postal_address" class="form-textarea" style="min-height: 80px;" placeholder="Full postal address in Rajasthan..."><?= htmlspecialchars($settings['postal_address'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
        </div>
    </div>

    <!-- SEO Settings Card -->
    <div class="card">
        <h2 style="font-size: 1.15rem; font-family: 'Manrope', sans-serif; font-weight: 700; margin-bottom: 1.25rem; color: var(--forest-900);">
            Default Search Engine Optimization (SEO) Metadata
        </h2>

        <div class="form-group">
            <label for="meta_title" class="form-label">Default Page Meta Title (Recommended 50–60 characters)</label>
            <input type="text" id="meta_title" name="meta_title" class="form-input" value="<?= htmlspecialchars($settings['meta_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="form-group">
            <label for="meta_description" class="form-label">Default Page Meta Description (Recommended 150–160 characters)</label>
            <textarea id="meta_description" name="meta_description" class="form-textarea" style="min-height: 80px;"><?= htmlspecialchars($settings['meta_description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>

    <div style="margin-top: 1.5rem; margin-bottom: 3rem;">
        <button type="submit" class="btn-primary" style="padding: 0.85rem 2rem; font-size: 1rem;" onclick="return confirm('Confirm saving site settings?');">
            Save All Settings
        </button>
    </div>
</form>

<?php renderAdminFooter(); ?>
