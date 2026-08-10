<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | The Zid storefront
    |--------------------------------------------------------------------------
    |
    | Amad Craft sells on Zid. This site does not (§2.2) — it shows the
    | catalogue for information and links out to the store for anyone who
    | wants to buy. `amad:import-store` reads the catalogue from here.
    |
    | Configurable rather than hard-coded because the storefront is the
    | client's to move: a domain change on their side must be one line here,
    | not a code edit.
    |
    */

    'url' => rtrim((string) env('STORE_URL', 'https://amadcraft.sa'), '/'),

    /*
    | The importer identifies itself honestly. A blank or forged user agent is
    | what gets a scraper blocked, and this one is reading the client's own
    | public catalogue with their permission.
    */
    'user_agent' => env(
        'STORE_USER_AGENT',
        'AmadCraftSiteImporter/1.0 (+https://amadcraft.sa; catalogue sync)'
    ),

    /*
    | Longest edge, in pixels, of an imported product image.
    |
    | The store serves 1000×1000 PNGs — around 500 KB each. At 332 products
    | that is 160 MB of originals for a grid that renders them at ~300 px.
    | The showcase registers no media conversions, so whatever is stored is
    | what the browser downloads; the resize has to happen on import.
    |
    | 600 px covers the widest card (≈300 px) on a 2× display. A page of 24
    | products then costs roughly 1 MB of images, all lazy-loaded — inside the
    | §15.1 budget. Raising this raises every product page's weight at once.
    */
    'image_max_edge' => 600,

    'image_quality' => 80,

];
