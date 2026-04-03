<?php
declare(strict_types=1);

namespace SmallMD;

class Router
{
    private const MIME_TYPES = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    public function __construct(private Config $config) {}

    public function handle(string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = '/' . trim($path, '/');

        // Serve theme assets through PHP so theme is switchable without touching nginx
        if (str_starts_with($path, '/assets/')) {
            $this->serveAsset($path);
            return;
        }

        // Strip .html suffix if someone types it
        if (str_ends_with($path, '.html')) {
            $path = substr($path, 0, -5);
        }

        // Map / to index
        $slug = ($path === '/') ? 'index' : ltrim($path, '/');

        // Security: no traversal
        if (str_contains($slug, '..') || str_contains($slug, "\0")) {
            $this->render404();
            return;
        }

        // Try exact slug, then slug/index for directories
        $file = $this->findContent($slug);

        if ($file === null) {
            $this->render404();
            return;
        }

        $parser  = new Parser();
        $page    = $parser->parse($file);
        $theme   = new Theme($this->config);

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        echo $theme->render($page);
    }

    private function serveAsset(string $path): void
    {
        // Strip /assets/ prefix and resolve against active theme
        $relative  = substr($path, strlen('/assets/'));
        $themeName = $this->config->get('theme', 'default');
        $file      = ROOT . '/themes/' . $themeName . '/assets/' . $relative;

        // Security: no traversal
        $realFile      = realpath($file);
        $realAssetsDir = realpath(ROOT . '/themes/' . $themeName . '/assets');

        if (
            $realFile === false ||
            $realAssetsDir === false ||
            !str_starts_with($realFile, $realAssetsDir . DIRECTORY_SEPARATOR)
        ) {
            http_response_code(404);
            return;
        }

        $ext   = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));
        $mime  = self::MIME_TYPES[$ext] ?? 'application/octet-stream';
        $mtime = filemtime($realFile);
        $etag  = '"' . dechex($mtime) . '-' . dechex(filesize($realFile)) . '"';

        // Validate conditional request
        if (
            isset($_SERVER['HTTP_IF_NONE_MATCH']) &&
            $_SERVER['HTTP_IF_NONE_MATCH'] === $etag
        ) {
            http_response_code(304);
            return;
        }

        http_response_code(200);
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=604800');
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        readfile($realFile);
    }

    private function findContent(string $slug): ?string
    {
        $base = ROOT . '/content/';
        $candidates = [
            $base . $slug . '.md',
            $base . $slug . '/index.md',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) return $path;
        }
        return null;
    }

    private function render404(): void
    {
        $file = ROOT . '/content/404.md';
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');

        if (is_file($file)) {
            $parser = new Parser();
            $page   = $parser->parse($file);
            $theme  = new Theme($this->config);
            echo $theme->render($page);
        } else {
            echo '<html><body><h1>404 Not Found</h1></body></html>';
        }
    }
}
