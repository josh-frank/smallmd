<?php
declare(strict_types=1);

namespace SmallMD;

use Symfony\Component\Yaml\Yaml;

class Config
{
    private array $data;

    private function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function load(string $path): self
    {
        if (!is_file($path)) {
            return new self([]);
        }
        $data = Yaml::parseFile($path) ?? [];
        return new self($data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->data;
    }
}
