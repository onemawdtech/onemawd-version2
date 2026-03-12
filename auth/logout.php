<?php
/**
 * OneMAWD - Logout
 */
require_once dirname(__DIR__) . '/config/app.php';

// Clear remember me token
clearRememberToken();

session_unset();
session_destroy();
header("Location: " . BASE_URL . "/auth/login.php");
exit;
