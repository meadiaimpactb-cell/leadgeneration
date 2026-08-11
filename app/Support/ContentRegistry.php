<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ImpactMetric;
use App\Models\Partner;
use App\Models\ProductCategory;
use App\Models\Report;
use App\Models\Sector;
use App\Models\ShowcaseProduct;
use App\Models\Solution;
use App\Models\Story;
use App\Models\TrainingProgram;
use InvalidArgumentException;

/**
 * Declares the content entities the admin panel manages generically.
 *
 * Solutions, products, stories, reports, programmes and partners are all the
 * same shape — translated fields, optional media, an active flag and an order
 * — so they share one controller and one form (§9.1). This registry is what
 * makes that one screen know which fields to draw.
 *
 * `fields` are per-locale (they live in the translation table).
 * `attributes` are shared across locales (they live on the base row).
 */
class ContentRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'solutions' => [
                'model' => Solution::class,
                'permission' => 'solutions.manage',
                'flag' => 'is_active',
                'media' => ['hero'],
                'fields' => [
                    'name' => 'text',
                    'summary' => 'textarea',
                    'body' => 'richtext',
                    'meta_title' => 'text',
                    'meta_description' => 'textarea',
                ],
                'attributes' => [
                    'slug' => 'slug',
                    'icon' => 'text',
                ],
                /*
                 * The audience segments this solution is offered to.
                 *
                 * "القطاعات" stopped being a content type of its own in the
                 * sidebar and became this picker. The records are unchanged —
                 * each segment still has its own page and its own editor,
                 * listed under Solutions — so this says which solutions speak
                 * to which audience; it does not flatten a segment into a tag.
                 */
                'taxonomies' => [
                    'sectors' => [
                        'entity' => 'sectors',
                        'relation' => 'sectors',
                        'table' => 'sectors',
                    ],
                ],
                'hasSections' => true,
            ],

            'sectors' => [
                'model' => Sector::class,
                'permission' => 'sectors.manage',
                'flag' => 'is_active',
                'media' => ['hero'],
                // `key` is intentionally absent: the four segments are fixed
                // by §3 and code branches on it. Their slug and copy are free.
                'fields' => [
                    'name' => 'text',
                    'summary' => 'textarea',
                    'body' => 'richtext',
                    'meta_title' => 'text',
                    'meta_description' => 'textarea',
                ],
                'attributes' => [
                    'slug' => 'slug',
                    'icon' => 'text',
                ],
                'creatable' => false,
                'deletable' => false,
                'hasSections' => true,
            ],

            'product-categories' => [
                'model' => ProductCategory::class,
                'permission' => 'products.manage',
                'flag' => 'is_active',
                'media' => [],
                'fields' => ['name' => 'text'],
                'attributes' => ['slug' => 'slug'],
            ],

            'products' => [
                'model' => ShowcaseProduct::class,
                'permission' => 'products.manage',
                'flag' => 'is_active',
                'media' => ['primary', 'gallery'],
                'fields' => [
                    'name' => 'text',
                    'craft_technique' => 'text',
                    'description' => 'textarea',
                    'meta_title' => 'text',
                    'meta_description' => 'textarea',
                ],
                'attributes' => [
                    'slug' => 'slug',
                    'product_category_id' => 'relation:product-categories',
                    // Informational store link only — never a price or a
                    // purchase field (§2.2, §4).
                    'external_store_url' => 'url',
                ],
            ],

            'impact-metrics' => [
                'model' => ImpactMetric::class,
                'permission' => 'impact.manage',
                'flag' => 'is_active',
                'media' => [],
                'fields' => [
                    'label' => 'text',
                    'note' => 'text',
                ],
                'attributes' => [
                    'key' => 'text',
                    'value_numeric' => 'number',
                    'value_suffix' => 'text',
                    'year' => 'number',
                    'sector_id' => 'relation:sectors',
                ],
            ],

            'stories' => [
                'model' => Story::class,
                'permission' => 'stories.manage',
                'flag' => 'is_published',
                'media' => ['person'],
                'fields' => [
                    'title' => 'text',
                    'quote' => 'textarea',
                    'attribution' => 'text',
                    'body' => 'richtext',
                ],
                'attributes' => ['slug' => 'slug'],
            ],

            'reports' => [
                'model' => Report::class,
                'permission' => 'reports.manage',
                'flag' => 'is_public',
                'media' => ['cover', 'file'],
                'fields' => [
                    'title' => 'text',
                    'summary' => 'textarea',
                ],
                'attributes' => [
                    'slug' => 'slug',
                    'year' => 'number',
                ],
            ],

            'training-programs' => [
                'model' => TrainingProgram::class,
                'permission' => 'training.manage',
                'flag' => 'is_active',
                'media' => ['hero'],
                'fields' => [
                    'name' => 'text',
                    'summary' => 'textarea',
                    'outcomes' => 'textarea',
                    'body' => 'richtext',
                ],
                'attributes' => [
                    'slug' => 'slug',
                    'duration_weeks' => 'number',
                ],
            ],

            'partners' => [
                'model' => Partner::class,
                'permission' => 'partners.manage',
                'flag' => 'is_active',
                'media' => ['logo'],
                'fields' => [
                    'display_name' => 'text',
                    'note' => 'text',
                ],
                'attributes' => [
                    'name' => 'text',
                    'type' => 'enum:partner,accreditation,client',
                    'website_url' => 'url',
                    'sector_id' => 'relation:sectors',
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function get(string $entity): array
    {
        $all = self::all();

        if (! isset($all[$entity])) {
            throw new InvalidArgumentException("Unknown content entity [{$entity}].");
        }

        return $all[$entity] + [
            'creatable' => true,
            'deletable' => true,
            'hasSections' => false,
        ];
    }

    public static function exists(string $entity): bool
    {
        return isset(self::all()[$entity]);
    }
}
