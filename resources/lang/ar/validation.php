<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Validation messages — Arabic
|--------------------------------------------------------------------------
| This file did not exist, and its absence was visible on the site: every
| server-side complaint rendered as its own key, so a visitor who left the
| phone field empty was told «validation.required». It read as a broken page
| rather than as an answerable mistake, and it affected every form — the lead
| form's own messages in leads.php were the only ones that ever spoke Arabic.
|
| Only the rules this application uses are translated. The rest keep their
| English text rather than a guessed translation: a wrong Arabic sentence is
| worse than a right English one, and an untranslated string here is visible
| the moment it appears.
*/

return [
    'accepted' => 'يجب قبول :attribute.',
    'active_url' => ':attribute ليس رابطًا صحيحًا.',
    'after' => 'يجب أن يكون :attribute تاريخًا بعد :date.',
    'alpha' => 'يجب ألا يحتوي :attribute إلا على حروف.',
    'alpha_dash' => 'يجب ألا يحتوي :attribute إلا على حروف وأرقام وشرطات.',
    'alpha_num' => 'يجب ألا يحتوي :attribute إلا على حروف وأرقام.',
    'array' => 'يجب أن يكون :attribute مصفوفة.',
    'before' => 'يجب أن يكون :attribute تاريخًا قبل :date.',
    'boolean' => 'يجب أن تكون قيمة :attribute صحيحة أو خاطئة.',
    'confirmed' => 'تأكيد :attribute غير مطابق.',
    'date' => ':attribute ليس تاريخًا صحيحًا.',
    'declined' => 'يجب رفض :attribute.',
    'different' => 'يجب أن يختلف :attribute عن :other.',
    'digits' => 'يجب أن يتكوّن :attribute من :digits رقمًا.',
    'email' => 'صيغة :attribute غير صحيحة.',
    'exists' => ':attribute المحدد غير موجود.',
    'file' => 'يجب أن يكون :attribute ملفًا.',
    'filled' => 'حقل :attribute مطلوب.',
    'image' => 'يجب أن يكون :attribute صورة.',
    'in' => ':attribute المحدد غير صحيح.',
    'integer' => 'يجب أن يكون :attribute رقمًا صحيحًا.',
    'json' => 'يجب أن يكون :attribute نص JSON صحيحًا.',
    'mimes' => 'يجب أن يكون :attribute ملفًا من نوع: :values.',
    'not_in' => ':attribute المحدد غير صحيح.',
    'numeric' => 'يجب أن يكون :attribute رقمًا.',
    'present' => 'يجب إرسال حقل :attribute.',
    'prohibited' => 'حقل :attribute غير مسموح به.',
    'required' => 'حقل :attribute مطلوب.',
    'required_if' => 'حقل :attribute مطلوب عندما يكون :other هو :value.',
    'same' => 'يجب أن يتطابق :attribute مع :other.',
    'string' => 'يجب أن يكون :attribute نصًا.',
    'unique' => ':attribute مستخدم من قبل.',
    'uploaded' => 'فشل رفع :attribute.',
    'url' => 'يجب أن يكون :attribute رابطًا صحيحًا.',
    'uuid' => 'يجب أن يكون :attribute معرّف UUID صحيحًا.',
    'max' => [
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :max عنصرًا.',
        'file' => 'يجب ألا يزيد :attribute عن :max كيلوبايت.',
        'numeric' => 'يجب ألا يزيد :attribute عن :max.',
        'string' => 'يجب ألا يزيد :attribute عن :max حرفًا.',
    ],
    'min' => [
        'array' => 'يجب أن يحتوي :attribute على :min عنصرًا على الأقل.',
        'file' => 'يجب ألا يقل :attribute عن :min كيلوبايت.',
        'numeric' => 'يجب ألا يقل :attribute عن :min.',
        'string' => 'يجب ألا يقل :attribute عن :min حرفًا.',
    ],
    'size' => [
        'array' => 'يجب أن يحتوي :attribute على :size عنصرًا.',
        'file' => 'يجب أن يكون :attribute :size كيلوبايت.',
        'numeric' => 'يجب أن يكون :attribute :size.',
        'string' => 'يجب أن يكون :attribute :size حرفًا.',
    ],
    'between' => [
        'array' => 'يجب أن يحتوي :attribute بين :min و :max عنصرًا.',
        'file' => 'يجب أن يكون :attribute بين :min و :max كيلوبايت.',
        'numeric' => 'يجب أن يكون :attribute بين :min و :max.',
        'string' => 'يجب أن يكون :attribute بين :min و :max حرفًا.',
    ],

    'custom' => [],

    'attributes' => [
        'contact' => 'البريد الإلكتروني',
        'organisation' => 'اسم الشركة',
        'phone' => 'رقم الهاتف',
        'message' => 'الرسالة',
        'name' => 'الاسم',
        'job_title' => 'المسمى الوظيفي',
        'sector' => 'القطاع',
        'file' => 'الملف',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
    ],
];
