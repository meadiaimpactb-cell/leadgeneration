<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use App\Models\ProductCategory;
use App\Models\ShowcaseProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The showcase stays a showcase.
 *
 * `/products` survived the navigation restructure on an explicit decision:
 * §2 permits showing the work "informationally, with no steer toward a direct
 * purchase", and 332 pieces across 16 category URLs are the site's largest
 * SEO asset. What was forbidden is the commercial catalogue, not the
 * portfolio.
 *
 * That decision only holds while the page keeps its side of it, so these are
 * the conditions written down: no price, no cart, no purchase path (§2.2);
 * every view ends at the one contact form (§6.1); the Zid link stays a small
 * aside for anyone who wants to buy a single piece; and the category URLs
 * stay in the sitemap.
 */
class ShowcaseIsNotAStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Page::query()->update(['status' => 'published', 'published_at' => now()]);
    }

    /**
     * A category with a product in it.
     *
     * Built here rather than looked up: the catalogue arrives from
     * `amad:import-store`, not from a seeder, so on a fresh test database
     * there are no categories at all — and these two tests silently skipped,
     * which is the same as not having written them. The fixture is small and
     * makes them run everywhere.
     */
    private function populatedCategory(): ProductCategory
    {
        $category = ProductCategory::query()->has('products')->first();

        if ($category !== null) {
            return $category;
        }

        $category = ProductCategory::query()->create([
            'slug' => 'test-category',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $category->translations()->create(['locale' => 'ar', 'name' => 'تصنيف للاختبار']);
        $category->translations()->create(['locale' => 'en', 'name' => 'Test category']);

        $product = ShowcaseProduct::query()->create([
            'slug' => 'test-piece',
            'product_category_id' => $category->id,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $product->translations()->create(['locale' => 'ar', 'name' => 'قطعة للاختبار']);
        $product->translations()->create(['locale' => 'en', 'name' => 'Test piece']);

        return $category;
    }

    #[Test]
    public function the_showcase_carries_no_price_and_no_purchase_control(): void
    {
        $body = $this->get('/ar/products')->assertOk()->getContent();

        foreach ([
            'ر.س', 'SAR', 'سعر', 'السعر',
            'أضف إلى السلة', 'add-to-cart', 'addToCart', 'اشترِ', 'اشتري', 'شراء',
            'data-price', 'itemprop="price"', 'name="quantity"',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $body,
                "The showcase renders a commercial control or a price: {$forbidden} (§2.2).");
        }
    }

    #[Test]
    public function a_category_view_carries_no_price_and_no_purchase_control(): void
    {
        $category = $this->populatedCategory();

        $body = $this->get("/ar/products?category={$category->slug}")->assertOk()->getContent();

        foreach (['ر.س', 'SAR', 'أضف إلى السلة', 'add-to-cart', 'name="quantity"'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $body,
                "A category view renders a commercial control: {$forbidden} (§2.2).");
        }
    }

    #[Test]
    public function the_showcase_ends_at_the_one_contact_field(): void
    {
        $body = $this->get('/ar/products')->assertOk()->getContent();

        // The shared LeadField, identified the way every other test on this
        // site identifies it — same name, same autocomplete pair, so it is
        // provably the one component and not a second form (§6.1).
        $this->assertStringContainsString('name="contact"', $body);
        $this->assertStringContainsString('autocomplete="email tel"', $body);

        preg_match_all('/<form[^>]*action="([^"]*)"/', $body, $actions);

        foreach ($actions[1] as $action) {
            $this->assertStringContainsString('/leads', $action,
                'A form on the showcase posts somewhere other than the lead endpoint.');
        }
    }

    #[Test]
    public function the_store_link_is_present_but_stays_an_aside(): void
    {
        $body = $this->get('/ar/products')->assertOk()->getContent();

        // Present — someone who wants one piece should be able to buy it.
        $this->assertStringContainsString('amadcraft.sa', $body);

        // But not dressed as the page's action: the only button-styled call
        // on the page belongs to the contact field.
        $this->assertStringNotContainsString('btn btn--cta-lg" href="https://amadcraft.sa', $body);
    }

    #[Test]
    public function the_category_urls_stay_in_the_sitemap(): void
    {
        $category = $this->populatedCategory();

        $this->get('/sitemap-ar.xml')
            ->assertOk()
            ->assertSee("/ar/products?category={$category->slug}", false);
    }

    /**
     * The premises and the collection are two different things, both called
     * المعرض. The block above the footer is the building — it must open a map,
     * never the product grid.
     */
    #[Test]
    public function the_visit_block_points_at_the_map_not_the_product_grid(): void
    {
        $body = $this->get('/ar')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/class="visit__open"\s+href="(?!\/ar\/products)[^"]*(maps|goo\.gl)[^"]*"/',
            $body,
            'The showroom-visit block does not open a map — check it has not been pointed at /products.',
        );
    }
}
