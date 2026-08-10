<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Product showcase — Arabic
|--------------------------------------------------------------------------
| Interface labels only (§0.1). Product names, category names and the store
| link's wording are content and live in the database.
|
| Note what has no key here and never will: price, currency, quantity, stock,
| "add to cart", "buy now". §2.2 puts every one of them out of scope, and a
| translation key is how such a thing gets built by accident.
*/

return [
    'filter_by_category' => 'التصفية حسب التصنيف',
    'pagination' => 'تصفّح المنتجات',
    'category_count' => ':count منتج|:count منتجات',
    'showing' => 'عرض :from–:to من :total',
    'page_of' => 'الصفحة :current من :last',
    'empty' => 'لا توجد منتجات في هذا التصنيف بعد.',
];
