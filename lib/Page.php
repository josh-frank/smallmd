<?php
declare(strict_types=1);

namespace SmallMD;

class Page
{
    public function __construct(
        public readonly string  $title,
        public readonly string  $template,
        public readonly mixed   $date,
        public readonly array   $meta,
        public readonly string  $body,
        public readonly array   $nav,
        public readonly string  $toc = '',
        public readonly string  $notes = '',
    ) {}
}
