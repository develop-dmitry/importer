<?php

namespace Kozlov\Importer\Handlers;

use Kozlov\Importer\Services\ProductService;

class ProductHandler extends Handler
{

    protected array $relation = [
        'unique_id' => 'unique_id',
        'title' => 'title',
        'content' => 'content',
        'price' => 'price',
        'thumbnail' => 'thumbnail',
        'categories' => 'categories',
        'attributes' => 'attributes'
    ];

    protected int $id;

    protected ProductService $service;

    public function __construct(array $data, ProductService $service)
    {
        parent::__construct($data);

        $this->service = $service;
    }

    public function import(): void
    {
        $this->id = $this->service->findOrCreate($this->unique_id);

        $this->setTitle();
        $this->setContent();
        $this->setPrice();
        $this->setThumbnail();
        $this->setCategories();
        $this->setAttributes();

        if (!$this->hasErrors()) {
            $this->publish();
        }
    }

    public function __get(string $name)
    {
        return $this->data[$this->relation[$name]];
    }

    protected function publish(): void
    {
        if (!$this->service->publish($this->id)) {
            $this->addError('Не удалось опубликовать товар');
        }
    }

    protected function setTitle(): void
    {
        if (!$this->service->updateTitle($this->id, $this->title)) {
            $this->addError('Не удалось обновить название товара');
        }
    }

    protected function setContent(): void
    {
        if (!$this->service->updateContent($this->id, $this->content)) {
            $this->addError('Не удалось обновить описание товара');
        }
    }

    protected function setPrice(): void
    {
        if (!$this->service->updatePrice($this->id, $this->price)) {
            $this->addError('Не удалось обновить цену товара');
        }
    }

    protected function setThumbnail(): void
    {
        if (!$this->service->updateThumbnail($this->id, $this->thumbnail)) {
            $this->addError('Не удалось установить миниатюру для товара');
        }
    }

    protected function setCategories(): void
    {
        $categories = [];

        foreach (explode($this->enumSeparator, $this->categories) as $item) {
            $item = explode($this->childSeparator, $item);

            if (empty($item)) {
                continue;
            }

            $parent = 0;

            foreach ($item as $child) {
                $parent = $this->service->findOrCreateCategory($child, $parent);
            }

            $categories[] = $parent;
        }

        if (!$this->service->updateCategories($this->id, $categories)) {
            $this->addError('Не удалось обновить категории товара');
        }
    }

    protected function setAttributes(): void
    {
        $attributes = [];

        foreach (explode($this->enumSeparator, $this->attributes) as $item) {
            $item = explode($this->childSeparator, $item);

            if (count($item) !== 2) {
                continue;
            }

            $name = $item[0];
            $value = $item[1];

            $attribute = $this->service->findOrCreateAttribute($name);

            if (!isset($attributes[$attribute['slug']])) {
                $attributes[$attribute['slug']] = [
                    'id' => $attribute['id'],
                    'options' => []
                ];
            }

            $attributes[$attribute['slug']]['options'][] = $value;
        }

        if (!$this->service->updateAttributes($this->id, $attributes)) {
            $this->addError('Не удалось обновить атрибуты товара');
        }
    }
}