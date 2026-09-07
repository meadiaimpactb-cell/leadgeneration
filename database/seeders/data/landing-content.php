<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Landing page content — approved by Amad Craft management
|--------------------------------------------------------------------------
|
| Source of truth: docs/landing-page-brief.ar.md, the management decision of
| 7 September 2026 that replaced the multi-page site with one landing page.
|
| The Arabic here is REPRODUCED LITERALLY from that brief. It is the client's
| own approved copy — not written, shortened or rephrased here (§22.1). If a
| line reads oddly, it reads that way in the brief, and the fix is a new
| approved brief, not an edit in this file.
|
| The English is a faithful translation of that approved Arabic, supplied
| because the brief asks for «مقابلًا إنجليزيًا مكافئًا ... دون فقدان أي محتوى»
| and §12 rates the English version as essential, not secondary. It carries no
| claim the Arabic does not make. It still needs Amad Craft's sign-off before
| launch — translated marketing copy is a business decision, not a technical
| one, and this file is where it is edited if they want different wording.
|
| This array is SEED DATA, not the runtime source. It is written into
| `sections` + `section_translations`, and from that moment the admin panel
| owns it: re-running the seeder never overwrites an edit the client has made
| (see LandingPageSeeder::seedRow).
|
| Keys per section:
|   type     — one of Section::TYPES, so SectionRenderer already knows it
|   anchor   — which `<section id="...">` group it belongs to on the page
|   ar / en  — heading, subheading, body (body is HTML, rendered by RichText)
|   settings — non-translatable structure: items, ctas, flow steps
|
| Repeatable items carry both languages on the same row (`title` / `title_en`)
| because `settings` is one JSON column shared by both locales — the rule
| `useSettingText` implements on the client.
*/

return [

    /*
    |--------------------------------------------------------------------------
    | 1) #home — Hero
    |--------------------------------------------------------------------------
    */
    [
        'type' => 'hero',
        'anchor' => 'home',
        'ar' => [
            'heading' => 'أمد الحرف',
            'subheading' => 'نحوّل الثقافة السعودية إلى حلول تستحق أن تُقدَّم',
            'body' => '<p>أمد الحرف شركة اجتماعية سعودية غير هادفة للربح، متخصصة في تطوير وإدارة الحلول الثقافية السعودية للجهات والمؤسسات.</p>'
                .'<p>نحوّل الحرف والثقافة السعودية إلى هدايا، مجموعات تنفيذية، حلول للمساحات، ومشاريع وتجارب ثقافية، من خلال شبكة من الحرفيين والمصممين السعوديين، وبمنهج يجمع بين الأصالة والتصميم المعاصر والجاهزية المؤسسية.</p>'
                .'<p>ليست مهمتنا أن نبيعك منتجاً.</p>'
                .'<p>مهمتنا أن نطوّر لك حلاً مناسباً لهدفك.</p>',
        ],
        'en' => [
            'heading' => 'Amad Craft',
            'subheading' => 'We turn Saudi culture into solutions worth presenting',
            'body' => '<p>Amad Craft is a Saudi non-profit social enterprise specialising in developing and managing Saudi cultural solutions for government bodies and institutions.</p>'
                .'<p>We turn Saudi crafts and culture into gifts, executive collections, solutions for spaces, and cultural projects and experiences — through a network of Saudi artisans and designers, and with an approach that combines authenticity, contemporary design and institutional readiness.</p>'
                .'<p>Our mission is not to sell you a product.</p>'
                .'<p>Our mission is to develop a solution that fits your objective.</p>',
        ],
        /*
         * Three calls to action, each tagging the lead with the segment it
         * came from. `source` is written to the lead's `sector_hint` column —
         * the column that already exists for exactly this, so no new field is
         * asked of the visitor (§6.1 keeps the form at two controls).
         */
        'settings' => [
            /*
             * The approved hero furniture, carried over from the home page
             * this one replaces: the same workshop film in the media pane,
             * the same alt text.
             *
             * The eyebrow drives two things at once, and both are wanted:
             * the vertical «B2B · CRAFT SUPPLY» lettering standing in the
             * seam beside the film, and the horizontal label with the Sadu
             * mark above the headline. `Hero` gates both on this one value.
             *
             * It was removed on 7 September at Amad Craft's instruction and
             * restored the same day on theirs. Kept as a content value rather
             * than a code branch precisely so that costs nothing either way.
             *
             * `/videos/amadcraft.gif` is 21.7MB and is this page's LCP
             * element — the single biggest thing between the site and §15.1's
             * 2.0s budget. Kept exactly as it is, so performance is unchanged
             * rather than worse. Hero.vue already renders `.mp4`/`.webm` as a
             * real <video> with this poster, so re-encoding the same footage
             * would cut it by roughly 95% and change nothing on screen — a
             * recommendation for Amad Craft, not a change to make unasked.
             */
            /*
             * ARABIC CARRIES NO LABEL HERE, AND THAT IS THE POINT.
             *
             * «B2B · CRAFT SUPPLY» is a Latin phrase, and it was printing
             * twice on a page written entirely in Arabic — once above the
             * headline and once standing in the seam. The English site is
             * where it belongs.
             *
             * No Arabic replacement is written: a label like that is copy,
             * and copy comes from Amad Craft (§22.1). Writing one here to
             * fill the space is the exact thing the first constraint forbids.
             * Set `eyebrow` in the panel and both the label and the vertical
             * lettering come back on the Arabic page.
             */
            'eyebrow' => null,
            'eyebrow_en' => 'B2B · CRAFT SUPPLY',
            'image' => [
                'alt' => 'جولة داخل معرض أمد الحرف',
                'url' => '/videos/amadcraft.gif',
                'webp' => null,
                'poster' => '/images/craft/hero-poster.webp',
            ],
            'ctas' => [
                ['label' => 'استكشف حلول الجهات', 'label_en' => 'Explore solutions for entities', 'source' => 'government'],
                ['label' => 'اكتشف فرص الشراكة', 'label_en' => 'Discover partnership opportunities', 'source' => 'partner'],
                ['label' => 'انضم إلى شبكة الحرفيين', 'label_en' => 'Join the artisan network', 'source' => 'artisan'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 2) Why Amad Craft
    |--------------------------------------------------------------------------
    */
    [
        'type' => 'rich_text',
        'anchor' => 'home',
        'ar' => [
            'heading' => 'لماذا أمد الحرف؟',
            'subheading' => 'لأن الثقافة السعودية تستحق أكثر من منتج تقليدي',
            'body' => '<p>قد يكون من السهل العثور على منتج يحمل شكلاً سعودياً.</p>'
                .'<p>لكن التحدي الحقيقي هو العثور على حل:</p>'
                .'<ul>'
                .'<li>يحمل معنى حقيقياً للثقافة السعودية.</li>'
                .'<li>مصمم بشكل يليق بالجهة وعلامتها.</li>'
                .'<li>قابل للتخصيص حسب المناسبة والاحتياج.</li>'
                .'<li>يمكن إنتاجه بجودة متسقة.</li>'
                .'<li>مناسب للكميات المطلوبة.</li>'
                .'<li>جاهز للتغليف والتقديم.</li>'
                .'<li>ويمكن الاعتماد عليه حتى موعد التسليم.</li>'
                .'</ul>'
                .'<p>هنا يأتي دور أمد الحرف.</p>'
                .'<p>نحن نربط بين الحرفة الأصيلة والسوق المؤسسي، ونحوّل الفكرة من حرفة أو خامة إلى منتج وحل متكامل قابل للتقديم والاستخدام والتوريد.</p>',
        ],
        'en' => [
            'heading' => 'Why Amad Craft?',
            'subheading' => 'Because Saudi culture deserves more than a conventional product',
            'body' => '<p>It may be easy to find a product that carries a Saudi look.</p>'
                .'<p>But the real challenge is finding a solution that:</p>'
                .'<ul>'
                .'<li>carries real meaning for Saudi culture.</li>'
                .'<li>is designed in a way worthy of the entity and its brand.</li>'
                .'<li>can be customised to the occasion and the need.</li>'
                .'<li>can be produced at consistent quality.</li>'
                .'<li>suits the quantities required.</li>'
                .'<li>is ready for packaging and presentation.</li>'
                .'<li>and can be relied upon right up to the delivery date.</li>'
                .'</ul>'
                .'<p>This is where Amad Craft comes in.</p>'
                .'<p>We connect authentic craft with the institutional market, turning an idea — a craft or a raw material — into a complete product and solution ready to be presented, used and supplied.</p>',
        ],
        'settings' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | 3) What we offer — four cards
    |--------------------------------------------------------------------------
    */
    [
        'type' => 'cards',
        'anchor' => 'home',
        'ar' => [
            'heading' => 'ماذا نقدم؟',
            'subheading' => 'أربع مساحات للحلول',
            'body' => null,
        ],
        'en' => [
            'heading' => 'What we offer',
            'subheading' => 'Four solution areas',
            'body' => null,
        ],
        'settings' => [
            /*
             * On navy. The old home page put a dark band a screen or two
             * below the hero — the impact figures — and the eye used it to
             * measure how far it had come. This page lost that when the
             * figures went; the four offer areas take the same role.
             * CardsGrid already draws `band`; nothing new is needed.
             */
            'variant' => 'band',
            'items' => [
                [
                    'number' => '01',
                    'title' => 'الهدايا المؤسسية',
                    'title_en' => 'Corporate gifts',
                    'body' => 'هدايا تعكس هوية المناسبة والجهة، من الهدايا التذكارية والتوزيعات إلى مجموعات الهدايا المتكاملة.',
                    'body_en' => 'Gifts that reflect the identity of the occasion and the entity, from commemorative gifts and giveaways to complete gift collections.',
                ],
                [
                    'number' => '02',
                    'title' => 'المجموعات التنفيذية وكبار الشخصيات',
                    'title_en' => 'Executive and VIP collections',
                    'body' => 'حلول أكثر تميزاً للوفود، كبار العملاء، القيادات والمناسبات الرسمية.',
                    'body_en' => 'More distinguished solutions for delegations, key clients, leadership and official occasions.',
                ],
                [
                    'number' => '03',
                    'title' => 'حلول المساحات',
                    'title_en' => 'Solutions for spaces',
                    'body' => 'منتجات وعناصر حرفية وثقافية تضيف الهوية السعودية إلى المكاتب، الفنادق، المساحات والوجهات.',
                    'body_en' => 'Craft and cultural products and elements that bring Saudi identity into offices, hotels, spaces and destinations.',
                ],
                [
                    'number' => '04',
                    'title' => 'المشاريع والتجارب الثقافية',
                    'title_en' => 'Cultural projects and experiences',
                    'body' => 'مشاريع تجمع بين الحرفة والمنتج والتجربة، بما يناسب الفعاليات والوجهات والمبادرات الثقافية والسياحية.',
                    'body_en' => 'Projects that bring together craft, product and experience, suited to events, destinations and cultural and tourism initiatives.',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 4) How we create value — the capability flow
    |--------------------------------------------------------------------------
    |
    | The brief asks for the chain to be shown «كعناصر متتابعة/flow بصري مع
    | الحفاظ على اتجاه RTL للأسهم» — as a visual sequence whose arrows follow
    | the reading direction. That is what `value_flow` renders; the arrow is
    | drawn by CSS from the writing direction, so it points correctly in both
    | languages without a second list.
    */
    [
        'type' => 'value_flow',
        'anchor' => 'home',
        'ar' => [
            'heading' => 'كيف نصنع القيمة؟',
            'subheading' => 'من الحرفة إلى الحل المؤسسي',
            'body' => '<p>الحرفي يمتلك المهارة.</p>'
                .'<p>لكن امتلاك المهارة لا يعني بالضرورة امتلاك القدرة على:</p>',
        ],
        'en' => [
            'heading' => 'How we create value',
            'subheading' => 'From craft to institutional solution',
            'body' => '<p>The artisan has the skill.</p>'
                .'<p>But having the skill does not necessarily mean having the capacity for:</p>',
        ],
        'settings' => [
            'steps' => [
                ['title' => 'تطوير منتج كامل', 'title_en' => 'Developing a complete product'],
                ['title' => 'تصميم التغليف', 'title_en' => 'Packaging design'],
                ['title' => 'ضبط الجودة', 'title_en' => 'Quality control'],
                ['title' => 'الإنتاج بالكميات', 'title_en' => 'Production at volume'],
                ['title' => 'إصدار الفواتير', 'title_en' => 'Issuing invoices'],
                ['title' => 'التعامل مع المشتريات', 'title_en' => 'Dealing with procurement'],
                ['title' => 'الالتزام بمواعيد التوريد', 'title_en' => 'Meeting supply deadlines'],
                ['title' => 'إدارة المشروع', 'title_en' => 'Project management'],
            ],
        ],
    ],

    [
        'type' => 'rich_text',
        'anchor' => 'home',
        'ar' => [
            'heading' => null,
            'subheading' => null,
            'body' => '<p>وهنا لا نطلب من الحرفي أن يكون مصنعاً أو شركة مبيعات.</p>'
                .'<p>أمد الحرف تتولى ما يحتاجه السوق، بينما يبقى الحرفي مركز العملية وقيمة المنتج.</p>'
                .'<p>نطوّر المنتجات، ندعم الإنتاج، ندير الجودة، ونفتح للحرفيين قنوات وصول إلى السوق.</p>'
                .'<p>وهذا هو جوهر أمد الحرف.</p>',
        ],
        'en' => [
            'heading' => null,
            'subheading' => null,
            'body' => '<p>Here we do not ask the artisan to be a factory or a sales company.</p>'
                .'<p>Amad Craft handles what the market requires, while the artisan remains the centre of the process and the value of the product.</p>'
                .'<p>We develop products, support production, manage quality, and open market access channels for artisans.</p>'
                .'<p>This is the essence of Amad Craft.</p>',
        ],
        'settings' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | 5) Choose what matters to you — three routed cards
    |--------------------------------------------------------------------------
    |
    | Each card's button scrolls to the one form and tags the segment. The
    | brief is explicit that these summary cards are NOT the anchor targets:
    | the navbar points at the detailed sections (6, 7, 8) below.
    */
    [
        'type' => 'segment_cards',
        'anchor' => 'home',
        'ar' => [
            'heading' => 'اختر ما يهمك',
            'subheading' => null,
            'body' => null,
        ],
        'en' => [
            'heading' => 'Choose what matters to you',
            'subheading' => null,
            'body' => null,
        ],
        'settings' => [
            'items' => [
                [
                    'source' => 'government',
                    'title' => 'الجهات الحكومية والخاصة',
                    'title_en' => 'Government and private entities',
                    'body' => 'لديكم مناسبة، فعالية، وفد، برنامج، مبادرة أو احتياج مؤسسي؟ نساعدكم على تحويل الاحتياج إلى حل ثقافي سعودي متكامل.',
                    'body_en' => 'Do you have an occasion, an event, a delegation, a programme, an initiative or an institutional need? We help you turn that need into a complete Saudi cultural solution.',
                    'label' => 'أخبرنا بما تحتاجه',
                    'label_en' => 'Tell us what you need',
                ],
                [
                    'source' => 'partner',
                    'title' => 'الشركاء',
                    'title_en' => 'Partners',
                    'body' => 'هل تقدمون خدمات الفعاليات أو الهدايا أو الضيافة وتبحثون عن محتوى سعودي أصيل تضيفونه إلى عروضكم؟ أمد الحرف يمكن أن تكون ذراعكم المتخصصة في الحلول الثقافية والحرفية السعودية.',
                    'body_en' => 'Do you provide events, gifting or hospitality services and are looking for authentic Saudi content to add to your offering? Amad Craft can be your specialised arm for Saudi cultural and craft solutions.',
                    'label' => 'لنبحث فرصة شراكة',
                    'label_en' => 'Let us explore a partnership',
                ],
                [
                    'source' => 'artisan',
                    'title' => 'الحرفيون',
                    'title_en' => 'Artisans',
                    'body' => 'لديك حرفة حقيقية، لكن الوصول إلى السوق المؤسسي ليس دائماً سهلاً؟ أمد الحرف تساعدك على تطوير حرفتك وتحويلها إلى فرص إنتاج وسوق واستدامة.',
                    'body_en' => 'You have a real craft, but reaching the institutional market is not always easy? Amad Craft helps you develop your craft and turn it into production, market and sustainability opportunities.',
                    'label' => 'أرغب في الانضمام إلى شبكة الحرفيين',
                    'label_en' => 'I want to join the artisan network',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 6) #government — Government and private entities
    |--------------------------------------------------------------------------
    | Five rows under one anchor. The navbar's «الجهات» lands on the first.
    */
    [
        'type' => 'rich_text',
        'anchor' => 'government',
        'ar' => [
            'heading' => 'لديكم هدف... ونحن نطوّر الحل',
            'subheading' => 'لا تحتاجون مورداً آخر للهدايا',
            'body' => '<p>غالباً لا تكون المشكلة في العثور على منتج.</p>'
                .'<p>المشكلة تبدأ عندما تحتاج الجهة إلى منتج مناسب، أصيل، مخصص، بجودة واضحة، وبكمية وموعد يمكن الاعتماد عليه.</p>'
                .'<p>وقد تجد الجهة نفسها أمام:</p>'
                .'<ul>'
                .'<li>منتجات متشابهة لا تحمل معنى حقيقياً.</li>'
                .'<li>موردين متعددين لكل جزء من الطلب.</li>'
                .'<li>صعوبة في تحويل الهوية السعودية إلى تصميم معاصر.</li>'
                .'<li>منتجات جميلة ولكنها غير مناسبة للاستخدام المؤسسي.</li>'
                .'<li>تفاوت في الجودة بين القطع.</li>'
                .'<li>محدودية في التخصيص.</li>'
                .'<li>صعوبة في تنفيذ الكميات المطلوبة.</li>'
                .'<li>تأخر في التسليم بسبب عدم وضوح مراحل التطوير والإنتاج.</li>'
                .'<li>تغليف لا يعكس قيمة الهدية أو مكانة الجهة.</li>'
                .'</ul>'
                .'<p>نحن نبني الحل من البداية حتى يصل إليكم بالشكل الذي يمكن تقديمه بثقة.</p>',
        ],
        'en' => [
            'heading' => 'You have an objective — and we develop the solution',
            'subheading' => 'You do not need another gift supplier',
            'body' => '<p>The problem is usually not finding a product.</p>'
                .'<p>The problem starts when an entity needs a product that is suitable, authentic, customised, of clear quality, and in a quantity and to a date that can be relied upon.</p>'
                .'<p>And the entity may find itself facing:</p>'
                .'<ul>'
                .'<li>Similar products that carry no real meaning.</li>'
                .'<li>Multiple suppliers for each part of the order.</li>'
                .'<li>Difficulty translating Saudi identity into contemporary design.</li>'
                .'<li>Beautiful products that are unsuitable for institutional use.</li>'
                .'<li>Variation in quality between pieces.</li>'
                .'<li>Limited customisation.</li>'
                .'<li>Difficulty producing the quantities required.</li>'
                .'<li>Late delivery, because the development and production stages were never clear.</li>'
                .'<li>Packaging that does not reflect the value of the gift or the standing of the entity.</li>'
                .'</ul>'
                .'<p>We build the solution from the beginning, so that it reaches you in a form you can present with confidence.</p>',
        ],
        /*
         * The brief's own label for this section, set as the
         * eyebrow. Approved copy, not a caption invented here.
         */
        'settings' => ['eyebrow' => 'القسم الأول · الجهات الحكومية والخاصة', 'eyebrow_en' => 'Section one · Government and private entities'],
    ],

    [
        'type' => 'rich_text',
        'anchor' => 'government',
        'ar' => [
            'heading' => 'ماذا تحصلون عليه مع أمد الحرف؟',
            'subheading' => 'حلاً يبدأ من احتياجكم، وليس من المنتجات الموجودة لدينا',
            'body' => '<p>بدلاً من أن نقول لكم: "هذه هي المنتجات المتوفرة لدينا."</p>'
                .'<p>نبدأ بالسؤال: ما الهدف؟ ولمن؟ وفي أي مناسبة؟ وما الصورة التي تريدون أن تتركها؟</p>'
                .'<p>ثم نعمل على تطوير الحل المناسب. قد يكون:</p>'
                .'<ul>'
                .'<li>هدية مؤسسية.</li>'
                .'<li>مجموعة لكبار الشخصيات.</li>'
                .'<li>هدية لوفد رسمي.</li>'
                .'<li>هدية للموظفين أو العملاء.</li>'
                .'<li>منتجاً يحمل هوية الجهة.</li>'
                .'<li>مجموعة موسمية.</li>'
                .'<li>حلولاً لمساحات أو وجهات.</li>'
                .'<li>مشروعاً ثقافياً متكاملاً.</li>'
                .'</ul>',
        ],
        'en' => [
            'heading' => 'What do you get with Amad Craft?',
            'subheading' => 'A solution that starts from your need, not from the products we happen to hold',
            'body' => '<p>Instead of telling you: "these are the products we have available."</p>'
                .'<p>We start with the question: what is the objective? For whom? On what occasion? And what impression do you want to leave?</p>'
                .'<p>Then we work on developing the right solution. It may be:</p>'
                .'<ul>'
                .'<li>A corporate gift.</li>'
                .'<li>A collection for VIPs.</li>'
                .'<li>A gift for an official delegation.</li>'
                .'<li>A gift for employees or clients.</li>'
                .'<li>A product carrying the entity\'s identity.</li>'
                .'<li>A seasonal collection.</li>'
                .'<li>Solutions for spaces or destinations.</li>'
                .'<li>A complete cultural project.</li>'
                .'</ul>',
        ],
        'settings' => [],
    ],

    [
        'type' => 'accordion',
        'anchor' => 'government',
        'ar' => [
            'heading' => 'أهم ما يهمكم... نعالجه من البداية',
            'subheading' => null,
            'body' => null,
        ],
        'en' => [
            'heading' => 'What matters most to you — addressed from the start',
            'subheading' => null,
            'body' => null,
        ],
        'settings' => [
            'items' => [
                [
                    'question' => 'هل المنتج يعكس هويتنا؟',
                    'question_en' => 'Does the product reflect our identity?',
                    'answer' => 'نطور الحل بحيث تكون الثقافة السعودية جزءاً أصيلاً من الفكرة، وليس مجرد زخرفة على منتج تجاري.',
                    'answer_en' => 'We develop the solution so that Saudi culture is an authentic part of the idea, not mere ornament on a commercial product.',
                ],
                [
                    'question' => 'هل يمكن تخصيصه؟',
                    'question_en' => 'Can it be customised?',
                    'answer' => 'نطور المنتجات والحلول وفق المناسبة والهوية والميزانية والكمية المطلوبة، بحسب طبيعة المشروع.',
                    'answer_en' => 'We develop products and solutions according to the occasion, the identity, the budget and the quantity required, depending on the nature of the project.',
                ],
                [
                    'question' => 'هل نستطيع الاعتماد على الجودة؟',
                    'question_en' => 'Can we rely on the quality?',
                    'answer' => 'نمرر العمل عبر مراحل تطوير واعتماد وإنتاج، بدلاً من الانتقال مباشرة إلى الكميات.',
                    'answer_en' => 'We take the work through stages of development, approval and production, rather than moving straight to volume.',
                ],
                [
                    'question' => 'ماذا لو لم يكن المنتج مناسباً بعد رؤيته؟',
                    'question_en' => 'What if the product turns out not to be suitable once we see it?',
                    'answer' => 'نبدأ بالتصور والنموذج/العينة قبل الانتقال إلى الإنتاج، عندما يتطلب المشروع ذلك.',
                    'answer_en' => 'We start with the concept and the prototype or sample before moving to production, when the project calls for it.',
                ],
                [
                    'question' => 'هل تستطيعون توفير الكمية؟',
                    'question_en' => 'Can you supply the quantity?',
                    'answer' => 'نخطط للإنتاج وفق الكمية المطلوبة وقدرة شبكة الإنتاج، بدلاً من بيع منتج غير قابل للتوسع.',
                    'answer_en' => 'We plan production according to the quantity required and the capacity of the production network, rather than selling a product that cannot scale.',
                ],
                [
                    'question' => 'هل سيتأخر الطلب؟',
                    'question_en' => 'Will the order be late?',
                    'answer' => 'يتم تحديد نطاق العمل ومراحل التطوير والإنتاج والتسليم قبل التنفيذ، حتى تكون الصورة واضحة منذ البداية.',
                    'answer_en' => 'The scope of work and the development, production and delivery stages are defined before execution, so the picture is clear from the start.',
                ],
                [
                    'question' => 'هل سنضطر للتعامل مع أكثر من طرف؟',
                    'question_en' => 'Will we have to deal with more than one party?',
                    'answer' => 'نحن ندير الحل من جانبنا، وننسق مراحل التطوير والإنتاج والجودة والتغليف والتسليم وفق نطاق المشروع.',
                    'answer_en' => 'We manage the solution from our side, coordinating the development, production, quality, packaging and delivery stages according to the project scope.',
                ],
                [
                    'question' => 'هل المنتج مجرد هدية؟',
                    'question_en' => 'Is the product just a gift?',
                    'answer' => 'لا. نحن نساعدكم على استخدام الثقافة السعودية كجزء من رسالة الجهة وتجربة المستفيد والعلاقة مع العميل أو الضيف.',
                    'answer_en' => 'No. We help you use Saudi culture as part of the entity\'s message, the beneficiary\'s experience and the relationship with the client or guest.',
                ],
            ],
        ],
    ],

    [
        'type' => 'process_steps',
        'anchor' => 'government',
        'ar' => [
            'heading' => 'كيف نعمل؟',
            'subheading' => 'من الاحتياج إلى التسليم',
            'body' => '<p>النتيجة؟ بدلاً من إدارة تفاصيل متعددة، تحصلون على شريك يدير الحل معكم من الفكرة إلى التنفيذ.</p>',
        ],
        'en' => [
            'heading' => 'How we work',
            'subheading' => 'From the need to the delivery',
            'body' => '<p>The result? Instead of managing many separate details, you get a partner who runs the solution with you from idea to execution.</p>',
        ],
        'settings' => [
            'items' => [
                [
                    'title' => 'نفهم الاحتياج',
                    'title_en' => 'We understand the need',
                    'body' => 'المناسبة، الجمهور، الكمية، الهوية، الميزانية والهدف.',
                    'body_en' => 'The occasion, the audience, the quantity, the identity, the budget and the objective.',
                ],
                [
                    'title' => 'نطوّر الفكرة',
                    'title_en' => 'We develop the idea',
                    'body' => 'نقترح المنتج أو المجموعة أو الحل المناسب.',
                    'body_en' => 'We propose the right product, collection or solution.',
                ],
                [
                    'title' => 'نطوّر النموذج',
                    'title_en' => 'We develop the prototype',
                    'body' => 'عند الحاجة، يتم تطوير نموذج أو عينة لاعتمادها.',
                    'body_en' => 'Where needed, a prototype or sample is developed for approval.',
                ],
                [
                    'title' => 'نجهز للإنتاج',
                    'title_en' => 'We prepare for production',
                    'body' => 'نحدد المواصفات والكميات ومتطلبات الجودة والتغليف.',
                    'body_en' => 'We define the specifications, the quantities, and the quality and packaging requirements.',
                ],
                [
                    'title' => 'ننتج ونتحقق',
                    'title_en' => 'We produce and verify',
                    'body' => 'يتم تنفيذ الطلب وفق المواصفات المعتمدة.',
                    'body_en' => 'The order is executed to the approved specifications.',
                ],
                [
                    'title' => 'نسلّم الحل',
                    'title_en' => 'We deliver the solution',
                    'body' => 'التغليف والتجهيز والتسليم وفق ما تم الاتفاق عليه.',
                    'body_en' => 'Packaging, preparation and delivery as agreed.',
                ],
            ],
        ],
    ],

    [
        'type' => 'segment_cta',
        'anchor' => 'government',
        'ar' => ['heading' => null, 'subheading' => null, 'body' => null],
        'en' => ['heading' => null, 'subheading' => null, 'body' => null],
        'settings' => [
            'source' => 'government',
            'label' => 'أخبرنا بما تحتاجه',
            'label_en' => 'Tell us what you need',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 7) #partners — Partners
    |--------------------------------------------------------------------------
    */
    [
        'type' => 'rich_text',
        'anchor' => 'partners',
        'ar' => [
            'heading' => 'أضف الثقافة السعودية إلى عروضك',
            'subheading' => 'لا نريد أن نكون مورداً آخر لك',
            'body' => '<p>إذا كنت شركة فعاليات، شركة هدايا، شركة تنظيم مؤتمرات، شركة ضيافة أو تعمل مع جهات تبحث عن محتوى سعودي...</p>'
                .'<p>فقد لا تحتاج إلى مورد إضافي.</p>'
                .'<p>قد تحتاج إلى شريك متخصص يكمل ما تقدمه.</p>',
        ],
        'en' => [
            'heading' => 'Add Saudi culture to your offering',
            'subheading' => 'We do not want to be another supplier to you',
            'body' => '<p>If you are an events company, a gifting company, a conference organiser, a hospitality company, or you work with entities looking for Saudi content...</p>'
                .'<p>then you may not need an additional supplier.</p>'
                .'<p>You may need a specialised partner who completes what you offer.</p>',
        ],
        /*
         * The brief's own label for this section, set as the
         * eyebrow. Approved copy, not a caption invented here.
         */
        'settings' => ['eyebrow' => 'القسم الثاني · الشركاء', 'eyebrow_en' => 'Section two · Partners'],
    ],

    [
        'type' => 'rich_text',
        'anchor' => 'partners',
        'ar' => [
            'heading' => 'نعرف أين تكمن المشكلة',
            'subheading' => 'عندما يحتاج عميلك إلى حل سعودي مميز، قد تواجه:',
            'body' => '<ul>'
                .'<li>منتجات جاهزة لا تناسب مفهوم الفعالية.</li>'
                .'<li>صعوبة العثور على حرفيين قادرين على تنفيذ الكميات.</li>'
                .'<li>تعدد الأطراف بين التصميم والإنتاج والتغليف.</li>'
                .'<li>تفاوت الجودة بين الموردين.</li>'
                .'<li>وقت ضيق قبل الفعالية.</li>'
                .'<li>صعوبة تطوير منتج مخصص للعميل.</li>'
                .'<li>عدم القدرة على ضمان استمرارية المنتج نفسه عند إعادة الطلب.</li>'
                .'<li>الحاجة إلى منتج يحمل قصة حقيقية وليس مجرد تصميم تراثي.</li>'
                .'<li>مخاطرة أن يكون المورد جيداً في الحرفة لكنه غير جاهز للتعامل المؤسسي.</li>'
                .'</ul>'
                .'<p>أمد الحرف مصممة لسد هذه الفجوة.</p>',
        ],
        'en' => [
            'heading' => 'We know where the problem lies',
            'subheading' => 'When your client needs a distinctive Saudi solution, you may face:',
            'body' => '<ul>'
                .'<li>Off-the-shelf products that do not fit the concept of the event.</li>'
                .'<li>Difficulty finding artisans able to produce the quantities.</li>'
                .'<li>Multiple parties across design, production and packaging.</li>'
                .'<li>Variation in quality between suppliers.</li>'
                .'<li>Tight time before the event.</li>'
                .'<li>Difficulty developing a product customised for the client.</li>'
                .'<li>No way to guarantee the same product again on a repeat order.</li>'
                .'<li>The need for a product that carries a real story, not merely a heritage-styled design.</li>'
                .'<li>The risk of a supplier who is good at the craft but not ready for institutional dealings.</li>'
                .'</ul>'
                .'<p>Amad Craft is built to close this gap.</p>',
        ],
        'settings' => [],
    ],

    [
        'type' => 'cards',
        'anchor' => 'partners',
        'ar' => [
            'heading' => 'ماذا تضيف أمد الحرف لشراكتك؟',
            'subheading' => null,
            'body' => null,
        ],
        'en' => [
            'heading' => 'What does Amad Craft add to your partnership?',
            'subheading' => null,
            'body' => null,
        ],
        'settings' => [
            'items' => [
                [
                    'title' => 'محتوى سعودي أصيل',
                    'title_en' => 'Authentic Saudi content',
                    'body' => 'منتجات وحلول مستندة إلى الحرفة والثقافة السعودية.',
                    'body_en' => 'Products and solutions grounded in Saudi craft and culture.',
                ],
                [
                    'title' => 'تطوير مخصص',
                    'title_en' => 'Custom development',
                    'body' => 'لا تقتصر الشراكة على المنتجات الجاهزة؛ يمكن تطوير حلول وفق مشروع العميل.',
                    'body_en' => 'The partnership is not limited to ready-made products; solutions can be developed around the client\'s project.',
                ],
                [
                    'title' => 'شبكة حرفيين ومصممين',
                    'title_en' => 'A network of artisans and designers',
                    'body' => 'الوصول إلى قدرات حرفية متنوعة بدلاً من الاعتماد على مورد واحد.',
                    'body_en' => 'Access to varied craft capabilities instead of depending on a single supplier.',
                ],
                [
                    'title' => 'تحويل الفكرة إلى منتج',
                    'title_en' => 'Turning an idea into a product',
                    'body' => 'نساعد في الانتقال من الفكرة أو الحرفة إلى منتج قابل للتقديم للعميل.',
                    'body_en' => 'We help move from an idea or a craft to a product that can be presented to the client.',
                ],
                [
                    'title' => 'دعم الإنتاج',
                    'title_en' => 'Production support',
                    'body' => 'تنسيق مراحل الإنتاج والتجهيز بما يناسب متطلبات المشروع.',
                    'body_en' => 'Coordinating the production and preparation stages to suit the project requirements.',
                ],
                [
                    'title' => 'جودة واتساق',
                    'title_en' => 'Quality and consistency',
                    'body' => 'مراجعة المواصفات والجودة قبل التسليم وفق طبيعة المشروع.',
                    'body_en' => 'Reviewing specifications and quality before delivery, according to the nature of the project.',
                ],
                [
                    'title' => 'مرونة في المشاريع',
                    'title_en' => 'Flexibility across projects',
                    'body' => 'من قطعة مميزة لكبار الشخصيات إلى كميات كبيرة لفعالية أو حملة.',
                    'body_en' => 'From a distinguished piece for VIPs to large quantities for an event or a campaign.',
                ],
            ],
        ],
    ],

    [
        'type' => 'role_split',
        'anchor' => 'partners',
        'ar' => [
            'heading' => 'أنت تعرف عميلك... ونحن نعرف الحل الثقافي',
            'subheading' => null,
            'body' => '<p>النتيجة: تضيف قيمة جديدة إلى عرضك دون الحاجة إلى بناء قدرات حرفية وإنتاجية من الصفر. وتصبح الثقافة السعودية جزءاً من الحل الذي تقدمه، وليس مجرد بند إضافي في العرض.</p>',
        ],
        'en' => [
            'heading' => 'You know your client — and we know the cultural solution',
            'subheading' => null,
            'body' => '<p>The result: you add new value to your offering without having to build craft and production capabilities from scratch. And Saudi culture becomes part of the solution you provide, not merely an additional line in the proposal.</p>',
        ],
        'settings' => [
            'columns' => [
                [
                    'title' => 'أنت',
                    'title_en' => 'You',
                    'body' => 'العميل، الفعالية، المشروع، العرض التجاري والعلاقة.',
                    'body_en' => 'The client, the event, the project, the commercial proposal and the relationship.',
                ],
                [
                    'title' => 'أمد الحرف',
                    'title_en' => 'Amad Craft',
                    'body' => 'التطوير الثقافي، المنتج، الحرفيون، الإنتاج، الجودة والتجهيز.',
                    'body_en' => 'Cultural development, the product, the artisans, production, quality and preparation.',
                ],
            ],
        ],
    ],

    [
        'type' => 'rich_text',
        'anchor' => 'partners',
        'ar' => [
            'heading' => 'لماذا تعمل معنا؟',
            'subheading' => null,
            'body' => '<p>لأنك لا تبحث فقط عن منتج. أنت تبحث عن: شريك يفهم طبيعة المشروع، يستطيع تطوير الحل، ويملك القدرة على تحويله إلى تنفيذ فعلي.</p>'
                .'<p>نحن نؤمن أن نجاح الشراكة ليس في بيع أكبر عدد من المنتجات للشريك، بل في خلق قيمة جديدة يستطيع الشريك بيعها لعملائه.</p>',
        ],
        'en' => [
            'heading' => 'Why work with us?',
            'subheading' => null,
            'body' => '<p>Because you are not looking only for a product. You are looking for a partner who understands the nature of the project, can develop the solution, and has the capacity to turn it into actual execution.</p>'
                .'<p>We believe the success of a partnership lies not in selling the partner the largest number of products, but in creating new value the partner can sell to their own clients.</p>',
        ],
        'settings' => [],
    ],

    [
        'type' => 'segment_cta',
        'anchor' => 'partners',
        'ar' => ['heading' => null, 'subheading' => null, 'body' => null],
        'en' => ['heading' => null, 'subheading' => null, 'body' => null],
        'settings' => [
            'source' => 'partner',
            'label' => 'لنبحث فرصة شراكة',
            'label_en' => 'Let us explore a partnership',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 8) #artisans — Artisans
    |--------------------------------------------------------------------------
    | Note the order: the brief puts the join button BEFORE the closing
    | «منظومة تربط ثلاثة أشياء» cards, not after them. Kept as written.
    */
    [
        'type' => 'rich_text',
        'anchor' => 'artisans',
        'ar' => [
            'heading' => 'حرفتك تستحق فرصة أكبر',
            'subheading' => 'أنت تعرف الحرفة... ولا يفترض أن تعرف كل شيء آخر',
            'body' => '<p>قد تكون لديك مهارة عالية وخبرة طويلة في الحرفة.</p>'
                .'<p>لكن الوصول إلى السوق قد يتطلب أشياء أخرى:</p>'
                .'<ul>'
                .'<li>تطوير المنتج.</li>'
                .'<li>التصميم.</li>'
                .'<li>التغليف.</li>'
                .'<li>التصوير.</li>'
                .'<li>التسعير.</li>'
                .'<li>الجودة.</li>'
                .'<li>الإنتاج بالكميات.</li>'
                .'<li>الفوترة والتعامل المؤسسي.</li>'
                .'<li>الوصول إلى الشركات والجهات.</li>'
                .'<li>التسويق.</li>'
                .'<li>إدارة الطلبات.</li>'
                .'<li>الالتزام بالمواصفات والمواعيد.</li>'
                .'</ul>'
                .'<p>عدم امتلاكك لهذه القدرات لا يعني أن حرفتك أقل قيمة.</p>'
                .'<p>ولهذا أُنشئت أمد الحرف.</p>',
        ],
        'en' => [
            'heading' => 'Your craft deserves a bigger opportunity',
            'subheading' => 'You know the craft — you are not supposed to know everything else',
            'body' => '<p>You may have a high level of skill and long experience in your craft.</p>'
                .'<p>But reaching the market may require other things:</p>'
                .'<ul>'
                .'<li>Product development.</li>'
                .'<li>Design.</li>'
                .'<li>Packaging.</li>'
                .'<li>Photography.</li>'
                .'<li>Pricing.</li>'
                .'<li>Quality.</li>'
                .'<li>Production at volume.</li>'
                .'<li>Invoicing and institutional dealings.</li>'
                .'<li>Reaching companies and entities.</li>'
                .'<li>Marketing.</li>'
                .'<li>Order management.</li>'
                .'<li>Meeting specifications and deadlines.</li>'
                .'</ul>'
                .'<p>Not having these capabilities does not make your craft any less valuable.</p>'
                .'<p>And this is why Amad Craft was created.</p>',
        ],
        /*
         * The brief's own label for this section, set as the
         * eyebrow. Approved copy, not a caption invented here.
         */
        'settings' => ['eyebrow' => 'القسم الثالث · الحرفيون', 'eyebrow_en' => 'Section three · Artisans'],
    ],

    [
        'type' => 'cards',
        'anchor' => 'artisans',
        'ar' => [
            'heading' => 'ماذا تقدم لك أمد الحرف؟',
            'subheading' => 'نساعدك على الانتقال من "حرفي" إلى "فرصة مستدامة"',
            'body' => null,
        ],
        'en' => [
            'heading' => 'What does Amad Craft offer you?',
            'subheading' => 'We help you move from "an artisan" to "a sustainable opportunity"',
            'body' => null,
        ],
        'settings' => [
            'items' => [
                [
                    'title' => 'التدريب والتطوير',
                    'title_en' => 'Training and development',
                    'body' => 'تطوير المهارات وتحسين جودة الإنتاج بما يتناسب مع احتياجات السوق.',
                    'body_en' => 'Developing skills and improving production quality in line with market needs.',
                ],
                [
                    'title' => 'تطوير المنتجات',
                    'title_en' => 'Product development',
                    'body' => 'المساعدة في تحويل مهارتك إلى منتجات أكثر ملاءمة للسوق والعملاء.',
                    'body_en' => 'Help turning your skill into products better suited to the market and to clients.',
                ],
                [
                    'title' => 'تحسين الجودة',
                    'title_en' => 'Quality improvement',
                    'body' => 'رفع مستوى المنتج وتوحيد المواصفات عندما يتطلب المشروع ذلك.',
                    'body_en' => 'Raising the standard of the product and unifying specifications when the project calls for it.',
                ],
                [
                    'title' => 'دعم الإنتاج',
                    'title_en' => 'Production support',
                    'body' => 'ربط الحرفة بفرص إنتاج فعلية ومشاريع تحتاج إلى كميات.',
                    'body_en' => 'Connecting the craft with real production opportunities and projects that need volume.',
                ],
                [
                    'title' => 'الوصول إلى السوق',
                    'title_en' => 'Market access',
                    'body' => 'فتح فرص للوصول إلى الجهات والشركات والأسواق التي يصعب على الحرفي الوصول إليها بمفرده.',
                    'body_en' => 'Opening routes to entities, companies and markets that an artisan finds hard to reach alone.',
                ],
                [
                    'title' => 'التطوير والاستدامة',
                    'title_en' => 'Development and sustainability',
                    'body' => 'الهدف ليس تنفيذ طلب واحد فقط، بل بناء قدرة تساعد الحرفة والحرفي على الاستمرار والنمو.',
                    'body_en' => 'The aim is not to fulfil a single order, but to build a capability that helps the craft and the artisan continue and grow.',
                ],
            ],
        ],
    ],

    [
        'type' => 'rich_text',
        'anchor' => 'artisans',
        'ar' => [
            'heading' => 'ماذا نطلب منك؟',
            'subheading' => null,
            'body' => '<p>أن تركز على ما تتقنه: حرفتك. ونحن نساعد في الجوانب التي تحتاجها الرحلة من الحرفة إلى السوق.</p>'
                .'<p>في نموذج أمد الحرف، الحرفي ليس مجرد منفذ. الحرفي هو نواة الإنتاج وقيمة المنتج.</p>'
                .'<p>وتشير نماذج التشغيل التي طورتها أمد الحرف إلى دمج الحرفيين في الإنتاج الفعلي، مع مسارات للتدريب وتطوير المهارات وتحسين جودة الإنتاج.</p>',
        ],
        'en' => [
            'heading' => 'What do we ask of you?',
            'subheading' => null,
            'body' => '<p>That you focus on what you do best: your craft. And we help with the aspects the journey from craft to market requires.</p>'
                .'<p>In the Amad Craft model, the artisan is not merely an executor. The artisan is the core of production and the value of the product.</p>'
                .'<p>The operating models Amad Craft has developed point to integrating artisans into actual production, with tracks for training, skills development and improving production quality.</p>',
        ],
        'settings' => [],
    ],

    [
        'type' => 'segment_cta',
        'anchor' => 'artisans',
        'ar' => ['heading' => null, 'subheading' => null, 'body' => null],
        'en' => ['heading' => null, 'subheading' => null, 'body' => null],
        'settings' => [
            'source' => 'artisan',
            'label' => 'أرغب في الانضمام إلى شبكة الحرفيين',
            'label_en' => 'I want to join the artisan network',
        ],
    ],

    [
        'type' => 'cards',
        'anchor' => 'artisans',
        'ar' => [
            'heading' => 'أمد الحرف ليست مجرد شركة منتجات',
            'subheading' => 'إنها منظومة تربط ثلاثة أشياء',
            'body' => null,
        ],
        'en' => [
            'heading' => 'Amad Craft is not merely a products company',
            'subheading' => 'It is a system that connects three things',
            'body' => null,
        ],
        'settings' => [
            'items' => [
                [
                    'title' => 'الحرفة',
                    'title_en' => 'The craft',
                    'body' => 'مهارة سعودية أصيلة تستحق الحفاظ عليها وتطويرها.',
                    'body_en' => 'An authentic Saudi skill that deserves to be preserved and developed.',
                ],
                [
                    'title' => 'السوق',
                    'title_en' => 'The market',
                    'body' => 'جهات وأفراد يبحثون عن منتجات وحلول ذات معنى وقيمة.',
                    'body_en' => 'Entities and individuals looking for products and solutions with meaning and value.',
                ],
                [
                    'title' => 'الفرصة',
                    'title_en' => 'The opportunity',
                    'body' => 'نحوّل العلاقة بينهما إلى منتجات ومشاريع وفرص إنتاج قابلة للاستمرار.',
                    'body_en' => 'We turn the relationship between them into products, projects and production opportunities that can last.',
                ],
            ],
        ],
    ],

    [
        'type' => 'rich_text',
        'anchor' => 'artisans',
        'ar' => [
            'heading' => null,
            'subheading' => null,
            'body' => '<p>ولهذا فإن دعم الحرفيين ليس نشاطاً جانبياً في أمد الحرف. الحرفيون جزء من نموذج عملنا نفسه.</p>',
        ],
        'en' => [
            'heading' => null,
            'subheading' => null,
            'body' => '<p>This is why supporting artisans is not a side activity at Amad Craft. Artisans are part of our operating model itself.</p>',
        ],
        'settings' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | 9) Why you can trust Amad Craft
    |--------------------------------------------------------------------------
    */
    [
        'type' => 'cards',
        'anchor' => 'trust',
        'ar' => [
            'heading' => 'لماذا يمكنكم الوثوق بأمد الحرف؟',
            'subheading' => 'لأننا لا نطلب منكم أن تثقوا بالكلام فقط — نحن نبني الثقة من خلال طريقة العمل',
            'body' => null,
        ],
        'en' => [
            'heading' => 'Why can you trust Amad Craft?',
            'subheading' => 'Because we do not ask you to trust words alone — we build trust through the way we work',
            'body' => null,
        ],
        'settings' => [
            // The closing argument, on navy — it carries the page into the form.
            'variant' => 'band',
            'items' => [
                [
                    'title' => 'نبدأ بالاحتياج',
                    'title_en' => 'We start with the need',
                    'body' => 'لا نبيع المنتج قبل فهم الغرض منه.',
                    'body_en' => 'We do not sell the product before understanding its purpose.',
                ],
                [
                    'title' => 'نوضح الحل',
                    'title_en' => 'We make the solution clear',
                    'body' => 'يكون نطاق المشروع والمخرجات والمراحل واضحة قبل التنفيذ.',
                    'body_en' => 'The project scope, the deliverables and the stages are clear before execution.',
                ],
                [
                    'title' => 'نطوّر قبل الإنتاج',
                    'title_en' => 'We develop before producing',
                    'body' => 'عند الحاجة، يتم اعتماد النموذج أو العينة قبل الانتقال للكميات.',
                    'body_en' => 'Where needed, the prototype or sample is approved before moving to volume.',
                ],
                [
                    'title' => 'نعمل مع حرفيين ومصممين سعوديين',
                    'title_en' => 'We work with Saudi artisans and designers',
                    'body' => 'للحفاظ على أصالة المحتوى المحلي وربط المنتج بمصدره الحقيقي.',
                    'body_en' => 'To preserve the authenticity of local content and connect the product to its real source.',
                ],
                [
                    'title' => 'ندير العملية',
                    'title_en' => 'We manage the process',
                    'body' => 'من تطوير المنتج إلى الإنتاج والجودة والتجهيز وفق نطاق المشروع.',
                    'body_en' => 'From product development through production, quality and preparation, according to the project scope.',
                ],
                [
                    'title' => 'نعمل كشريك',
                    'title_en' => 'We work as a partner',
                    'body' => 'نجاحنا لا يقاس فقط ببيع منتج، بل بنجاح الحل الذي نقدمه للعميل.',
                    'body_en' => 'Our success is measured not only by selling a product, but by the success of the solution we provide to the client.',
                ],
            ],
        ],
    ],

    [
        'type' => 'rich_text',
        'anchor' => 'trust',
        'ar' => [
            'heading' => null,
            'subheading' => null,
            'body' => '<p>والأهم... نحن شركة اجتماعية سعودية غير هادفة للربح، وأمد الحرف أُنشئت أصلاً لتمكين الحرفيين وربطهم بفرص حقيقية في السوق.</p>',
        ],
        'en' => [
            'heading' => null,
            'subheading' => null,
            'body' => '<p>And most importantly — we are a Saudi non-profit social enterprise, and Amad Craft was created in the first place to empower artisans and connect them with real opportunities in the market.</p>',
        ],
        'settings' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | 10) Closing statement, leading into the form
    |--------------------------------------------------------------------------
    */
    /*
     * `rich_text`, not `intro_statement`: that component drops the heading
     * and prints its body as plain text split on blank lines, so the approved
     * heading would vanish and the paragraph markup would show as literal
     * `<p>` tags on the page.
     */
    [
        'type' => 'rich_text',
        'anchor' => 'trust',
        'ar' => [
            'heading' => 'لا تبحث عن مورد آخر — ابحث عن شريك يفهم ما وراء المنتج',
            'subheading' => null,
            'body' => '<p>إذا كنت جهة تبحث عن حل ثقافي سعودي، أو شريكاً يريد إضافة قيمة جديدة لعملائه، أو حرفياً يريد الوصول إلى فرص أكبر...</p>'
                .'<p>هناك نقطة مشتركة واحدة: الحاجة إلى جهة تفهم الثقافة، وتفهم السوق، وتستطيع تحويل الاثنين إلى تنفيذ.</p>'
                .'<p>هذه هي أمد الحرف.</p>',
        ],
        'en' => [
            'heading' => 'Do not look for another supplier — look for a partner who understands what lies behind the product',
            'subheading' => null,
            'body' => '<p>Whether you are an entity looking for a Saudi cultural solution, a partner wanting to add new value for your clients, or an artisan wanting to reach bigger opportunities...</p>'
                .'<p>there is one thing in common: the need for an organisation that understands the culture, understands the market, and can turn the two into execution.</p>'
                .'<p>This is Amad Craft.</p>',
        ],
        'settings' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | 11) #contact — the one form on the site
    |--------------------------------------------------------------------------
    | The form itself is rendered by LandingContact from `lead_fields`; this
    | row carries only the copy around it (§22.1).
    */
    [
        'type' => 'contact_block',
        'anchor' => 'contact',
        'ar' => [
            'heading' => 'لنبدأ من احتياجك',
            'subheading' => null,
            'body' => '<p>لا تحتاج إلى كتابة تفاصيل طويلة. أرسل لنا اسمك وطريقة التواصل المناسبة، وسيتواصل معك فريق أمد الحرف لفهم احتياجك وتحديد الخطوة المناسبة.</p>',
        ],
        'en' => [
            'heading' => 'Let us start from your need',
            'subheading' => null,
            'body' => '<p>You do not need to write long details. Send us your name and the contact method that suits you, and the Amad Craft team will get in touch to understand your need and agree the right next step.</p>',
        ],
        'settings' => [
            'submitLabel' => 'تواصل مع أمد الحرف',
            'submitLabel_en' => 'Contact Amad Craft',
            /*
             * The small line above the form that reflects which button the
             * visitor arrived from. The brief asks for it («يُفضّل تغيير عنوان
             * صغير يظهر فوق النموذج ليعكس الشريحة») while the visible fields
             * stay at two. Keyed by the same source values the buttons carry.
             */
            'contexts' => [
                'government' => 'أخبرنا بما تحتاجه وسنعود إليك بالحل المناسب.',
                'government_en' => 'Tell us what you need and we will come back to you with the right solution.',
                'partner' => 'لنبحث فرصة شراكة تضيف قيمة جديدة إلى عروضك.',
                'partner_en' => 'Let us explore a partnership that adds new value to your offering.',
                'artisan' => 'أخبرنا عن حرفتك، وسنتواصل معك بشأن الانضمام إلى الشبكة.',
                'artisan_en' => 'Tell us about your craft, and we will be in touch about joining the network.',
            ],
        ],
    ],
];
