<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Page;
use App\Models\Report;
use App\Models\Solution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * ⚠️ DEMONSTRATION DATA — NOT FOR PRODUCTION.
 *
 * The parts of the site DemoContentSeeder left empty, so every route can be
 * walked end to end:
 *
 *   · the legal pages the footer links to — those links were 404s
 *   · a live campaign landing page at /{locale}/c/{slug}
 *   · sections on each solution page, so they are not bare
 *   · partner logos and downloadable report files
 *
 * Draft copy, to be reviewed and edited in the admin panel. The legal text
 * in particular is not legal advice and needs a lawyer before launch.
 */
class DemoExtrasSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DemoExtrasSeeder must never run in production.');

            return;
        }

        $this->legalPages();
        $this->solutionSections();
        $this->campaign();
        // Partner logos are attached by DemoContentSeeder, alongside the names
        // they belong to. They used to be assigned here, round-robin from the
        // craft photo library, which put an unrelated photograph on each real
        // organisation's row.
        $this->reportFiles();

        $this->command?->info('Demo extras seeded: legal pages, campaign, solution sections, report files.');
    }

    /**
     * The privacy and terms pages.
     *
     * Not decoration: the footer already links to them, so without these the
     * live site was serving two 404s — and §13 counts a broken internal link
     * as an indexing defect, not a cosmetic one.
     */
    private function legalPages(): void
    {
        $pages = [
            'legal/privacy' => [
                'ar' => ['سياسة الخصوصية', 'كيف نتعامل مع بياناتك'],
                'en' => ['Privacy policy', 'How we handle your data'],
            ],
            'legal/terms' => [
                'ar' => ['الشروط والأحكام', 'شروط استخدام الموقع'],
                'en' => ['Terms & conditions', 'Terms of use for this site'],
            ],
        ];

        foreach ($pages as $slug => $copy) {
            $page = Page::query()->firstOrCreate(['slug' => $slug], ['template' => 'legal']);

            $page->forceFill([
                'status' => 'published',
                'published_at' => now(),
                // Legal pages carry no ranking value and dilute the crawl
                // budget across two locales (§13).
                'is_indexable' => false,
            ])->save();

            foreach (['ar', 'en'] as $locale) {
                $page->translations()->updateOrCreate(['locale' => $locale], [
                    'title' => $copy[$locale][0],
                    'subtitle' => $copy[$locale][1],
                ]);
            }

            $page->sections()->delete();

            $body = $this->legalBody($slug);

            $section = $page->sections()->create([
                'type' => 'rich_text',
                'sort_order' => 0,
                'is_active' => true,
            ]);

            foreach (['ar', 'en'] as $locale) {
                $section->translations()->updateOrCreate(['locale' => $locale], [
                    'body' => $body[$locale],
                ]);
            }
        }
    }

    /**
     * Give each solution a real page rather than a hero and nothing else.
     */
    private function solutionSections(): void
    {
        foreach (Solution::query()->get() as $solution) {
            if ($solution->sections()->exists()) {
                continue;
            }

            $copy = $this->solutionCopy($solution->slug);

            $this->attachSections($solution, [
                ['cards', [
                    'ar' => ['ما يشمله'],
                    'en' => ['What it includes'],
                ], ['items' => $copy['cards']]],

                ['media_split', [
                    'ar' => ['كيف ننفّذه', null, $copy['how']['ar']],
                    'en' => ['How we deliver it', null, $copy['how']['en']],
                ], ['image' => $this->imagePayload('7.webp', 1086, 1448), 'flip' => true]],

                ['accordion', [
                    'ar' => ['أسئلة متكررة'],
                    'en' => ['Frequently asked'],
                ], ['items' => $copy['faq']]],

                ['cta_band', [
                    'ar' => ['نبدأ بمحادثة قصيرة', 'اتركوا وسيلة تواصل واحدة، ونعود إليكم باقتراح مبدئي.', null, 'تواصلوا معي'],
                    'en' => ['It starts with a short conversation', 'Leave one way to reach you and we will come back with an initial proposal.', null, 'Contact me'],
                ]],
            ]);
        }
    }

    /**
     * Draft copy for each solution's inner page.
     *
     * Written per solution rather than shared, because "what it includes" for
     * a delegation gift is not what it means for artisan sourcing — and a
     * generic block on four pages reads as filler to exactly the buyer this
     * site is for.
     *
     * @return array{cards: list<array<string, string>>, how: array<string, string>, faq: list<array<string, string>>}
     */
    private function solutionCopy(string $slug): array
    {
        return match ($slug) {
            'event-collateral' => [
                'cards' => [
                    ['icon' => '🏆', 'title' => 'درع المتحدّث', 'body' => 'قطعة حرفية بدل الدرع الزجاجي المكرّر، بنقش اسم المتحدّث.',
                        'title_en' => 'The speaker award', 'body_en' => 'A craft piece instead of the same glass trophy, engraved with the speaker\'s name.'],
                    ['icon' => '🎒', 'title' => 'حقيبة المشارك', 'body' => 'محتوى متجانس الخامة يجعل الفعالية تبدو مصمَّمة لا مجمَّعة.',
                        'title_en' => 'The attendee bag', 'body_en' => 'Contents in consistent materials, so the event looks designed rather than assembled.'],
                    ['icon' => '🪑', 'title' => 'ركن حرفي حيّ', 'body' => 'حرفي يعمل داخل جناحكم أمام الزوّار — تجربة تُروى لا تُوزَّع.',
                        'title_en' => 'A live craft corner', 'body_en' => 'An artisan working inside your stand in front of visitors — an experience retold, not handed out.'],
                ],
                'how' => [
                    'ar' => 'نبدأ من تاريخ الفعالية ونرجع للخلف: موعد التسليم، ثم التغليف، ثم الإنتاج، ثم اعتماد العيّنة. تعرفون في أول اجتماع إن كان التاريخ يكفي أو لا — نقولها بصراحة بدل أن نكتشفها متأخرين.',
                    'en' => 'We start from the event date and work backwards: delivery, packaging, production, sample approval. You know in the first meeting whether the date is enough — we say so plainly rather than discovering it late.',
                ],
                'faq' => [
                    ['question' => 'ما أقصر مدة ممكنة؟', 'answer' => 'تعتمد على الحرفة والكمية. أخبرونا بالتاريخ أولًا ونجيبكم بصدق قبل أي التزام.',
                        'question_en' => 'What is the shortest possible turnaround?', 'answer_en' => 'It depends on the craft and the quantity. Tell us the date first and we answer honestly before any commitment.'],
                    ['question' => 'هل تنفّذون داخل مدن أخرى؟', 'answer' => 'نعم، ونرتّب الشحن والتسليم إلى موقع الفعالية.',
                        'question_en' => 'Do you deliver in other cities?', 'answer_en' => 'Yes, and we arrange shipping and delivery to the venue.'],
                ],
            ],

            'custom-production' => [
                'cards' => [
                    ['icon' => '📐', 'title' => 'مواصفة مكتوبة', 'body' => 'الخامة والمقاس والوزن واللون مثبّتة قبل بدء الإنتاج.',
                        'title_en' => 'A written specification', 'body_en' => 'Material, size, weight and colour fixed before production starts.'],
                    ['icon' => '🔍', 'title' => 'فحص على دفعات', 'body' => 'ضبط الجودة أثناء العمل لا في نهايته، لأن التصحيح المتأخر مكلف.',
                        'title_en' => 'Batch inspection', 'body_en' => 'Quality controlled during the work rather than at the end of it, because late correction is expensive.'],
                    ['icon' => '🔁', 'title' => 'قابلية التكرار', 'body' => 'نوثّق المواصفة لتتمكّنوا من إعادة الطلب بنفس النتيجة لاحقًا.',
                        'title_en' => 'Repeatable', 'body_en' => 'We keep the specification on file so you can reorder later and get the same result.'],
                ],
                'how' => [
                    'ar' => 'نحوّل ما تحتاجونه إلى مواصفة مكتوبة، ثم نوزّع الإنتاج على الحرفيين المناسبين لكل مرحلة. تصلكم عيّنة تُعتمد باليد قبل الدخول في الكمية الكاملة، ويُفحص العمل على دفعات حتى التسليم.',
                    'en' => 'We turn your need into a written specification, then distribute production across the right artisans for each stage. A sample is approved in the hand before full volume begins, and work is inspected in batches through to delivery.',
                ],
                'faq' => [
                    ['question' => 'هل يمكن إعادة الطلب لاحقًا بنفس المواصفة؟', 'answer' => 'نعم. نحتفظ بملف المواصفة والعيّنة المعتمدة لكل مشروع.',
                        'question_en' => 'Can we reorder later to the same specification?', 'answer_en' => 'Yes. We keep the specification file and the approved sample for every project.'],
                    ['question' => 'ماذا لو اختلفت القطع اليدوية قليلًا؟', 'answer' => 'الاختلاف اليسير طبيعة العمل اليدوي وقيمته. نتفق مسبقًا على حدود التفاوت المقبولة.',
                        'question_en' => 'What if handmade pieces differ slightly?', 'answer_en' => 'Small variation is the nature of handwork and part of its value. We agree acceptable tolerances in advance.'],
                ],
            ],

            'artisan-sourcing' => [
                'cards' => [
                    ['icon' => '🧭', 'title' => 'اختيار الحرفيين', 'body' => 'نرشّح المناسبين لحرفتكم وكميتكم، لا الأقرب فحسب.',
                        'title_en' => 'Selecting the artisans', 'body_en' => 'We nominate the ones suited to your craft and your quantity, not simply the nearest.'],
                    ['icon' => '📝', 'title' => 'توثيق الاتفاق', 'body' => 'المواصفة والسعر والجدول مكتوبة تحمي الطرفين.',
                        'title_en' => 'Documenting the agreement', 'body_en' => 'Specification, price and schedule in writing, protecting both sides.'],
                    ['icon' => '📊', 'title' => 'تقرير الأثر', 'body' => 'يوضّح كم حرفيًا استفاد من طلبكم ومن أي منطقة.',
                        'title_en' => 'The impact report', 'body_en' => 'It sets out how many artisans your order reached, and from which regions.'],
                ],
                'how' => [
                    'ar' => 'دورنا هنا تشغيلي بحت: نختار الحرفيين، ونوثّق الاتفاق، ونتابع الإنتاج ميدانيًا، ونضبط الجودة، ثم نصدر تقرير الأثر. تتعاملون مع الحرفة مباشرة، ويبقى الضمان علينا.',
                    'en' => 'Our role here is purely operational: we select the artisans, document the agreement, follow production on the ground, control quality, then issue the impact report. You deal with the craft directly; the guarantee stays with us.',
                ],
                'faq' => [
                    ['question' => 'هل نتعامل مع الحرفي مباشرة؟', 'answer' => 'نعم إن رغبتم، ويبقى ضبط الجودة والجدول من مسؤوليتنا.',
                        'question_en' => 'Do we deal with the artisan directly?', 'answer_en' => 'Yes if you wish, and quality control and the schedule stay our responsibility.'],
                    ['question' => 'كيف يُحتسب أثر الطلب؟', 'answer' => 'بعدد الحرفيين المشاركين وساعات العمل والمناطق المستفيدة، ويصلكم موثّقًا.',
                        'question_en' => 'How is the impact of an order counted?', 'answer_en' => 'By the artisans involved, the hours worked and the regions reached — documented and sent to you.'],
                ],
            ],

            // corporate-gifts and anything added later
            default => [
                'cards' => [
                    ['icon' => '🎨', 'title' => 'اختيار الحرفة', 'body' => 'نرشّح الحرفة التي تناسب رسالتكم ومن ستُهدى إليه.',
                        'title_en' => 'Choosing the craft', 'body_en' => 'We nominate the craft that suits your message and the person receiving it.'],
                    ['icon' => '🏷️', 'title' => 'هويتكم على القطعة', 'body' => 'الشعار ينفَّذ بأسلوب يحترم الحرفة ولا يطمسها.',
                        'title_en' => 'Your identity on the piece', 'body_en' => 'The mark is applied in a way that respects the craft rather than covering it.'],
                    ['icon' => '🎁', 'title' => 'تغليف جاهز للتقديم', 'body' => 'كل قطعة في علبتها، جاهزة للتسليم المباشر دون تجهيز إضافي.',
                        'title_en' => 'Ready to hand over', 'body_en' => 'Every piece in its own box, ready to give with no further preparation.'],
                ],
                'how' => [
                    'ar' => 'نفهم أولًا المناسبة ومن سيستلم الهدية والميزانية، ثم نقترح ثلاثة اتجاهات حرفية بخامات مختلفة. تختارون اتجاهًا فنصنع عيّنة فعلية تُعتمد باليد لا بالصورة، وبعدها يبدأ الإنتاج بجدول واضح.',
                    'en' => 'We first understand the occasion, the recipient and the budget, then propose three craft directions in different materials. You choose one, we produce a real sample approved in the hand rather than from a photo, and production then runs to a clear schedule.',
                ],
                'faq' => [
                    ['question' => 'ما أقل كمية للطلب؟', 'answer' => 'تختلف بحسب الحرفة والقطعة. أخبرونا بالكمية ونوضّح لكم الممكن بصدق.',
                        'question_en' => 'What is the minimum order?', 'answer_en' => 'It varies with the craft and the piece. Tell us the quantity and we will tell you honestly what is possible.'],
                    ['question' => 'هل يمكن تنفيذ الشعار بالحفر أو التطريز؟', 'answer' => 'نعم، ونرشّح الأنسب منهما بحسب الخامة حتى لا يفقد العمل اليدوي قيمته.',
                        'question_en' => 'Can the mark be engraved or embroidered?', 'answer_en' => 'Yes, and we recommend whichever suits the material so the handwork does not lose its value.'],
                ],
            ],
        };
    }

    /**
     * A live campaign so /{locale}/c/{slug} can be seen working, and so the
     * dashboard's per-campaign breakdown has something to show.
     */
    private function campaign(): void
    {
        $campaign = Campaign::query()->firstOrCreate(
            ['slug' => 'riyadh-season'],
            [
                'is_active' => true,
                'starts_at' => now()->subMonth(),
                'ends_at' => now()->addMonths(3),
                'default_utm_source' => 'google',
                'default_utm_medium' => 'cpc',
                'default_utm_campaign' => 'riyadh-season',
                'template' => 'default',
            ]
        );

        $campaign->forceFill([
            'is_active' => true,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonths(3),
        ])->save();

        $campaign->translations()->updateOrCreate(['locale' => 'ar'], [
            'title' => 'هدايا موسم الرياض — بحرفة سعودية',
            'meta_title' => 'هدايا موسم الرياض بحرفة سعودية | أمد الحرف',
            'meta_description' => 'هدايا حرفية سعودية لفعاليات موسم الرياض، بهوية جهتكم وبكميات وجدول تسليم مضبوط.',
        ]);
        $campaign->translations()->updateOrCreate(['locale' => 'en'], [
            'title' => 'Riyadh Season gifts, in Saudi craft',
            'meta_title' => 'Riyadh Season gifts in Saudi craft | Amad Craft',
            'meta_description' => 'Saudi craft gifts for Riyadh Season events — in your identity, at volume, on schedule.',
        ]);

        $campaign->sections()->delete();

        $this->attachSections($campaign, [
            ['hero', [
                'ar' => ['فعاليتكم في الموسم تستحق هدية تُروى', 'قطع حرفية سعودية بهوية جهتكم، جاهزة قبل موعد فعاليتكم.'],
                'en' => ['Your event deserves a gift worth retelling', 'Saudi craft pieces in your identity, ready before your event date.'],
            ]],

            ['cards', [
                'ar' => ['لماذا أمد الحرف لموسمكم'],
                'en' => ['Why Amad Craft for your season'],
            ], ['items' => [
                ['icon' => '⏱️', 'title' => 'جاهز قبل الموعد', 'body' => 'جدول عكسي من تاريخ فعاليتكم، لا وعد مفتوح.',
                    'title_en' => 'Ready before the date', 'body_en' => 'A schedule built backwards from your event date, not an open-ended promise.'],
                ['icon' => '🇸🇦', 'title' => 'حرفة سعودية أصيلة', 'body' => 'سدو وخوص وزري ونقش حساوي بأيدٍ سعودية.',
                    'title_en' => 'Genuine Saudi craft', 'body_en' => 'Sadu, palm-frond weaving, Zari and Al-Ahsa engraving, by Saudi hands.'],
                ['icon' => '🎯', 'title' => 'بهويتكم', 'body' => 'الشعار والألوان والتغليف على دليل هويتكم.',
                    'title_en' => 'In your identity', 'body_en' => 'Mark, colours and packaging follow your brand guide.'],
            ]]],

            ['contact_block', [
                'ar' => ['أخبرونا بتاريخ فعاليتكم', 'ثلاث خانات فقط، ونعود إليكم باقتراح وجدول تسليم.', null, 'تواصلوا معي'],
                'en' => ['Tell us your event date', 'Three fields only, and we come back with a proposal and a schedule.', null, 'Contact me'],
            ]],
        ]);
    }

    /**
     * Removes the 612-byte demo PDFs this seeder used to attach to every
     * report, and attaches nothing in their place.
     *
     * The reasoning was "a real file makes the download link testable". What
     * it actually produced was a public button on the credibility page reading
     * «تحميل التقرير (612 B)» — a size that announces the file is empty, on a
     * document a government buyer was invited to put in their own report. The
     * link worked and the promise behind it did not, which is the worse of the
     * two failures.
     *
     * `ReportsList` now shows «التقرير قيد الإعداد» when no file is attached,
     * and a test that needs a downloadable report attaches its own fixture.
     * Amad Craft's real publications replace this state by being uploaded.
     */
    private function reportFiles(): void
    {
        foreach (Report::query()->with('media')->get() as $report) {
            foreach ($report->getMedia('file') as $file) {
                // Only the generated stand-ins: a file the client uploaded
                // through the panel is theirs and is never touched by a seeder.
                if (str_starts_with((string) $file->file_name, 'demo-report-')) {
                    $file->delete();
                }
            }
        }
    }

    // ---------------------------------------------------------------- //

    /**
     * DRAFT legal text.
     *
     * ⚠️ Written to make the pages real rather than empty. It is not legal
     * advice and has not been reviewed by a lawyer. Amad Craft's counsel must
     * replace it before launch — particularly the personal-data section, which
     * has to match what the site actually collects and how long it is kept.
     *
     * @return array<string, string>
     */
    private function legalBody(string $slug): array
    {
        if ($slug === 'legal/privacy') {
            return [
                'ar' => '<p>تحترم أمد الحرف خصوصية زوّار موقعها، ولا تجمع من البيانات إلا ما يلزم للرد على طلب التواصل.</p>'
                    .'<h3>ما نجمعه</h3>'
                    // Must list what the form actually asks for. The three
                    // required fields are the client's own decision; a privacy
                    // notice that under-states what is collected is the one
                    // kind of stale copy with a legal cost.
                    .'<p>عند إرسال نموذج التواصل نحفظ ما كتبته فيه: اسم جهتكم، ورقم الهاتف، والبريد الإلكتروني، والرسالة الاختيارية إن كتبتها. لا نطلب بيانات تتجاوز ذلك.</p>'
                    .'<p>نحفظ كذلك مصدر زيارتك (الصفحة التي أتيت منها ووسوم الحملة إن وُجدت) لقياس فاعلية قنواتنا، وبصمة مشفّرة لعنوان الإنترنت للحماية من الإرسال الآلي. لا نحفظ عنوان الإنترنت نفسه.</p>'
                    .'<h3>لماذا نجمعه</h3>'
                    .'<p>للتواصل معك بشأن طلبك، ولقياس أداء الموقع. لا نبيع بياناتك ولا نشاركها لأغراض تسويقية مع أطراف أخرى.</p>'
                    .'<h3>من يطّلع عليها</h3>'
                    .'<p>فريق المبيعات في أمد الحرف، ونظام إدارة علاقات العملاء الذي نستخدمه لمتابعة الطلبات.</p>'
                    .'<h3>حقوقك</h3>'
                    .'<p>يمكنك طلب الاطّلاع على بياناتك أو حذفها في أي وقت بمراسلتنا على البريد الموضّح في صفحة التواصل.</p>'
                    .'<h3>ملفات الارتباط</h3>'
                    .'<p>نستخدم ملف ارتباط واحدًا لتذكّر لغتك المفضّلة، وأدوات قياس لفهم كيفية استخدام الموقع.</p>',
                'en' => '<p>Amad Craft respects the privacy of everyone who visits this site, and collects no more than is needed to answer a contact request.</p>'
                    .'<h3>What we collect</h3>'
                    .'<p>When you submit the contact form we store what you entered in it: your organisation name, your phone number, your email address, and the optional message if you wrote one. We ask for nothing beyond that.</p>'
                    .'<p>We also store where your visit came from (the referring page and any campaign tags) to measure our channels, and a hashed fingerprint of your IP address to guard against automated submissions. We do not store the IP address itself.</p>'
                    .'<h3>Why we collect it</h3>'
                    .'<p>To reply to your request, and to measure how the site performs. We do not sell your data or share it with third parties for marketing.</p>'
                    .'<h3>Who sees it</h3>'
                    .'<p>The Amad Craft sales team, and the customer-relationship system we use to follow up requests.</p>'
                    .'<h3>Your rights</h3>'
                    .'<p>You may request access to your data or its deletion at any time, using the email address on the contact page.</p>'
                    .'<h3>Cookies</h3>'
                    .'<p>We use one cookie to remember your preferred language, and measurement tools to understand how the site is used.</p>',
            ];
        }

        return [
            'ar' => '<p>باستخدامك هذا الموقع فإنك توافق على الشروط التالية.</p>'
                .'<h3>طبيعة الموقع</h3>'
                .'<p>هذا موقع تعريفي مؤسسي. لا يتم عبره بيع ولا دفع ولا استقبال طلبات شراء. لشراء المنتجات مباشرة، يرجى زيارة متجر أمد الحرف الإلكتروني.</p>'
                .'<h3>المحتوى والملكية الفكرية</h3>'
                .'<p>جميع النصوص والصور والتصاميم والشعارات المعروضة مملوكة لأمد الحرف، ولا يجوز استخدامها أو إعادة نشرها دون إذن كتابي.</p>'
                .'<h3>العروض والأسعار</h3>'
                .'<p>ما يُعرض في صفحات المنتجات تعريفي ولا يُعدّ عرضًا سعريًا مُلزِمًا. تُحدَّد الأسعار والكميات ومدد التنفيذ في عرض رسمي منفصل.</p>'
                .'<h3>الروابط الخارجية</h3>'
                .'<p>قد يحتوي الموقع على روابط لمواقع أخرى، ولا نتحمّل مسؤولية محتواها.</p>'
                .'<h3>التعديل</h3>'
                .'<p>قد تُحدَّث هذه الشروط، ويسري التحديث من تاريخ نشره على هذه الصفحة.</p>',
            'en' => '<p>By using this site you agree to the following terms.</p>'
                .'<h3>What this site is</h3>'
                .'<p>This is a corporate showcase site. No selling, payment or purchase ordering takes place here. To buy products directly, please visit the Amad Craft online store.</p>'
                .'<h3>Content and intellectual property</h3>'
                .'<p>All text, images, designs and marks shown here belong to Amad Craft and may not be used or republished without written permission.</p>'
                .'<h3>Quotes and pricing</h3>'
                .'<p>What appears on the product pages is informational and is not a binding quotation. Prices, quantities and lead times are set in a separate formal proposal.</p>'
                .'<h3>External links</h3>'
                .'<p>This site may link to other sites; we are not responsible for their content.</p>'
                .'<h3>Changes</h3>'
                .'<p>These terms may be updated, effective from the date the update appears on this page.</p>',
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

    private function attachImage(Model $model, string $collection, string $file): void
    {
        $path = public_path("images/{$file}");

        if (! is_file($path)) {
            return;
        }

        $media = $model->addMedia($path)->preservingOriginal()->toMediaCollection($collection);

        foreach (['ar' => 'قطعة حرفية من إنتاج أمد الحرف', 'en' => 'A craft piece made by Amad Craft'] as $locale => $text) {
            $media->translations()->updateOrCreate(['locale' => $locale], ['alt_text' => $text]);
        }
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
