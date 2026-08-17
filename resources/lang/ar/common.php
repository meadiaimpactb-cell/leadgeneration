<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Functional UI strings — Arabic
|--------------------------------------------------------------------------
| §0.1 permits exactly this: interface labels, alerts and error messages.
| Marketing copy, headlines and body content are NOT written here — they come
| from Amad Craft and live in the database translation tables.
*/

return [
    'skip_to_content' => 'تخطَّ إلى المحتوى',
    'menu' => 'القائمة',
    'open_menu' => 'فتح القائمة',
    'close_menu' => 'إغلاق القائمة',
    'close' => 'إغلاق',
    'done' => 'تمام',
    'home' => 'الرئيسية',
    'loading' => 'جارٍ التحميل…',
    'language' => 'اللغة',
    'switch_language' => 'تغيير اللغة',
    'read_more' => 'اقرأ المزيد',
    'learn_more' => 'اعرف أكثر',
    'view_all' => 'عرض الكل',
    'previous' => 'السابق',
    'next' => 'التالي',
    'breadcrumb' => 'مسار التنقل',
    'logo_alt' => 'شعار أمد الحرف',
    'brand_latin' => 'AMAD CRAFT',
    'news' => 'آخر الأخبار',
    'external_link' => 'يفتح في نافذة جديدة',
    'required' => 'مطلوب',
    'optional' => 'اختياري',
    'other_segments' => 'شرائح أخرى قد تعنيكم',
    // Names the bridge diagram for a reader who cannot see it. A label, not a
    // description: the diagram's own labels are real text in reading order,
    // so a screen reader gets the model itself rather than a paraphrase.
    'diagram' => 'رسم توضيحي',

    /*
     * The footer's Latin micro-labels.
     *
     * They stay Latin in Arabic too, and that is the design, not an
     * oversight: `.mono-label` sets `direction: ltr` precisely so "SITEMAP"
     * reads in Latin order beside Arabic content, the same device as
     * `brand_latin` above. What was wrong was that they were typed into the
     * template, which made four headings on every page of the site the only
     * words Amad Craft could not change without a developer (§22.5).
     */
    'label_sitemap' => 'sitemap',
    'label_company' => 'company',
    'label_contact' => 'contact',
    'label_location' => 'location',

    /*
     * Announced, not shown — so these are Arabic while the labels above are
     * not. A screen reader on the Arabic site was hearing "company" and
     * "legal" in English, because these three `aria-label`s were literals
     * while the one beside them already used t('common.menu').
     */
    'nav_company' => 'روابط الشركة',
    'nav_legal' => 'الروابط النظامية',
    'pagination' => 'تصفّح النتائج',

    /*
     * The caption beside each home-page section number — "01 / 05 · MANIFESTO".
     *
     * Keyed by section TYPE, so the caption follows the block when the client
     * reorders or removes it in the panel. Latin in both languages, like
     * `label_sitemap` above and for the same reason: `.mono-label` sets
     * `direction: ltr` and this is the identity's device, not an untranslated
     * string. The words are exactly what the approved design already shows.
     *
     * A section can override its own caption from the panel with an
     * `index_label` entry in its settings JSON; this is only the default.
     */
    'section_label_intro_statement' => 'manifesto',
    'section_label_solutions_grid' => 'solutions',
    'section_label_gallery' => 'showroom',
    'section_label_sector_spotlight' => 'sectors',
    'section_label_story_carousel' => 'voices',
];
