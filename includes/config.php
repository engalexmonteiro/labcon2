<?php
// Configurações do banco de dados - ajuste conforme seu ambiente
define('DB_HOST', 'localhost');
define('DB_NAME', 'labcon');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATION', 'utf8mb4_unicode_ci');

define('APP_NAME', 'LabCon');
define('SESSION_NAME', 'labcon_sess');
define('APP_SECRET', 'labcon-change-this-secret-key');

if (APP_SECRET === 'labcon-change-this-secret-key') {
    error_log('[LabCon SECURITY] APP_SECRET não foi alterado do valor padrão. Atualize includes/config.php antes de usar em produção.');
}

// Configurações de segurança de sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);

// Ativar cookie seguro apenas quando servido via HTTPS
$_labcon_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
ini_set('session.cookie_secure', $_labcon_https ? '1' : '0');
unset($_labcon_https);
