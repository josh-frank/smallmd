<?php
declare(strict_types=1);

namespace SmallMD;

use League\CommonMark\CommonMarkConverter;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

class Theme
{
    private Environment $twig;

    public function __construct(private Config $config)
    {
        $themeName = $config->get('theme', 'default');
        $themePath = ROOT . '/themes/' . $themeName . '/templates';

        $loader = new FilesystemLoader($themePath);

        $cache = ($config->get('cache', false))
            ? ROOT . '/var/cache'
            : false;

        $this->twig = new Environment($loader, [
            'cache'       => $cache,
            'auto_reload' => true,
        ]);

        // Useful filters
        $this->twig->addFilter(new TwigFilter('excerpt', function (string $html, int $words = 30): string {
            $text  = strip_tags($html);
            $parts = explode(' ', $text);
            if (count($parts) <= $words) return $text;
            return implode(' ', array_slice($parts, 0, $words)) . '…';
        }));
    }

    public function render(Page $page): string
    {
        $template = $page->template . '.html';

        // Fallback to page.html if specific template missing
        if (!file_exists(ROOT . '/themes/' . $this->config->get('theme', 'default') . '/templates/' . $template)) {
            $template = 'page.html';
        }

        return $this->twig->render($template, [
            'page'        => $page,
            'site'        => $this->config->all(),
            'footer_html' => $this->loadFooter(),
        ]);
    }

    private function loadFooter(): string
    {
        $file = ROOT . '/content/footer.md';
        if (!is_file($file)) return '';

        $parser = new CommonMarkConverter([
            'html_input'         => 'allow',
            'allow_unsafe_links' => false,
        ]);

        $raw = file_get_contents($file);

        // Strip front matter if present
        if (str_starts_with(ltrim($raw), '---')) {
            $raw = ltrim($raw);
            $end = strpos($raw, '---', 3);
            if ($end !== false) {
                $raw = substr($raw, $end + 3);
            }
        }

        // Substitute {{ year }} and {{ author }} before parsing
        $vars = [
            '{{ year }}'   => date('Y'),
            '{{ author }}' => $this->config->get('author') ?? $this->config->get('title', ''),
        ];
        $raw = strtr($raw, $vars);

        return $parser->convert($raw)->getContent();
    }
}
