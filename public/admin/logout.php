<?php
/**
 * Shubh Labh CMS - Admin Logout Handler
 */

require_once dirname(__DIR__, 2) . '/config/Config.php';
require_once dirname(__DIR__, 2) . '/app/Logger.php';
require_once dirname(__DIR__, 2) . '/app/Session.php';
require_once dirname(__DIR__, 2) . '/app/Auth.php';

use ShubhLabh\App\Auth;

Auth::logout();

header('Location: /admin/login.php');
exit;
