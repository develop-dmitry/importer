<?php

namespace Kozlov\Importer\Services;

interface ProductService
{

    public function findOrCreate(string $key): int;

    public function publish(string $id): bool;

    public function updateTitle(string $id, string $title): bool;

    public function updateContent(string $id, string $content): bool;

    public function updatePrice(string $id, string $price): bool;

    public function updateThumbnail(string $id, string $thumbnail): bool;

    public function updateCategories(string $id, array $categories): bool;

    public function updateAttributes(string $id, array $attributes): bool;

    public function findOrCreateCategory(string $name, int $parent = 0): int;

    public function findOrCreateAttribute(string $name): array;
}