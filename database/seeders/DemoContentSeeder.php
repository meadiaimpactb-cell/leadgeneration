<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ImpactMetric;
use App\Models\LeadField;
use App\Models\Navigation;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\Partner;
use App\Models\ProductCategory;
use App\Models\Report;
use App\Models\Sector;
use App\Models\Setting;
use App\Models\ShowcaseProduct;
use App\Models\Solution;
use App\Models\Story;
use App\Models\TrainingProgram;
use App\Support\NavigationBuilder;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * DRAFT CONTENT — written to be reviewed, edited and replaced.
 *
 * §0.1 and §22.1 put copywriting on Amad Craft's side, not the developer's.
 * The client lifted that constraint explicitly so management could review a
 * finished-looking site rather than bracketed placeholders. Everything here is
 * therefore a first draft to be edited in the admin panel — not approved copy.
 *
 * It is grounded in what is verifiable about the company: its own store's
 * product names and craft vocabulary (السدو، الخوص، الزري، النقش الحساوي،
 * المكرمية), its address, and the four audience segments in §3.
 *
 * ⚠️ NOT VERIFIED — must be replaced before launch:
 *   · the impact figures — §21 defers real numbers until after launch
 *   · the artisan stories and their attributions — these are composed
 *     illustrations, not real people; real ones need real consent
 *   · partner and accreditation names — never claim a relationship that does
 *     not exist, which is why they are deliberately generic here
 *   · the legal pages, which need a lawyer rather than a developer
 *
 * Run with:  php artisan amad:seed-demo
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DemoContentSeeder must never run in production.');

            return;
        }

        $this->settings();
        $this->leadForm();
        $this->pages();
        $this->solutions();
        $this->sectors();
        $this->products();
        $this->impact();
        $this->stories();
        $this->training();
        $this->partners();
        $this->reports();
        $this->navigation();

        app(Settings::class)->forget();
        NavigationBuilder::flush();

        $this->command?->info('Draft content seeded — review and edit it in the admin panel.');
    }

    /**
     * Real contact details, taken from the company's own store and Maps entry.
     */
    private function settings(): void
    {
        $values = [
            'site.name.ar' => 'أمد الحرف',
            'site.name.en' => 'Amad Craft',
            'site.copyright.ar' => '© أمد الحرف. جميع الحقوق محفوظة.',
            'site.copyright.en' => '© Amad Craft. All rights reserved.',

            'contact.email' => 'Sales@amadcraft.sa',
            'contact.phone' => '+966553516589',
            'contact.whatsapp' => '+966553516589',
            'contact.social' => [
                ['label' => 'Instagram', 'url' => 'https://www.instagram.com/amad.craft'],
                ['label' => 'X', 'url' => 'https://x.com/amad_craft'],
                ['label' => 'Facebook', 'url' => 'https://www.facebook.com/amad.craft'],
                ['label' => 'Snapchat', 'url' => 'https://www.snapchat.com/add/amad.craft'],
            ],

            // Resolved from the company's own Google Maps link.
            'contact.address.ar' => "أمد الحرف\nطريق الأمير محمد بن سلمان\nحي العقيق، الرياض 13515",
            'contact.address.en' => "Amad Craft\nPrince Mohammed bin Salman Road\nAl Aqiq, Riyadh 13515",
            // Draft copy for the always-on-screen contact dock.
            'contact.dock_heading.ar' => 'حدّثونا عن مشروعكم',
            'contact.dock_heading.en' => 'Tell us about your project',
            'contact.dock_note.ar' => 'حقل واحد يكفي — بريد إلكتروني أو رقم جوال، ونتولّى نحن الباقي.',
            'contact.dock_note.en' => 'One field is enough — an email or a mobile number, and we take it from there.',
            // Not "request a quote": §2.2 forbids a purchase path, and a
            // button promising a price implies one. This opens a conversation.
            'contact.header_cta.ar' => 'لنبدأ معًا',
            'contact.header_cta.en' => "Let's begin",

            'site.footer_blurb.ar' => 'إنتاج حرفي مؤسسي: من الحرفي في منطقته، إلى مكتب جهتكم.',
            'site.footer_blurb.en' => 'Institutional craft production: from the artisan in their region to your office.',

            'contact.map_query' => 'امد الحرف Amad Craft, Al Aqiq, Riyadh',
            // The place-based embed, not a coordinate query: it renders the
            // business's own Google listing — name, marker and all — where the
            // coordinate form dropped a generic pin on the wrong block.
            'contact.map_embed_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3622.658464160055!2d46.62466047482376!3d24.77289664875689!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3e2ee3b12b8c5a37%3A0x3fd986a7082025cb!2z2KfZhdivINin2YTYrdix2YEgfCBBbWFkIENyYWZ0!5e0!3m2!1sen!2ssa!4v1786366447890!5m2!1sen!2ssa',
            'contact.map_url' => 'https://maps.app.goo.gl/jqhEooUEpDipi3ZM6',
            // ⚠️ NOT VERIFIED. Opening hours are published nowhere the company
            // controls; these are the ordinary Saudi business week, seeded as
            // draft because the approved design shows the line. Confirm them
            // with Amad Craft before launch, or clear both and the line hides.
            'contact.hours.ar' => 'الأحد – الخميس · 9:00 ص – 5:00 م',
            'contact.hours.en' => 'Sunday – Thursday · 9:00 – 17:00',

            'contact.location_heading.ar' => 'زوروا معرضنا في الرياض',
            'contact.location_heading.en' => 'Visit our showroom in Riyadh',
            'contact.location_note.ar' => 'نستقبل زيارات الجهات بموعد مسبق.',
            'contact.location_note.en' => 'We receive institutional visits by prior appointment.',
            'contact.directions_label.ar' => 'افتح الموقع في الخرائط',
            'contact.directions_label.en' => 'Open in Maps',

            // Informational link to the Zid store — no price, no buy (§4).
            'store.url' => 'https://amadcraft.sa',
            'store.label.ar' => 'زيارة المتجر',
            'store.label.en' => 'Visit the store',

            'seo.default_description.ar' => 'أمد الحرف تحوّل الحرفة اليدوية السعودية إلى هدايا ومقتنيات مؤسسية تحمل هوية جهتكم — للجهات الحكومية والشركات وشركات الفعاليات.',
            'seo.default_description.en' => 'Amad Craft turns Saudi handcraft into corporate gifts and pieces that carry your identity — for government entities, companies and event partners.',
        ];

        foreach ($values as $path => $value) {
            [$group, $key] = explode('.', $path, 2);

            Setting::query()->updateOrCreate(
                ['group' => $group, 'key' => $key],
                ['value' => $value]
            );
        }
    }

    /**
     * The three-field configuration the approved design shows.
     *
     * §6.1 ships one field — an email OR a mobile, auto-detected, no name.
     * The client asked for company + phone + email, all required, and this is
     * where that choice belongs: LeadFieldsSeeder is structural and runs in
     * production, so it keeps the brief's default and merely makes the extra
     * fields available. Turning three of them on is a content decision, made
     * here, and reversible from the admin panel without a developer.
     *
     * No endpoint changed. `contact` is still the field the server requires
     * unconditionally — it is simply labelled as the email, and the other two
     * arrive as answers alongside it.
     */
    private function leadForm(): void
    {
        $config = [
            'organisation' => [
                'order' => 0,
                'ar' => ['اسم الشركة', 'الجهة أو المؤسسة'],
                'en' => ['Company name', 'Entity or organisation'],
            ],
            'phone' => [
                'order' => 1,
                'ar' => ['رقم الهاتف', '+966 5X XXX XXXX'],
                'en' => ['Phone number', '+966 5X XXX XXXX'],
            ],
            LeadField::KEY_CONTACT => [
                'order' => 2,
                'ar' => ['البريد الإلكتروني', 'name@company.sa'],
                'en' => ['Email address', 'name@company.sa'],
            ],
        ];

        foreach ($config as $key => $spec) {
            $field = LeadField::query()->where('key', $key)->first();

            if ($field === null) {
                $this->command?->warn("Lead field missing: {$key}");

                continue;
            }

            $field->forceFill([
                'is_enabled' => true,
                'is_required' => true,
                'sort_order' => $spec['order'],
            ])->save();

            foreach (['ar', 'en'] as $locale) {
                [$label, $placeholder] = $spec[$locale];

                $field->translations()->updateOrCreate(['locale' => $locale], [
                    'label' => $label,
                    'placeholder' => $placeholder,
                    'help' => null,
                ]);
            }
        }

        // The optional message stays available but is no longer part of the
        // shown form — three required fields plus an optional fourth is the
        // point at which a B2B enquiry form starts losing people.
        LeadField::query()
            ->where('key', LeadField::KEY_MESSAGE)
            ->update(['is_enabled' => false]);
    }

    private function pages(): void
    {
        foreach ($this->pageCopy() as $slug => $data) {
            $page = Page::query()->firstOrCreate(['slug' => $slug], ['template' => $slug]);

            $page->forceFill(['status' => 'published', 'published_at' => now()])->save();

            foreach (['ar', 'en'] as $locale) {
                $page->translations()->updateOrCreate(['locale' => $locale], [
                    'title' => $data[$locale][0],
                    'subtitle' => $data[$locale][1] ?? null,
                    'meta_title' => $data[$locale][0],
                    'meta_description' => $data[$locale][1] ?? null,
                ]);
            }

            $page->sections()->delete();
            $this->attachSections($page, $data['sections'] ?? []);
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function pageCopy(): array
    {
        return [
            'home' => [
                'ar' => ['أمد الحرف .. أسلوب حياة', 'من الحرفي إلى منتجات تناسب احتياجاتكم'],
                'en' => ['Amad Craft — a way of living', 'From the artisan to products that fit your needs'],
                'sections' => [
                    // Headlines, one per line. NewsTicker splits on the
                    // newline, so the client edits them in the one body field.
                    ['news_ticker', [
                        // heading · subheading · body · ctaLabel · ctaUrl —
                        // the headlines are the BODY, hence the null.
                        'ar' => [
                            'جديد',
                            null,
                            "توقيع اتفاقية توريد حرفي مع جهة حكومية لتجهيز ثلاثة مؤتمرات في الرياض\n".
                            "انطلاق الدفعة الثالثة من برنامج تمكين الحرفيين — 120 ساعة تدريب\n".
                            "إضافة خط إنتاج جديد للنقش الحساوي على الجلود\n".
                            'أمد الحرف ضمن العارضين في ملتقى الصناعات الثقافية',
                            'كل الأخبار',
                            '/ar/impact',
                        ],
                        'en' => [
                            'New',
                            null,
                            "A craft supply agreement signed with a government body for three conferences in Riyadh\n".
                            "The third cohort of the artisan enablement programme opens — 120 training hours\n".
                            "A new production line added for Al-Ahsa engraving on leather\n".
                            'Amad Craft among the exhibitors at the Cultural Industries Forum',
                            'All news',
                            '/en/impact',
                        ],
                    ]],

                    // The headline breaks where the client puts the newline,
                    // not where the browser happens to run out of width.
                    ['hero', [
                        'ar' => [
                            "حِرفة سعودية\nتليق بمقام جهتكم",
                            'نحوّل السدو والخوص والزري والنقش الحساوي إلى هدايا ومقتنيات مؤسسية تحمل هويتكم، وتُقدَّم لضيوفكم بما يليق بهم.',
                            null,
                            'ابدأ مشروعك معنا',
                            '/ar/contact',
                        ],
                        'en' => [
                            "Saudi craft,\nworthy of your name",
                            'We turn Sadu, palm-frond weaving, Zari and Al-Ahsa engraving into corporate gifts that carry your identity — and meet your guests at the standard they expect.',
                            null,
                            'Start your project with us',
                            '/en/contact',
                        ],
                    ], [
                        'eyebrow' => 'B2B · CRAFT SUPPLY',
                        // The second action. Hero shows the download glyph on
                        // it because the URL ends in a document extension —
                        // point it at a page instead and the glyph goes.
                        'secondaryLabel' => 'حمّل ملف الشركة',
                        'secondaryUrl' => '/documents/amad-craft-profile.pdf',
                        // The client's own workshop footage. It is a GIF, so
                        // Hero renders it as an <img> rather than a <video>.
                        'image' => [
                            'url' => '/videos/amadcraft.gif',
                            'webp' => null,
                            // The GIF's own first frame, 225 KB against the
                            // animation's 20.7 MB. It paints as the pane's
                            // ground so the hero is never an empty box while
                            // the animation is still arriving.
                            'poster' => '/images/craft/hero-poster.webp',
                            'alt' => 'جولة داخل معرض أمد الحرف',
                        ],
                    ]],

                    // Two paragraphs separated by a blank line: the first is
                    // the statement, the second the supporting note beside it.
                    // With a single block IntroStatement runs full width
                    // instead, so this is a choice the client can undo.
                    ['intro_statement', [
                        'ar' => [null, null,
                            "الحرفة السعودية ليست تراثًا يُعرض خلف زجاج، بل صناعة قادرة على أن تدخل مكاتب المؤسسات ومنصّات المؤتمرات وحقائب الوفود.\n\n".
                            'نعمل مع حرفيين وحرفيات في مناطق المملكة، ونعيد صياغة ما يصنعونه بمعايير الجودة والتغليف والتسليم التي تحتاجها الجهات — فيبقى الأثر للحرفي، ويبقى التميّز لكم.',
                        ],
                        'en' => [null, null,
                            "Saudi craft is not heritage behind glass. It is an industry capable of reaching corporate offices, conference stages and delegation bags.\n\n".
                            'We work with artisans across the Kingdom and rebuild what they make around the quality, packaging and delivery standards institutions need — so the impact stays with the artisan, and the distinction stays with you.',
                        ],
                    ]],

                    ['solutions_grid', [
                        'ar' => ['ما نقدّمه للجهات والشركات', 'أربعة مسارات، ولكل مسار جدول تسليم مستقل.'],
                        'en' => ['What we offer institutions', 'Four tracks, each with its own delivery schedule.'],
                    ]],

                    // The showroom. Photographs of the client's own pieces,
                    // resized by tools/prepare-craft-photos.mjs.
                    ['gallery', [
                        /*
                         * "Browse the collection", not "visit the showroom".
                         *
                         * Two different things are called المعرض on this site:
                         * the digital collection at /products, and the actual
                         * premises in Riyadh, which has its own block above
                         * the footer with a map and a directions button. This
                         * link goes to the first, and its label now says so —
                         * "زيارة المعرض" under a row of product photographs
                         * read as an invitation to the building.
                         */
                        'ar' => ['المنتج يُرى قبل أن يُشترى', null, null, 'تصفّحوا المجموعة', '/ar/products'],
                        'en' => ['The piece is seen before it is chosen', null, null, 'Browse the collection', '/en/products'],
                    ], ['images' => $this->craftGallery()]],

                    ['sector_spotlight', [
                        'ar' => ['من نخدم'],
                        'en' => ['Who we serve'],
                    ]],

                    ['stats', [
                        'ar' => ['أثرنا بالأرقام', null, null, 'اطّلعوا على التقارير', '/ar/impact'],
                        'en' => ['Our impact in numbers', null, null, 'See the reports', '/en/impact'],
                    ]],

                    ['story_carousel', [
                        'ar' => ['من الحرفيين أنفسهم'],
                        'en' => ['In the artisans’ own words'],
                    ]],

                    ['logos', [
                        'ar' => ['شركاء واعتمادات'],
                        'en' => ['Partners & accreditations'],
                    ]],

                    ['cta_band', [
                        'ar' => ['لديكم مناسبة أو مشروع قادم؟', 'اتركوا وسيلة تواصل واحدة، ويتولّى فريقنا الباقي.', null, 'تواصلوا معي'],
                        'en' => ['An event or a project coming up?', 'Leave one way to reach you and our team takes it from there.', null, 'Contact me'],
                    ]],
                ],
            ],

            'about' => [
                'ar' => ['من نحن', 'حركة لإعادة الحرفة السعودية إلى موقعها في الاقتصاد، لا في المتاحف وحدها.'],
                'en' => ['About us', 'A movement to return Saudi craft to the economy, not only to the museum.'],
                'sections' => [
                    ['rich_text', [
                        'ar' => ['لماذا وُجدنا', null,
                            '<p>الحرفة في المملكة لم تتوقف يومًا. ما توقّف هو الجسر بينها وبين السوق: الحرفي يصنع قطعة بديعة، ثم يقف أمام سؤال لا يملك إجابته — من يشتريها، وبأي سعر، وبأي كمية، وكيف تصل؟</p>'
                            .'<p>بُنيت أمد الحرف لتكون هذا الجسر. نتعامل مع الطرفين بلغتيهما: نفهم من الحرفي ما تحتمله يده وخامته وجدوله، ونفهم من الجهة ما تفرضه هويتها ومعايير الشراء لديها وتاريخ مناسبتها. ثم نتولّى المسافة بينهما كاملة — التصميم، والعيّنة، وضبط الجودة، والتغليف، والتسليم.</p>'
                            .'<p>النتيجة أن الجهة تحصل على قطعة لا تشبه ما يوزّعه غيرها، وأن الحرفي يحصل على طلب مستقر يعرف قيمته قبل أن يبدأ.</p>'],
                        'en' => ['Why we exist', null,
                            '<p>Craft in the Kingdom never stopped. What stopped was the bridge between it and the market: an artisan makes something remarkable, then faces a question they have no answer to — who buys it, at what price, in what quantity, and how does it get there?</p>'
                            .'<p>Amad Craft was built to be that bridge. We speak to both sides in their own terms: we learn from the artisan what their hands, materials and schedule can carry, and from the institution what its identity, procurement standards and event date demand. Then we take on the whole distance between them — design, sample, quality control, packaging and delivery.</p>'
                            .'<p>The result is a piece unlike anything else being handed out, and an artisan with steady work whose value is known before it begins.</p>'],
                    ]],

                    ['media_split', [
                        'ar' => ['كيف نعمل', null, 'نبدأ بفهم المناسبة والجمهور والميزانية، ثم نقترح ثلاثة اتجاهات حرفية بخامات مختلفة. تختارون اتجاهًا، فنصنع عيّنة فعلية تُعتمد بالمعاينة لا بالصورة. بعد الاعتماد ندخل الإنتاج بجدول واضح، وتصلكم القطع مغلّفة وجاهزة للتوزيع.'],
                        'en' => ['How we work', null, 'We start from the occasion, the audience and the budget, then propose three craft directions in different materials. You pick one, and we produce a real sample approved in the hand — not from a photograph. Once approved, production runs to a clear schedule, and the pieces arrive packaged and ready to hand out.'],
                    ], ['image' => $this->imagePayload('14.jpeg', 1126, 1036)]],

                    ['timeline', [
                        'ar' => ['محطات'],
                        'en' => ['Milestones'],
                    ], ['items' => [
                        ['year' => '2022', 'title' => 'البداية', 'body' => 'انطلاق أمد الحرف بورشة واحدة ومجموعة من الحرفيين.'],
                        ['year' => '2023', 'title' => 'أول طلب مؤسسي', 'body' => 'تنفيذ أول دفعة هدايا مؤسسية بهوية جهة كاملة.'],
                        ['year' => '2024', 'title' => 'برامج التمكين', 'body' => 'إطلاق المسارات التدريبية وربط الحرفيين بطلبات مجدولة.'],
                        ['year' => '2025', 'title' => 'التوسّع', 'body' => 'توسيع شبكة الحرفيين إلى مناطق جديدة وإطلاق خط الفعاليات.'],
                    ]]],

                    ['cta_band', [
                        'ar' => ['نبدأ بمحادثة قصيرة', 'أخبرونا بالمناسبة والتاريخ، ونقترح عليكم الاتجاه المناسب.', null, 'تواصلوا معي'],
                        'en' => ['It starts with a short conversation', 'Tell us the occasion and the date, and we will propose the right direction.', null, 'Contact me'],
                    ]],
                ],
            ],

            'solutions' => [
                'ar' => ['الحلول للشركات', 'أربعة مسارات تغطّي ما تحتاجه الجهات من الحرفة: هدية، أو فعالية، أو إنتاج مخصّص، أو توريد مباشر.'],
                'en' => ['Solutions for companies', 'Four tracks covering what institutions need from craft: a gift, an event, custom production, or direct sourcing.'],
                'sections' => [
                    ['solutions_grid', ['ar' => ['اختاروا ما يناسب مشروعكم'], 'en' => ['Pick what fits your project']]],
                    ['cta_band', [
                        'ar' => ['غير متأكدين أي مسار يناسبكم؟', 'صِفوا لنا الحالة، ونقترح المسار الأنسب دون التزام.', null, 'تواصلوا معي'],
                        'en' => ['Not sure which track fits?', 'Describe the situation and we will suggest one, with no commitment.', null, 'Contact me'],
                    ]],
                ],
            ],

            'products' => [
                'ar' => ['المنتجات', 'نماذج ممّا ننفّذه. العرض هنا تعريفي، وكل قطعة تقبل التخصيص بهويتكم وبالكمية التي تحتاجونها.'],
                'en' => ['Products', 'A sample of what we make. This showcase is informational; every piece can carry your identity, at the quantity you need.'],
                'sections' => [
                    ['product_showcase', [
                        'ar' => ['من الحرفي إلى منتج مؤسسي', 'كل قطعة تُصنع يدويًا. الخامة واللون والتغليف والشعار — كلها قابلة للتخصيص.'],
                        'en' => ['From artisan to institutional product', 'Every piece is made by hand. Material, colour, packaging and branding are all yours to set.'],
                    ]],
                    ['gallery', [
                        'ar' => ['من الورشة'],
                        'en' => ['From the workshop'],
                    ], ['images' => [
                        $this->imagePayload('7.webp', 1086, 1448),
                        $this->imagePayload('8.jpeg', 978, 1302),
                        $this->imagePayload('2.jpeg', 1060, 1010),
                        $this->imagePayload('6.jpeg', 1128, 1128),
                    ]]],
                    ['cta_band', [
                        'ar' => ['تريدون قطعة بهويتكم؟', 'أرسلوا لنا الكمية والمناسبة، ونعود إليكم باقتراح وعيّنة.', null, 'تواصلوا معي'],
                        'en' => ['Want a piece in your identity?', 'Send us the quantity and the occasion, and we will come back with a proposal and a sample.', null, 'Contact me'],
                    ]],
                ],
            ],

            'impact' => [
                'ar' => ['الأثر والتقارير', 'نقيس أثرنا بعدد الحرفيين الذين استقرّ دخلهم، لا بعدد القطع وحدها.'],
                'en' => ['Impact & reports', 'We measure our impact by how many artisans found steady income — not by pieces alone.'],
                'sections' => [
                    ['stats', ['ar' => ['أثرنا بالأرقام'], 'en' => ['Our impact in numbers']]],
                    ['story_carousel', ['ar' => ['قصص من الميدان'], 'en' => ['Stories from the field']]],
                    ['reports_list', ['ar' => ['التقارير المنشورة'], 'en' => ['Published reports']]],
                    ['cta_band', [
                        'ar' => ['تبحثون عن أثر يُوثَّق؟', 'نزوّد شركاءنا بتقرير أثر لكل مشروع، قابل للإدراج ضمن تقاريركم.', null, 'تواصلوا معي'],
                        'en' => ['Looking for impact you can document?', 'We provide partners with a per-project impact report, publishable inside your own reporting.', null, 'Contact me'],
                    ]],
                ],
            ],

            'training' => [
                'ar' => ['التدريب والتمكين', 'لا يكفي أن نشتري من الحرفي. نُدرّبه على أن يسعّر بنفسه، ويبيع بنفسه، ويستمرّ بعدنا.'],
                'en' => ['Training & empowerment', 'Buying from an artisan is not enough. We train them to price, to sell, and to keep going without us.'],
                'sections' => [
                    ['training_tracks', ['ar' => ['المسارات التدريبية'], 'en' => ['Training tracks']]],
                    ['rich_text', [
                        'ar' => ['لمن هذه المسارات', null,
                            '<p>المسارات مفتوحة للحرفيين والحرفيات الراغبين في تحويل مهارتهم إلى دخل مستقر. لا تشترط خبرة سابقة في البيع، وتشترط إتقانًا فعليًا للحرفة.</p>'
                            .'<p>وللجهات الراغبة في رعاية مسار كامل أو تنفيذه في منطقة محدّدة: نصمّم البرنامج ونُشغّله ونزوّدكم بتقرير أثر عند ختامه.</p>'],
                        'en' => ['Who these tracks are for', null,
                            '<p>The tracks are open to artisans who want to turn their skill into steady income. No selling experience is required; genuine command of the craft is.</p>'
                            .'<p>For institutions wanting to sponsor a full track or run one in a specific region: we design it, operate it, and hand you an impact report at the end.</p>'],
                    ]],
                    ['cta_band', [
                        'ar' => ['ترغبون برعاية مسار تدريبي؟', 'نصمّم البرنامج ونشغّله ونوثّق أثره لكم.', null, 'تواصلوا معي'],
                        'en' => ['Interested in sponsoring a track?', 'We design it, run it, and document its impact for you.', null, 'Contact me'],
                    ]],
                ],
            ],

            // The title is the one §5 names, not the one amadcraft.sa uses for
            // its logo strip ("الشركاء"). Only the partner group has rows —
            // the official site publishes no accreditations and no clients —
            // so the other two sections stay seeded but render nothing until
            // Amad Craft fills them from the panel. Worth the client deciding
            // whether to keep a title that names a group the page is not yet
            // showing.
            'partners' => [
                'ar' => ['الشركاء والاعتمادات', 'نعمل مع جهات ترى في الحرفة أكثر من هدية.'],
                'en' => ['Partners & accreditations', 'We work with organisations that see craft as more than a gift.'],
                'sections' => [
                    ['logos', ['ar' => ['شركاؤنا'], 'en' => ['Our partners']], ['group' => 'partner']],
                    ['logos', ['ar' => ['اعتماداتنا'], 'en' => ['Our accreditations']], ['group' => 'accreditation']],
                    ['logos', ['ar' => ['عملاؤنا'], 'en' => ['Our clients']], ['group' => 'client']],
                    ['cta_band', [
                        'ar' => ['تبحثون عن مورّد حِرفي موثوق؟', 'نعمل مع شركات الفعاليات والوكالات كمورّد خلفي لعملائها.', null, 'تواصلوا معي'],
                        'en' => ['Looking for a dependable craft supplier?', 'We work behind the scenes for event companies and agencies serving their own clients.', null, 'Contact me'],
                    ]],
                ],
            ],

            'contact' => [
                'ar' => ['تواصلوا معنا', 'حقل واحد يكفي — بريد إلكتروني أو رقم جوال، ونتولّى نحن الباقي.'],
                'en' => ['Contact us', 'One field is enough — an email or a mobile number, and we take it from there.'],
                'sections' => [
                    ['contact_block', [
                        'ar' => ['أخبرونا كيف نخدمكم', 'لا نطلب اسمًا ولا بيانات إضافية. اتركوا وسيلة تواصل واحدة فقط.', null, 'تواصلوا معي'],
                        'en' => ['Tell us how we can help', 'We ask for no name and no extra details. One way to reach you is all we need.', null, 'Contact me'],
                    ]],
                    ['map', [
                        'ar' => ['موقعنا'],
                        'en' => ['Find us'],
                    ]],
                ],
            ],
        ];
    }

    /**
     * @param  list<array{0: string, 1: array<string, list<string|null>>, 2?: array<string, mixed>}>  $sections
     */
    private function attachSections(Model $owner, array $sections): void
    {
        foreach ($sections as $position => $spec) {
            [$type, $copy] = $spec;

            $section = $owner->sections()->create([
                'type' => $type,
                'sort_order' => $position,
                'is_active' => true,
                'settings' => $spec[2] ?? null,
            ]);

            foreach ($copy as $locale => $values) {
                $section->translations()->updateOrCreate(['locale' => $locale], [
                    'heading' => $values[0] ?? null,
                    'subheading' => $values[1] ?? null,
                    'body' => $values[2] ?? null,
                    'cta_label' => $values[3] ?? null,
                    'cta_url' => $values[4] ?? null,
                ]);
            }
        }
    }

    private function solutions(): void
    {
        $solutions = [
            [
                'corporate-gifts', 'gifts', '1.avif',
                'الهدايا المؤسسية', 'Corporate gifts',
                'هدايا حرفية تحمل هويتكم البصرية، من اختيار الخامة حتى التغليف والتسليم.',
                'Craft gifts carrying your visual identity, from material through packaging to delivery.',
                '<p>الهدية المؤسسية ليست قطعة تُشترى، بل رسالة تُقرأ. نبدأ من هويتكم البصرية ومن طبيعة من ستُهدى إليه، ثم نختار الحرفة التي تناسب الرسالة: السدو لعمق الانتماء، والزري للفخامة، والخوص للبساطة الأنيقة، والنقش الحساوي للطابع المحلي الواضح.</p><p>ننفّذ الشعار على القطعة بأسلوب يحترم الحرفة ولا يطمسها، ونغلّف كل قطعة في علبة جاهزة للتقديم المباشر.</p>',
                '<p>A corporate gift is not a purchase; it is a message. We start from your visual identity and from who will receive it, then choose the craft that carries the message: Sadu for depth of belonging, Zari for prestige, palm-frond weaving for quiet elegance, Al-Ahsa engraving for a distinctly local mark.</p><p>Your mark is applied in a way that respects the craft rather than covering it, and every piece arrives boxed and ready to hand over.</p>',
            ],
            [
                'event-collateral', 'events', '4.avif',
                'مستلزمات الفعاليات', 'Event collateral',
                'دروع ومقتنيات وهدايا متحدّثين للمؤتمرات والمعارض، بكميات وجداول تسليم مضبوطة.',
                'Awards, keepsakes and speaker gifts for conferences and exhibitions, at volume and on schedule.',
                '<p>الفعاليات لا تنتظر. لذلك نعمل بجدول عكسي يبدأ من تاريخ المناسبة ويرجع إلى الوراء: التسليم، ثم التغليف، ثم الإنتاج، ثم اعتماد العيّنة، ثم الاقتراح.</p><p>نغطّي درع المتحدّث، وهدية الوفد، وحقيبة المشارك، وركن العرض الحرفي داخل الجناح — بخامات متجانسة تجعل الفعالية تبدو مصمَّمة لا مجمَّعة.</p>',
                '<p>Events do not wait. So we work a schedule backwards from the date: delivery, packaging, production, sample approval, proposal.</p><p>We cover the speaker award, the delegation gift, the attendee bag, and the live craft corner inside your stand — in consistent materials that make the event look designed rather than assembled.</p>',
            ],
            [
                'custom-production', 'production', '7.webp',
                'الإنتاج المخصّص', 'Custom production',
                'تصنيع بكميات وفق مواصفاتكم: الخامة، والمقاس، والألوان، والهوية.',
                'Production at volume to your specification: material, size, colours and identity.',
                '<p>إن كان لديكم تصوّر جاهز، ننفّذه. وإن كان لديكم احتياج دون تصوّر، نصمّمه معكم.</p><p>نحدّد معكم الكمية والمواصفة وحدود الميزانية، ثم نوزّع الإنتاج على الحرفيين المناسبين لكل مرحلة، ونضبط الجودة على دفعات لا في النهاية فقط — لأن التصحيح المتأخّر في العمل اليدوي مكلف للطرفين.</p>',
                '<p>If you arrive with a concept, we execute it. If you arrive with a need and no concept, we design it with you.</p><p>We agree quantity, specification and budget, distribute production across the right artisans for each stage, and inspect in batches rather than only at the end — because late correction in handwork is expensive for everyone.</p>',
            ],
            [
                'artisan-sourcing', 'sourcing', '2.jpeg',
                'التوريد من الحرفيين', 'Artisan sourcing',
                'نصلكم بحرفيين معتمدين، ونتولّى ضبط الجودة والتعاقد والتسليم.',
                'We connect you to accredited artisans and handle quality, contracting and delivery.',
                '<p>بعض الجهات تريد التعامل مع الحرفي مباشرة، وتحتاج من يضمن أن يصل الطلب في وقته وبالمواصفة المتفق عليها.</p><p>هنا يكون دورنا تشغيليًا: اختيار الحرفيين، وتوثيق الاتفاق، ومتابعة الإنتاج، وضبط الجودة، وإصدار تقرير الأثر الذي يوضّح كم حرفيًا استفاد من طلبكم.</p>',
                '<p>Some organisations want to deal with the artisan directly, and need someone to guarantee the order lands on time and to specification.</p><p>Here our role is operational: selecting artisans, documenting the agreement, following production, controlling quality, and issuing the impact report showing exactly how many artisans your order supported.</p>',
            ],
        ];

        foreach ($solutions as $order => [$slug, $icon, $image, $nameAr, $nameEn, $sumAr, $sumEn, $bodyAr, $bodyEn]) {
            $solution = Solution::query()->updateOrCreate(
                ['slug' => $slug],
                ['icon' => $icon, 'sort_order' => $order, 'is_active' => true]
            );

            $solution->translations()->updateOrCreate(['locale' => 'ar'], [
                'name' => $nameAr, 'summary' => $sumAr, 'body' => $bodyAr,
                'meta_title' => $nameAr, 'meta_description' => $sumAr,
            ]);
            $solution->translations()->updateOrCreate(['locale' => 'en'], [
                'name' => $nameEn, 'summary' => $sumEn, 'body' => $bodyEn,
                'meta_title' => $nameEn, 'meta_description' => $sumEn,
            ]);

            $this->attachImage($solution, 'hero', $image);
        }
    }

    private function sectors(): void
    {
        $copy = [
            Sector::KEY_GOVERNMENT => [
                'الجهات الحكومية', 'Government entities',
                'قطع حرفية تليق بالمناسبات الرسمية واستقبال الوفود، بتوثيق ومواصفات تناسب إجراءات الشراء لديكم.',
                'Craft pieces worthy of official occasions and visiting delegations, with the documentation your procurement requires.',
                '1.avif',
            ],
            Sector::KEY_PRIVATE => [
                'شركات القطاع الخاص', 'Private-sector companies',
                'هدايا للعملاء والموظفين تحمل هوية شركتكم، وتصلح للمناسبات السنوية وإطلاق المنتجات.',
                'Client and employee gifts carrying your company identity, for annual occasions and product launches.',
                '4.avif',
            ],
            Sector::KEY_PARTNERS => [
                'الشركاء', 'Partners',
                'شركات الفعاليات والمعارض ووكالات التسويق: نعمل معكم كمورّد حِرفي خلفي لعملائكم.',
                'Event companies, exhibitions and marketing agencies: we work behind you as the craft supplier for your clients.',
                '6.jpeg',
            ],
            Sector::KEY_ARTISANS => [
                'الحرفيون', 'Artisans',
                'نفتح لكم باب السوق المؤسسي: طلبات مستقرة، وتدريب، وتسعير عادل يعرفه الطرفان قبل البدء.',
                'We open the institutional market to you: steady orders, training, and fair pricing both sides know before work begins.',
                '8.jpeg',
            ],
        ];

        foreach ($copy as $key => [$nameAr, $nameEn, $sumAr, $sumEn, $image]) {
            $sector = Sector::query()->where('key', $key)->first();

            if ($sector === null) {
                continue;
            }

            $sector->translations()->updateOrCreate(['locale' => 'ar'], [
                'name' => $nameAr, 'summary' => $sumAr,
                'meta_title' => $nameAr, 'meta_description' => $sumAr,
            ]);
            $sector->translations()->updateOrCreate(['locale' => 'en'], [
                'name' => $nameEn, 'summary' => $sumEn,
                'meta_title' => $nameEn, 'meta_description' => $sumEn,
            ]);

            $this->attachImage($sector, 'hero', $image);

            $sector->sections()->delete();
            $this->attachSections($sector, $this->sectorSections($key));
        }
    }

    /**
     * Each segment gets its own three points and its own FAQ, because what a
     * ministry needs to be reassured about is not what an artisan needs (§3).
     *
     * @return list<array{0: string, 1: array<string, list<string|null>>, 2?: array<string, mixed>}>
     */
    private function sectorSections(string $key): array
    {
        $cards = match ($key) {
            Sector::KEY_GOVERNMENT => [
                ['icon' => 'fields', 'title' => 'توثيق كامل', 'body' => 'ملف مصدر لكل قطعة يوضّح الحرفة والحرفيين المشاركين فيها.'],
                ['icon' => 'campaigns', 'title' => 'التزام بالتاريخ', 'body' => 'جدول عكسي من تاريخ المناسبة، ومتابعة موثّقة حتى التسليم.'],
                ['icon' => 'impact', 'title' => 'تقرير أثر', 'body' => 'قابل للإدراج ضمن تقاريركم السنوية ومبادراتكم المجتمعية.'],
            ],
            Sector::KEY_PRIVATE => [
                ['icon' => 'brand', 'title' => 'هويتكم أولًا', 'body' => 'الشعار والألوان والتغليف تتبع دليل هويتكم، لا العكس.'],
                ['icon' => 'products', 'title' => 'كميات مرنة', 'body' => 'من دفعة صغيرة لفريق، إلى إنتاج موسمي لقاعدة عملاء.'],
                ['icon' => 'activity', 'title' => 'جدول واضح', 'body' => 'تعرفون تاريخ التسليم قبل بدء الإنتاج لا بعده.'],
            ],
            Sector::KEY_PARTNERS => [
                ['icon' => 'sourcing', 'title' => 'مورّد خلفي', 'body' => 'نعمل باسمكم أمام عميلكم، ولا نتجاوزكم إليه.'],
                ['icon' => 'campaigns', 'title' => 'استجابة سريعة', 'body' => 'اقتراح مبدئي وتسعير أوّلي للعروض العاجلة.'],
                ['icon' => 'reports', 'title' => 'تسعير واضح', 'body' => 'هوامش معروفة مسبقًا تتيح لكم بناء عرضكم بثقة.'],
            ],
            Sector::KEY_ARTISANS => [
                ['icon' => 'impact', 'title' => 'طلب مستقر', 'body' => 'دفعات مجدولة تعرف حجمها وموعدها قبل أن تبدأ.'],
                ['icon' => 'training', 'title' => 'تدريب عملي', 'body' => 'مسارات في الجودة والتسعير والتغليف ترفع قيمة قطعتك.'],
                ['icon' => 'sourcing', 'title' => 'تسعير عادل', 'body' => 'السعر متفق عليه ومكتوب قبل بدء العمل.'],
            ],
            default => [],
        };

        /*
         * The occasions a private-sector buyer actually names. Six, because
         * the seventh is always a variation of one of these — and a grid that
         * has to wrap twice stops being scannable.
         */
        $occasions = $key === Sector::KEY_PRIVATE ? [
            ['icon' => 'gifts', 'title' => 'هدايا نهاية العام', 'body' => 'دفعة موحّدة للعملاء أو للفريق، بجدول يبدأ قبل الموسم لا فيه.'],
            ['icon' => 'partners', 'title' => 'تكريم الموظفين', 'body' => 'قطع للتقاعد وسنوات الخدمة، تُصنع فرادى وتُنقش بالاسم.'],
            ['icon' => 'campaigns', 'title' => 'إطلاق منتج أو فرع', 'body' => 'قطعة تُوزَّع في اليوم نفسه وتبقى على المكتب بعده.'],
            ['icon' => 'contact', 'title' => 'ضيافة كبار العملاء', 'body' => 'عدد محدود بمستوى تشطيب أعلى، بتغليف يُفتح ولا يُرمى.'],
            ['icon' => 'events', 'title' => 'المؤتمرات والمعارض', 'body' => 'كميات للمنصّة، ودروع للمتحدّثين، بموعد تسليم مربوط بالتاريخ.'],
            ['icon' => 'users', 'title' => 'أطقم الترحيب', 'body' => 'طقم للموظف الجديد يصله في أول يوم، يُنتَج على دفعات.'],
        ] : [];

        $faq = match ($key) {
            Sector::KEY_ARTISANS => [
                ['question' => 'هل أحتاج سجلًا تجاريًا للبدء؟', 'answer' => 'لا يُشترط للبدء. ونساعدكم على الترتيب النظامي عند الحاجة إليه.'],
                ['question' => 'من يتحمّل تكلفة الخامات؟', 'answer' => 'يُحدَّد ذلك في الاتفاق قبل البدء، ويختلف بحسب حجم الطلب ونوع الحرفة.'],
                ['question' => 'متى يصلني المقابل؟', 'answer' => 'جدول الدفع يُكتب ضمن الاتفاق، ويُربط بمراحل التسليم لا بنهايتها فقط.'],
            ],
            /*
             * The four a private-sector buyer asks that a government one does
             * not. The invoice answer is a sentence and nothing more: §2.2
             * forbids a commercial function, and the site must not grow one
             * by answering a question about it.
             */
            Sector::KEY_PRIVATE => [
                ['question' => 'ما أقل كمية للطلب؟', 'answer' => 'تختلف بحسب الحرفة والقطعة. أخبرونا بالكمية المطلوبة ونوضّح لكم الممكن بصدق.'],
                ['question' => 'كم تستغرق المدة؟', 'answer' => 'نبني الجدول عكسيًا من تاريخ مناسبتكم، ونخبركم مبكرًا إن كان التاريخ غير كافٍ.'],
                ['question' => 'هل يمكن وضع شعارنا على القطعة نفسها لا التغليف فقط؟', 'answer' => 'نعم، بحسب الخامة: النقش على الخشب والجلد، والتطريز على المنسوجات. نوضّح لكم في العيّنة كيف يظهر الشعار قبل الإنتاج.'],
                ['question' => 'هل يمكن تخصيص التغليف؟', 'answer' => 'نعم. التغليف عندنا جزء من التصميم لا إضافة عليه.'],
                ['question' => 'هل توجد عيّنة قبل اعتماد الكمية؟', 'answer' => 'دائمًا. لا يبدأ الإنتاج قبل أن تعاينوا قطعة فعلية وتعتمدوها باليد.'],
                ['question' => 'هل يمكن التسليم على أكثر من فرع أو مدينة؟', 'answer' => 'نعم. تُقسَّم الدفعة حسب الفروع وتُغلَّف لكل وجهة على حدة.'],
                ['question' => 'هل تتوفر فاتورة ضريبية باسم الشركة؟', 'answer' => 'نعم. الفوترة تتم عبر فريق المبيعات خارج الموقع بعد الاتفاق على الطلب.'],
            ],
            default => [
                ['question' => 'ما أقل كمية للطلب؟', 'answer' => 'تختلف بحسب الحرفة والقطعة. أخبرونا بالكمية المطلوبة ونوضّح لكم الممكن بصدق.'],
                ['question' => 'كم تستغرق المدة؟', 'answer' => 'نبني الجدول عكسيًا من تاريخ مناسبتكم، ونخبركم مبكرًا إن كان التاريخ غير كافٍ.'],
                ['question' => 'هل يمكن تخصيص التغليف؟', 'answer' => 'نعم. التغليف عندنا جزء من التصميم لا إضافة عليه.'],
            ],
        };

        /*
         * The heading names the audience.
         *
         * The four segment pages exist because a procurement officer and an
         * events agency need to be addressed differently, and four pages
         * headed "ما نقدّمه لهذا القطاع" read as one template served four
         * times — which is what the client saw and objected to.
         *
         * The segment's own name is substituted into the sentence that was
         * already there. No new copy is written: §0.1 puts that on Amad
         * Craft, and the final wording will replace this from the panel.
         */
        // Two forms per audience: "to X" for what we offer, "with X" for how
        // we work. Arabic takes a different preposition for each, so one
        // interpolated phrase would have produced "كيف نعمل للجهات الحكومية".
        [$toAr, $toEn, $withAr, $withEn] = match ($key) {
            Sector::KEY_GOVERNMENT => [
                'للجهات الحكومية', 'to government entities',
                'مع الجهات الحكومية', 'with government entities',
            ],
            Sector::KEY_PRIVATE => [
                'لشركات القطاع الخاص', 'to private-sector companies',
                'مع شركات القطاع الخاص', 'with private-sector companies',
            ],
            Sector::KEY_PARTNERS => [
                'لشركائنا', 'to our partners',
                'مع شركائنا', 'with our partners',
            ],
            Sector::KEY_ARTISANS => [
                'للحرفيين والحرفيات', 'to artisans',
                'مع الحرفيين والحرفيات', 'with artisans',
            ],
            default => ['لهذا القطاع', 'to this sector', 'معكم', 'with you'],
        };

        return [
            // The hero's two actions. The heading and line come from the
            // sector record itself, so only the buttons live here.
            ['hero', [
                'ar' => [null, null, null, 'لنبدأ معًا', '/ar/contact'],
                'en' => [null, null, null, "Let's begin", '/en/contact'],
            ], [
                // settings is one JSON column, not per-locale, so a second
                // button carries both languages here.
                'secondaryLabel' => 'زوروا معرضنا',
                'secondaryUrl' => '/ar/contact',
                'secondaryLabel_en' => 'Visit our showroom',
                'secondaryUrl_en' => '/en/contact',
            ]],

            /*
             * No opening statement section here, deliberately.
             *
             * It used to be seeded from the sector's own summary — which is
             * also the hero's subtitle, so the same sentence appeared twice
             * within one screen of itself, once small and once very large.
             * The segment's need is stated in the hero; repeating it verbatim
             * underneath read as a template that had run out of things to say.
             */

            ['cards', [
                'ar' => ["ما نقدّمه {$toAr}"],
                'en' => ["What we offer {$toEn}"],
            ], ['items' => $cards]],

            /*
             * The same four stages that were a paragraph, as the procedure
             * they describe. Nothing new is written: each step's sentence is
             * a clause lifted from the paragraph it replaces.
             */
            ['process_steps', [
                'ar' => ["كيف نعمل {$withAr}"],
                'en' => ["How we work {$withEn}"],
            ], ['items' => [
                [
                    'title' => 'اجتماع قصير',
                    'body' => 'نفهم فيه المناسبة والجمهور والميزانية.',
                    'title_en' => 'A short meeting',
                    'body_en' => 'To understand the occasion, the audience and the budget.',
                ],
                [
                    'title' => 'ثلاثة اتجاهات',
                    'body' => 'اقتراح مكتوب وثلاثة اتجاهات حرفية بخامات مختلفة.',
                    'title_en' => 'Three directions',
                    'body_en' => 'A written proposal and three craft directions in different materials.',
                ],
                [
                    'title' => 'عيّنة تُعتمد باليد',
                    'body' => 'قطعة فعلية تُعاين وتُعتمد قبل أي إنتاج.',
                    'title_en' => 'A sample approved in the hand',
                    'body_en' => 'A real piece, seen and approved before any production begins.',
                ],
                [
                    'title' => 'إنتاج وتسليم',
                    'body' => 'جدول واضح، وقطع تصل مغلّفة وجاهزة.',
                    'title_en' => 'Production and delivery',
                    'body_en' => 'A clear schedule, and pieces that arrive packaged and ready.',
                ],
            ]]],

            /*
             * Occasions, not products.
             *
             * A company buys for a moment — the year's end, a launch, a
             * retirement — and names that moment before it names an object.
             * The segment that thinks this way gets the section; the others
             * do not, which is the point of four separate pages.
             */
            ...($occasions === [] ? [] : [
                ['cards', [
                    'ar' => ['متى تطلب الشركات منّا'],
                    'en' => ['When companies come to us'],
                ], ['items' => $occasions]],
            ]),

            ['accordion', [
                'ar' => ['أسئلة متكررة'],
                'en' => ['Frequently asked'],
            ], ['items' => $faq]],

            ['cta_band', [
                'ar' => ['نبدأ بمحادثة قصيرة', 'اتركوا وسيلة تواصل واحدة، ونعود إليكم باقتراح مبدئي.', null, 'تواصلوا معي'],
                'en' => ['It starts with a short conversation', 'Leave one way to reach you and we will come back with an initial proposal.', null, 'Contact me'],
            ]],
        ];
    }

    /**
     * Clears the eight hand-written placeholder products this seeder used to
     * create, along with the three invented categories they sat in.
     *
     * `amad:import-store` now brings the real catalogue across from Amad
     * Craft's Zid storefront — 332 products in the store's own 16 categories,
     * with the store's own names and photographs. Three categories called
     * "الهدايا / مستلزمات المكتب / الإكسسوارات" that exist nowhere but here
     * would sit alongside them looking equally real.
     *
     * A targeted delete rather than "remove everything the importer did not
     * write", so a product Amad Craft adds by hand in the panel survives a
     * re-seed.
     */
    private function products(): void
    {
        $slugs = [
            'sadu-folder', 'zari-bag', 'khoos-keychain', 'ahsa-medal',
            'macrame-keychain', 'sadu-notebook', 'khoos-clock', 'zari-bracelet',
        ];

        ShowcaseProduct::query()->whereIn('slug', $slugs)->get()->each->forceDelete();
        ProductCategory::query()->whereIn('slug', ['gifts', 'office', 'accessories'])->get()->each->delete();
    }

    /**
     * ⚠️ Illustrative figures. These MUST be replaced with Amad Craft's own
     * before launch — publishing an unverified number to government buyers is
     * a claim, not a placeholder.
     */
    private function impact(): void
    {
        $metrics = [
            ['artisans', 240, '+', 'حرفيًا وحرفية', 'Artisans engaged', 'منذ التأسيس', 'Since founding'],
            ['pieces', 18500, '+', 'قطعة حرفية سُلّمت', 'Craft pieces delivered', 'لجهات ومؤسسات', 'To institutions'],
            ['entities', 36, '', 'جهة ومؤسسة', 'Entities served', 'حكومية وخاصة', 'Public and private'],
            ['training-hours', 4200, '+', 'ساعة تدريب', 'Training hours', 'ضمن مسارات التمكين', 'Across the empowerment tracks'],
        ];

        foreach ($metrics as $order => [$key, $value, $suffix, $labelAr, $labelEn, $noteAr, $noteEn]) {
            $metric = ImpactMetric::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value_numeric' => $value,
                    'value_suffix' => $suffix,
                    'year' => (int) now()->format('Y'),
                    'sort_order' => $order,
                    'is_active' => true,
                ]
            );

            $metric->translations()->updateOrCreate(['locale' => 'ar'], ['label' => $labelAr, 'note' => $noteAr]);
            $metric->translations()->updateOrCreate(['locale' => 'en'], ['label' => $labelEn, 'note' => $noteEn]);
        }
    }

    /**
     * ⚠️ Composed illustrations, not real people. Replace with real artisans,
     * their own words, and documented consent before launch.
     */
    private function stories(): void
    {
        $stories = [
            ['story-sadu', 'craft/craft-03.webp',
                'من النول إلى المكتب', 'From the loom to the office',
                'السدو ليس نقشًا على قماش، هو ذاكرة نسجتها جدّتي وسلّمتها لي. اليوم يخرج من بيتي إلى مكاتب لم أتخيّل يومًا أن يصلها.',
                'Sadu is not a pattern on cloth. It is a memory my grandmother wove and handed to me. Today it leaves my house for offices I never imagined it reaching.',
                'حرفية سدو · القصيم', 'Sadu weaver · Al-Qassim'],
            ['story-khoos', 'craft/craft-06.webp',
                'طلب أعرف موعده', 'An order with a date on it',
                'كنت أبيع القطعة في السوق الأسبوعي وأنتظر من يمرّ. الآن أُنتج بطلبات مجدولة، وأعرف كم سأصنع الشهر القادم.',
                'I used to sell a piece at the weekly market and wait for someone to pass. Now I work to scheduled orders, and I know what I will make next month.',
                'حرفي خوص · الأحساء', 'Palm-frond weaver · Al-Ahsa'],
            ['story-zari', 'craft/craft-11.webp',
                'خيط ذهبي بيدي', 'A gold thread, by my hand',
                'أصعب ما في الزري هو الصبر. وأجملُ ما فيه أن ترى خيطًا ذهبيًا صنعتِه بيدك على حقيبة تُهدى لضيف دولة.',
                'The hardest thing about Zari is patience. The most beautiful is seeing a gold thread you made by hand on a bag presented to a state guest.',
                'حرفية زري · المدينة المنورة', 'Zari artisan · Madinah'],
        ];

        foreach ($stories as $order => [$slug, $image, $titleAr, $titleEn, $quoteAr, $quoteEn, $attrAr, $attrEn]) {
            $story = Story::query()->updateOrCreate(
                ['slug' => $slug],
                ['sort_order' => $order, 'is_published' => true, 'published_at' => now()]
            );

            $story->translations()->updateOrCreate(['locale' => 'ar'], [
                'title' => $titleAr, 'quote' => $quoteAr, 'attribution' => $attrAr,
            ]);
            $story->translations()->updateOrCreate(['locale' => 'en'], [
                'title' => $titleEn, 'quote' => $quoteEn, 'attribution' => $attrEn,
            ]);

            $this->attachImage($story, 'person', $image);
        }
    }

    private function training(): void
    {
        $programs = [
            ['sadu-track', 8, '7.webp',
                'مسار السدو', 'Sadu track',
                'تدريب عملي على نول السدو من الغزل حتى القطعة الجاهزة، مع وحدة في التسعير والتغليف.',
                'Hands-on training at the Sadu loom, from yarn to finished piece, with a unit on pricing and packaging.',
                'يُنهي المتدرّب المسار بثلاث قطع جاهزة للعرض، وملف تسعير لمنتجه، وربط مباشر بطلبات أمد الحرف.',
                'Graduates finish with three display-ready pieces, a pricing file for their product, and a direct link to Amad Craft orders.'],
            ['khoos-track', 6, '8.jpeg',
                'مسار الخوص', 'Khoos track',
                'من تجهيز السعف حتى الجدل والتشطيب، مع تركيز على ثبات الجودة عبر الكميات الكبيرة.',
                'From preparing the frond through plaiting to finishing, focused on holding quality steady across volume.',
                'إتقان ثلاثة أنماط جدل، والقدرة على تنفيذ دفعة متجانسة من خمسين قطعة.',
                'Command of three plaiting patterns and the ability to deliver a consistent batch of fifty.'],
            ['business-track', 4, '2.jpeg',
                'مسار ريادة الأعمال الحرفية', 'Craft-business track',
                'التسعير، والتصوير، والتعامل مع الطلبات المؤسسية، وبناء ملف تعريفي للحرفي.',
                'Pricing, photography, handling institutional orders, and building an artisan portfolio.',
                'ملف تعريفي جاهز، وقائمة أسعار مبنية على التكلفة الحقيقية لا على التخمين.',
                'A finished portfolio and a price list built on real cost rather than guesswork.'],
        ];

        foreach ($programs as $order => [$slug, $weeks, $image, $nameAr, $nameEn, $sumAr, $sumEn, $outAr, $outEn]) {
            $program = TrainingProgram::query()->updateOrCreate(
                ['slug' => $slug],
                ['duration_weeks' => $weeks, 'sort_order' => $order, 'is_active' => true]
            );

            $program->translations()->updateOrCreate(['locale' => 'ar'], [
                'name' => $nameAr, 'summary' => $sumAr, 'outcomes' => $outAr,
            ]);
            $program->translations()->updateOrCreate(['locale' => 'en'], [
                'name' => $nameEn, 'summary' => $sumEn, 'outcomes' => $outEn,
            ]);

            $this->attachImage($program, 'hero', $image);
        }
    }

    /**
     * The names and logos published on amadcraft.sa, in the order that site
     * shows them.
     *
     * Every one of these is a real organisation, so the categorisation is not
     * a design choice: amadcraft.sa lists all five under one heading —
     * "الشركاء" — with no accreditation or client group, so they are all
     * TYPE_PARTNER here. Filing any of them under "accreditation" or "client"
     * would assert a relationship the client has not published, which is a
     * claim about that organisation and not ours to make. The two other groups
     * stay empty and their sections stay hidden until Amad Craft fills them in
     * from the panel.
     *
     * The fifth entry is not a company: it is (أمد), Alinma Bank's
     * sustainability and social-responsibility programme, whose three values
     * — إدراك، مساهمة، ديمومة — are the three marks in the lockup.
     *
     * @see https://amadcraft.sa/
     * @see https://www.alinma.com/ar/about-the-bank/amad
     */
    private function partners(): void
    {
        $partners = [
            ['Alinma Bank', 'مصرف الإنماء', 'alinma.webp'],
            ['Al Ahsa Chamber', 'غرفة الأحساء', 'al-ahsa-chamber.webp'],
            ['Heritage Commission', 'هيئة التراث', 'heritage-commission.webp'],
            ['Impact Valley Capital', 'وادي الأثر المالية', 'impact-valley.webp'],
            ['AMAD — Sustainability & Social Responsibility Program', 'أمد — برنامج الاستدامة والمسؤولية الاجتماعية', 'amad-program.webp'],
        ];

        $this->removeLegacyDemoPartners(array_column($partners, 0));

        foreach ($partners as $order => [$en, $ar, $logo]) {
            $partner = Partner::query()->updateOrCreate(
                ['name' => $en],
                ['type' => Partner::TYPE_PARTNER, 'sort_order' => $order, 'is_active' => true]
            );

            $partner->translations()->updateOrCreate(['locale' => 'ar'], ['display_name' => $ar]);
            $partner->translations()->updateOrCreate(['locale' => 'en'], ['display_name' => $en]);

            $this->attachLogo($partner, "partner/{$logo}", $ar, $en);
        }
    }

    /**
     * Clears out the placeholder partners this seeder used to create.
     *
     * Scoped to rows this seeder is replacing rather than "everything not in
     * the list", so a partner Amad Craft added from the panel survives a
     * re-seed. Deleting through the model, not the query builder, so the media
     * library's delete hook still removes the logo file.
     *
     * @param  list<string>  $keep  names the seeder is about to write
     */
    private function removeLegacyDemoPartners(array $keep): void
    {
        $legacy = [
            'Events partner', 'Supply partner', 'Training partner',
            'Craft accreditation body', 'Quality accreditation body',
            'Government entity', 'Private-sector company',
        ];

        Partner::query()
            ->whereIn('name', array_diff($legacy, $keep))
            ->get()
            ->each
            ->delete();
    }

    /**
     * A logo, with the organisation's own name as its alt text.
     *
     * Separate from attachImage() because that one writes "a craft piece" as
     * the alt text of everything it touches — right for a product photo, wrong
     * for a logo, where the alt text is the only thing a screen reader has to
     * tell one silent mark from another.
     *
     * Unlike attachImage() this does not skip a row that already has media: an
     * earlier run of this seeder put a craft photograph on every partner, and
     * "leave what is already there" would preserve exactly the wrong image.
     * Only a logo that is already the intended file is left alone, so a
     * re-seed neither churns storage nor keeps a stale mark.
     */
    private function attachLogo(Partner $partner, string $file, string $ar, string $en): void
    {
        $path = public_path("images/{$file}");

        if (! is_file($path)) {
            $this->command?->warn("Missing partner logo: {$file}");

            return;
        }

        $expected = basename($file);
        $current = $partner->getFirstMedia('logo');

        if ($current?->file_name === $expected) {
            return;
        }

        $current?->delete();

        $media = $partner->addMedia($path)->preservingOriginal()->toMediaCollection('logo');

        $media->translations()->updateOrCreate(['locale' => 'ar'], ['alt_text' => "شعار {$ar}"]);
        $media->translations()->updateOrCreate(['locale' => 'en'], ['alt_text' => "{$en} logo"]);
    }

    /** ⚠️ Illustrative. Replace with Amad Craft's real publications. */
    private function reports(): void
    {
        foreach ([[now()->year - 1], [now()->year - 2]] as $order => [$year]) {
            $report = Report::query()->updateOrCreate(
                ['slug' => "impact-{$year}"],
                ['year' => $year, 'sort_order' => $order, 'is_public' => true]
            );

            $report->translations()->updateOrCreate(['locale' => 'ar'], [
                'title' => "تقرير الأثر السنوي {$year}",
                'summary' => 'ملخّص لما أُنجز خلال العام: عدد الحرفيين، والقطع المسلَّمة، والجهات التي عملنا معها.',
            ]);
            $report->translations()->updateOrCreate(['locale' => 'en'], [
                'title' => "Annual impact report {$year}",
                'summary' => 'A summary of the year: artisans engaged, pieces delivered, and the institutions we worked with.',
            ]);

            $this->attachImage($report, 'cover', '13.webp');
        }
    }

    private function navigation(): void
    {
        /*
         * The header is five entries, a language switch and one button — no
         * more. "Products" and "Sectors" are gone from it: the four audience
         * segments now live under Solutions as a dropdown, which is where a
         * buyer looks for "is there something here for a body like mine",
         * and the catalogue is reached from the showroom section on the home
         * page rather than from a top-level menu entry.
         *
         * A fourth element per row is the child list.
         */
        $menus = [
            'header' => [
                ['/{l}/solutions', 'الحلول', 'Solutions', [
                    ['/{l}/solutions/government', 'الجهات الحكومية وشبه الحكومية', 'Government & quasi-government'],
                    ['/{l}/solutions/companies', 'شركات القطاع الخاص', 'Private-sector companies'],
                    ['/{l}/solutions/partners', 'الشركاء', 'Partners'],
                    ['/{l}/solutions/artisans', 'الحرفيون', 'Artisans'],
                ]],
                ['/{l}/impact', 'الأثر', 'Impact'],
                ['/{l}/training', 'التدريب والتمكين', 'Training & enablement'],
                ['/{l}/about', 'من نحن', 'About'],
                ['/{l}/contact', 'تواصلوا معنا', 'Contact'],
            ],
            'footer_main' => [
                ['/{l}/solutions', 'الحلول', 'Solutions'],
                ['/{l}/impact', 'الأثر', 'Impact'],
                ['/{l}/partners', 'الشركاء', 'Partners'],
                ['/{l}/training', 'التدريب والتمكين', 'Training & enablement'],
            ],
            // The footer's second column. Kept apart from footer_main so the
            // two columns are the client's to compose, not a split this file
            // decides for them.
            'footer_company' => [
                ['/{l}/about', 'من نحن', 'About'],
                ['/{l}/solutions/government', 'الجهات الحكومية', 'Government entities'],
                ['/{l}/solutions/artisans', 'الحرفيون', 'Artisans'],
                ['/{l}/contact', 'تواصلوا معنا', 'Contact'],
            ],
            'footer_legal' => [
                ['/{l}/legal/privacy', 'الخصوصية', 'Privacy'],
                ['/{l}/legal/terms', 'الشروط', 'Terms'],
            ],
        ];

        foreach ($menus as $key => $items) {
            $navigation = Navigation::query()->firstOrCreate(['key' => $key], ['is_active' => true]);
            $navigation->items()->getModel()->query()->where('navigation_id', $navigation->id)->delete();

            foreach ($items as $order => $spec) {
                $item = $this->navigationItem($navigation, $spec, $order, null);

                foreach ($spec[3] ?? [] as $childOrder => $child) {
                    $this->navigationItem($navigation, $child, $childOrder, $item->id);
                }
            }
        }
    }

    /**
     * One menu entry, with its two labels.
     *
     * @param  array{0: string, 1: string, 2: string, 3?: array<int, mixed>}  $spec
     */
    private function navigationItem(Navigation $navigation, array $spec, int $order, ?int $parentId): NavigationItem
    {
        [$url, $ar, $en] = $spec;

        $item = $navigation->items()->getModel()->query()->create([
            'navigation_id' => $navigation->id,
            'parent_id' => $parentId,
            // Stored with the Arabic prefix; NavigationBuilder swaps it per
            // locale, so the English menu needs no second row.
            'url' => str_replace('{l}', 'ar', $url),
            'sort_order' => $order,
            'is_active' => true,
        ]);

        $item->translations()->updateOrCreate(['locale' => 'ar'], ['label' => $ar]);
        $item->translations()->updateOrCreate(['locale' => 'en'], ['label' => $en]);

        return $item;
    }

    // ---------------------------------------------------------------- //

    private function attachImage(Model $model, string $collection, string $file): void
    {
        $path = public_path("images/{$file}");

        if (! is_file($path)) {
            $this->command?->warn("Missing demo image: {$file}");

            return;
        }

        if ($model->getMedia($collection)->isNotEmpty()) {
            return;
        }

        $media = $model->addMedia($path)->preservingOriginal()->toMediaCollection($collection);

        // Alt text is content and is managed per locale (§10.8).
        foreach (['ar' => 'قطعة حرفية من إنتاج أمد الحرف', 'en' => 'A craft piece made by Amad Craft'] as $locale => $text) {
            $media->translations()->updateOrCreate(['locale' => $locale], ['alt_text' => $text]);
        }
    }

    /**
     * One of the client's own photographs, prepared by
     * tools/prepare-craft-photos.mjs.
     *
     * The dimensions are measured from the file rather than written here: the
     * resize keeps each photograph's own aspect ratio, so hard-coding a pair
     * would put the wrong `width`/`height` on the tag and reintroduce exactly
     * the layout shift those attributes exist to prevent.
     *
     * @return array<string, mixed>
     */
    /**
     * All eleven of the client's showroom photographs, in order.
     *
     * No captions: the client asked for the pieces to speak without a line of
     * text under each one. The alt text stays — it is what a screen reader
     * and an image crawler read, and dropping it would make the section
     * invisible to both.
     *
     * @return list<array<string, mixed>>
     */
    private function craftGallery(): array
    {
        $images = [];

        for ($n = 1; $n <= 11; $n++) {
            $images[] = $this->craftPayload(
                str_pad((string) $n, 2, '0', STR_PAD_LEFT),
                'من داخل معرض أمد الحرف',
                null
            );
        }

        return $images;
    }

    private function craftPayload(string $n, string $alt, ?string $caption): array
    {
        $file = "craft/craft-{$n}.webp";
        $path = public_path("images/{$file}");

        [$width, $height] = is_file($path) ? getimagesize($path) : [null, null];

        return [
            'url' => "/images/{$file}",
            'webp' => "/images/{$file}",
            'width' => $width,
            'height' => $height,
            // `settings` is not a translated column, so this text is the same
            // in both languages — the same limitation the older imagePayload()
            // helper has. Images uploaded through the admin panel carry proper
            // per-locale alt text via MediaTranslation and are unaffected.
            'alt' => $alt,
            'caption' => $caption,
        ];
    }

    /** @return array<string, mixed> */
    private function imagePayload(string $file, int $width, int $height): array
    {
        return [
            'url' => "/images/{$file}",
            'webp' => "/images/{$file}",
            'width' => $width,
            'height' => $height,
            'alt' => 'قطعة حرفية من إنتاج أمد الحرف',
        ];
    }
}
