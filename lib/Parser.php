<?php
declare(strict_types=1);

namespace SmallMD;

use League\CommonMark\CommonMarkConverter;
use Symfony\Component\Yaml\Yaml;

class Parser
{
    private CommonMarkConverter $md;

    public function __construct()
    {
        $this->md = new CommonMarkConverter([
            'html_input'         => 'allow',
            'allow_unsafe_links' => false,
        ]);
    }

    public function parse(string $filepath): Page
    {
        $raw  = file_get_contents($filepath);
        $meta = [];
        $body = $raw;

        // Strip front matter: ---\n...\n---
        if (str_starts_with(ltrim($raw), '---')) {
            $raw = ltrim($raw);
            $end = strpos($raw, '---', 3);
            if ($end !== false) {
                $yaml = substr($raw, 3, $end - 3);
                $body = substr($raw, $end + 3);
                $meta = Yaml::parse($yaml) ?? [];
            }
        }

        $html = $this->md->convert($body)->getContent();

        // Auto-generate title from first H1 if not in front matter
        if (empty($meta['title'])) {
            if (preg_match('/<h1[^>]*>(.*?)<\/h1>/i', $html, $m)) {
                $meta['title'] = strip_tags($m[1]);
            }
        }

        // Build nav from content/ directory
        $nav = $this->buildNav();

        return new Page(
            title:    $meta['title']    ?? 'Untitled',
            template: $meta['template'] ?? 'page',
            date:     $meta['date']     ?? null,
            meta:     $meta,
            body:     $html,
            nav:      $nav,
        );
    }

    private function buildNav(): array
    {
        $nav   = [];
        $files = glob(ROOT . '/content/*.md') ?: [];

        foreach ($files as $file) {
            $slug = basename($file, '.md');
            if ($slug === '404') continue;

            // Quick scan for title in front matter only
            $raw   = file_get_contents($file);
            $title = $slug;

            if (str_starts_with(ltrim($raw), '---')) {
                $raw = ltrim($raw);
                $end = strpos($raw, '---', 3);
                if ($end !== false) {
                    $yaml  = substr($raw, 3, $end - 3);
                    $meta  = Yaml::parse($yaml) ?? [];
                    $title = $meta['title'] ?? $slug;
                }
            }

            $nav[] = [
                'slug'  => $slug === 'index' ? '/' : '/' . $slug,
                'title' => $title,
                'order' => $meta['nav_order'] ?? 99,
            ];
        }

        usort($nav, fn($a, $b) => $a['order'] <=> $b['order']);
        return $nav;
    }
}
