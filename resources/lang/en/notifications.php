<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Internal alert strings — English
|--------------------------------------------------------------------------
| See the Arabic file for why these are here rather than in the database.
| The team's working language is Arabic, so these are the fallback for an
| English-locale run of the scheduler rather than the usual case.
*/

return [
    'alert_subject' => 'عميل محتمل جديد · New lead — :contact',
    'alert_greeting' => 'New lead',

    'summary_subject' => 'Daily lead summary',
    'summary_greeting' => "Today's summary",
    'summary_arrived' => 'Enquiries received: :count',
    'summary_awaiting' => 'Enquiries still awaiting a reply: :count',
    'summary_not_synced' => 'Enquiries that never reached the CRM: :count',
    'summary_action' => 'Open the leads list',
];
