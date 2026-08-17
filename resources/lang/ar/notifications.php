<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Internal alert strings — Arabic
|--------------------------------------------------------------------------
| These go to the Amad Craft team, never to a visitor. They are operational
| text — counts, labels, a link into the panel — not marketing copy, which is
| why they live here and not in the database (§22.1 vs §22.5).
|
| The subject lines below are DEFAULTS. The client can replace them from the
| notifications screen; until they do, these are what is sent, because an
| internal alert that is not sent is a lead nobody hears about.
*/

return [
    // The new-enquiry alert. :contact is the address or number the visitor
    // left — it belongs in the subject so a phone can triage from the list.
    'alert_subject' => 'عميل محتمل جديد · New lead — :contact',
    'alert_greeting' => 'عميل محتمل جديد',

    // The daily digest.
    'summary_subject' => 'ملخص العملاء المحتملين اليومي',
    'summary_greeting' => 'ملخص اليوم',
    'summary_arrived' => 'طلبات وصلت: :count',
    'summary_awaiting' => 'طلبات ما زالت بانتظار الرد: :count',
    'summary_not_synced' => 'طلبات لم تصل إلى الـ CRM: :count',
    'summary_action' => 'فتح قائمة العملاء المحتملين',
];
