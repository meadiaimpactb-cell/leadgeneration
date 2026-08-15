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
    'phone_invalid' => 'الرقم غير صحيح لهذه الدولة. تأكدوا من عدد الأرقام وبادئتها.',
    'email_invalid' => 'صيغة البريد غير صحيحة. مثال: name@company.sa',
    'message_too_long' => 'الرسالة أطول من اللازم. اختصرها في سطر واحد.',

    /*
     * The thank-you after a successful submit — the DEFAULT wording only.
     *
     * These two are the one exception to "copy comes from the panel". The
     * client writes their own in Settings → Confirmation messages, and until
     * they do, these stand: unlike the confirmation email, this message cannot
     * simply not appear, because a form that submits and then says nothing
     * reads as a form that broke.
     *
     * A third, separately-worded `success` line for screen readers used to sit
     * here. It was removed with the move: the announcement now reads out the
     * same two strings the visitor is shown, so what is heard and what is seen
     * cannot drift apart once the client edits one of them.
     */
    'success_title' => 'وصلنا طلبك',
    'success_body' => 'سنتواصل معك قريبًا.',
    'error_title' => 'تعذّر الإرسال',
    'error_body' => 'لم يصل طلبك. تحقّق من اتصالك وحاول مرة أخرى — ما كتبته محفوظ.',
    'error' => 'تعذّر إرسال الطلب الآن. حاول مرة أخرى بعد قليل.',
    'too_many' => 'وصلتنا محاولات كثيرة من هذا الجهاز. انتظر دقيقة ثم حاول مجددًا.',
];
