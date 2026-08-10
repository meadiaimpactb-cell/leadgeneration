<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProductCategory;
use App\Models\ShowcaseProduct;
use App\Support\StoreCatalogue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Imports Amad Craft's catalogue from their Zid storefront into the showcase.
 *
 * What it brings across: category names and order, product names, the product
 * image, and the link back to the store listing.
 *
 * What it deliberately leaves behind — and must keep leaving behind — is
 * every commercial field the store carries: price, currency, discount, SKU,
 * stock level, availability, "add to cart". §2.2 puts all of it out of scope,
 * and the showcase tables have no column for any of it. This site exists to
 * produce institutional leads (§1); a price on the page turns an enquiry into
 * a transaction and loses the lead.
 *
 * Locale: the storefront is Arabic-only, so this writes Arabic translations
 * only. That is not an oversight to paper over — §12 forbids falling back to
 * Arabic on the English site, so imported products stay out of /en until
 * someone writes real English names in the admin panel. Machine-translating a
 * craft term like "الزري" would be worse than an empty section.
 *
 * Safe to re-run: products are keyed by their store URL, so a second run
 * updates in place and re-downloads nothing whose image is already stored.
 */
class ImportStoreCatalogue extends Command
{
    protected $signature = 'amad:import-store
        {--limit=0 : Stop after this many products (0 imports the whole catalogue)}
        {--skip-images : Leave existing images alone and download none}
        {--prune : Deactivate showcase products the store no longer lists}
        {--dry-run : Report what would change and write nothing}';

    protected $description = "Import the product catalogue from Amad Craft's Zid store (no prices, no stock)";

    public function handle(StoreCatalogue $store): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $this->line('Reading '.config('store.url'));

        try {
            $categories = $store->categories();
            $listings = $store->productListings();
        } catch (Throwable $e) {
            $this->error('Could not read the store: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($categories === [] || $listings === []) {
            $this->error('The store returned no catalogue. Nothing was changed.');

            return self::FAILURE;
        }

        if ($limit > 0) {
            $listings = array_slice($listings, 0, $limit);
            $this->warn("Limited to {$limit} products — this is a partial import.");
        }

        $this->line(count($categories).' categories, '.count($listings).' products.');

        if ($dryRun) {
            $this->warn('Dry run: nothing will be written.');
            $this->table(['#', 'Category'], array_map(
                fn (array $c, int $i): array => [$i + 1, $c['name']],
                $categories,
                array_keys($categories),
            ));

            return self::SUCCESS;
        }

        $categoryIds = $this->importCategories($categories);
        $seen = $this->importProducts($store, $listings, $categoryIds);

        if ($this->option('prune')) {
            $this->prune($seen);
        }

        // Nothing on the public side is cached per product, but the menus and
        // settings caches are keyed broadly enough to hold a stale count.
        Cache::flush();

        $this->newLine();
        $this->info('Catalogue imported.');
        $this->line('  Prices, stock and purchase actions were not imported and never will be (§2.2).');
        $this->line('  Products carry Arabic only — add English names in the panel before enabling /en.');

        return self::SUCCESS;
    }

    /**
     * @param  list<array{id: int, name: string, url: string}>  $categories
     * @return array<string, int> category name => local id
     */
    private function importCategories(array $categories): array
    {
        $ids = [];

        foreach ($categories as $order => $category) {
            $record = ProductCategory::query()->updateOrCreate(
                ['slug' => $this->categorySlug($category)],
                ['sort_order' => $order, 'is_active' => true],
            );

            $record->translations()->updateOrCreate(['locale' => 'ar'], ['name' => $category['name']]);

            $ids[$category['name']] = $record->id;
        }

        $this->line(count($ids).' categories written.');

        return $ids;
    }

    /**
     * A stable, ASCII slug for a category.
     *
     * Built from the store's numeric id rather than its Arabic name, because
     * the two drift: the category displayed as "مجموعة لمسة" still sits at
     * /categories/1412587/لايف-ستايل. Keying on the name would create a
     * second row the day the client renames one; keying on the id survives
     * every rename.
     *
     * @param  array{id: int, name: string, url: string}  $category
     */
    private function categorySlug(array $category): string
    {
        return 'store-'.$category['id'];
    }

    /**
     * @param  list<array{position: int, name: string, url: string}>  $listings
     * @param  array<string, int>  $categoryIds
     * @return list<int> ids of everything imported
     */
    private function importProducts(StoreCatalogue $store, array $listings, array $categoryIds): array
    {
        $bar = $this->output->createProgressBar(count($listings));
        $bar->start();

        $seen = [];
        $missingCategory = [];
        $failed = [];

        foreach (array_chunk($listings, StoreCatalogue::CONCURRENCY) as $chunk) {
            foreach ($store->products($chunk) as $product) {
                $bar->advance();

                if ($product === null) {
                    $failed[] = 'unreadable product page';

                    continue;
                }

                $categoryId = $this->matchCategory($product['category'], $categoryIds);

                if ($categoryId === null && $product['category'] !== null) {
                    $missingCategory[$product['category']] = true;
                }

                $record = ShowcaseProduct::query()->updateOrCreate(
                    ['external_store_url' => $product['url']],
                    [
                        'slug' => $this->productSlug($product['url']),
                        'product_category_id' => $categoryId,
                        'sort_order' => $product['position'],
                        'is_active' => true,
                    ],
                );

                $record->translations()->updateOrCreate(['locale' => 'ar'], [
                    'name' => $product['name'],
                    'description' => $product['description'],
                ]);

                if (! $this->option('skip-images')) {
                    $this->attachImage($store, $record, $product);
                }

                $seen[] = $record->id;
            }
        }

        $bar->finish();
        $this->newLine(2);

        if ($missingCategory !== []) {
            $this->warn('Products referenced categories the store does not list: '
                .implode(', ', array_keys($missingCategory)));
        }

        if ($failed !== []) {
            $this->warn(count($failed).' product pages could not be read and were skipped.');
        }

        $this->line(count($seen).' products written.');

        return $seen;
    }

    /**
     * Resolves a product's category, which Zid may give as a path.
     *
     * A product filed under a subcategory arrives as
     * "مجموعة لمسة > حرف خشبية" — both segments are real categories in the
     * store's own flat list. The most specific one that we know about wins,
     * so a product lands in "حرف خشبية" rather than in its broad parent.
     *
     * @param  array<string, int>  $categoryIds
     */
    private function matchCategory(?string $category, array $categoryIds): ?int
    {
        if ($category === null) {
            return null;
        }

        $segments = array_reverse(array_map(trim(...), explode('>', $category)));

        foreach ($segments as $segment) {
            if (isset($categoryIds[$segment])) {
                return $categoryIds[$segment];
            }
        }

        return null;
    }

    /**
     * The store's own URL slug, so a product keeps one identity across both
     * sites. Truncated well inside the column's 191 characters, with a hash
     * of the full value appended so two long names that share a prefix cannot
     * collide into one row.
     */
    private function productSlug(string $url): string
    {
        $tail = rawurldecode((string) Str::afterLast(rtrim($url, '/'), '/'));

        return Str::length($tail) <= 180
            ? $tail
            : Str::limit($tail, 170, '').'-'.substr(md5($url), 0, 8);
    }

    /**
     * @param  array{url: string, name: string, image: string|null}  $product
     */
    private function attachImage(StoreCatalogue $store, ShowcaseProduct $record, array $product): void
    {
        if ($product['image'] === null) {
            return;
        }

        $expected = $this->imageName($product['image']);

        if ($record->getFirstMedia('primary')?->file_name === $expected) {
            return;
        }

        $file = $store->downloadImage($product['image'], $expected);

        if ($file === null) {
            return;
        }

        try {
            $record->getFirstMedia('primary')?->delete();

            $media = $record->addMedia($file)->toMediaCollection('primary');

            // Alt text is content and is per-locale (§10.8). The product's own
            // name is the only honest description of its photograph.
            $media->translations()->updateOrCreate(
                ['locale' => 'ar'],
                ['alt_text' => $product['name']],
            );
        } finally {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function imageName(string $url): string
    {
        return substr(md5($url), 0, 16).'.webp';
    }

    /**
     * @param  list<int>  $seen
     */
    private function prune(array $seen): void
    {
        // Deactivated, not deleted. A product pulled from the store for a
        // season is usually coming back, and its row may already carry
        // English copy someone wrote by hand.
        $stale = ShowcaseProduct::query()
            ->whereNotNull('external_store_url')
            ->whereNotIn('id', $seen)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        if ($stale > 0) {
            $this->warn("{$stale} products are no longer listed in the store and were hidden.");
        }
    }
}
