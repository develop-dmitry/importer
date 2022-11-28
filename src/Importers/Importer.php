<?php

namespace Kozlov\Importer\Importers;

abstract class Importer
{

    protected string $elemSeparator = ';';

    protected int $chunk = 5;

    protected string $path;

    protected string $handler;

    protected int $start;

    public function __construct(string $path, string $handler)
    {
        $this->path = $path;
        $this->handler = $handler;
        $this->start = 1;
    }

    public function setPage(int $page): void
    {
        $this->start = $page * $this->chunk - 5 + 1;
    }

    public abstract function import(): array;

    protected function read(): array
    {
        return $this->constructAssociatedArray(file($this->path));
    }

    protected function constructAssociatedArray(array $strings): array
    {
        $result = [];

        $keys = explode($this->elemSeparator, $strings[0]);

        $keys = array_map(function ($key) {
            return trim($key);
        }, $keys);

        $chunk = array_splice($strings, $this->start, $this->chunk);

        foreach ($chunk as $item) {
            $values = [];
            $item = explode($this->elemSeparator, $item);

            foreach ($item as $index => $value) {
                $values[$keys[$index]] = trim($value);
            }

            $result[] = $values;
        }

        return $result;
    }
}