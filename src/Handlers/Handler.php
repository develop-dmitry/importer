<?php

namespace Kozlov\Importer\Handlers;

abstract class Handler
{

    protected string $enumSeparator = ',';

    protected string $childSeparator = '>';

    protected array $errors = [];

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function import(): void
    {
        $this->setTitle($this->data['title']);
        $this->setContent($this->data['content']);
        $this->setPrice($this->data['price']);
        $this->setThumbnail($this->data['thumbnail']);
        $this->setCategories($this->data['categories']);
        $this->setAttributes($this->data['attributes']);
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    protected function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    protected abstract function setTitle(string $title);

    protected abstract function setContent(string $content);

    protected abstract function setPrice(string $price);

    protected abstract function setAttributes(string $attributes);

    protected abstract function setThumbnail(string $thumbnail);

    protected abstract function setCategories(string $categories);
}