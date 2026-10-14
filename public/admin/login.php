<?php
/**
 * Shubh Labh CMS - Secure Admin Login
 */

require_once dirname(__DIR__, 2) . '/config/Config.php';
require_once dirname(__DIR__, 2) . '/app/Logger.php';
require_once dirname(__DIR__, 2) . '/app/Database.php';
require_once dirname(__DIR__, 2) . '/app/Session.php';
require_once dirname(__DIR__, 2) . '/app/CSRF.php';
require_once dirname(__DIR__, 2) . '/app/RateLimiter.php';
require_once dirname(__DIR__, 2) . '/app/Auth.php';

use ShubhLabh\App\Auth;
use ShubhLabh\App\CSRF;
use ShubhLabh\App\Session;

Session::start();

// Redirect to dashboard if already authenticated
if (Auth::isAuthenticated()) {
    header('Location: /admin/index.php');
    exit;
}

$errorMessage = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validate CSRF token
    if (!CSRF::validate()) {
        $errorMessage = 'Security validation failed (Invalid CSRF token). Please refresh and try again.';
    } else {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $result = Auth::authenticate($email, $password);

        if ($result['success']) {
            header('Location: /admin/index.php');
            exit;
        } else {
            $errorMessage = $result['error'];
        }
    }
}

// Generate CSRF token for form
$csrfToken = CSRF::getToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Shubh Labh CMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..700;1,9..40,400..700&family=Manrope:wght@400..800&family=Rozha+One&display=swap" rel="stylesheet">
    <style>
        :root {
            --forest-900: #143d28;
            --forest-800: #194d33;
            --brand-green: #1b5e20;
            --brand-light: #2e7d32;
            --gold-500: #d4a373;
            --gold-400: #e0b589;
            --surface-bg: #f5f3ef;
            --card-bg: #ffffff;
            --text-dark: #1f2937;
            --text-muted: #4b5563;
            --border-color: #e5e7eb;
            --danger-bg: #fef2f2;
            --danger-border: #fecaca;
            --danger-text: #991b1b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background-color: var(--surface-bg);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 1.5rem;
        }

        .login-card {
            background: var(--card-bg);
            width: 100%;
            max-width: 440px;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(20, 61, 40, 0.1), 0 8px 10px -6px rgba(20, 61, 40, 0.05);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .login-header {
            background: var(--forest-900);
            color: #ffffff;
            padding: 2.25rem 2rem;
            text-align: center;
        }

        .brand-badge {
            display: inline-block;
            font-family: 'Rozha One', serif;
            font-size: 1.25rem;
            color: var(--gold-400);
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .login-title {
            font-family: 'Manrope', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            color: #ffffff;
        }

        .login-subtitle {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.8);
            margin-top: 0.25rem;
        }

        .login-body {
            padding: 2rem;
        }

        .alert-error {
            background-color: var(--danger-bg);
            border: 1px solid var(--danger-border);
            color: var(--danger-text);
            padding: 0.875rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.375rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 0.875rem;
            font-size: 0.95rem;
            font-family: inherit;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: #ffffff;
            color: var(--text-dark);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--brand-green);
            box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.15);
        }

        .password-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            font-size: 0.8125rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }

        .password-toggle:focus {
            outline: 2px solid var(--brand-green);
        }

        .btn-submit {
            width: 100%;
            padding: 0.875rem;
            background: var(--brand-green);
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 600;
            font-family: 'Manrope', sans-serif;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.1s ease;
            margin-top: 0.5rem;
        }

        .btn-submit:hover {
            background-color: var(--forest-900);
        }

        .btn-submit:focus {
            outline: 3px solid rgba(27, 94, 32, 0.4);
            outline-offset: 2px;
        }

        .login-footer {
            padding: 1.25rem 2rem;
            background: #fafaf9;
            border-top: 1px solid var(--border-color);
            text-align: center;
            font-size: 0.8125rem;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
        }

        .login-footer a {
            color: var(--brand-green);
            text-decoration: none;
            font-weight: 500;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <main class="login-card">
        <header class="login-header">
            <span class="brand-badge">शुभ लाभ</span>
            <h1 class="login-title">Content Management</h1>
            <p class="login-subtitle">Shubh Labh Public Website Admin Portal</p>
        </header>

        <section class="login-body">
            <?php if (!empty($errorMessage)): ?>
                <div class="alert-error" role="alert">
                    <span>⚠️</span>
                    <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="/admin/login.php" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label for="email" class="form-label">Administrator Email</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-input" 
                        required 
                        autocomplete="email" 
                        placeholder="operator@shubhlabhfeeds.com"
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="password-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            required 
                            autocomplete="current-password" 
                            placeholder="••••••••••••"
                        >
                        <button 
                            type="button" 
                            class="password-toggle" 
                            id="togglePassword" 
                            aria-label="Toggle password visibility" 
                            aria-pressed="false"
                        >Show</button>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Sign In to Dashboard</button>
            </form>
        </section>

        <footer class="login-footer">
            <a href="/" target="_self">← Public Website</a>
            <a href="https://shubhlabh-crm.vercel.app/" target="_blank" rel="noopener noreferrer">CRM Portal ↗</a>
        </footer>
    </main>

    <script>
        document.getElementById('togglePassword')?.addEventListener('click', function() {
            const passInput = document.getElementById('password');
            const isPassword = passInput.getAttribute('type') === 'password';
            passInput.setAttribute('type', isPassword ? 'text' : 'password');
            this.textContent = isPassword ? 'Hide' : 'Show';
            this.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
        });
    </script>

</body>
</html>
