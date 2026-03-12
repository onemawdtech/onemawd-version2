
<?php
date_default_timezone_set('Asia/Manila');   
/**
 * OneMAWD - Application Configuration
 */

// ── Security: Direct-access guard ─────────────────────────
define('OMAWD_ACCESS', true);

// ── Session security hardening ────────────────────────────
ini_set('session.gc_maxlifetime', 28800);   // 8 hours — prevents premature garbage collection
ini_set('session.cookie_lifetime', 28800);  // 8-hour session cookie
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');  // Lax allows normal navigation; Strict can break redirects
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
session_start();

// Session fingerprint — invalidate if user-agent changes mid-session
$_fingerprint = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . 'omawd_salt_v2');
if (isset($_SESSION['_fingerprint']) && $_SESSION['_fingerprint'] !== $_fingerprint) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['_fingerprint'] = $_fingerprint;

// ── Security headers ──────────────────────────────────────
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

// ── CSRF protection ───────────────────────────────────────
function csrfToken() {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="_csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf() {
    $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        die('<h1>403 Forbidden</h1><p>Invalid or missing security token. <a href="javascript:history.back()">Go back</a></p>');
    }
}

// ── Origin / Referrer validation (block third-party POST) ─
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $originAllowed = false;
    if ($origin) {
        $parsedOrigin = parse_url($origin, PHP_URL_HOST);
        $originAllowed = ($parsedOrigin === $host || $parsedOrigin === parse_url('http://' . $host, PHP_URL_HOST));
    } elseif ($referer) {
        $parsedReferer = parse_url($referer, PHP_URL_HOST);
        $originAllowed = ($parsedReferer === $host || $parsedReferer === parse_url('http://' . $host, PHP_URL_HOST));
    } else {
        // No origin/referer — allow only for same-site form posts (some browsers strip headers)
        $originAllowed = true;
    }

    if (!$originAllowed) {
        http_response_code(403);
        die('<h1>403 Forbidden</h1><p>Cross-origin requests are not allowed.</p>');
    }

    // Verify CSRF on all POST requests (skip if no token in session yet — first login)
    if (!empty($_SESSION['_csrf_token'])) {
        verifyCsrf();
    }
}

// Base path
define('BASE_PATH', dirname(__DIR__));

// Auto-detect base URL (works from any document root)
$_scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
if (preg_match('#(/eclass)#', $_scriptName, $_m)) {
    define('BASE_URL', $_m[1]);
} else {
    define('BASE_URL', '');
}

// App info
define('APP_NAME', 'OneMAWD');
define('APP_VERSION', '2.0');

// Include database
require_once BASE_PATH . '/config/database.php';

// ── Remember Me: auto-restore session from token cookie ───
if (!isset($_SESSION['user_id']) && !empty($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare("
        SELECT at.user_id, a.role, a.section
        FROM auth_tokens at
        JOIN accounts a ON a.id = at.user_id
        WHERE at.token_hash = ? AND at.expires_at > NOW()
    ");
    $stmt->execute([$tokenHash]);
    $tokenRow = $stmt->fetch();
    if ($tokenRow) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $tokenRow['user_id'];
        $_SESSION['user_role'] = $tokenRow['role'];
        $_SESSION['user_section'] = $tokenRow['section'];
        $_SESSION['_fingerprint'] = $_fingerprint;
        // Rotate token for security
        $newToken = bin2hex(random_bytes(32));
        $newHash = hash('sha256', $newToken);
        $pdo->prepare("UPDATE auth_tokens SET token_hash = ?, expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE token_hash = ?")
            ->execute([$newHash, $tokenHash]);
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        setcookie('remember_token', $newToken, [
            'expires'  => time() + (30 * 24 * 3600),
            'path'     => '/',
            'httponly'  => true,
            'secure'   => $secure,
            'samesite' => 'Lax',
        ]);
    } else {
        // Token invalid or expired — clear cookie
        setcookie('remember_token', '', ['expires' => 1, 'path' => '/']);
    }
    // Clean up expired tokens periodically (1% chance)
    if (random_int(1, 100) === 1) {
        $pdo->exec("DELETE FROM auth_tokens WHERE expires_at < NOW()");
    }
}

// Helper functions
function redirect($path) {
    header("Location: " . BASE_URL . $path);
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('/auth/login.php');
    }
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isOfficer() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'officer';
}

function isTeacher() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'teacher';
}

function hasSectionScope() {
    return (isOfficer() || isTeacher()) && getUserSection();
}

function getUserSection() {
    return $_SESSION['user_section'] ?? null;
}

function requireAdmin() {
    if (!isAdmin()) {
        setFlash('error', 'Access denied. Admin privileges required.');
        redirect('/dashboard.php');
    }
}

function requireNotTeacher() {
    if (isTeacher()) {
        setFlash('error', 'Access denied. Teachers can only access Subjects and Attendance.');
        redirect('/subjects/');
    }
}

function getTeacherSubjectIds() {
    if (!isTeacher()) return [];
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM subjects WHERE teacher_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function currentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function formatDate($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

function formatMoney($amount) {
    return '₱' . number_format($amount, 2);
}

function getInitials($name) {
    $parts = explode(' ', $name);
    $initials = '';
    foreach ($parts as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    return substr($initials, 0, 2);
}

function generateUuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        random_int(0, 0xffff), random_int(0, 0xffff),
        random_int(0, 0xffff),
        random_int(0, 0x0fff) | 0x4000,
        random_int(0, 0x3fff) | 0x8000,
        random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
    );
}

// ── Remember Me helpers ───────────────────────────────────
function createRememberToken(int $userId): void {
    global $pdo;
    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);
    // Remove any existing tokens for this user (one device at a time, or allow multiple — keeping it simple)
    $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?")->execute([$userId]);
    $pdo->prepare("INSERT INTO auth_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))")
        ->execute([$userId, $hash]);
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    setcookie('remember_token', $token, [
        'expires'  => time() + (30 * 24 * 3600),
        'path'     => '/',
        'httponly'  => true,
        'secure'   => $secure,
        'samesite' => 'Lax',
    ]);
}

function clearRememberToken(): void {
    global $pdo;
    if (!empty($_COOKIE['remember_token'])) {
        $hash = hash('sha256', $_COOKIE['remember_token']);
        $pdo->prepare("DELETE FROM auth_tokens WHERE token_hash = ?")->execute([$hash]);
    }
    setcookie('remember_token', '', ['expires' => 1, 'path' => '/']);
}
