<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use App\Models\ProductCategory;
use App\Models\ShowcaseProduct;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The product showcase, now that it carries Amad Craft's real catalogue.
 *
 * Two things are being protected here.
 *
 * The first is §2.2, which is the reason this site is not the store: no
 * price, no currency, no stock, no purchase. The importer reads all of those
 * off the storefront's JSON-LD and drops them, and the page must never grow a
 * way to show them. That is a rule about the product, not about the code, so
 * it is asserted against the rendered HTML rather than against a method.
 *
 * The second is that filtering and paging happen on the server. With a few
 * hundred products, "send everything and let Vue hide most of it" is both a
 * page-weight problem (§15.1) and an indexing one (§13) — a crawler cannot
 * press a filter button.
 */
class ProductCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([StructureSeeder::class, NavigationSeeder::class]);

        Page::query()->update(['status' => 'published', 'published_at' => now()]);
    }

    private function category(string $slug, string $name, int $order = 0): ProductCategory
    {
        $category = ProductCategory::query()->create([
            'slug' => $slug, 'sort_order' => $order, 'is_active' => true,
        ]);

        $category->translations()->create(['locale' => 'ar', 'name' => $name]);

        return $category;
    }

    private function products(int $count, ?ProductCategory $category = null, string $prefix = 'p'): void
    {
        for ($i = 0; $i < $count; $i++) {
            $product = ShowcaseProduct::query()->create([
                'slug' => "{$prefix}-{$i}",
                'product_category_id' => $category?->id,
                'sort_order' => $i,
                'is_active' => true,
                'external_store_url' => "https://amadcraft.sa/products/{$prefix}-{$i}",
            ]);

            $product->translations()->create(['locale' => 'ar', 'name' => "منتج {$prefix} {$i}"]);
        }
    }

    #[Test]
    public function the_listing_pages_rather_than_sending_the_whole_catalogue(): void
    {
        $this->products(30);

        $props = $this->get('/ar/products')->assertOk()->viewData('page')['props'];

        $this->assertCount(24, $props['products']);
        $this->assertSame(2, $props['pagination']['last']);
        $this->assertSame(30, $props['pagination']['total']);
        $this->assertNotNull($props['pagination']['next']);
        $this->assertNull($props['pagination']['prev']);
    }

    #[Test]
    public function the_second_page_carries_the_remainder(): void
    {
        $this->products(30);

        $props = $this->get('/ar/products?page=2')->assertOk()->viewData('page')['props'];

        $this->assertCount(6, $props['products']);
        $this->assertNotNull($props['pagination']['prev']);
        $this->assertNull($props['pagination']['next']);
    }

    #[Test]
    public function a_category_filter_is_applied_on_the_server(): void
    {
        $gifts = $this->category('store-1', 'الهدايا');
        $office = $this->category('store-2', 'مستلزمات المكتب', 1);

        $this->products(3, $gifts, 'g');
        $this->products(5, $office, 'o');

        $props = $this->get('/ar/products?category=store-1')->assertOk()->viewData('page')['props'];

        $this->assertCount(3, $props['products']);
        $this->assertSame('store-1', $props['activeCategory']);
    }

    #[Test]
    public function an_unknown_category_is_a_404_not_the_whole_catalogue(): void
    {
        // Answering 200 with everything is a soft-404: search engines index a
        // URL that means nothing, and a mistyped link looks like it worked.
        $this->products(3);

        $this->get('/ar/products?category=does-not-exist')->assertNotFound();
    }

    #[Test]
    public function an_empty_category_is_not_offered_as_a_filter(): void
    {
        $full = $this->category('store-1', 'الهدايا');
        $this->category('store-2', 'فارغ', 1);

        $this->products(2, $full, 'g');

        $props = $this->get('/ar/products')->assertOk()->viewData('page')['props'];

        $this->assertSame(['store-1'], array_column($props['categories'], 'slug'));
    }

    #[Test]
    public function the_view_all_count_includes_products_that_sit_in_no_category(): void
    {
        // Summing the category chips under-reported the total, so "view all"
        // promised fewer products than it listed.
        $gifts = $this->category('store-1', 'الهدايا');

        $this->products(3, $gifts, 'g');
        $this->products(2, null, 'loose');

        $props = $this->get('/ar/products')->assertOk()->viewData('page')['props'];

        $this->assertSame(5, $props['totalCount']);
        $this->assertSame(3, $props['categories'][0]['count']);
    }

    #[Test]
    public function a_category_page_canonicalises_to_itself(): void
    {
        $gifts = $this->category('store-1', 'الهدايا');
        $this->products(2, $gifts, 'g');

        $props = $this->get('/ar/products?category=store-1')->assertOk()->viewData('page')['props'];

        $this->assertStringEndsWith('/ar/products?category=store-1', $props['seo']['canonical']);
    }

    #[Test]
    public function the_page_shows_no_price_stock_or_purchase_action(): void
    {
        $this->products(3);

        $body = $this->get('/ar/products')->assertOk()->getContent();

        foreach (['ر.س', 'SAR', 'أضف للسلة', 'add to cart', 'اشتر', 'السعر', 'متبقي'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $body,
                "The showcase rendered [{$forbidden}]. §2.2 puts every commercial function out of scope.",
            );
        }
    }

    #[Test]
    public function the_product_resource_exposes_no_commercial_field(): void
    {
        $this->products(1);

        $props = $this->get('/ar/products')->assertOk()->viewData('page')['props'];
        $keys = array_keys($props['products'][0]);

        foreach (['price', 'currency', 'stock', 'quantity', 'availability', 'sku'] as $forbidden) {
            $this->assertNotContains($forbidden, $keys);
        }
    }

    #[Test]
    public function an_uploaded_image_records_its_own_dimensions(): void
    {
        // MediaResource passes width/height to every <img> so the browser can
        // reserve the box (§15.1). Nothing was writing them, so every image on
        // the site shipped without them and shifted the layout as it loaded.
        $this->products(1);

        $product = ShowcaseProduct::query()->sole();
        $media = $product->addMedia(UploadedFile::fake()->image('piece.jpg', 640, 480))
            ->toMediaCollection('primary');

        $this->assertSame(640, $media->fresh()->getCustomProperty('width'));
        $this->assertSame(480, $media->fresh()->getCustomProperty('height'));
    }
}
