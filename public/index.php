<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

require ROOT . '/vendor/autoload.php';

use SmallMD\Router;
use SmallMD\Config;

$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'nonce-{$nonce}'; script-src 'nonce-{$nonce}'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none';");
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$config = Config::load(ROOT . '/config/site.yaml');
$router = new Router($config);
$router->handle($_SERVER['REQUEST_URI'] ?? '/', $nonce);