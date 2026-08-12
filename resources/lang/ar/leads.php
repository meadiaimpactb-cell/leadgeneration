<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Lead field strings — Arabic (§6.1, §10.6)
|--------------------------------------------------------------------------
| Error messages explain what went wrong AND how to fix it. The visitor was
| never asked to choose between email and phone, so no message may imply they
| picked the wrong type.
|
| The heading and reassurance line above the field are NOT here — those are
| marketing copy and come from the admin panel.
*/

return [
    'optional' => '(اختياري)',
    'placeholder' => 'بريدك الإلكتروني أو رقم جوالك',
    'dock_cta' => 'تواصلوا معنا',
    'submit' => 'تواصلوا معي',
    'submitting' => 'جارٍ الإرسال…',
    'add_message' => 'أضف رسالة (اختياري)',
    'message_label' => 'رسالتك',
    'message_placeholder' => 'سطر واحد…',

    'contact_required' => 'اكتب بريدك الإلكتروني أو رقم جوالك لنتمكن من التواصل معك.',
    'contact_invalid' => 'تعذّر التعرّف على ما كتبته. اكتب بريدًا إلكترونيًا مثل name@company.sa أو رقم جوال مثل 05xxxxxxxx.',
    'message_too_long' => 'الرسالة أطول من اللازم. اختصرها في سطر واحد.',

    'success' => 'وصلنا طلبك، وسنتواصل معك قريبًا.',
    'error' => 'تعذّر إرسال الطلب الآن. حاول مرة أخرى بعد قليل.',
    'too_many' => 'وصلتنا محاولات كثيرة من هذا الجهاز. انتظر دقيقة ثم حاول مجددًا.',
];
