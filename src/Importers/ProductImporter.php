<?php

namespace Kozlov\Importer\Importers;

use Kozlov\Importer\Handlers\ProductHandler;
use Kozlov\Importer\Importers\Exceptions\FileNotFoundException;
use Kozlov\Importer\Importers\Exceptions\HandlerNotFoundException;
use Kozlov\Importer\Importers\Exceptions\ServiceNotFoundException;
use Kozlov\Importer\Services\ProductService;

class ProductImporter extends Importer
{

    protected ?ProductService $service = null;

    /**
     * @throws HandlerNotFoundException
     */
    public function __construct(string $path, string $handler)
    {
        if (!class_exists($handler)) {
            throw new HandlerNotFoundException('Обработчик импорта не найден');
        }

        parent::__construct($path, $handler);
    }

    public function setService(ProductService $service) {
        $this->service = $service;
    }

    /**
     * @throws FileNotFoundException
     * @throws ServiceNotFoundException
     */
    public function import(): array
    {
        if (!file_exists($this->path)) {
            throw new FileNotFoundException('Файл для импорт не найден');
        }

        if (!$this->service) {
            throw new ServiceNotFoundException('Не найден сервис для импорта');
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
            $handler = new $class($import, $this->service);
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
}