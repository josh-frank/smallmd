<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

require ROOT . '/vendor/autoload.php';

use SmallMD\Router;
use SmallMD\Config;

$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: " . implode('; ', [
    "default-src 'self'",
    "style-src 'self' 'nonce-{$nonce}' https://*.googleapis.com 'unsafe-hashes' 'sha256-a6mcqZ043xibzvNjj7LmbkIhZ0e0BjYZ/2qih6Mqw1o=' 'sha256-Vmf35/seiSHA++jhCBJCep+j1l498+1v8RnVbox53XU='",
    "style-src-attr 'unsafe-hashes' 'sha256-a6mcqZ043xibzvNjj7LmbkIhZ0e0BjYZ/2qih6Mqw1o=' 'sha256-Vmf35/seiSHA++jhCBJCep+j1l498+1v8RnVbox53XU='",
    "script-src 'nonce-{$nonce}' 'unsafe-hashes' 'sha256-BylnATObMDEcEC05wmP4SZ95f/7Zsp0ok0W7UYkBgKQ='",
    "script-src-attr 'unsafe-hashes' 'sha256-BylnATObMDEcEC05wmP4SZ95f/7Zsp0ok0W7UYkBgKQ='",
    "img-src 'self' data: https://*.linodeobjects.com",
    "font-src 'self' https://*.gstatic.com",
    "frame-ancestors 'none'",
]));
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$config = Config::load(ROOT . '/config/site.yaml');
$router = new Router($config);
$router->handle($_SERVER['REQUEST_URI'] ?? '/', $nonce);