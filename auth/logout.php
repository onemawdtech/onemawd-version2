<?php
/**
 * eClass - Logout
 */
require_once dirname(__DIR__) . '/config/app.php';
session_destroy();
header("Location: " . BASE_URL . "/auth/login.php");
exit;
