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

        // Build TOC and inject heading anchors if toc: true in front matter
        $toc = '';
        if (!empty($meta['toc'])) {
            [$html, $toc] = $this->buildToc($html);
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
            toc:      $toc,
        );
    }

    /**
     * Inject id attributes into h2/h3 headings and return [annotated $html, $tocHtml].
     * h1 is skipped — it's the page title and already rendered by the template.
     *
     * @return array{string, string}
     */
    private function buildToc(string $html): array
    {
        $slugCounts = [];
        $entries    = [];

        // Add id="" to every h2/h3, collecting entries for the TOC list
        $html = preg_replace_callback(
            '/<(h[23])([^>]*)>(.*?)<\/h[23]>/is',
            function (array $m) use (&$slugCounts, &$entries): string {
                $tag   = $m[1];           // h2 or h3
                $attrs = $m[2];
                $inner = $m[3];

                $text = strip_tags($inner);
                $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));

                // Deduplicate: "intro", "intro-2", "intro-3" …
                if (isset($slugCounts[$slug])) {
                    $slugCounts[$slug]++;
                    $slug .= '-' . $slugCounts[$slug];
                } else {
                    $slugCounts[$slug] = 1;
                }

                $entries[] = ['tag' => $tag, 'id' => $slug, 'text' => $text];

                return "<{$tag}{$attrs} id=\"{$slug}\">{$inner}</{$tag}>";
            },
            $html
        );

        if (empty($entries)) {
            return [$html, ''];
        }

        // Build a flat <ul> with one level of nesting: h2 = top, h3 = indented
        $items = '';
        foreach ($entries as $entry) {
            $indent = ($entry['tag'] === 'h3') ? ' class="toc-sub"' : '';
            $items .= "<li{$indent}><a href=\"#{$entry['id']}\">{$entry['text']}</a></li>\n";
        }

        $toc = "<nav class=\"toc\">\n<ul>\n{$items}</ul>\n</nav>\n";

        return [$html, $toc];
    }

    private function buildNav(): array
    {
        $nav   = [];
        $files = glob(ROOT . '/content/*.md') ?: [];

        foreach ($files as $file) {
            $slug = basename($file, '.md');
            if (in_array($slug, ['404', 'footer'])) continue;

            // Reset $meta and $title each iteration so navbar
            // key doesn't bleed from one file into the next
            // when no front matter present
            $meta  = [];
            $title = $slug;
            $raw   = file_get_contents($file);

            if (str_starts_with(ltrim($raw), '---')) {
                $raw = ltrim($raw);
                $end = strpos($raw, '---', 3);
                if ($end !== false) {
                    $yaml  = substr($raw, 3, $end - 3);
                    $meta  = Yaml::parse($yaml) ?? [];
                    $title = $meta['title'] ?? $slug;
                }
            }

            // Only include in nav if navbar: true is set in front matter
            if (empty($meta['navbar'])) continue;

            $nav[] = [
                'slug'  => $slug === 'index' ? '/' : '/' . $slug,
                'title' => $title,
                'order' => $meta['nav_order'] ?? 99,
            ];
        }

        usort($nav, fn($a, $b) => $a['order'] <=> $b['order']);
        return $nav;
    }

    public function renderFooter(): string
    {
        $file = ROOT . '/content/footer.md';
        if (!is_file($file)) return '';

        $raw = file_get_contents($file);

        // Strip front matter if present
        if (str_starts_with(ltrim($raw), '---')) {
            $raw = ltrim($raw);
            $end = strpos($raw, '---', 3);
            if ($end !== false) {
                $raw = substr($raw, $end + 3);
            }
        }

        return $this->md->convert($raw)->getContent();
    }
}
