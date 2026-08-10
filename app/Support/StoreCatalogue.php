<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Reads Amad Craft's Zid storefront.
 *
 * Everything here comes out of the schema.org JSON-LD the store already
 * publishes for search engines, not out of its markup. That is a deliberate
 * choice: the JSON-LD is a contract Zid maintains for crawlers, while the
 * surrounding HTML is theme output that changes whenever the client picks a
 * new template. Parsing the markup would give an importer that breaks
 * silently on a redesign and fills the site with blanks.
 *
 * This class only reads. It knows nothing about the database, and it never
 * returns a price, a stock level or an availability flag — see
 * ImportStoreCatalogue for why those are out of scope.
 */
class StoreCatalogue
{
    /** Product pages fetched at once. Their store, so stay well-mannered. */
    public const CONCURRENCY = 6;

    /** Guard against a pagination bug turning into an unbounded crawl. */
    private const MAX_LIST_PAGES = 40;

    /**
     * The categories the store publishes, in its own order.
     *
     * @return list<array{id: int, name: string, url: string}>
     */
    public function categories(): array
    {
        $list = $this->itemList((string) $this->get('/categories'));

        $categories = [];

        foreach ($list as $item) {
            $url = (string) ($item['url'] ?? '');
            $name = trim((string) ($item['name'] ?? ''));

            // /categories/{id}/{slug} — the id is the only stable identifier;
            // the slug keeps the name it had when the category was created.
            if ($name === '' || ! preg_match('#/categories/(\d+)#', $url, $m)) {
                continue;
            }

            $categories[] = ['id' => (int) $m[1], 'name' => $name, 'url' => $url];
        }

        return $categories;
    }

    /**
     * Every product the store lists, in its own order.
     *
     * @return list<array{position: int, name: string, url: string}>
     */
    public function productListings(): array
    {
        $listings = [];
        $seen = [];

        for ($page = 1; $page <= self::MAX_LIST_PAGES; $page++) {
            // Zid answers 404 for the page after the last one, so a missing
            // page is the end of the catalogue rather than a broken import.
            $body = $this->get('/products?page='.$page, allowMissing: true);

            if ($body === null) {
                break;
            }

            $items = $this->itemList($body);

            if ($items === []) {
                break;
            }

            $added = 0;

            foreach ($items as $item) {
                $url = $this->canonicalUrl((string) ($item['url'] ?? ''));
                $name = trim((string) ($item['name'] ?? ''));

                if ($url === null || $name === '' || isset($seen[$url])) {
                    continue;
                }

                $seen[$url] = true;
                $listings[] = ['position' => count($listings), 'name' => $name, 'url' => $url];
                $added++;
            }

            // A page that adds nothing new means pagination has wrapped around
            // rather than ended — Zid serves the last page again past the end.
            if ($added === 0) {
                break;
            }
        }

        return $listings;
    }

    /**
     * Fetches a batch of product pages concurrently.
     *
     * A page that fails after retries yields null rather than aborting the
     * run: one unreachable product should not cost the other 331.
     *
     * @param  list<array{position: int, name: string, url: string}>  $listings
     * @return list<array{position: int, name: string, url: string, image: string|null, category: string|null, description: string|null}|null>
     */
    public function products(array $listings): array
    {
        $responses = Http::pool(fn (Pool $pool): array => array_map(
            fn (array $listing) => $this->request($pool)->get($listing['url']),
            $listings,
        ));

        $products = [];

        foreach ($listings as $i => $listing) {
            $response = $responses[$i] ?? null;

            $products[] = $response instanceof Response && $response->successful()
                ? $this->parseProduct($listing, $response->body())
                : null;
        }

        return $products;
    }

    /**
     * Downloads a product image and re-encodes it as a right-sized WebP.
     *
     * Returns the path of a temporary file the caller owns and must delete,
     * or null if the image could not be read.
     */
    public function downloadImage(string $url, string $name): ?string
    {
        try {
            $response = $this->request()->timeout(60)->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $source = @imagecreatefromstring($response->body());

        if ($source === false) {
            return null;
        }

        $path = $this->resize($source, $name);
        imagedestroy($source);

        return $path;
    }

    /**
     * @param  \GdImage  $source
     */
    private function resize($source, string $name): ?string
    {
        $max = (int) config('store.image_max_edge');
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $max / max($width, $height));

        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        // Without these two the transparent background of a cut-out product
        // photo is flattened to black, which is worse than not importing it.
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        imagecopyresampled(
            $canvas, $source,
            0, 0, 0, 0,
            $targetWidth, $targetHeight, $width, $height,
        );

        // The media library takes its stored file name from the path it is
        // handed, so this has to be a real directory plus the intended name —
        // not tempnam()'s "amaAA9D.tmp" with a suffix glued on.
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'amad-store-import';

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.$name;
        $written = imagewebp($canvas, $path, (int) config('store.image_quality'));
        imagedestroy($canvas);

        return $written ? $path : null;
    }

    /**
     * @param  array{position: int, name: string, url: string}  $listing
     * @return array{position: int, name: string, url: string, image: string|null, category: string|null, description: string|null}|null
     */
    private function parseProduct(array $listing, string $html): ?array
    {
        $product = null;

        foreach ($this->jsonLdBlocks($html) as $block) {
            foreach ($block['@graph'] ?? [$block] as $node) {
                if (($node['@type'] ?? null) === 'Product') {
                    $product = $node;
                    break 2;
                }
            }
        }

        if ($product === null) {
            return null;
        }

        $name = trim((string) ($product['name'] ?? $listing['name']));

        if ($name === '') {
            return null;
        }

        return [
            'position' => $listing['position'],
            'name' => $name,
            'url' => $listing['url'],
            'image' => $this->firstImage($product['image'] ?? null),
            'category' => $this->trimmedOrNull($product['category'] ?? null),
            // Zid fills a product's JSON-LD `description` with the *store's*
            // blurb when the product has none of its own, so the same
            // paragraph would land on all 332 rows. Better an empty field the
            // client can fill than the same sentence repeated site-wide.
            'description' => null,
        ];
    }

    private function firstImage(mixed $image): ?string
    {
        if (is_array($image)) {
            $image = $image[0] ?? null;
        }

        $url = $this->trimmedOrNull($image);

        return $url !== null && str_starts_with($url, 'http') ? $url : null;
    }

    private function trimmedOrNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    /**
     * The first schema.org ItemList in a page, flattened to its elements.
     *
     * @return list<array<string, mixed>>
     */
    private function itemList(string $html): array
    {
        foreach ($this->jsonLdBlocks($html) as $block) {
            foreach ($block['@graph'] ?? [$block] as $node) {
                if (($node['@type'] ?? null) === 'ItemList') {
                    return array_values(array_filter(
                        $node['itemListElement'] ?? [],
                        is_array(...),
                    ));
                }
            }
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jsonLdBlocks(string $html): array
    {
        preg_match_all(
            '#<script[^>]*type="application/ld\+json"[^>]*>(.*?)</script>#is',
            $html,
            $matches,
        );

        $blocks = [];

        foreach ($matches[1] ?? [] as $raw) {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }

            if (is_array($decoded)) {
                $blocks[] = $decoded;
            }
        }

        return $blocks;
    }

    /**
     * Absolute, on the store's own host, and stripped of query and fragment.
     *
     * The listing markup carries tracking parameters on some links; keeping
     * them would make the same product import twice under two keys.
     */
    private function canonicalUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || ! str_starts_with($url, config('store.url'))) {
            return null;
        }

        return strtok($url, '?#') ?: null;
    }

    private function get(string $path, bool $allowMissing = false): ?string
    {
        $response = $this->request()->get(config('store.url').$path);

        if ($allowMissing && $response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new RuntimeException("{$path} returned {$response->status()}");
        }

        return $response->body();
    }

    private function request(?Pool $pool = null): PendingRequest
    {
        return ($pool ?? Http::retry(3, 400, throw: false))
            ->withHeaders([
                'User-Agent' => (string) config('store.user_agent'),
                'Accept-Language' => 'ar',
            ])
            ->timeout(30);
    }
}
