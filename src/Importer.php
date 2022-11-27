<?php

namespace Kozlov\Importer;

use Kozlov\Importer\Exceptions\FileNotFoundException;
use Kozlov\Importer\Exceptions\HandlerNotFoundException;
use Kozlov\Importer\Handlers\Handler;

class Importer
{

    protected string $path;

    protected string $handler;

    protected int $start = 1;

    protected string $elemSeparator = ';';

    protected int $chunk = 5;

    public function __construct(string $path, string $handler)
    {
        $this->path = $path;
        $this->handler = $handler;
    }

    /**
     * @throws HandlerNotFoundException
     * @throws FileNotFoundException
     */
    public function import(): array
    {
        if (!file_exists($this->path)) {
            throw new FileNotFoundException('Файл для импорт не найден');
        }

        if (!class_exists($this->handler) || !($this->handler instanceof Handler)) {
            throw new HandlerNotFoundException('Обработчик импорта не найден');
        }

        $imported = $this->read();

        if (empty($imported)) {
            return [
                'status' => true,
                'message' => 'Импорт завершен'
            ];
        }

        $class = $this->handler;
        $response = ['status' => true];

        foreach ($imported as $import) {
            $handler = new $class($import);
            $handler->import();

            if ($handler->hasErrors()) {
                $response['status'] = false;

                $response['errors'][] = [
                    'unique_id' => $import['unique_id'],
                    'title' => $import['title'],
                    'messages' => $handler->getErrors()
                ];
            }
        }

        return $response;
    }

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

    protected function setPage(int $page): void
    {
        $this->start = $page * $this->chunk - 5 + 1;
    }
}