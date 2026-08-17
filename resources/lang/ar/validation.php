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
| Only the rules this application uses are translated. The rest are left out
| rather than guessed at: a wrong Arabic sentence is worse than a right
| English one.
|
| ⚠️ BUT AN OMITTED RULE DOES NOT FALL BACK TO ENGLISH — IT PRINTS ITS KEY.
|
| That is what this note used to claim, and it is false here. APP_FALLBACK_LOCALE
| is `ar`, so a rule missing from this file has nowhere to fall back to and
| Laravel renders the key itself: an Arabic administrator whose campaign end
| date preceded its start date was told «validation.after_or_equal». Measured,
| not assumed — `after_or_equal`, `required_with` and the password rules all
| printed their keys before the entries below were added.
|
| So the rule is narrower than it looks: leaving a rule out is safe only while
| nothing uses it. Anything added to a FormRequest, a controller or a cast that
| introduces a new rule must be translated here in the same change.
*/

return [
    'accepted' => 'يجب قبول :attribute.',
    'active_url' => ':attribute ليس رابطًا صحيحًا.',
    'after' => 'يجب أن يكون :attribute تاريخًا بعد :date.',
    // A campaign whose end date precedes its start (CampaignController).
    'after_or_equal' => 'يجب أن يكون :attribute تاريخًا بعد :date أو مساويًا له.',
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
    /*
     * The bilingual editor's own rule: a locale that has been written in must
     * carry the fields that go with it, while a locale left untouched stays
     * untouched (PageController, ResourceController).
     */
    'required_with' => 'حقل :attribute مطلوب عند وجود :values.',
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

    /*
     * Password::min(12)->letters()->numbers()->symbols(), which guards every
     * panel account (ProfileController, UserController). `mixed` and
     * `uncompromised` are not in that chain today and are translated anyway:
     * they are the same rule's message set, and the cost of the omission is
     * an administrator being told «validation.password.mixed» while trying to
     * choose a password — which is exactly the failure this file now warns
     * about.
     */
    'password' => [
        'letters' => 'يجب أن تحتوي :attribute على حرف واحد على الأقل.',
        'mixed' => 'يجب أن تحتوي :attribute على حرف كبير وحرف صغير على الأقل.',
        'numbers' => 'يجب أن تحتوي :attribute على رقم واحد على الأقل.',
        'symbols' => 'يجب أن تحتوي :attribute على رمز واحد على الأقل.',
        'uncompromised' => 'ظهرت :attribute هذه في تسريب بيانات. اختر غيرها.',
    ],

    'custom' => [
        'slug' => [
            // Names a page the editor can actually find. Deleting a page now
            // releases its identifier, so the only page that can be holding
            // this one is a live page in the list on the previous screen.
            'unique' => 'هذا المعرّف مستعمل في صفحة أخرى. افتح قائمة الصفحات لترى أيّها، أو اختر معرّفًا غيره.',
        ],
    ],

    'attributes' => [
        'slug' => 'المعرّف في الرابط',
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
