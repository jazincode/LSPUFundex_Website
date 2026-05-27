<?php
define('LSPUFUNDEX', true);
define('BASE_URL',   '/LSPUFundex/');
define('APP_NAME',   'LSPUFundex');
define('APP_VERSION','1.0.0');
define('SCHOOL_NAME','Laguna State Polytechnic University');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_token() {
    return $_SESSION['csrf_token'] ?? '';
}

function verify_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('
        <div style="font-family:Arial;text-align:center;margin-top:100px;">
            <h2 style="color:#e74c3c;">❌ Invalid Request</h2>
            <p style="color:#555;">CSRF token mismatch. Please go back and try again.</p>
            <a href="javascript:history.back()"
               style="background:#1b4f72;color:white;padding:10px 24px;
                      border-radius:8px;text-decoration:none;">Go Back</a>
        </div>');
    }
}
?>