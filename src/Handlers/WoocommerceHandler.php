<?php

namespace Kozlov\Importer\Handlers;

use Kozlov\Importer\Exceptions\HandlerNotFoundException;

class WoocommerceHandler extends Handler
{

    protected string $uniqueKey = 'unique_id';

    protected int $id;

    /**
     * @throws HandlerNotFoundException
     */
    public function import(): void
    {
        $this->setID();

        parent::import();

        if (!$this->hasErrors()) {
            $this->publish();
        }
    }

    /**
     * @throws HandlerNotFoundException
     */
    protected function setID(): void
    {
        $id = $this->getID();

        if (!$id) {
            $id = $this->createEntity();

            if (!$id) {
                throw new HandlerNotFoundException('Не удалось найти импортируемую сущность');
            }
        }

        $this->id = $id;
    }

    protected function getID(): int|false
    {
        if (!class_exists('WP_Query')) {
            return false;
        }

        $query = new WP_Query([
            'post_type' => 'product',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => $this->uniqueKey,
                    'value' => $this->data[$this->uniqueKey]
                ]
            ]
        ]);

        if ($query->have_posts()) {
            return $query->get_posts()[0]->ID;
        }

        return false;
    }

    protected function createEntity(): int|false
    {
        if (
            !function_exists('wp_insert_post') ||
            !function_exists('get_current_user_id') ||
            !function_exists('update_post_meta')
        ) {
            return false;
        }

        $id = wp_insert_post([
            'post_title' => 'Импортируемый товар',
            'post_type' => 'product',
            'post_status' => 'draft',
            'post_author' => get_current_user_id()
        ]);

        if (!is_int($id)) {
            return false;
        }

        update_post_meta($id, $this->uniqueKey, $this->data[$this->uniqueKey);

        return $id;
    }

    protected function publish(): void
    {
        $args = [
            'ID' => $this->id,
            'post_status' => 'publish'
        ];

        if (!$this->updatePost($args)) {
            $this->addError('Не удалось опубликовать товар');
        }
    }

    protected function setTitle(string $title): void
    {
        $params = [
            'ID' => $this->id,
            'post_title' => $title
        ];

        if (!$this->updatePost($params)) {
            $this->addError('Не удалось обновить название товара');
        }
    }

    protected function setContent(string $content)
    {
        $params = [
            'ID' => $this->id,
            'post_content' => $content
        ];

        if (!$this->updatePost($params)) {
            $this->addError('Не удалось обновить описание товара');
        }
    }

    protected function setPrice(string $price)
    {
        if (!class_exists('WC_Product')) {
            $this->addError('Не удалось обновить цену товара');
        }

        $product = new WC_Product($this->id);

        $product->set_price($price);
        $product->set_regular_price($price);
        $product->set_sale_price('');

        $product->save();
    }

    protected function setAttributes(string $data)
    {
        if (
            !class_exists('WC_Product') ||
            !class_exists('WC_Product_Attribute')

        ) {
            $this->addError('Не удалось обновить атрибуты товара');
        }

        $product = new WC_Product($this->id);

        $taxonomies = [];

        foreach (explode($this->enumSeparator, $data) as $item) {
            $item = explode($this->childSeparator, $item);

            if (count($item) < 2) {
                continue;
            }

            $name = $item[0];
            $value = $item[1];

            $attribute = $this->getAttribute($name);

            if (!isset($taxonomies[$attribute->slug])) {
                $taxonomies[$attribute->slug] = [
                    'id' => $attribute->id,
                    'options' => []
                ];
            }

            $taxonomies[$attribute->slug]['options'][] = $value;
        }

        $attributes = [];

        foreach ($taxonomies as $slug => $taxonomy) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_id($taxonomy['id']);
            $attribute->set_name($slug);
            $attribute->set_options($taxonomy['options']);

            $attributes[] = $attribute;
        }

        $product->set_attributes($attributes);

        $product->save();
    }

    protected function getAttribute(string $name): stdClass|false
    {
        if (
            !function_exists('wc_attribute_taxonomy_id_by_name') ||
            !function_exists('wc_get_attribute')
        ) {
            return false;
        }

        $id = wc_attribute_taxonomy_id_by_name($name);

        if ($id === 0) {
            $id = $this->createAttribute($name);
        }

        if (!$id) {
            return false;
        }

        return wc_get_attribute($id);
    }

    protected function createAttribute(string $name): int|false
    {
        if (!function_exists('wc_create_attribute')) {
            return false;
        }

        return wc_create_attribute([
            'name' => $name
        ]);
    }

    protected function setThumbnail(string $thumbnail)
    {
        if (
            !function_exists('media_sideload_image') ||
            !function_exists('set_post_thumbnail') ||
            !function_exists('is_wp_error')
        ) {
            $this->addError('Не удалось обновить миниатюру товара');
        }

        $thumbnail = media_sideload_image($thumbnail, $this->id, '', 'id');

        if (is_wp_error($thumbnail)) {
            $this->addError('Не удалось загрузить миниатюру товара');
        }

        if (!set_post_thumbnail($this->id, $thumbnail)) {
            $this->addError('Не удалось установить миниатюру для товара');
        }
    }

    protected function setCategories(string $categories)
    {
        $categoryIDs = [];

        if (!empty($categories)) {
            foreach (explode($this->enumSeparator, $categories) as $item) {
                $item = explode($this->childSeparator, $item);

                if (empty($item)) {
                    continue;
                }

                $parent = 0;

                foreach ($item as $child) {
                    if ($parent === false) {
                        continue;
                    }

                    $parent = $this->getCategory($child, $parent);
                }

                if ($parent) {
                    $categoryIDs[] = $parent;
                }
            }
        }

        if (!$this->updatePostTerms('product_cat', $categoryIDs)) {
            $this->addError('Не удалось обновить категории товара');
        }
    }

    protected function getCategory(string $name, int $parent = 0): int|false
    {
        if (
            !function_exists('get_terms') ||
            !function_exists('wp_insert_term')
        ) {
            return false;
        }

        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'name' => $name,
            'get' => 'all',
            'parent' => $parent,
            'number' => 1,
            'update_term_meta_cache' => false,
            'suppress_filter' => true
        ]);

        if (empty($terms)) {
            $term = wp_insert_term($name, 'product_cat', [
                'parent' => $parent
            ]);

            return $term['term_id'];
        }

        return $terms[0]->term_id;
    }

    protected function updatePostTerms(string $taxonomy, array $terms): bool
    {
        if (
            !function_exists('wp_set_post_terms') ||
            !function_exists('is_wp_error')
        ) {
            return false;
        }

        $isUpdate = wp_set_post_terms($this->id, $terms, $taxonomy);

        return !is_wp_error($isUpdate) && $isUpdate !== false;
    }

    protected function updatePost(array $data): bool
    {
        if (
            !function_exists('wp_update_post') ||
            !function_exists('wp_slash')
        ) {
            return false;
        }

        return is_int(wp_update_post(wp_slash($data)));
    }
}