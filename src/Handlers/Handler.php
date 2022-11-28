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

    public abstract function import(): void;

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
}