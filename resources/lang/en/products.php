<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Product showcase — English
|--------------------------------------------------------------------------
| Interface labels only (§0.1). Product and category names are content.
|
| Note what has no key here and never will: price, currency, quantity, stock,
| "add to cart", "buy now". §2.2 puts every one of them out of scope, and a
| translation key is how such a thing gets built by accident.
*/

return [
    'filter_by_category' => 'Filter by category',
    'pagination' => 'Product pages',
    'category_count' => ':count product|:count products',
    'showing' => 'Showing :from–:to of :total',
    'page_of' => 'Page :current of :last',
    'empty' => 'No products in this category yet.',
];
