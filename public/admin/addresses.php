<?php
/**
 * Shubh Labh CMS - Contact & Address Management
 */

require_once dirname(__DIR__, 2) . '/config/Config.php';
require_once dirname(__DIR__, 2) . '/app/Logger.php';
require_once dirname(__DIR__, 2) . '/app/Database.php';
require_once dirname(__DIR__, 2) . '/app/Session.php';
require_once dirname(__DIR__, 2) . '/app/CSRF.php';
require_once dirname(__DIR__, 2) . '/app/Sanitizer.php';
require_once dirname(__DIR__, 2) . '/app/AuditLogger.php';
require_once dirname(__DIR__, 2) . '/app/Models/CompanyAddress.php';
require_once __DIR__ . '/layout.php';

use ShubhLabh\App\AuditLogger;
use ShubhLabh\App\CSRF;
use ShubhLabh\App\Database;
use ShubhLabh\App\Sanitizer;
use ShubhLabh\App\Session;
use ShubhLabh\App\Models\CompanyAddress;
use function ShubhLabh\Admin\renderAdminHeader;
use function ShubhLabh\Admin\renderAdminFooter;

Session::requireAuth('/admin/login.php');

$action = $_GET['action'] ?? 'list';
$addressId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate()) {
        Session::setFlash('error', 'Security validation failed (Invalid CSRF token). Please try again.');
        header('Location: /admin/addresses.php');
        exit;
    }

    $formAction = $_POST['form_action'] ?? 'save';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;

    try {
        $pdo = Database::getConnection();

        // 1. Delete Address
        if ($formAction === 'delete' && $id) {
            $stmt = $pdo->prepare('DELETE FROM company_addresses WHERE id = :id');
            $stmt->execute(['id' => $id]);

            AuditLogger::log('DELETE', 'company_addresses', $id);
            Session::setFlash('success', 'Address record successfully deleted.');
            header('Location: /admin/addresses.php');
            exit;
        }

        // 2. Toggle Active Status
        if ($formAction === 'toggle_status' && $id) {
            $newActive = !empty($_POST['is_active']);
            $stmt = $pdo->prepare('UPDATE company_addresses SET is_active = :act, updated_at = NOW() WHERE id = :id');
            $stmt->bindValue(':act', $newActive, \PDO::PARAM_BOOL);
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            $stmt->execute();

            AuditLogger::log($newActive ? 'ACTIVATE' : 'DEACTIVATE', 'company_addresses', $id);
            Session::setFlash('success', $newActive ? 'Address activated and visible on public website.' : 'Address deactivated and hidden from public website.');
            header('Location: /admin/addresses.php');
            exit;
        }

        // 3. Set Primary Address
        if ($formAction === 'set_primary' && $id) {
            $pdo->beginTransaction();
            $pdo->exec('UPDATE company_addresses SET is_primary = false');
            $stmt = $pdo->prepare('UPDATE company_addresses SET is_primary = true, is_active = true, updated_at = NOW() WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $pdo->commit();

            AuditLogger::log('SET_PRIMARY', 'company_addresses', $id);
            Session::setFlash('success', 'Designated address marked as Primary Company Location.');
            header('Location: /admin/addresses.php');
            exit;
        }

        // 4. Create or Edit Address
        $locationName = trim($_POST['location_name'] ?? '');
        $addressLine1 = trim($_POST['address_line1'] ?? '');
        $addressLine2 = trim($_POST['address_line2'] ?? '');
        $landmark = trim($_POST['landmark'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '');
        $country = trim($_POST['country'] ?? 'India');
        $officePhone = trim($_POST['office_phone'] ?? '');
        $whatsappNumber = trim($_POST['whatsapp_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rawMapsUrl = trim($_POST['google_maps_url'] ?? '');
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $isPrimary = isset($_POST['is_primary']) ? 1 : 0;

        // Validation
        if (empty($locationName)) {
            Session::setFlash('error', 'Location / Office name is required.');
            header('Location: /admin/addresses.php' . ($id ? "?action=edit&id={$id}" : '?action=create'));
            exit;
        }

        if (empty($addressLine1)) {
            Session::setFlash('error', 'Address Line 1 is required.');
            header('Location: /admin/addresses.php' . ($id ? "?action=edit&id={$id}" : '?action=create'));
            exit;
        }

        if (empty($city) || empty($state) || empty($pincode)) {
            Session::setFlash('error', 'City, State, and PIN Code are required.');
            header('Location: /admin/addresses.php' . ($id ? "?action=edit&id={$id}" : '?action=create'));
            exit;
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::setFlash('error', "Invalid email address format: '{$email}'.");
            header('Location: /admin/addresses.php' . ($id ? "?action=edit&id={$id}" : '?action=create'));
            exit;
        }

        $cleanMapsUrl = null;
        if (!empty($rawMapsUrl)) {
            $cleanMapsUrl = Sanitizer::cleanUrl($rawMapsUrl);
            if (empty($cleanMapsUrl)) {
                Session::setFlash('error', 'Invalid Google Maps URL. Please provide a valid https:// Google Maps or directions link.');
                header('Location: /admin/addresses.php' . ($id ? "?action=edit&id={$id}" : '?action=create'));
                exit;
            }
        }

        // Database persistence
        if ($isPrimary) {
            // If setting this address as primary, reset existing primary
            $pdo->beginTransaction();
            $pdo->exec('UPDATE company_addresses SET is_primary = false');
        }

        if ($id) {
            // Update
            $stmt = $pdo->prepare('
                UPDATE company_addresses SET
                    location_name = :loc_name,
                    address_line1 = :line1,
                    address_line2 = :line2,
                    landmark = :landmark,
                    city = :city,
                    state = :state,
                    pincode = :pincode,
                    country = :country,
                    office_phone = :phone,
                    whatsapp_number = :wa,
                    email = :email,
                    google_maps_url = :maps_url,
                    display_order = :disp_order,
                    is_active = :is_act,
                    is_primary = :is_prim,
                    updated_at = NOW()
                WHERE id = :id
            ');
            $stmt->execute([
                'loc_name'   => $locationName,
                'line1'      => $addressLine1,
                'line2'      => !empty($addressLine2) ? $addressLine2 : null,
                'landmark'   => !empty($landmark) ? $landmark : null,
                'city'       => $city,
                'state'      => $state,
                'pincode'    => $pincode,
                'country'    => !empty($country) ? $country : 'India',
                'phone'      => !empty($officePhone) ? $officePhone : null,
                'wa'         => !empty($whatsappNumber) ? $whatsappNumber : null,
                'email'      => !empty($email) ? $email : null,
                'maps_url'   => $cleanMapsUrl,
                'disp_order' => $displayOrder,
                'is_act'     => $isActive,
                'is_prim'    => $isPrimary,
                'id'         => $id,
            ]);

            if ($isPrimary) {
                $pdo->commit();
            }

            AuditLogger::log('UPDATE', 'company_addresses', $id, ['location_name' => $locationName]);
            Session::setFlash('success', "Address '{$locationName}' updated successfully.");

        } else {
            // Create
            $stmt = $pdo->prepare('
                INSERT INTO company_addresses (
                    location_name, address_line1, address_line2, landmark,
                    city, state, pincode, country, office_phone, whatsapp_number,
                    email, google_maps_url, display_order, is_active, is_primary
                ) VALUES (
                    :loc_name, :line1, :line2, :landmark,
                    :city, :state, :pincode, :country, :phone, :wa,
                    :email, :maps_url, :disp_order, :is_act, :is_prim
                )
            ');
            $stmt->execute([
                'loc_name'   => $locationName,
                'line1'      => $addressLine1,
                'line2'      => !empty($addressLine2) ? $addressLine2 : null,
                'landmark'   => !empty($landmark) ? $landmark : null,
                'city'       => $city,
                'state'      => $state,
                'pincode'    => $pincode,
                'country'    => !empty($country) ? $country : 'India',
                'phone'      => !empty($officePhone) ? $officePhone : null,
                'wa'         => !empty($whatsappNumber) ? $whatsappNumber : null,
                'email'      => !empty($email) ? $email : null,
                'maps_url'   => $cleanMapsUrl,
                'disp_order' => $displayOrder,
                'is_act'     => $isActive,
                'is_prim'    => $isPrimary,
            ]);
            $newId = (int)$pdo->lastInsertId();

            if ($isPrimary) {
                $pdo->commit();
            }

            AuditLogger::log('CREATE', 'company_addresses', $newId, ['location_name' => $locationName]);
            Session::setFlash('success', "New address '{$locationName}' created successfully.");
        }

        header('Location: /admin/addresses.php');
        exit;

    } catch (\Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        Session::setFlash('error', 'Database operation failed: ' . $e->getMessage());
        header('Location: /admin/addresses.php' . ($id ? "?action=edit&id={$id}" : '?action=create'));
        exit;
    }
}

// Fetch single address for editing
$editAddress = null;
if (($action === 'edit' || $action === 'preview') && $addressId) {
    $editAddress = CompanyAddress::getById($addressId);
    if (!$editAddress) {
        Session::setFlash('error', 'Address record not found.');
        header('Location: /admin/addresses.php');
        exit;
    }
}

// Fetch list of addresses
$addresses = CompanyAddress::getAll();

renderAdminHeader('Contact & Address Management', 'addresses');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Contact &amp; Address Management</h1>
        <p class="page-subtitle">Configure public office locations, factory addresses, contact phones, and Google Maps directions.</p>
    </div>
    <?php if ($action === 'list'): ?>
        <div>
            <a href="/admin/addresses.php?action=create" class="btn-primary">+ Add New Address</a>
        </div>
    <?php else: ?>
        <div>
            <a href="/admin/addresses.php" class="btn-secondary">&larr; Back to Address List</a>
        </div>
    <?php endif; ?>
</div>

<?php if ($action === 'create' || $action === 'edit'): ?>
    <!-- CREATE / EDIT ADDRESS FORM -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
        <div class="card">
            <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--forest-900); margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
                <?= $action === 'edit' ? 'Edit Address: ' . htmlspecialchars($editAddress['location_name'], ENT_QUOTES, 'UTF-8') : 'Add New Location Address' ?>
            </h2>

            <form method="POST" action="/admin/addresses.php">
                <?= CSRF::getInputField() ?>
                <input type="hidden" name="form_action" value="save">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= (int)$editAddress['id'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label" for="location_name">Office / Location Name *</label>
                    <input type="text" id="location_name" name="location_name" class="form-input" required
                           placeholder="e.g. Head Office &amp; Works, Jaipur Sales Branch, Kota Depot"
                           value="<?= htmlspecialchars($editAddress['location_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <span class="form-help">Displayed prominently at the top of the location card on the website.</span>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="address_line1">Address Line 1 *</label>
                        <input type="text" id="address_line1" name="address_line1" class="form-input" required
                               placeholder="Building name, Plot / Survey No., Street"
                               value="<?= htmlspecialchars($editAddress['address_line1'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="address_line2">Address Line 2 (Optional)</label>
                        <input type="text" id="address_line2" name="address_line2" class="form-input"
                               placeholder="Industrial Area, Phase / Block / Ward"
                               value="<?= htmlspecialchars($editAddress['address_line2'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="landmark">Landmark (Optional)</label>
                        <input type="text" id="landmark" name="landmark" class="form-input"
                               placeholder="e.g. Near Dairy Circle, Opposite Highway Toll"
                               value="<?= htmlspecialchars($editAddress['landmark'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="city">City / District *</label>
                        <input type="text" id="city" name="city" class="form-input" required
                               placeholder="e.g. Jaipur, Ajmer, Alwar, Kota"
                               value="<?= htmlspecialchars($editAddress['city'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="state">State *</label>
                        <input type="text" id="state" name="state" class="form-input" required
                               placeholder="e.g. Rajasthan"
                               value="<?= htmlspecialchars($editAddress['state'] ?? 'Rajasthan', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="pincode">PIN Code *</label>
                        <input type="text" id="pincode" name="pincode" class="form-input" required
                               placeholder="e.g. 302013"
                               value="<?= htmlspecialchars($editAddress['pincode'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="country">Country *</label>
                    <input type="text" id="country" name="country" class="form-input" required
                           value="<?= htmlspecialchars($editAddress['country'] ?? 'India', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <hr style="border: 0; border-top: 1px dashed var(--border-color); margin: 1.5rem 0;">

                <h3 style="font-size: 1rem; font-weight: 700; color: var(--forest-900); margin-bottom: 1rem;">
                    Contact Details for this Location
                </h3>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="office_phone">Office Phone / Mobile (Optional)</label>
                        <input type="text" id="office_phone" name="office_phone" class="form-input"
                               placeholder="e.g. +91 98765 43210"
                               value="<?= htmlspecialchars($editAddress['office_phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <span class="form-help">Clickable tel: phone link on website.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="whatsapp_number">WhatsApp Number (Optional)</label>
                        <input type="text" id="whatsapp_number" name="whatsapp_number" class="form-input"
                               placeholder="e.g. +91 98765 43210"
                               value="<?= htmlspecialchars($editAddress['whatsapp_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <span class="form-help">Enables 1-click WhatsApp messaging to this location.</span>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="email">Email Address (Optional)</label>
                        <input type="email" id="email" name="email" class="form-input"
                               placeholder="e.g. contact@shubhlabhfeeds.in"
                               value="<?= htmlspecialchars($editAddress['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <span class="form-help">Official email for this location.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="display_order">Display Order</label>
                        <input type="number" id="display_order" name="display_order" class="form-input" min="0" max="999"
                               value="<?= (int)($editAddress['display_order'] ?? 1) ?>">
                        <span class="form-help">Lower numbers appear first on the public website.</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="google_maps_url">Google Maps Location / Directions URL (Optional)</label>
                    <input type="url" id="google_maps_url" name="google_maps_url" class="form-input"
                           placeholder="https://maps.google.com/?q=..."
                           value="<?= htmlspecialchars($editAddress['google_maps_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <span class="form-help">Full share link from Google Maps. Powers the <strong>Get Directions</strong> button.</span>
                </div>

                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 6px; padding: 1rem; margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
                    <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; font-size: 0.925rem; font-weight: 600;">
                        <input type="checkbox" name="is_active" value="1" <?= (!isset($editAddress['is_active']) || $editAddress['is_active']) ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                        <span>🟢 Active (Publish this location on public website)</span>
                    </label>

                    <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; font-size: 0.925rem; font-weight: 600;">
                        <input type="checkbox" name="is_primary" value="1" <?= (!empty($editAddress['is_primary'])) ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                        <span>⭐ Mark as Primary / Head Office Location</span>
                    </label>
                    <span class="form-help" style="margin-left: 26px;">The primary address receives featured badge highlighting and appears first.</span>
                </div>

                <div style="display: flex; gap: 1rem; align-items: center;">
                    <button type="submit" class="btn-primary">💾 Save Address</button>
                    <a href="/admin/addresses.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <!-- LIVE PREVIEW CARD -->
        <div class="card" style="position: sticky; top: 80px;">
            <h3 style="font-size: 1rem; font-weight: 700; color: var(--forest-900); margin-bottom: 1rem;">
                👁️ Live Card Preview
            </h3>
            <p style="font-size: 0.8125rem; color: var(--text-muted); margin-bottom: 1rem;">
                This preview shows how this address renders in the public website's Contact section.
            </p>

            <div style="background: #ffffff; border: 2px solid #143d28; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 12px rgba(20,61,40,0.08);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                    <h4 id="prev-name" style="font-size: 1.15rem; font-weight: 800; color: #143d28; margin: 0;">
                        <?= htmlspecialchars($editAddress['location_name'] ?? 'Head Office & Works', ENT_QUOTES, 'UTF-8') ?>
                    </h4>
                    <span id="prev-badge" style="background: #d4a373; color: #143d28; font-size: 0.7rem; font-weight: 700; padding: 2px 8px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.5px;">
                        Primary
                    </span>
                </div>

                <div style="display: flex; gap: 8px; align-items: flex-start; margin-bottom: 1rem; font-size: 0.875rem; color: #475569; line-height: 1.5;">
                    <span style="color: #1b5e20; font-size: 1.1rem; line-height: 1;">📍</span>
                    <span id="prev-address">
                        <?= htmlspecialchars($editAddress['formatted_address'] ?? 'RIICO Industrial Area, Phase 1, Near Highway By-Pass, Jaipur, Rajasthan 302013, India', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.8125rem; color: #334155; margin-bottom: 1.25rem;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span>📞</span>
                        <strong id="prev-phone"><?= htmlspecialchars($editAddress['office_phone'] ?? '+91 98765 43210', ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span>✉️</span>
                        <span id="prev-email"><?= htmlspecialchars($editAddress['email'] ?? 'contact@shubhlabhfeeds.in', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>

                <a href="#" onclick="return false;" style="display: inline-flex; align-items: center; gap: 6px; background: #1b5e20; color: #ffffff; padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none; font-size: 0.8125rem; font-weight: 700;">
                    <span>🗺️ Get Directions</span>
                    <span>↗</span>
                </a>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- ADDRESSES LIST VIEW -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; background: #ffffff; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <h2 style="font-size: 1.1rem; font-weight: 700; color: var(--forest-900); margin: 0;">
                    Configured Locations (<?= count($addresses) ?>)
                </h2>
                <p style="font-size: 0.8125rem; color: var(--text-muted); margin: 3px 0 0;">
                    Active locations are published live on <a href="/#contact" target="_blank" style="color: var(--brand-green); font-weight: 600;">public website contact section ↗</a>.
                </p>
            </div>
            <div>
                <a href="/admin/addresses.php?action=create" class="btn-primary" style="padding: 0.5rem 1rem; font-size: 0.8125rem;">+ Add Address</a>
            </div>
        </div>

        <?php if (empty($addresses)): ?>
            <div style="padding: 3rem 1.5rem; text-align: center; color: var(--text-muted);">
                <p style="font-size: 1.1rem; font-weight: 600; color: var(--text-dark); margin-bottom: 0.5rem;">No company addresses configured yet.</p>
                <p style="font-size: 0.875rem; margin-bottom: 1.25rem;">Click below to add your primary office or manufacturing works address.</p>
                <a href="/admin/addresses.php?action=create" class="btn-primary">+ Add First Address</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">Order</th>
                            <th>Location / Office</th>
                            <th>Complete Address</th>
                            <th>Contact Channels</th>
                            <th>Maps Link</th>
                            <th style="text-align: center;">Status</th>
                            <th style="text-align: right; width: 220px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($addresses as $addr): ?>
                            <tr style="<?= !$addr['is_active'] ? 'opacity: 0.65; background: #fafafa;' : '' ?>">
                                <td style="font-weight: 700; color: var(--text-muted); font-size: 0.85rem;">
                                    #<?= (int)$addr['display_order'] ?>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--forest-900); font-size: 0.95rem; display: flex; align-items: center; gap: 6px;">
                                        <span><?= htmlspecialchars($addr['location_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if (!empty($addr['is_primary'])): ?>
                                            <span style="background: var(--gold-500); color: var(--forest-900); font-size: 0.68rem; font-weight: 800; padding: 1px 6px; border-radius: 999px; text-transform: uppercase;">
                                                ⭐ Primary
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                                        <?= htmlspecialchars($addr['city'], ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($addr['state'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </td>
                                <td style="max-width: 280px; font-size: 0.825rem; color: #334155; line-height: 1.4;">
                                    <?= htmlspecialchars($addr['formatted_address'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td style="font-size: 0.8125rem;">
                                    <?php if (!empty($addr['office_phone'])): ?>
                                        <div>📞 <?= htmlspecialchars($addr['office_phone'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($addr['whatsapp_number'])): ?>
                                        <div style="color: #047857;">💬 WA: <?= htmlspecialchars($addr['whatsapp_number'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($addr['email'])): ?>
                                        <div style="color: #64748b;">✉️ <?= htmlspecialchars($addr['email'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                    <?php if (empty($addr['office_phone']) && empty($addr['whatsapp_number']) && empty($addr['email'])): ?>
                                        <span style="color: #94a3b8; font-style: italic;">No phone/email</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($addr['google_maps_url'])): ?>
                                        <a href="<?= htmlspecialchars($addr['google_maps_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" style="color: var(--brand-green); font-weight: 600; font-size: 0.8125rem; display: inline-flex; align-items: center; gap: 4px;">
                                            <span>🗺️ View Map</span>
                                            <span>↗</span>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 0.75rem;">Default Search</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <form method="POST" action="/admin/addresses.php" style="display: inline;">
                                        <?= CSRF::getInputField() ?>
                                        <input type="hidden" name="form_action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= (int)$addr['id'] ?>">
                                        <input type="hidden" name="is_active" value="<?= $addr['is_active'] ? '0' : '1' ?>">
                                        <button type="submit" style="background: none; border: none; cursor: pointer; padding: 0;" title="<?= $addr['is_active'] ? 'Click to deactivate' : 'Click to activate' ?>">
                                            <?php if ($addr['is_active']): ?>
                                                <span style="background: #dcfce7; color: #166534; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 999px; border: 1px solid #bbf7d0;">
                                                    🟢 Active
                                                </span>
                                            <?php else: ?>
                                                <span style="background: #f1f5f9; color: #64748b; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 999px; border: 1px solid #cbd5e1;">
                                                    ⚪ Inactive
                                                </span>
                                            <?php endif; ?>
                                        </button>
                                    </form>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <div style="display: inline-flex; gap: 0.4rem; align-items: center;">
                                        <?php if (empty($addr['is_primary'])): ?>
                                            <form method="POST" action="/admin/addresses.php" style="display: inline;">
                                                <?= CSRF::getInputField() ?>
                                                <input type="hidden" name="form_action" value="set_primary">
                                                <input type="hidden" name="id" value="<?= (int)$addr['id'] ?>">
                                                <button type="submit" class="btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" title="Make Primary Office">
                                                    ⭐ Make Primary
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <a href="/admin/addresses.php?action=edit&id=<?= (int)$addr['id'] ?>" class="btn-secondary" style="padding: 0.25rem 0.6rem; font-size: 0.75rem;">
                                            ✏️ Edit
                                        </a>

                                        <form method="POST" action="/admin/addresses.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to permanently delete this address? This cannot be undone.');">
                                            <?= CSRF::getInputField() ?>
                                            <input type="hidden" name="form_action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$addr['id'] ?>">
                                            <button type="submit" class="btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" title="Delete Address">
                                                🗑️
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
// Dynamic live preview updates when editing
(function() {
    const nameInput = document.getElementById('location_name');
    const line1Input = document.getElementById('address_line1');
    const line2Input = document.getElementById('address_line2');
    const cityInput = document.getElementById('city');
    const stateInput = document.getElementById('state');
    const pinInput = document.getElementById('pincode');
    const phoneInput = document.getElementById('office_phone');
    const emailInput = document.getElementById('email');

    const prevName = document.getElementById('prev-name');
    const prevAddr = document.getElementById('prev-address');
    const prevPhone = document.getElementById('prev-phone');
    const prevEmail = document.getElementById('prev-email');

    function updatePreview() {
        if (prevName && nameInput) prevName.textContent = nameInput.value.trim() || 'Location Name';
        if (prevPhone && phoneInput) prevPhone.textContent = phoneInput.value.trim() || '—';
        if (prevEmail && emailInput) prevEmail.textContent = emailInput.value.trim() || '—';

        if (prevAddr) {
            const parts = [
                line1Input?.value.trim(),
                line2Input?.value.trim(),
                cityInput?.value.trim(),
                stateInput?.value.trim(),
                pinInput?.value.trim()
            ].filter(Boolean);
            prevAddr.textContent = parts.length > 0 ? parts.join(', ') : 'Address lines will appear here...';
        }
    }

    [nameInput, line1Input, line2Input, cityInput, stateInput, pinInput, phoneInput, emailInput].forEach(el => {
        el?.addEventListener('input', updatePreview);
    });
})();
</script>

<?php
renderAdminFooter();
