<?php
declare(strict_types=1);

namespace SmallMD;

class Router
{
    public function __construct(private Config $config) {}

    public function handle(string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = '/' . trim($path, '/');

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
