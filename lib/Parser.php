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

        // Build TOC if toc: true in front matter
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
     * Walk the rendered HTML with DOMDocument to find h2/h3 in document order.
     * Adds id attributes to each heading in the body, and returns a nested TOC.
     *
     * @return array{string, string} [annotated body html, toc html]
     */
    private function buildToc(string $html): array
    {
        // DOMDocument gives us nodes in document order — no regex tricks needed
        $dom = new \DOMDocument();
        @$dom->loadHTML(
            '<?xml encoding="utf-8"?><div id="toc-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        $xpath    = new \DOMXPath($dom);
        $headings = $xpath->query('//div[@id="toc-root"]//*[self::h2 or self::h3]');

        if ($headings->length === 0) {
            return [$html, ''];
        }

        $slugCounts = [];
        $entries    = [];

        foreach ($headings as $node) {
            $text = $node->textContent;
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $text), '-'));

            // Deduplicate slugs
            if (isset($slugCounts[$slug])) {
                $slugCounts[$slug]++;
                $slug .= '-' . $slugCounts[$slug];
            } else {
                $slugCounts[$slug] = 1;
            }

            $node->setAttribute('id', $slug);
            $entries[] = ['tag' => $node->nodeName, 'id' => $slug, 'text' => $text];
        }

        // Serialize back — extract just the inner HTML of our wrapper div
        $root      = $xpath->query('//div[@id="toc-root"]')->item(0);
        $innerHtml = '';
        foreach ($root->childNodes as $child) {
            $innerHtml .= $dom->saveHTML($child);
        }

        // Build TOC: h2 = top-level <li>, h3 = nested <li> inside a <ul> under its parent h2
        $toc    = "<nav class=\"toc\">\n<ul>\n";
        $inSub  = false;
        $openH2 = false;

        foreach ($entries as $entry) {
            $link = '<a href="#' . $entry['id'] . '">' . htmlspecialchars($entry['text']) . '</a>';

            if ($entry['tag'] === 'h2') {
                if ($inSub)  { $toc .= "</ul></li>\n"; $inSub = false; }
                elseif ($openH2) { $toc .= "</li>\n"; }
                $toc   .= '<li>' . $link;
                $openH2 = true;
            } else {
                // h3
                if (!$inSub) { $toc .= "\n<ul>\n"; $inSub = true; }
                $toc .= '<li>' . $link . "</li>\n";
            }
        }

        if ($inSub)       { $toc .= "</ul></li>\n"; }
        elseif ($openH2)  { $toc .= "</li>\n"; }

        $toc .= "</ul>\n</nav>\n";

        return [$innerHtml, $toc];
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
