<?php

declare(strict_types=1);

/**
 * Shared-hosting front controller.
 *
 * Recommended layout:
 *   /home/USER/searphp/        <- the full application (outside public_html)
 *   /home/USER/public_html/    <- this file + .htaccess + .user.ini
 *
 * If the app is somewhere else, set SEARPHP_ROOT in .htaccess:
 *   SetEnv SEARPHP_ROOT /home/USER/searphp
 */

$appRoot = getenv('SEARPHP_ROOT');
if (!is_string($appRoot) || trim($appRoot) === '') {
    $appRoot = $_SERVER['SEARPHP_ROOT'] ?? null;
}
if (!is_string($appRoot) || trim($appRoot) === '') {
    $appRoot = dirname(__DIR__) . '/searphp';
}

$frontController = rtrim($appRoot, '/\\') . '/public/index.php';

if (!is_file($frontController)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'SearPHP is not installed. Check SEARPHP_ROOT.';
    exit;
}

require $frontController;
