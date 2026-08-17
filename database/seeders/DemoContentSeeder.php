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
use Database\Seeders\Concerns\SeedsRows;
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
    use SeedsRows;

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
            // The dock survives on campaign pages only, and it opens the same
            // three-field form as everywhere else — so this line describes
            // that form, not the single field it once described.
            'contact.dock_note.ar' => 'ثلاث خانات وننطلق — اسم جهتكم ورقمكم، ونتولّى نحن الباقي.',
            'contact.dock_note.en' => 'Three fields and we begin — your organisation and your number, and we take it from there.',
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

            // The visit request on /contact, and the sentence it starts the
            // message box with. Draft copy — the button disappears if the
            // client empties the label.
            'contact.visit_cta.ar' => 'احجزوا زيارة بموعد مسبق',
            'contact.visit_cta.en' => 'Book a visit by appointment',
            'contact.visit_prompt.ar' => 'نرغب بزيارة المعرض يوم…',
            'contact.visit_prompt.en' => 'We would like to visit the showroom on…',

            // What WhatsApp opens with. In this market it is the channel that
            // actually gets answered, so it is a button, not a printed number.
            'contact.whatsapp_message.ar' => 'مرحبًا أمد الحرف، أرغب بالتواصل بخصوص…',
            'contact.whatsapp_message.en' => 'Hello Amad Craft, I would like to talk about…',

            // ⚠️ Deliberately absent: contact.response_promise.* and its
            // switch. See StructureSeeder — a reply-time commitment is Amad
            // Craft's to make, and seeding one would publish a promise nobody
            // at the company has agreed to.

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

        /*
         * The optional message, back on (decision A).
         *
         * The company's decision fixed the three REQUIRED fields; it never
         * removed §7's optional message, and turning it off left the contact
         * page heading «أخبرونا كيف نخدمكم» asking a question the form had no
         * way to receive. It costs the visitor nothing — `LeadField` keeps it
         * collapsed behind a link until asked for (§10.6), so the form is
         * still three inputs on arrival — and it catches the sentence that
         * qualifies a lead: "we have a conference on the 14th".
         *
         * Nothing downstream needed building: `message` is a reserved column
         * on `leads`, `LeadPayload` already carries it to the CRM, and
         * `NewLeadReceived` already prints it.
         */
        LeadField::query()
            ->where('key', LeadField::KEY_MESSAGE)
            ->update(['is_enabled' => true, 'is_required' => false, 'sort_order' => 3]);
    }

    private function pages(): void
    {
        foreach ($this->pageCopy() as $slug => $data) {
            // A demo seeder owns the pages it draws: it deletes and rebuilds
            // their sections a few lines down. So the three columns that
            // decide whether the page renders at all are enforced rather than
            // set-on-create — a `home` row left as a draft, or pointed at the
            // wrong template by an earlier run, would leave this seeder
            // reporting success over a page nobody can see.
            $page = $this->seedRow(
                Page::query(),
                identity: ['slug' => $slug],
                structure: ['template' => $slug, 'status' => 'published', 'published_at' => now()],
            );

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
                            /*
                             * The form on this page, not a trip to /contact.
                             *
                             * The home page ends in the one form (§6.1), and
                             * `CtaBand` carries `id="lead"` on every page that
                             * renders it. Sending a visitor who has just
                             * decided to act through a full page load — and
                             * making them find the form again at the other end
                             * — is a step this site exists to remove.
                             */
                            '#lead',
                        ],
                        'en' => [
                            "Saudi craft,\nworthy of your name",
                            'We turn Sadu, palm-frond weaving, Zari and Al-Ahsa engraving into corporate gifts that carry your identity — and meet your guests at the standard they expect.',
                            null,
                            'Start your project with us',
                            '#lead',
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
                    /*
                     * The hero's action is «زوروا معرضنا», not the site-wide
                     * "let's begin". This is the page a visitor opens after a
                     * solutions page has already convinced them; what is left
                     * to settle is whether we are real, and the room in Riyadh
                     * settles that better than any paragraph. It points at the
                     * visit block above the footer, which carries the address,
                     * the hours and the map. The form is still at the bottom
                     * of the page — this adds a path, it does not replace one.
                     */
                    ['hero', [
                        'ar' => [null, null, null, 'زوروا معرضنا', '#visit'],
                        'en' => [null, null, null, 'Visit our showroom', '#visit'],
                    ]],

                    ['rich_text', [
                        'ar' => ['لماذا وُجدنا', null,
                            '<p>الحرفة في المملكة لم تتوقف يومًا. ما توقّف هو الجسر بينها وبين السوق: الحرفي يصنع قطعة بديعة، ثم يقف أمام سؤال لا يملك إجابته — من يشتريها، وبأي سعر، وبأي كمية، وكيف تصل؟</p>'
                            .'<p>بُنيت أمد الحرف لتكون هذا الجسر. نتعامل مع الطرفين بلغتيهما: نفهم من الحرفي ما تحتمله يده وخامته وجدوله، ونفهم من الجهة ما تفرضه هويتها ومعايير الشراء لديها وتاريخ مناسبتها. ثم نتولّى المسافة بينهما كاملة — التصميم، والعيّنة، وضبط الجودة، والتغليف، والتسليم.</p>'
                            .'<p>النتيجة أن الجهة تحصل على قطعة لا تشبه ما يوزّعه غيرها، وأن الحرفي يحصل على طلب مستقر يعرف قيمته قبل أن يبدأ.</p>'],
                        'en' => ['Why we exist', null,
                            '<p>Craft in the Kingdom never stopped. What stopped was the bridge between it and the market: an artisan makes something remarkable, then faces a question they have no answer to — who buys it, at what price, in what quantity, and how does it get there?</p>'
                            .'<p>Amad Craft was built to be that bridge. We speak to both sides in their own terms: we learn from the artisan what their hands, materials and schedule can carry, and from the institution what its identity, procurement standards and event date demand. Then we take on the whole distance between them — design, sample, quality control, packaging and delivery.</p>'
                            .'<p>The result is a piece unlike anything else being handed out, and an artisan with steady work whose value is known before it begins.</p>'],
                    ], [
                        /*
                         * The first photograph of the PLACE on this page, and
                         * the reason the leather clutch that used to sit here
                         * is gone. On the page telling the company's own story
                         * the single image was a product shot repeated from
                         * every other page; the showroom is the one subject
                         * that belongs here and nowhere else.
                         */
                        'image' => $this->craftPayload('01', 'من داخل معرض أمد الحرف في الرياض', null),
                    ]],

                    /*
                     * The bridge model — this page's signature, and what
                     * replaced the «كيف نعمل» media split that used to sit
                     * here.
                     *
                     * That block described the operational process: understand
                     * the occasion, propose directions, make a sample, produce.
                     * All four solutions pages carry that same procedure, in
                     * `process_steps`, per segment. On /about the question is
                     * not "how will you run my order" — the visitor settled
                     * that two pages ago — it is "what ARE you". Repeating the
                     * procedure here answered a question nobody was asking and
                     * put the same paragraph on a fifth page.
                     *
                     * Every string below is lifted from the opening story
                     * directly above it: the two parties in their own terms,
                     * and the five stages of «المسافة بينهما كاملة». Nothing
                     * here is a new claim — it is the paragraph, drawn.
                     */
                    ['bridge_model', [
                        'ar' => ['نموذجنا: جسر بين طرفين', 'طرفان لا يصل أحدهما إلى الآخر، ومسافة نتولّاها كاملة.'],
                        'en' => ['Our model: a bridge between two sides', 'Two parties who cannot reach each other, and a distance we take on in full.'],
                    ], [
                        'start' => [
                            'title' => 'الحرفي',
                            'title_en' => 'The artisan',
                            'body' => 'يده، وخامته، وجدوله',
                            'body_en' => 'Their hands, their materials, their schedule',
                        ],
                        'end' => [
                            'title' => 'الجهة',
                            'title_en' => 'The institution',
                            'body' => 'هويتها، ومعايير الشراء لديها، وتاريخ مناسبتها',
                            'body_en' => 'Its identity, its procurement standards, its date',
                        ],
                        'middle' => [
                            'title' => 'أمد الحرف',
                            'title_en' => 'Amad Craft',
                            'body' => 'نتولّى المسافة بينهما كاملة',
                            'body_en' => 'We take on the whole distance between them',
                        ],
                        'items' => [
                            ['title' => 'التصميم', 'title_en' => 'Design'],
                            ['title' => 'العيّنة', 'title_en' => 'Sample'],
                            ['title' => 'ضبط الجودة', 'title_en' => 'Quality control'],
                            ['title' => 'التغليف', 'title_en' => 'Packaging'],
                            ['title' => 'التسليم', 'title_en' => 'Delivery'],
                        ],
                    ]],

                    /*
                     * ⚠️ PLACEHOLDER HISTORY — the four years and the four
                     * ✅ These four are REAL, and sourced.
                     *
                     * They come from Amad Craft's own «تقرير مركز التدريب
                     * والإنتاج بالأحساء — سبتمبر 2025 إلى فبراير 2026»: the
                     * cohort dates and workshop names (p.5), the Banan
                     * recognition (p.25), the registration volume (p.7), the
                     * 75 trainees (p.7, and the two cohort rosters on p.14 and
                     * p.21 sum to the same number), and the 44 production
                     * contracts (p.24).
                     *
                     * They replace four invented milestones — a 2022 founding,
                     * a first institutional order, and so on — none of which
                     * had a source. A founding year a developer chose is not a
                     * placeholder, it is a published fact about the company.
                     *
                     * The period is stated as month · year rather than year
                     * alone because that is the precision the report supports.
                     * Nothing here reaches back before September 2025: the
                     * report does not cover it, so the site does not claim it.
                     *
                     * Still panel-managed — year, title, note, add, reorder —
                     * and `Timeline` renders nothing below three milestones.
                     */
                    ['timeline', [
                        'ar' => ['محطات'],
                        'en' => ['Milestones'],
                    ], ['items' => [
                        ['year' => '09 · 2025', 'title' => 'انطلاق المجموعة التدريبية الأولى',
                            'title_en' => 'The first training cohort opens',
                            'body' => 'ثلاث ورش في مركز التدريب والإنتاج بالأحساء: السدو للمبتدئين، والسجاد اليدوي، والنقش على الجبس.',
                            'body_en' => 'Three workshops at the Al-Ahsa training and production centre: Sadu for beginners, hand-woven rugs, and gypsum carving.'],
                        ['year' => '11 · 2025', 'title' => 'التكريم في أسبوع الحرف السعودي الدولي',
                            'title_en' => 'Honoured at Saudi International Handicrafts Week',
                            'body' => 'المشاركة في معرض بنان ضمن جناح الشركاء الرئيسيين، والتكريم من الرئيس التنفيذي لهيئة التراث.',
                            'body_en' => 'A place in the principal partners’ pavilion at Banan, and recognition from the CEO of the Heritage Commission.'],
                        ['year' => '12 · 2025', 'title' => 'انطلاق المجموعة الثانية',
                            'title_en' => 'The second cohort opens',
                            'body' => 'ورش الخوص، والسدو التطويرية، والنقش على الجبس، بعد استقبال أكثر من ثلاثمئة طلب تسجيل.',
                            'body_en' => 'Palm-frond weaving, advanced Sadu and gypsum carving, after more than three hundred applications.'],
                        ['year' => '02 · 2026', 'title' => 'اختتام الفترة الأولى',
                            'title_en' => 'The first period closes',
                            'body' => 'خمس وسبعون متدربة أنهين البرنامج، وأربعة وأربعون عقد إنتاج مبرمة مع حرفيات.',
                            'body_en' => 'Seventy-five women completed the programme, and forty-four production contracts were signed with artisans.'],
                    ]]],

                    /*
                     * The figures, the accreditations and the Vision 2030 band
                     * are all CALLS to the components the home page and
                     * /impact already use — one ImpactStats reading one set of
                     * records, one PartnersLogos, one CardsGrid. They are
                     * rendered in the position the panel puts them, through
                     * SectionRenderer's `data` prop, so reordering this page
                     * needs no developer and editing a figure changes it in
                     * all three places at once.
                     */
                    ['stats', [
                        'ar' => ['أمد الحرف بالأرقام'],
                        'en' => ['Amad Craft in numbers'],
                    ]],

                    ['logos', [
                        'ar' => ['اعتماداتنا وشركاؤنا'],
                        'en' => ['Our accreditations and partners'],
                    ]],

                    /*
                     * Seeded with its heading and NO points, exactly as on
                     * /impact and for the same reason: which Vision 2030
                     * objective this work sits under is a claim a semi-public
                     * body scores against their own framework, and it is Amad
                     * Craft's to make. `CardsGrid` renders `v-if items.length`,
                     * so the section is silent until the panel fills it.
                     */
                    ['cards', [
                        'ar' => ['امتدادنا في رؤية 2030'],
                        'en' => ['Where this sits in Vision 2030'],
                    ], ['items' => [], 'variant' => 'band']],

                    /*
                     * Built, and switched OFF — the fourth element.
                     *
                     * Publishing the team is Amad Craft's decision, and the
                     * one thing that must never stand in for it is a grid of
                     * invented names over stock portraits: that does not read
                     * as a placeholder, it reads as a claim about who works
                     * here (§22.1). The row exists with its heading and no
                     * members, so the panel switches it on the day there are
                     * real people and real photographs to put in it.
                     */
                    ['team', [
                        'ar' => ['فريق العمل'],
                        'en' => ['The team'],
                    ], ['items' => []], false],

                    /*
                     * The place, at length. The editorial spread at the top of
                     * the page opens with one showroom photograph; this is
                     * where the page breathes.
                     *
                     * Four of the eleven photographs Amad Craft supplied, not
                     * all eleven — the home page's mosaic already runs the
                     * full set, and repeating it here would make the two pages
                     * the same document. There is no photograph of the team,
                     * of an artisan at work, or of a workshop anywhere in this
                     * project; §22.1 forbids inventing one, so the subject
                     * here is the room.
                     */
                    ['gallery', [
                        'ar' => ['المعرض من الداخل'],
                        'en' => ['Inside the showroom'],
                    ], ['images' => [
                        $this->craftPayload('03', 'من داخل معرض أمد الحرف في الرياض', null),
                        $this->craftPayload('06', 'رفوف العرض في معرض أمد الحرف', null),
                        $this->craftPayload('09', 'جدار العرض في معرض أمد الحرف', null),
                        $this->craftPayload('11', 'صالة معرض أمد الحرف', null),
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
                    /*
                     * The hero's two actions. `page_translations` carries no
                     * CTA columns, so they live on a section the way the four
                     * segment heroes do: the label per locale in the section
                     * translation, the second action in `settings` — which is
                     * one JSON column, hence the `_en` suffix rather than a
                     * second row (§12: no fallback, so English absent means
                     * English absent).
                     *
                     * The first action goes to the report shelf rather than to
                     * the form. A public body arriving here has usually come
                     * for the document, and making them read the page first to
                     * reach it is a toll, not a funnel.
                     */
                    ['hero', [
                        'ar' => [null, null, null, 'حمّلوا تقرير الأثر'],
                        'en' => [null, null, null, 'Download the impact report'],
                    ], [
                        'ctaUrl' => '#reports',
                        'secondaryLabel' => 'لنبدأ معًا',
                        'secondaryLabel_en' => 'Let us begin',
                        'secondaryUrl' => '#lead',
                    ]],

                    ['stats', ['ar' => ['أثرنا بالأرقام'], 'en' => ['Our impact in numbers']]],

                    /*
                     * «كيف نقيس» and «ارتباطنا برؤية 2030» are seeded with
                     * their headings and NO items, so neither renders yet.
                     *
                     * Both are methodology, and methodology is the one thing
                     * on this page that cannot be drafted from outside. The
                     * hero promises we measure «استقرار الدخل» — whether that
                     * means three consecutive months of orders or six is a
                     * fact only Amad Craft holds, and a placeholder definition
                     * on the page whose entire job is to be checkable would do
                     * more damage than an absent section. Same for the Vision
                     * 2030 wording, which a semi-public body scores against
                     * their own framework.
                     *
                     * The rows exist in the section builder; `CardsGrid`
                     * renders `v-if="items.length"`. Filling `items` in the
                     * panel switches each section on with no further work.
                     */
                    ['cards', [
                        'ar' => ['كيف نقيس', 'تعريف كل مؤشر من المؤشرات أعلاه.'],
                        'en' => ['How we measure', 'What each of the figures above counts.'],
                    ], ['items' => []]],

                    ['story_carousel', ['ar' => ['قصص من الميدان'], 'en' => ['Stories from the field']]],

                    ['cards', [
                        'ar' => ['ارتباطنا برؤية 2030'],
                        'en' => ['How this aligns with Vision 2030'],
                    ], ['items' => [], 'variant' => 'band']],

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
                    /*
                     * Two doors from the hero, because this page has two
                     * readers. The first is a plain fragment link down to the
                     * tracks — an artisan wants to see what is on offer before
                     * anything else. The second has no URL, so `PageHero`
                     * renders a button and the page handles it: it records who
                     * is asking before moving them to the form, which is not
                     * something an href can carry.
                     */
                    ['hero', [
                        'ar' => [null, null, null, 'التحقوا بمسار'],
                        'en' => [null, null, null, 'Join a track'],
                    ], [
                        'ctaUrl' => '#tracks',
                        'secondaryLabel' => 'ارعوا مسارًا',
                        'secondaryLabel_en' => 'Sponsor a track',
                        // The tag the lead is filed under. Here rather than in
                        // code, so the vocabulary belongs to the panel.
                        'secondaryInterest' => 'sponsor',
                    ]],

                    ['training_tracks', ['ar' => ['المسارات التدريبية'], 'en' => ['Training tracks']]],

                    /*
                     * The same component the segment pages use for "how we
                     * work". The fifth step is the one that matters: a track
                     * here ends at Amad Craft's own orders, not at a
                     * certificate, and that is the whole difference between
                     * this and a training institute.
                     */
                    ['process_steps', [
                        'ar' => ['كيف يعمل المسار'],
                        'en' => ['How a track runs'],
                    ], ['items' => [
                        ['title' => 'التقديم', 'body' => 'عبر النموذج في هذه الصفحة أو بزيارة المعرض.',
                            'title_en' => 'Apply', 'body_en' => 'Through the form on this page, or by visiting the showroom.'],
                        ['title' => 'مقابلة تقييم', 'body' => 'نرى عملكم ونتحقّق من إتقان الحرفة — لا من خبرة البيع.',
                            'title_en' => 'An assessment interview', 'body_en' => 'We see your work and check your command of the craft — not your selling experience.'],
                        ['title' => 'التدريب العملي', 'body' => 'بمدة المسار المعلنة على بطاقته، على النول أو الخامة نفسها.',
                            'title_en' => 'Hands-on training', 'body_en' => 'For the length stated on the track card, at the loom or the material itself.'],
                        ['title' => 'مخرَج ملموس', 'body' => 'قطع جاهزة للعرض وملف تسعير مبني على التكلفة الحقيقية.',
                            'title_en' => 'Something you can hold', 'body_en' => 'Display-ready pieces and a price file built on real cost.'],
                        ['title' => 'الربط بالطلبات', 'body' => 'ما يُنتَج بعد المسار يدخل طلبات أمد الحرف، منسوبًا إلى صانعه.',
                            'title_en' => 'A route to orders', 'body_en' => 'What you make after the track enters Amad Craft orders, credited to you.'],
                    ]]],

                    /*
                     * The paragraph that used to close this page, split in two.
                     *
                     * Not a rewrite: the section was already two sentences
                     * addressed to two different people, and as one block the
                     * institution won — an artisan who read to the bottom of
                     * the page met a form headed «ترغبون برعاية مسار تدريبي؟».
                     * Two columns give each of them a door, and each door
                     * carries the tag its lead is filed under.
                     */
                    ['audience_split', [
                        'ar' => ['لمن هذه المسارات'],
                        'en' => ['Who these tracks are for'],
                    ], ['items' => [
                        [
                            'interest' => 'trainee',
                            'title' => 'للحرفيين والحرفيات',
                            'body' => 'المسارات مفتوحة لمن يريد تحويل مهارته إلى دخل مستقر. لا تشترط خبرة سابقة في البيع، وتشترط إتقانًا فعليًا للحرفة.',
                            'actionLabel' => 'التحقوا بمسار',
                            // The work being learned, photographed in the room
                            // where it is learned. The alt describes the
                            // action, because that is what the frame is for.
                            'image' => $this->imagePayload('sojad.png', 220, 403),
                            'imageAlt' => 'متدرّبة تعمل على نول السجاد داخل مركز التدريب',
                            'imageAlt_en' => 'A trainee working at the rug loom in the training centre',
                            'title_en' => 'For artisans',
                            'body_en' => 'Open to anyone wanting to turn their skill into steady income. No selling experience is required; genuine command of the craft is.',
                            'actionLabel_en' => 'Join a track',
                        ],
                        [
                            'interest' => 'sponsor',
                            'title' => 'للجهات الراعية',
                            'body' => 'ترغبون برعاية مسار كامل أو تنفيذه في منطقة تحدّدونها؟ نصمّم البرنامج ونُشغّله ونزوّدكم بتقرير أثر عند ختامه.',
                            'actionLabel' => 'ارعوا مسارًا',
                            /*
                             * A finished piece held up at the door of the
                             * training centre — the sign is in the frame. It
                             * is the one photograph that answers a sponsor's
                             * actual question: what does a funded track hand
                             * over, and does the place exist.
                             */
                            'image' => $this->imagePayload('gbs.png', 278, 485),
                            'imageAlt' => 'قطعة جبس منقوشة من إنتاج مركز التدريب والتأهيل',
                            'imageAlt_en' => 'A carved gypsum piece made at the training and qualification centre',
                            // The measurement behind that report is stated once,
                            // on /impact. A second account of it here would be a
                            // second version of the same methodology.
                            'linkLabel' => 'كيف نقيس الأثر',
                            'linkUrl' => '/ar/impact',
                            'title_en' => 'For sponsoring organisations',
                            'body_en' => 'Want to sponsor a full track, or run one in a region you choose? We design it, operate it, and hand you an impact report at the end.',
                            'actionLabel_en' => 'Sponsor a track',
                            'linkLabel_en' => 'How we measure impact',
                            'linkUrl_en' => '/en/impact',
                        ],
                    ]]],

                    /*
                     * Training figures, named by key and read from the one
                     * register — never restated here. Two of the three have no
                     * value yet and stay invisible until Amad Craft counts
                     * them; see the metrics seeder.
                     */
                    ['stats', [
                        'ar' => ['أثر التدريب بالأرقام'],
                        'en' => ['Training impact in numbers'],
                    ], ['keys' => ['training-hours', 'training-graduates', 'training-to-production']]],

                    /*
                     * Graduates of these tracks — the same story records
                     * /impact publishes, filtered by tag. Seeded with the tag
                     * and no tagged stories, so the section is silent: what
                     * happened to somebody who took a track is theirs to say,
                     * and a graduate testimonial written from outside is a
                     * fabricated endorsement, not placeholder copy (§22.1).
                     *
                     * Tagging one real story in the panel switches it on.
                     */
                    ['story_carousel', [
                        'ar' => ['من خريجي المسارات'],
                        'en' => ['From the graduates'],
                    ], ['tag' => 'training-graduate']],

                    /*
                     * Five, and not one of them carries a figure — no fee, no
                     * income, no employment promise. §2.2 forbids this site
                     * growing a commercial function, and a page about training
                     * is exactly where one would start.
                     */
                    ['accordion', [
                        'ar' => ['أسئلة متكررة'],
                        'en' => ['Frequently asked'],
                    ], ['items' => [
                        [
                            'question' => 'هل التدريب مجاني للحرفي؟',
                            'answer' => 'التدريب جزء من التأهيل للعمل معنا. تفاصيل كل مسار تُوضَّح في مقابلة التقييم قبل أي التزام.',
                            'question_en' => 'Is the training free for the artisan?',
                            'answer_en' => 'Training is part of being qualified to work with us. Each track is explained in full at the assessment interview, before any commitment.',
                        ],
                        [
                            'question' => 'أين تُقام المسارات، وهل تصل مناطق خارج الرياض؟',
                            'answer' => 'نُنفّذ المسار حيث يتوفّر الحرفيون والمكان المناسب، وللجهة الراعية أن تحدّد منطقة تنفيذ المسار الذي ترعاه.',
                            'question_en' => 'Where do the tracks run, and do they reach beyond Riyadh?',
                            'answer_en' => 'A track runs where the artisans and a suitable space are, and a sponsoring organisation chooses the region for the track it funds.',
                        ],
                        [
                            'question' => 'هل يُشترط سجل تجاري للالتحاق؟',
                            'answer' => 'لا يُشترط للالتحاق. ونساعدكم على الترتيب النظامي عند الحاجة إليه.',
                            'question_en' => 'Do I need a commercial registration to join?',
                            'answer_en' => 'Not to join. We help with the paperwork if and when it becomes necessary.',
                        ],
                        [
                            'question' => 'ماذا يحدث بعد إنهاء المسار؟',
                            'answer' => 'يُربط المتدرّب بطلبات أمد الحرف، والقطعة تُعرض منسوبة إلى صانعها. تفاصيل العمل معنا في صفحة الحرفيين.',
                            'question_en' => 'What happens once the track ends?',
                            'answer_en' => 'You are connected to Amad Craft orders, and your piece is shown credited to you. How working with us runs is set out on the artisans page.',
                        ],
                        [
                            'question' => 'كيف ترعى جهتنا مسارًا، وماذا نستلم في الختام؟',
                            'answer' => 'نصمّم البرنامج ونُشغّله ونزوّدكم بتقرير أثر عند ختامه. أرسلوا لنا ما ترغبون بتحقيقه ونعود إليكم بمقترح مسار.',
                            'question_en' => 'How does our organisation sponsor a track, and what do we receive at the end?',
                            'answer_en' => 'We design the programme, operate it, and hand you an impact report when it closes. Send us what you want it to achieve and we will come back with a proposed track.',
                        ],
                    ]]],

                    /*
                     * One form, two faces. The three fields never change (§6.1)
                     * — only the heading, the example in the message box, and
                     * the tag the lead is stored with. The section's own
                     * heading is what a visitor who pressed nothing sees, and
                     * that lead carries no tag: they did not say.
                     */
                    ['cta_band', [
                        'ar' => ['ترغبون برعاية مسار تدريبي؟', 'نصمّم البرنامج ونشغّله ونوثّق أثره لكم.', null, 'تواصلوا معي'],
                        'en' => ['Interested in sponsoring a track?', 'We design it, run it, and document its impact for you.', null, 'Contact me'],
                    ], ['variants' => [
                        'sponsor' => [
                            'messagePlaceholder' => 'نرغب برعاية مسار تدريبي في منطقتنا',
                            'messagePlaceholder_en' => 'We would like to sponsor a track in our region',
                        ],
                        'trainee' => [
                            'heading' => 'جاهزون تنضمّون لمسار؟',
                            'heading_en' => 'Ready to join a track?',
                            'subheading' => 'اتركوا وسيلة تواصل واحدة، ونرتّب معكم مقابلة التقييم.',
                            'subheading_en' => 'Leave one way to reach you and we will arrange the assessment interview.',
                            'messagePlaceholder' => 'أعمل في حرفة الخوص وأرغب بالالتحاق',
                            'messagePlaceholder_en' => 'I work in palm-frond weaving and would like to join',
                        ],
                    ]]],
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

            // The subtitle and the reassurance line describe the form that is
            // actually on the page. They used to describe the §6.1 single
            // field, which the client replaced with three required ones — so a
            // visitor read "we ask for no name" directly above a required
            // company-name field. A form that contradicts its own promise
            // costs more trust than a long form does.
            'contact' => [
                'ar' => ['تواصلوا معنا', 'ثلاث خانات وننطلق — اسم جهتكم ورقمكم، ونتولّى نحن الباقي.'],
                'en' => ['Contact us', 'Three fields and we begin — your organisation and your number, and we take it from there.'],
                'sections' => [
                    ['contact_block', [
                        'ar' => ['أخبرونا كيف نخدمكم', 'لا استمارات طويلة ولا تفاصيل الآن — البقية نستكملها معكم في أول مكالمة.', null, 'تواصلوا معي'],
                        'en' => ['Tell us how we can help', 'No long forms and no details now — we cover the rest with you on the first call.', null, 'Contact me'],
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
     * The fourth element switches a section OFF. It exists for a section that
     * is built, correct and deliberately not published yet — the About page's
     * team grid — so that turning it on is a toggle in the panel rather than
     * a developer's afternoon.
     *
     * @param  list<array{0: string, 1: array<string, list<string|null>>, 2?: array<string, mixed>, 3?: bool}>  $sections
     */
    private function attachSections(Model $owner, array $sections): void
    {
        foreach ($sections as $position => $spec) {
            [$type, $copy] = $spec;

            $section = $owner->sections()->create([
                'type' => $type,
                'sort_order' => $position,
                'is_active' => $spec[3] ?? true,
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
                ['icon' => 'fields', 'title' => 'توثيق كامل', 'body' => 'ملف مصدر لكل قطعة يوضّح الحرفة والحرفيين المشاركين فيها.',
                    'title_en' => 'Full documentation', 'body_en' => 'A provenance file for each piece, naming the craft and the artisans who made it.'],
                ['icon' => 'campaigns', 'title' => 'التزام بالتاريخ', 'body' => 'جدول عكسي من تاريخ المناسبة، ومتابعة موثّقة حتى التسليم.',
                    'title_en' => 'The date is held', 'body_en' => 'A schedule built backwards from the occasion, tracked in writing through to delivery.'],
                ['icon' => 'impact', 'title' => 'تقرير أثر', 'body' => 'قابل للإدراج ضمن تقاريركم السنوية ومبادراتكم المجتمعية.',
                    'title_en' => 'An impact report', 'body_en' => 'Publishable inside your annual reporting and your community initiatives.'],
            ],
            Sector::KEY_PRIVATE => [
                ['icon' => 'brand', 'title' => 'هويتكم أولًا', 'body' => 'الشعار والألوان والتغليف تتبع دليل هويتكم، لا العكس.',
                    'title_en' => 'Your identity first', 'body_en' => 'Mark, colours and packaging follow your brand guide, not the other way round.'],
                ['icon' => 'products', 'title' => 'كميات مرنة', 'body' => 'من دفعة صغيرة لفريق، إلى إنتاج موسمي لقاعدة عملاء.',
                    'title_en' => 'Flexible quantities', 'body_en' => 'From a small batch for one team to seasonal production for a client base.'],
                ['icon' => 'activity', 'title' => 'جدول واضح', 'body' => 'تعرفون تاريخ التسليم قبل بدء الإنتاج لا بعده.',
                    'title_en' => 'A clear schedule', 'body_en' => 'You know the delivery date before production starts, not after.'],
            ],
            Sector::KEY_PARTNERS => [
                ['icon' => 'sourcing', 'title' => 'مورّد خلفي', 'body' => 'نعمل باسمكم أمام عميلكم، ولا نتجاوزكم إليه.',
                    'title_en' => 'A supplier behind you', 'body_en' => 'We work under your name in front of your client, and never go around you.'],
                ['icon' => 'campaigns', 'title' => 'استجابة سريعة', 'body' => 'اقتراح مبدئي وتسعير أوّلي للعروض العاجلة.',
                    'title_en' => 'A fast response', 'body_en' => 'An initial proposal and an indicative price for urgent bids.'],
                ['icon' => 'reports', 'title' => 'تسعير واضح', 'body' => 'هوامش معروفة مسبقًا تتيح لكم بناء عرضكم بثقة.',
                    'title_en' => 'Pricing you can plan on', 'body_en' => 'Margins known in advance, so you can build your own proposal with confidence.'],
            ],
            Sector::KEY_ARTISANS => [
                ['icon' => 'impact', 'title' => 'طلب مستقر', 'body' => 'دفعات مجدولة تعرف حجمها وموعدها قبل أن تبدأ.',
                    'title_en' => 'Steady orders', 'body_en' => 'Scheduled batches whose size and date you know before you begin.'],
                ['icon' => 'training', 'title' => 'تدريب عملي', 'body' => 'مسارات في الجودة والتسعير والتغليف ترفع قيمة قطعتك.',
                    'title_en' => 'Hands-on training', 'body_en' => 'Tracks in quality, pricing and packaging that raise what your piece is worth.'],
                ['icon' => 'sourcing', 'title' => 'تسعير عادل', 'body' => 'السعر متفق عليه ومكتوب قبل بدء العمل.',
                    'title_en' => 'Fair pricing', 'body_en' => 'The price is agreed and written down before work begins.'],
            ],
            default => [],
        };

        /*
         * The occasions a private-sector buyer actually names. Six, because
         * the seventh is always a variation of one of these — and a grid that
         * has to wrap twice stops being scannable.
         */
        $occasions = $key === Sector::KEY_PRIVATE ? [
            ['icon' => 'gifts', 'title' => 'هدايا نهاية العام', 'body' => 'دفعة موحّدة للعملاء أو للفريق، بجدول يبدأ قبل الموسم لا فيه.',
                'title_en' => 'Year-end gifts', 'body_en' => 'One consistent batch for clients or for the team, on a schedule that starts before the season rather than inside it.'],
            ['icon' => 'partners', 'title' => 'تكريم الموظفين', 'body' => 'قطع للتقاعد وسنوات الخدمة، تُصنع فرادى وتُنقش بالاسم.',
                'title_en' => 'Recognising employees', 'body_en' => 'Pieces for retirements and service years, made one at a time and engraved with the name.'],
            ['icon' => 'campaigns', 'title' => 'إطلاق منتج أو فرع', 'body' => 'قطعة تُوزَّع في اليوم نفسه وتبقى على المكتب بعده.',
                'title_en' => 'A launch or a new branch', 'body_en' => 'A piece handed out on the day that stays on the desk afterwards.'],
            ['icon' => 'contact', 'title' => 'ضيافة كبار العملاء', 'body' => 'عدد محدود بمستوى تشطيب أعلى، بتغليف يُفتح ولا يُرمى.',
                'title_en' => 'Hosting key clients', 'body_en' => 'A limited number at a higher finish, in packaging that is opened rather than thrown away.'],
            ['icon' => 'events', 'title' => 'المؤتمرات والمعارض', 'body' => 'كميات للمنصّة، ودروع للمتحدّثين، بموعد تسليم مربوط بالتاريخ.',
                'title_en' => 'Conferences and exhibitions', 'body_en' => 'Volume for the stand and awards for the speakers, delivered against the date.'],
            ['icon' => 'users', 'title' => 'أطقم الترحيب', 'body' => 'طقم للموظف الجديد يصله في أول يوم، يُنتَج على دفعات.',
                'title_en' => 'Welcome sets', 'body_en' => 'A set that reaches a new joiner on their first day, produced in batches.'],
        ] : [];

        /*
         * A partner's promises, not a buyer's benefits.
         *
         * This is the section the page exists for. An events agency's first
         * question is never about the object — it is "if I introduce you to
         * my client, do you take them from me". Everything else on the page
         * is answered elsewhere; this is answered nowhere else.
         */
        $pledges = $key === Sector::KEY_PARTNERS ? [
            ['icon' => 'shield', 'title' => 'عميلكم يبقى عميلكم', 'body' => 'لا نتواصل معه مباشرة إلا بطلبكم.',
                'title_en' => 'Your client stays yours', 'body_en' => 'We do not approach them directly unless you ask us to.'],
            ['icon' => 'brand', 'title' => 'نظهر بالقدر الذي تختارونه', 'body' => 'باسمكم بالكامل، أو كشريك معلن — القرار قراركم.',
                'title_en' => 'We appear as much as you choose', 'body_en' => 'Entirely under your name, or as a named partner — your decision.'],
            ['icon' => 'campaigns', 'title' => 'مواعيدكم التزامنا', 'body' => 'تاريخ فعاليتكم هو خط الإنتاج عندنا.',
                'title_en' => 'Your dates are our commitment', 'body_en' => 'The date of your event is what our production line is built around.'],
        ] : [];

        /** How a partner works with us, as three named arrangements. */
        $models = $key === Sector::KEY_PARTNERS ? [
            ['icon' => 'events', 'title' => 'شركات الفعاليات والمؤتمرات', 'body' => 'هدايا المتحدّثين وكبار الحضور، ودروع وتذكارات، وأطقم ضيافة — بجدول مربوط بتاريخ الفعالية.',
                'title_en' => 'Event and conference companies', 'body_en' => 'Speaker and VIP gifts, awards and keepsakes, hospitality sets — on a schedule tied to the event date.'],
            ['icon' => 'store', 'title' => 'شركات المعارض', 'body' => 'ركن حرفي حيّ أو قطع جاهزة لأجنحة عملائكم، بكميات مضبوطة.',
                'title_en' => 'Exhibition companies', 'body_en' => 'A live craft corner or finished pieces for your clients\' stands, in controlled quantities.'],
            ['icon' => 'partners', 'title' => 'التسويق بالعمولة', 'body' => 'تعرّفوننا على العميل ونتولّى التنفيذ. تفاصيل النموذج مع فريق المبيعات.',
                'title_en' => 'Referral partnerships', 'body_en' => 'You introduce the client and we handle delivery. The detail of the arrangement sits with the sales team.'],
        ] : [];

        /*
         * The steps differ by segment because the relationship does. A
         * government body is buying a procedure it can check; a partner has
         * bought before and is racing a tender deadline — the same four
         * stages would have been the template the client objected to.
         */
        /*
         * The four crafts §3 names, as a structure the client fills.
         *
         * Deliberately placeholders: §3 is explicit that the targets are four
         * specific crafts, and naming them myself would be inventing the
         * company's own sourcing strategy. The grid exists; the names arrive
         * with the content file.
         */
        $targets = $key === Sector::KEY_ARTISANS ? [
            ['icon' => 'stories', 'title' => 'الحرفة الأولى', 'body' => 'يُدخل الاسم والوصف من لوحة التحكم.',
                'title_en' => 'The first craft', 'body_en' => 'Name and description are entered from the admin panel.'],
            ['icon' => 'stories', 'title' => 'الحرفة الثانية', 'body' => 'يُدخل الاسم والوصف من لوحة التحكم.',
                'title_en' => 'The second craft', 'body_en' => 'Name and description are entered from the admin panel.'],
            ['icon' => 'stories', 'title' => 'الحرفة الثالثة', 'body' => 'يُدخل الاسم والوصف من لوحة التحكم.',
                'title_en' => 'The third craft', 'body_en' => 'Name and description are entered from the admin panel.'],
            ['icon' => 'stories', 'title' => 'الحرفة الرابعة', 'body' => 'يُدخل الاسم والوصف من لوحة التحكم.',
                'title_en' => 'The fourth craft', 'body_en' => 'Name and description are entered from the admin panel.'],
        ] : [];

        /*
         * What the artisan gets — the section that replaces the buyer-facing
         * services grid, which was addressed to someone who is not reading
         * this page.
         */
        $gains = $key === Sector::KEY_ARTISANS ? [
            ['icon' => 'store', 'title' => 'قناة بيع مؤسسية', 'body' => 'منتجاتكم تصل إلى جهات وشركات لا يصلها الحرفي منفردًا.',
                'title_en' => 'An institutional channel', 'body_en' => 'Your work reaches organisations an artisan working alone does not reach.'],
            ['icon' => 'training', 'title' => 'تطوير مهني', 'body' => 'تدريب مستمر على الجودة والتغليف والتسعير.',
                'title_en' => 'Professional development', 'body_en' => 'Continuing training in quality, packaging and pricing.'],
            ['icon' => 'stories', 'title' => 'اسمكم وقصتكم', 'body' => 'القطعة تُعرض باسم صانعها، وقصته تُروى معها.',
                'title_en' => 'Your name and your story', 'body_en' => 'A piece is shown under its maker\'s name, with their story told alongside it.'],
        ] : [];

        $steps = match ($key) {
            /*
             * A joining journey, not a purchase. This is the one segment
             * that is not buying: the reader is deciding whether to work
             * with us, so the sequence is theirs — from first contact to a
             * standing relationship — and it is five steps because the
             * training stage is the one they most need to see named.
             */
            Sector::KEY_ARTISANS => [
                ['title' => 'تواصلوا معنا', 'body' => 'أو زوروا المعرض ومعكم نماذج من أعمالكم.',
                    'title_en' => 'Get in touch', 'body_en' => 'Or visit the showroom, bringing samples of your work.'],
                ['title' => 'جلسة تعارف', 'body' => 'نرى أعمالكم ونسمع منكم، وتسألون ما تشاؤون.',
                    'title_en' => 'A first conversation', 'body_en' => 'We see your work and hear from you, and you ask whatever you need to.'],
                ['title' => 'تأهيل وتدريب عند الحاجة', 'body' => 'الجودة والتغليف والتسعير — بما ينقص لا بما يُفترض.',
                    'title_en' => 'Training where it is needed', 'body_en' => 'Quality, packaging and pricing — what is missing, not what is assumed.'],
                ['title' => 'أول طلبية', 'body' => 'بسعر مكتوب متفق عليه قبل أن تبدأ اليد بالعمل.',
                    'title_en' => 'A first order', 'body_en' => 'At a written price, agreed before the work begins.'],
                ['title' => 'شراكة مستمرة', 'body' => 'طلبات مجدولة تعرفون حجمها وموعدها، وقصتكم تُروى مع منتجاتكم.',
                    'title_en' => 'A standing partnership', 'body_en' => 'Scheduled orders whose size and date you know, and your story told with your work.'],
            ],
            Sector::KEY_PARTNERS => [
                ['title' => 'أرسلوا موجز الفعالية', 'body' => 'التاريخ والجمهور والعدد التقريبي يكفي للبدء.',
                    'title_en' => 'Send the brief', 'body_en' => 'The date, the audience and a rough count are enough to start.'],
                ['title' => 'تسعير أوّلي سريع', 'body' => 'رقم مبدئي تبنون عليه عرضكم دون انتظار.',
                    'title_en' => 'A fast initial price', 'body_en' => 'An indicative figure you can build your own proposal on.'],
                ['title' => 'عيّنة ثم إنتاج', 'body' => 'اعتماد على القطعة، ثم إنتاج على جدول الفعالية لا جدولنا.',
                    'title_en' => 'Sample, then production', 'body_en' => 'Approval on the piece, then production on the event\'s schedule, not ours.'],
                ['title' => 'تسليم حيث تحتاجون', 'body' => 'إلى موقع الفعالية أو مستودعكم، وبالتغليف الذي يناسب ظهوركم.',
                    'title_en' => 'Delivery where you need it', 'body_en' => 'To the venue or to your warehouse, packaged to suit how you appear.'],
            ],
            default => [
                ['title' => 'اجتماع قصير', 'body' => 'نفهم فيه المناسبة والجمهور والميزانية.',
                    'title_en' => 'A short meeting', 'body_en' => 'To understand the occasion, the audience and the budget.'],
                ['title' => 'ثلاثة اتجاهات', 'body' => 'اقتراح مكتوب وثلاثة اتجاهات حرفية بخامات مختلفة.',
                    'title_en' => 'Three directions', 'body_en' => 'A written proposal and three craft directions in different materials.'],
                ['title' => 'عيّنة تُعتمد باليد', 'body' => 'قطعة فعلية تُعاين وتُعتمد قبل أي إنتاج.',
                    'title_en' => 'A sample approved in the hand', 'body_en' => 'A real piece, seen and approved before any production begins.'],
                ['title' => 'إنتاج وتسليم', 'body' => 'جدول واضح، وقطع تصل مغلّفة وجاهزة.',
                    'title_en' => 'Production and delivery', 'body_en' => 'A clear schedule, and pieces that arrive packaged and ready.'],
            ],
        };

        /*
         * Both languages on every item.
         *
         * `settings` is one JSON column shared by the two locales, so a
         * repeatable list inside it carries `question` and `question_en` — the
         * same shape the process steps already used. Until these were added,
         * `FaqAccordion` printed the Arabic keys verbatim on /en, so an
         * English-reading buyer met six Arabic questions under an English
         * heading. §12 forbids that far more strongly than it forbids a
         * shorter page.
         */
        $faq = match ($key) {
            /*
             * A partner's six. Every one of them is about the relationship,
             * not the product — and the commission answer carries no figure,
             * because a percentage on a public page is a price list (§2.2).
             */
            Sector::KEY_PARTNERS => [
                ['question' => 'هل تتعاملون مع عميلنا مباشرة أم من خلالنا فقط؟', 'answer' => 'من خلالكم، ما لم تطلبوا غير ذلك كتابةً. العميل الذي تُعرّفوننا به يبقى عميلكم.',
                    'question_en' => 'Do you deal with our client directly, or only through us?', 'answer_en' => 'Through you, unless you ask otherwise in writing. A client you introduce us to stays your client.'],
                ['question' => 'هل يمكن أن تصلنا القطع بدون أي إشارة إلى أمد الحرف؟', 'answer' => 'نعم. التغليف والبطاقات تُنفَّذ باسمكم بالكامل إن أردتم، أو باسم الطرفين — أنتم تحدّدون.',
                    'question_en' => 'Can the pieces reach us with no mention of Amad Craft?', 'answer_en' => 'Yes. Packaging and cards can carry your name alone, or both names — you decide.'],
                ['question' => 'ما أسرع مدة لتسعير أوّلي لمنافسة عاجلة؟', 'answer' => 'أرسلوا الموجز ونعود إليكم برقم مبدئي في أقرب وقت ممكن حسب وضوح المتطلّب. الرقم النهائي بعد اعتماد العيّنة.',
                    'question_en' => 'How fast can we get an indicative price for an urgent bid?', 'answer_en' => 'Send the brief and we come back with an indicative figure as soon as the requirement is clear. The final figure follows sample approval.'],
                ['question' => 'هل يمكن إحضار عميلنا لزيارة المعرض معنا؟', 'answer' => 'نعم، وبموعد مسبق. كثير من الشركاء يجعلون الزيارة جزءًا من عرضهم.',
                    'question_en' => 'Can we bring our client to the showroom with us?', 'answer_en' => 'Yes, by appointment. Many partners make the visit part of their own pitch.'],
                ['question' => 'هل تخدمون أكثر من فعالية لنفس الشريك في وقت واحد؟', 'answer' => 'نعم. كل فعالية تُدار كطلب مستقل بجدولها الخاص.',
                    'question_en' => 'Can you run more than one of our events at the same time?', 'answer_en' => 'Yes. Each event is handled as its own order on its own schedule.'],
                ['question' => 'كيف يعمل نموذج الإحالة أو العمولة؟', 'answer' => 'يُتّفق عليه كتابةً قبل البدء ويختلف بحسب حجم التعاون ودوركم فيه. التفاصيل مع فريق المبيعات.',
                    'question_en' => 'How does the referral or commission model work?', 'answer_en' => 'Agreed in writing before we begin, and it varies with the size of the collaboration and your role in it. The detail sits with the sales team.'],
            ],
            Sector::KEY_ARTISANS => [
                ['question' => 'هل أحتاج سجلًا تجاريًا للبدء؟', 'answer' => 'لا يُشترط للبدء. ونساعدكم على الترتيب النظامي عند الحاجة إليه.',
                    'question_en' => 'Do I need a commercial registration to start?', 'answer_en' => 'Not to start. We help with the paperwork if and when it becomes necessary.'],
                ['question' => 'من يتحمّل تكلفة الخامات؟', 'answer' => 'يُحدَّد ذلك في الاتفاق قبل البدء، ويختلف بحسب حجم الطلب ونوع الحرفة.',
                    'question_en' => 'Who covers the cost of materials?', 'answer_en' => 'It is set in the agreement before work begins, and varies with the size of the order and the craft.'],
                ['question' => 'متى يصلني المقابل؟', 'answer' => 'جدول الدفع يُكتب ضمن الاتفاق، ويُربط بمراحل التسليم لا بنهايتها فقط.',
                    'question_en' => 'When am I paid?', 'answer_en' => 'The payment schedule is written into the agreement and tied to delivery stages, not only to the end of them.'],
                ['question' => 'هل أعمل معكم حصريًا أم أستطيع البيع في مكاني؟', 'answer' => 'لا حصرية. تبيعون حيث شئتم، وما تنتجونه لنا يُتفق عليه طلبًا بطلب.',
                    'question_en' => 'Is this exclusive, or can I still sell where I am?', 'answer_en' => 'No exclusivity. Sell wherever you like; what you make for us is agreed order by order.'],
                ['question' => 'هل التدريب مدفوع أم مجاني؟', 'answer' => 'التدريب جزء من التأهيل للعمل معنا. تفاصيل كل مسار تُوضَّح في جلسة التعارف قبل أي التزام.',
                    'question_en' => 'Is the training paid or free?', 'answer_en' => 'Training is part of being qualified to work with us. Each track is explained in full at the first meeting, before any commitment.'],
                ['question' => 'كيف تُعرض منتجاتي وباسم من تُباع؟', 'answer' => 'باسمكم. القطعة تُعرض منسوبة إلى صانعها، وقصته تُروى معها.',
                    'question_en' => 'How are my pieces shown, and under whose name?', 'answer_en' => 'Yours. A piece is shown credited to the person who made it, with their story alongside it.'],
            ],
            /*
             * The four a private-sector buyer asks that a government one does
             * not. The invoice answer is a sentence and nothing more: §2.2
             * forbids a commercial function, and the site must not grow one
             * by answering a question about it.
             */
            Sector::KEY_PRIVATE => [
                ['question' => 'ما أقل كمية للطلب؟', 'answer' => 'تختلف بحسب الحرفة والقطعة. أخبرونا بالكمية المطلوبة ونوضّح لكم الممكن بصدق.',
                    'question_en' => 'What is the minimum order?', 'answer_en' => 'It varies with the craft and the piece. Tell us the quantity you need and we will tell you honestly what is possible.'],
                ['question' => 'كم تستغرق المدة؟', 'answer' => 'نبني الجدول عكسيًا من تاريخ مناسبتكم، ونخبركم مبكرًا إن كان التاريخ غير كافٍ.',
                    'question_en' => 'How long does it take?', 'answer_en' => 'We build the schedule backwards from your date, and tell you early if the date is not enough.'],
                ['question' => 'هل يمكن وضع شعارنا على القطعة نفسها لا التغليف فقط؟', 'answer' => 'نعم، بحسب الخامة: النقش على الخشب والجلد، والتطريز على المنسوجات. نوضّح لكم في العيّنة كيف يظهر الشعار قبل الإنتاج.',
                    'question_en' => 'Can our mark go on the piece itself, not just the packaging?', 'answer_en' => 'Yes, depending on the material: engraving on wood and leather, embroidery on textiles. The sample shows you how it sits before production.'],
                ['question' => 'هل يمكن تخصيص التغليف؟', 'answer' => 'نعم. التغليف عندنا جزء من التصميم لا إضافة عليه.',
                    'question_en' => 'Can the packaging be customised?', 'answer_en' => 'Yes. Packaging here is part of the design, not an addition to it.'],
                ['question' => 'هل توجد عيّنة قبل اعتماد الكمية؟', 'answer' => 'دائمًا. لا يبدأ الإنتاج قبل أن تعاينوا قطعة فعلية وتعتمدوها باليد.',
                    'question_en' => 'Is there a sample before we commit to the quantity?', 'answer_en' => 'Always. Production does not begin until you have held a real piece and approved it.'],
                ['question' => 'هل يمكن التسليم على أكثر من فرع أو مدينة؟', 'answer' => 'نعم. تُقسَّم الدفعة حسب الفروع وتُغلَّف لكل وجهة على حدة.',
                    'question_en' => 'Can you deliver to more than one branch or city?', 'answer_en' => 'Yes. The batch is split by destination and packed separately for each.'],
                ['question' => 'هل تتوفر فاتورة ضريبية باسم الشركة؟', 'answer' => 'نعم. الفوترة تتم عبر فريق المبيعات خارج الموقع بعد الاتفاق على الطلب.',
                    'question_en' => 'Is a tax invoice available in the company name?', 'answer_en' => 'Yes. Invoicing is handled by the sales team off the site, once the order is agreed.'],
            ],
            default => [
                ['question' => 'ما أقل كمية للطلب؟', 'answer' => 'تختلف بحسب الحرفة والقطعة. أخبرونا بالكمية المطلوبة ونوضّح لكم الممكن بصدق.',
                    'question_en' => 'What is the minimum order?', 'answer_en' => 'It varies with the craft and the piece. Tell us the quantity you need and we will tell you honestly what is possible.'],
                ['question' => 'كم تستغرق المدة؟', 'answer' => 'نبني الجدول عكسيًا من تاريخ مناسبتكم، ونخبركم مبكرًا إن كان التاريخ غير كافٍ.',
                    'question_en' => 'How long does it take?', 'answer_en' => 'We build the schedule backwards from your date, and tell you early if the date is not enough.'],
                ['question' => 'هل يمكن تخصيص التغليف؟', 'answer' => 'نعم. التغليف عندنا جزء من التصميم لا إضافة عليه.',
                    'question_en' => 'Can the packaging be customised?', 'answer_en' => 'Yes. Packaging here is part of the design, not an addition to it.'],
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
            /*
             * Both actions land on this page's own form, not on /contact.
             *
             * A segment page ends in a full lead form — the same one, already
             * carrying `sectorHint` so the sales team knows which audience
             * asked. Sending a visitor who has just read the whole pitch to a
             * second page to find a second form spends the attention the page
             * earned. The home hero has always done it this way; these four
             * were the exception, and the owner closed it.
             */
            ['hero', [
                'ar' => [null, null, null, 'لنبدأ معًا', '#lead'],
                'en' => [null, null, null, "Let's begin", '#lead'],
            ], [
                // settings is one JSON column, not per-locale, so a second
                // button carries both languages here.
                'secondaryLabel' => 'زوروا معرضنا',
                'secondaryUrl' => '#lead',
                'secondaryLabel_en' => 'Visit our showroom',
                'secondaryUrl_en' => '#lead',
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
            ], ['items' => $steps]],

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

            // The partner's answer to "will you take my client from me".
            ...($pledges === [] ? [] : [
                ['cards', [
                    'ar' => ['تعهّدنا لشركائنا'],
                    'en' => ['Our pledge to partners'],
                ], ['items' => $pledges]],
            ]),

            ...($models === [] ? [] : [
                ['cards', [
                    'ar' => ['نماذج التعاون'],
                    'en' => ['Ways we work together'],
                ], ['items' => $models]],
            ]),

            // Named crafts, not "any artisan": the specificity is the serious
            // signal, not an exclusion (§3).
            ...($targets === [] ? [] : [
                ['cards', [
                    'ar' => ['من نستهدف', 'نركّز على حرف محددة نتقنها ونعرف سوقها.'],
                    'en' => ['Who we look for', 'We focus on a few crafts we know well and whose market we understand.'],
                ], ['items' => $targets]],
            ]),

            ...($gains === [] ? [] : [
                ['cards', [
                    'ar' => ['ماذا تكسبون معنا'],
                    'en' => ['What you gain with us'],
                ], ['items' => $gains]],
            ]),

            /*
             * The artisans' own words — seeded as an EMPTY container, on purpose.
             *
             * The page asks an artisan to trust it, and the one thing that earns
             * that is another artisan's sentence. Which is exactly why I cannot
             * write it: a quote attributed to a craftsperson who never said it
             * is not placeholder copy, it is a fabricated endorsement, and §22.1
             * forbids inventing content precisely here.
             *
             * So the row exists in the section builder with its heading, and
             * `Testimonial.vue` renders nothing while `body` is null. Amad Craft
             * pastes a real quote and a real name, and the section appears. Until
             * then the page is quieter than designed and honest.
             */
            ...($key === Sector::KEY_ARTISANS ? [
                ['testimonial', [
                    'ar' => ['من الحرفيين أنفسهم'],
                    'en' => ['In their own words'],
                ], null],
            ] : []),

            /*
             * The trust strip and the segment's figures — a ROW each, with no
             * content of their own.
             *
             * `Sector.vue` draws both itself, directly under the hero and
             * after the timeline, and takes their headings from these rows.
             * Only the government segment had them, seeded with placeholders
             * by DemoExtrasSeeder, so on the other three pages the strip and
             * the band had nowhere to read a heading from and never appeared —
             * which is why /solutions/companies showed neither, though its
             * page asks for both.
             *
             * Empty on purpose. Which clients may be named, and what this
             * segment's numbers are, is Amad Craft's to say — and both
             * components already render nothing while their data is empty, so
             * the rows sit in the builder waiting rather than showing five
             * grey marks and four zeroes. Government keeps its placeholders:
             * DemoExtrasSeeder matches on type and reuses the row created
             * here rather than adding a second one.
             *
             * Headings are lifted verbatim from sections the client already
             * has — «عملاؤنا» from the partners page, «أثرنا بالأرقام» from
             * the home page — never written here (§22.1).
             */
            ['logos', [
                'ar' => ['عملاؤنا'],
                'en' => ['Our clients'],
            ]],

            ['stats', [
                'ar' => ['أثرنا بالأرقام'],
                'en' => ['Our impact in numbers'],
            ]],

            ['accordion', [
                'ar' => ['أسئلة متكررة'],
                'en' => ['Frequently asked'],
            ], ['items' => $faq]],

            ['cta_band', [
                'ar' => ['نبدأ بمحادثة قصيرة', 'اتركوا وسيلة تواصل واحدة، ونعود إليكم باقتراح مبدئي.', null, 'تواصلوا معي'],
                'en' => ['It starts with a short conversation', 'Leave one way to reach you and we will come back with an initial proposal.', null, 'Contact me'],
            ], $key === Sector::KEY_ARTISANS ? [
                // The label only. Same field, same column, same requirement —
                // but an individual artisan is not a company, and asking them
                // to type their craft under "company name" is how a form
                // starts feeling written for someone else.
                'firstFieldLabel' => 'الاسم أو اسم المشروع الحرفي',
            ] : null],
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

            /*
             * The two figures /training asks for and Amad Craft has not
             * counted yet. Seeded with their labels and NO value, so the
             * register holds the definition — which is the part that can be
             * agreed now — and the page shows nothing until somebody types the
             * number in the panel.
             *
             * A drafted number here would be worse than an absent section: a
             * count of people whose livelihoods changed is precisely the claim
             * a sponsor would repeat in their own reporting.
             */
            ['training-graduates', null, '', 'متدربًا أنهى مسارًا', 'Trainees who completed a track', null, null],
            ['training-to-production', null, '', 'تحوّلوا لحرفيين منتِجين معنا', 'Now producing artisans with us', null, null],
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
     *
     * The photographs are Amad Craft's own, from their workshop: hands at the
     * loom, palm fronds being split at the bench.
     *
     * They replace `craft-03/06/11.webp`, which were also Amad Craft's but of
     * the showroom and of finished pieces on a white sweep — so a story about
     * a grandmother's loom arrived illustrated with a blue notebook, and the
     * section that exists to put a person in front of the reader showed them
     * merchandise instead.
     *
     * Matched to the craft each story names, because the caption states it and
     * a photograph that contradicts its caption is the same defect in a new
     * coat:
     *
     *   story-sadu   → the ground loom, red and black warp        (exact)
     *   story-khoos  → palm fronds split at the bench             (exact)
     *   story-zari   → a loom with coloured warp                  (APPROXIMATE)
     *
     * ⚠️ The third is weaving, not Zari — Zari is gold thread worked onto
     * fabric, and no photograph of it exists here. It is the closest of the
     * three and it is still the wrong craft; swap it the moment a Zari
     * photograph arrives.
     *
     * The faces are turned away or cropped in all three, which is what makes
     * them usable before written consent per person; the warning above still
     * stands for the words.
     */
    private function stories(): void
    {
        $stories = [
            ['story-sadu', 'image (2).jpg',
                'من النول إلى المكتب', 'From the loom to the office',
                'السدو ليس نقشًا على قماش، هو ذاكرة نسجتها جدّتي وسلّمتها لي. اليوم يخرج من بيتي إلى مكاتب لم أتخيّل يومًا أن يصلها.',
                'Sadu is not a pattern on cloth. It is a memory my grandmother wove and handed to me. Today it leaves my house for offices I never imagined it reaching.',
                'حرفية سدو · القصيم', 'Sadu weaver · Al-Qassim',
                'حرفيات ينسجن السدو على النول في ورشة أمد الحرف', 'Artisans weaving Sadu at the loom in the Amad Craft workshop'],
            ['story-khoos', 'image (4).jpg',
                'طلب أعرف موعده', 'An order with a date on it',
                'كنت أبيع القطعة في السوق الأسبوعي وأنتظر من يمرّ. الآن أُنتج بطلبات مجدولة، وأعرف كم سأصنع الشهر القادم.',
                'I used to sell a piece at the weekly market and wait for someone to pass. Now I work to scheduled orders, and I know what I will make next month.',
                'حرفي خوص · الأحساء', 'Palm-frond weaver · Al-Ahsa',
                'حرفيات يجهّزن سعف النخيل على طاولة العمل', 'Artisans preparing palm fronds at the workbench'],
            ['story-zari', 'image (3).jpg',
                'خيط ذهبي بيدي', 'A gold thread, by my hand',
                'أصعب ما في الزري هو الصبر. وأجملُ ما فيه أن ترى خيطًا ذهبيًا صنعتِه بيدك على حقيبة تُهدى لضيف دولة.',
                'The hardest thing about Zari is patience. The most beautiful is seeing a gold thread you made by hand on a bag presented to a state guest.',
                'حرفية زري · المدينة المنورة', 'Zari artisan · Madinah',
                'حرفية تُعِدّ خيوط السداة على النول', 'An artisan setting the warp threads on the loom'],
        ];

        foreach ($stories as $order => [$slug, $image, $titleAr, $titleEn, $quoteAr, $quoteEn, $attrAr, $attrEn, $altAr, $altEn]) {
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

            // Clears what earlier revisions attached here — before attaching,
            // because `attachImage` returns early when the collection is
            // occupied, and the old catalogue shot would keep its place.
            $this->detachSeededMedia($story, 'person', [
                'craft-03.webp', 'craft-06.webp', 'craft-11.webp',
                '7.webp', '8.jpeg', '11.webp',
            ]);

            if ($image !== null) {
                // Describes what is in the frame — people at work — not "a
                // craft piece", which is what the shared default says and
                // what these photographs are precisely not.
                $this->attachImage($story, 'person', $image, [
                    'ar' => $altAr,
                    'en' => $altEn,
                ]);
            }
        }
    }

    /**
     * ⚠️ Illustrative track descriptions. The outcome line on each is the
     * field the card is built around and is required in the panel.
     *
     * Only the Sadu track is illustrated, and it is illustrated by its own
     * room: `sadu.png` is Amad Craft's photograph of a trainee at the Sadu
     * loom. The other two frames stay empty on purpose.
     *
     * They used to hold `7.webp` / `8.jpeg` / `2.jpeg` — also the client's own
     * photographs, but of finished pieces on a shelf, so the craft-business
     * track was illustrated with a wall clock. This section is about people
     * learning; a photograph of merchandise is not a photograph of a track.
     *
     * All three frames are now filled from Amad Craft's workshop photography:
     *
     *   sadu-track     → sadu.png        the loom                    (exact)
     *   khoos-track    → image (4).jpg   fronds split at the bench   (exact)
     *   business-track → gbs.png         a finished piece held up at
     *                                    the centre's own door    (APPROXIMATE)
     *
     * ⚠️ The third is the honest weak point. The craft-business track teaches
     * pricing, photography and portfolios, and no photograph of those sessions
     * exists. `gbs.png` was chosen because it is the only frame that reads as
     * "a result, held up, at the training centre" rather than as a particular
     * craft — but it is a gypsum piece, and a picture that argues with its own
     * caption is the defect this section already had once. Replace it the
     * moment a photograph of a pricing or portfolio session arrives.
     *
     * `image (4).jpg` is also the artisan story's photograph on /impact. One
     * image in two places is a smaller fault than an empty frame, and the
     * training report (pp.10–23) is full of alternatives that would separate
     * them — they need exporting out of the PDF first.
     */
    private function training(): void
    {
        $programs = [
            ['sadu-track', 8, 'sadu.png',
                'مسار السدو', 'Sadu track',
                'تدريب عملي على نول السدو من الغزل حتى القطعة الجاهزة، مع وحدة في التسعير والتغليف.',
                'Hands-on training at the Sadu loom, from yarn to finished piece, with a unit on pricing and packaging.',
                'يُنهي المتدرّب المسار بثلاث قطع جاهزة للعرض، وملف تسعير لمنتجه، وربط مباشر بطلبات أمد الحرف.',
                'Graduates finish with three display-ready pieces, a pricing file for their product, and a direct link to Amad Craft orders.'],
            ['khoos-track', 6, 'image (4).jpg',
                'مسار الخوص', 'Khoos track',
                'من تجهيز السعف حتى الجدل والتشطيب، مع تركيز على ثبات الجودة عبر الكميات الكبيرة.',
                'From preparing the frond through plaiting to finishing, focused on holding quality steady across volume.',
                'إتقان ثلاثة أنماط جدل، والقدرة على تنفيذ دفعة متجانسة من خمسين قطعة.',
                'Command of three plaiting patterns and the ability to deliver a consistent batch of fifty.'],
            ['business-track', 4, 'gbs.png',
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

            // Clears the catalogue photographs earlier revisions attached here.
            // Before attaching, not after: `attachImage` declines a collection
            // that already holds something, so the clock would have kept its
            // place and the new photograph would have been dropped silently.
            $this->detachSeededMedia($program, 'hero', ['7.webp', '8.jpeg', '2.jpeg']);

            if ($image !== null) {
                // Describes the room, not the object — this frame's whole
                // purpose is to show a person learning the craft.
                $this->attachImage($program, 'hero', $image, [
                    'ar' => 'متدرّبة على نول السدو داخل مركز التدريب',
                    'en' => 'A trainee at the Sadu loom in the training centre',
                ]);
            }
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

    /**
     * Removes an image an earlier revision of this seeder attached, and only
     * that image.
     *
     * Matched by file name against an explicit list, never "delete whatever is
     * in this collection": the client uploads through the panel into the same
     * collections, and a seeder that clears a collection wholesale destroys
     * their work on the next `migrate --seed`. The standing rule is no data
     * loss in any migration; this is the same rule one layer up.
     *
     * @param  list<string>  $fileNames
     */
    private function detachSeededMedia(Model $model, string $collection, array $fileNames): void
    {
        foreach ($model->getMedia($collection) as $media) {
            if (in_array($media->file_name, $fileNames, true)) {
                $media->delete();
            }
        }
    }

    /** ⚠️ Illustrative. Replace with Amad Craft's real publications. */
    private function reports(): void
    {
        foreach ([[now()->year - 1], [now()->year - 2]] as $order => [$year]) {
            $report = Report::query()->updateOrCreate(
                ['slug' => "impact-{$year}"],
                ['year' => $year, 'sort_order' => $order, 'is_public' => true]
            );

            /*
             * The year is NOT in the title. It is one fact, and it already has
             * a field — `year` — which drives both the badge and the generated
             * cover. Typing it into the title as well is how a card came to be
             * headed 2025 while wearing a 2023 badge: two places to state one
             * thing, and only one of them got corrected.
             */
            $report->translations()->updateOrCreate(['locale' => 'ar'], [
                'title' => 'تقرير الأثر السنوي',
                'summary' => 'ملخّص لما أُنجز خلال العام: عدد الحرفيين، والقطع المسلَّمة، والجهات التي عملنا معها.',
            ]);
            $report->translations()->updateOrCreate(['locale' => 'en'], [
                'title' => 'Annual impact report',
                'summary' => 'A summary of the year: artisans engaged, pieces delivered, and the institutions we worked with.',
            ]);

            /*
             * No cover attached. `13.webp` was here — a stock photograph of a
             * necklace on a model, identical on both reports, from outside the
             * identity, in the space a document cover belongs. `ReportCover`
             * draws a navy face with the mark and the year instead, and an
             * uploaded cover still wins over it the moment one arrives.
             */
            $this->detachSeededMedia($report, 'cover', ['13.webp']);
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
                /*
                 * The four descriptions turn the panel from a list of links
                 * into a chooser. A visitor hesitating between «الشركاء» and
                 * «شركات القطاع الخاص» is one wrong click from a page written
                 * for someone else; one line each settles it before the click.
                 *
                 * Draft wording, editable from the panel like any other copy.
                 */
                ['/{l}/solutions', 'الحلول', 'Solutions', [
                    ['/{l}/solutions/government', 'الجهات الحكومية وشبه الحكومية', 'Government & quasi-government',
                        [], 'هدايا رسمية بتوثيق كامل', 'Official gifts, fully documented'],
                    ['/{l}/solutions/companies', 'شركات القطاع الخاص', 'Private-sector companies',
                        [], 'هدايا تحمل هوية شركتكم', 'Gifts carrying your company identity'],
                    ['/{l}/solutions/partners', 'الشركاء', 'Partners',
                        [], 'مورّدكم الخلفي للفعاليات', 'Your behind-the-scenes events supplier'],
                    ['/{l}/solutions/artisans', 'الحرفيون', 'Artisans',
                        [], 'انضموا لشبكة الحرفيين', 'Join the artisan network'],
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
            // Enforced for the same reason as NavigationSeeder: an inactive
            // menu row is invisible to NavigationBuilder and unreachable from
            // the panel, and this seeder is about to rewrite every item in it.
            $navigation = $this->seedRow(
                Navigation::query(),
                identity: ['key' => $key],
                structure: ['is_active' => true],
            );

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
     * One menu entry, with its two labels and — where it has one — the
     * one-line description the solutions panel shows beneath the name.
     *
     * @param  array{0: string, 1: string, 2: string, 3?: array<int, mixed>, 4?: string, 5?: string}  $spec
     */
    private function navigationItem(Navigation $navigation, array $spec, int $order, ?int $parentId): NavigationItem
    {
        [$url, $ar, $en] = $spec;
        $descriptionAr = $spec[4] ?? null;
        $descriptionEn = $spec[5] ?? null;

        $item = $navigation->items()->getModel()->query()->create([
            'navigation_id' => $navigation->id,
            'parent_id' => $parentId,
            // Stored with the Arabic prefix; NavigationBuilder swaps it per
            // locale, so the English menu needs no second row.
            'url' => str_replace('{l}', 'ar', $url),
            'sort_order' => $order,
            'is_active' => true,
        ]);

        $item->translations()->updateOrCreate(['locale' => 'ar'], [
            'label' => $ar, 'description' => $descriptionAr,
        ]);
        $item->translations()->updateOrCreate(['locale' => 'en'], [
            'label' => $en, 'description' => $descriptionEn,
        ]);

        return $item;
    }

    // ---------------------------------------------------------------- //

    /**
     * @param  array{ar: string, en: string}|null  $alt  Overrides the default
     *                                                   description. A photograph of
     *                                                   people at work is not "a craft
     *                                                   piece", and alt text that
     *                                                   describes the wrong thing is
     *                                                   worse than none.
     */
    private function attachImage(Model $model, string $collection, string $file, ?array $alt = null): void
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
        $alt ??= ['ar' => 'قطعة حرفية من إنتاج أمد الحرف', 'en' => 'A craft piece made by Amad Craft'];

        foreach ($alt as $locale => $text) {
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
