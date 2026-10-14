<?php

namespace ShubhLabh\Admin;

use ShubhLabh\App\CSRF;
use ShubhLabh\App\Session;

/**
 * Shared Admin UI Layout & Navigation
 */
function renderAdminHeader(string $title, string $activePage = 'dashboard'): void
{
    $adminName = Session::get('admin_full_name', 'Administrator');
    $adminRole = Session::get('admin_role', 'editor');
    $flashes = Session::getFlashes();
    $csrfField = CSRF::getInputField();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> | Shubh Labh CMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..700;1,9..40,400..700&family=Manrope:wght@500..800&family=Rozha+One&display=swap" rel="stylesheet">
    <style>
        :root {
            --forest-900: #143d28;
            --forest-800: #194d33;
            --brand-green: #1b5e20;
            --brand-light: #2e7d32;
            --gold-500: #d4a373;
            --gold-400: #e0b589;
            --surface-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --success-bg: #ecfdf5;
            --success-border: #a7f3d0;
            --success-text: #065f46;
            --danger-bg: #fef2f2;
            --danger-border: #fecaca;
            --danger-text: #991b1b;
            --warning-bg: #fffbeb;
            --warning-border: #fde68a;
            --warning-text: #92400e;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background-color: var(--surface-bg);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            background-color: var(--forest-900);
            color: #ffffff;
            padding: 0.875rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--gold-500);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: #ffffff;
        }

        .brand-badge {
            font-family: 'Rozha One', serif;
            font-size: 1.25rem;
            color: var(--gold-500);
        }

        .nav-title {
            font-family: 'Manrope', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-tag {
            font-size: 0.875rem;
            color: #e2e8f0;
        }

        .user-badge {
            background: rgba(212, 163, 115, 0.25);
            color: var(--gold-500);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
            margin-left: 0.35rem;
        }

        .btn-link {
            color: #ffffff;
            text-decoration: none;
            font-size: 0.8125rem;
            padding: 0.4rem 0.75rem;
            border-radius: 4px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.2s;
        }

        .btn-link:hover { background: rgba(255, 255, 255, 0.1); }
        .btn-logout { background: #dc2626; border-color: #dc2626; }
        .btn-logout:hover { background: #b91c1c; }

        .app-body {
            display: flex;
            flex: 1;
        }

        .sidebar {
            width: 250px;
            background: #ffffff;
            border-right: 1px solid var(--border-color);
            padding: 1.5rem 0;
            display: flex;
            flex-direction: column;
        }

        .menu-list {
            list-style: none;
        }

        .menu-item a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            color: var(--text-dark);
            text-decoration: none;
            font-size: 0.925rem;
            font-weight: 500;
            transition: background 0.15s, color 0.15s;
        }

        .menu-item a:hover {
            background: #f1f5f9;
            color: var(--brand-green);
        }

        .menu-item.active a {
            background: rgba(27, 94, 32, 0.08);
            color: var(--brand-green);
            font-weight: 700;
            border-left: 4px solid var(--brand-green);
        }

        .badge-upcoming {
            background: #e2e8f0;
            color: #475569;
            font-size: 0.6875rem;
            font-weight: 600;
            padding: 0.15rem 0.4rem;
            border-radius: 3px;
            margin-left: auto;
            text-transform: uppercase;
        }

        .main-content {
            flex: 1;
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .page-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-family: 'Manrope', sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--forest-900);
        }

        .page-subtitle {
            color: var(--text-muted);
            font-size: 0.925rem;
            margin-top: 0.25rem;
        }

        .flash-alert {
            padding: 0.875rem 1.25rem;
            border-radius: 6px;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }

        .flash-success { background: var(--success-bg); border: 1px solid var(--success-border); color: var(--success-text); }
        .flash-error   { background: var(--danger-bg); border: 1px solid var(--danger-border); color: var(--danger-text); }
        .flash-warning { background: var(--warning-bg); border: 1px solid var(--warning-border); color: var(--warning-text); }

        .btn-primary {
            background: var(--brand-green);
            color: #ffffff;
            border: none;
            padding: 0.65rem 1.25rem;
            border-radius: 6px;
            font-family: 'Manrope', sans-serif;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.15s;
        }

        .btn-primary:hover { background: var(--forest-900); }

        .btn-secondary {
            background: #ffffff;
            color: var(--text-dark);
            border: 1px solid var(--border-color);
            padding: 0.65rem 1.25rem;
            border-radius: 6px;
            font-family: 'Manrope', sans-serif;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.15s;
        }

        .btn-secondary:hover { background: #f8fafc; }

        .btn-danger {
            background: #ef4444;
            color: #ffffff;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.8125rem;
            cursor: pointer;
        }

        .btn-danger:hover { background: #dc2626; }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .data-table th {
            text-align: left;
            padding: 0.75rem 1rem;
            background: #f8fafc;
            border-bottom: 2px solid var(--border-color);
            color: var(--text-muted);
            font-weight: 600;
        }

        .data-table td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .data-table tr:hover { background: #fdfdfd; }

        .status-badge {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .status-published { background: #dcfce7; color: #166534; }
        .status-draft     { background: #fef3c7; color: #92400e; }
        .status-archived  { background: #f1f5f9; color: #475569; }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.35rem;
        }

        .form-help {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.65rem 0.85rem;
            font-size: 0.925rem;
            font-family: inherit;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: #ffffff;
            color: var(--text-dark);
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--brand-green);
            box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.15);
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }

        @media (max-width: 800px) {
            .app-body { flex-direction: column; }
            .sidebar { width: 100%; border-right: none; border-bottom: 1px solid var(--border-color); }
            .grid-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="/admin/index.php" class="nav-brand">
            <span class="brand-badge">शुभ लाभ</span>
            <span class="nav-title">CMS Admin</span>
        </a>
        <div class="nav-actions">
            <span class="user-tag">
                <?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?>
                <span class="user-badge"><?= htmlspecialchars($adminRole, ENT_QUOTES, 'UTF-8') ?></span>
            </span>
            <a href="/" target="_blank" class="btn-link">Public Website ↗</a>
            <a href="https://shubhlabh-crm.vercel.app/" target="_blank" rel="noopener noreferrer" class="btn-link">CRM Portal ↗</a>
            <a href="/admin/logout.php" class="btn-link btn-logout">Sign Out</a>
        </div>
    </nav>

    <div class="app-body">
        <aside class="sidebar">
            <ul class="menu-list">
                <li class="menu-item <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                    <a href="/admin/index.php">📊 Dashboard</a>
                </li>
                <li class="menu-item <?= $activePage === 'sections' ? 'active' : '' ?>">
                    <a href="/admin/sections.php">📑 Website Sections</a>
                </li>
                <li class="menu-item <?= $activePage === 'products' ? 'active' : '' ?>">
                    <a href="/admin/products.php">🌾 Products Catalog</a>
                </li>
                <li class="menu-item <?= $activePage === 'articles' ? 'active' : '' ?>">
                    <a href="/admin/articles.php">📖 Knowledge Hub</a>
                </li>
                <li class="menu-item <?= $activePage === 'navigation' ? 'active' : '' ?>">
                    <a href="/admin/navigation.php">🧭 Navigation Menus</a>
                </li>
                <li class="menu-item <?= $activePage === 'settings' ? 'active' : '' ?>">
                    <a href="/admin/settings.php">⚙️ Site Settings</a>
                </li>
                <li class="menu-item <?= $activePage === 'media' ? 'active' : '' ?>">
                    <a href="/admin/media.php">🖼️ Media Library <span class="badge-upcoming">Planned</span></a>
                </li>
                <li class="menu-item <?= $activePage === 'audit' ? 'active' : '' ?>">
                    <a href="/admin/audit.php">📜 Audit History</a>
                </li>
            </ul>
        </aside>

        <main class="main-content">
            <?php foreach ($flashes as $type => $messages): ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="flash-alert flash-<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" role="alert">
                        <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
<?php
}

function renderAdminFooter(): void
{
?>
        </main>
    </div>

    <footer style="background: #ffffff; border-top: 1px solid var(--border-color); padding: 1.25rem 2rem; text-align: center; color: var(--text-muted); font-size: 0.8125rem;">
        Shubh Labh Cattle Feed &bull; Content Management System &bull; Sprint SL-WEB-11D
    </footer>

</body>
</html>
<?php
}
