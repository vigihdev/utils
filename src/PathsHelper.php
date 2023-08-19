<?php

namespace vigihdev\utils;

final class PathsHelper
{

    /** @var array|string $items */
    private $items;

    /**
     * 
     * @param string $path
     * @return self
     */
    public static function from(string $path): self
    {
        return new self($path);
    }

    public function __construct(string $path)
    {
        $this->items = explode('/', $path);
    }

    public function slice(int $offset, ?int $length = null): self
    {
        $this->items = array_slice($this->items, $offset, $length);
        return $this;
    }

    public function first(): ?string
    {
        return is_array($this->items) && count($this->items) > 0 ? current($this->items) : null;
    }

    public function end(): ?string
    {
        return is_array($this->items) && count($this->items) > 0 ? end($this->items) : null;
    }

    public function endOf(string $name): ?string
    {
        if (is_array($this->items) && ArrayHelper::isIndexed($this->items)) {
            $indexs = [];
            foreach ($this->items as $index => $item) {
                if ($name === $item) {
                    $indexs[] = $index;
                }
            }
            return !empty($indexs) ? $this->slice(0, end($indexs) + 1)->join('/')->getItem() : null;
        }
        return null;
    }

    public function startOf(string $name): ?string
    {
        if (is_array($this->items) && ArrayHelper::isIndexed($this->items)) {
            foreach ($this->items as $index => $item) {
                if ($name === $item) {
                    return $this->slice($index)->join('/')->getItem();
                }
            }
        }
        return null;
    }

    public function join(string $separator = ''): self
    {
        $this->items = implode($separator, $this->items);
        return $this;
    }

    public function length(): ?int
    {
        if (is_array($this->items)) {
            return count($this->items);
        }
        return is_string($this->items) ? strlen($this->items) : null;
    }

    public function getItem(): mixed
    {
        return $this->items;
    }
}
