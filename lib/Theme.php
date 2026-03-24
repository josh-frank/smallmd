<?php
declare(strict_types=1);

namespace SmallMD;

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
            'page'   => $page,
            'site'   => $this->config->all(),
        ]);
    }
}
