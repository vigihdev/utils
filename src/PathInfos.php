<?php

namespace vigihdev\utils;

final class PathInfos
{
    /** @var string $dirname */
    public $dirname;

    /** @var string $basename */
    public $basename;

    /** @var string $extension */
    public $extension;

    /** @var string $filename */
    public $filename;

    /** @var string $path */
    private $path;

    public static function from(string $path): self
    {
        return new self($path);
    }

    public function __construct(string $path)
    {
        $this->path = $path;
        foreach (pathinfo($path) as $prop => $value) {
            if (property_exists($this, $prop)) {
                $this->$prop = $value;
            }
        }
    }

    public function isDir(): bool
    {
        return is_dir($this->path);
    }

    public function isFile(): bool
    {
        return !$this->isDir() && file_exists($this->path);
    }

    public function isFilePhp(): bool
    {
        return $this->isFile() && substr($this->path, -4, 4) === '.php';
    }
}
