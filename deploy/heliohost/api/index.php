<?php

/**
 * Wex API front controller for the single-directory (shared hosting) layout:
 *
 *   <web root>/index.html, assets/, sw.js ...   React build
 *   <web root>/api/index.php                    this file
 *   <web root>/__WEX_CORE_DIR__/                app/, vendor/, writable/, .env (denied by .htaccess)
 *   <web root>/uploads/products/                public product images (FCPATH)
 */

$minPhpVersion = '8.3';
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo 'PHP ' . $minPhpVersion . '+ is required (current: ' . PHP_VERSION . ').';
    exit(1);
}

// Restore the Authorization header where Apache/PHP-FPM drops it (see root .htaccess).
if (empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $_SERVER['HTTP_AUTHORIZATION'] = $value;
                break;
            }
        }
    }
}

$webRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR;
$coreDir = $webRoot . '__WEX_CORE_DIR__';

// FCPATH is the web root (not api/) so uploads/products/* are served by Apache directly.
define('FCPATH', $webRoot);
chdir(FCPATH);

// Present this script as /index.php so CodeIgniter keeps the full /api/v1/... path
// (otherwise it strips the '/api' script directory and no route matches).
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF']    = '/index.php';

require $coreDir . '/app/Config/Paths.php';

$paths = new Config\Paths();

require $paths->systemDirectory . '/Boot.php';

exit(CodeIgniter\Boot::bootWeb($paths));
