<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

require ROOT . '/vendor/autoload.php';

use SmallMD\Router;
use SmallMD\Config;

$config = Config::load(ROOT . '/config/site.yaml');
$router = new Router($config);
$router->handle($_SERVER['REQUEST_URI'] ?? '/');
