/**
 * Adds the labels the regrouped sidebar needs to both admin lang files.
 *
 * Written as a script rather than by hand because the two files must stay in
 * step key for key: a label present in one language and missing in the other
 * renders the raw key in the panel, which is how a missing translation gets
 * shipped without anyone noticing.
 *
 *   node tools/add-admin-labels.mjs
 */
import { readFile, writeFile } from 'node:fs/promises';

const ADDITIONS = {
    ar: {
        nav_clients: 'العملاء والتكاملات',
        nav_visibility: 'الظهور والقياس',
        nav_settings: 'الإعدادات',
        soon: 'قيد التنفيذ',
        media_library: 'مكتبة الوسائط',
        campaigns_landing: 'الحملات وصفحات الهبوط',
        crm_link: 'ربط CRM',
        notifications: 'التنبيهات',
        confirmations: 'رسائل التأكيد',
        spam_guard: 'الحماية من السبام',
        sitemap: 'خريطة الموقع',
        activity_log: 'سجل النشاط',
        users_roles: 'المستخدمون والصلاحيات',
        languages: 'اللغات',
        backups: 'النسخ الاحتياطي',
        'upcoming.phase': 'المرحلة :n',
        'upcoming.note':
            'موضع هذه الشاشة في اللوحة معتمد، ووظيفتها تُبنى في مرحلتها. لا ينقص شيء من النظام الحالي.',
        'upcoming.media_title': 'مكتبة الوسائط',
        'upcoming.media_body':
            'قسم واحد لكل الصور والملفات: رفع متعدد، بحث ومجلدات، مقاسات تُولَّد تلقائيًا، ونص بديل بالعربية والإنجليزية لكل صورة. وكل حقل صورة في اللوحة يختار منها.',
        'upcoming.crm_title': 'ربط CRM',
        'upcoming.crm_body':
            'اختيار النظام النشط وبيانات الاتصال، زر لاختبار الاتصال، حالة المزامنة، سجل كل محاولة إرسال، وإعادة إرسال الطلبات الفاشلة فرديًا أو جماعيًا.',
        'upcoming.notifications_title': 'التنبيهات',
        'upcoming.notifications_body':
            'مستلمو التنبيهات وأنواعها لكل مستلم — طلب جديد، فشل إرسال إلى CRM، ملخص يومي — مع قالب بريد قابل للتحرير. الإرسال عبر الطابور فلا يتأخر الزائر.',
        'upcoming.confirmations_title': 'رسائل التأكيد',
        'upcoming.confirmations_body':
            'الرسالة التي تظهر للزائر بعد إرسال النموذج، بالعربية والإنجليزية، وقالب بريد التأكيد حين يُفعَّل.',
        'upcoming.spam_title': 'الحماية من السبام',
        'upcoming.spam_body':
            'حد المعدل لكل عنوان IP، ومفاتيح Turnstile الاختيارية، وتبويب للطلبات المصنَّفة سبامًا مع زر يعيدها إن كانت حقيقية. الحقل المخفي يعمل الآن بالفعل.',
        'upcoming.sitemap_title': 'خريطة الموقع',
        'upcoming.sitemap_body':
            'شاشة اطمئنان: رابط الملف، وقت آخر توليد، عدد الروابط، واستثناء صفحات بعينها. الخريطة نفسها تُولَّد الآن تلقائيًا عند كل طلب.',
        'upcoming.activity_title': 'سجل النشاط',
        'upcoming.activity_body':
            'من فعل ماذا ومتى — إنشاء وتعديل ونشر وتصدير وتغيير إعدادات ودخول — بفلاتر حسب المستخدم والنوع والفترة، للقراءة فقط.',
        'upcoming.languages_title': 'اللغات',
        'upcoming.languages_body':
            'مفتاح تفعيل النسخة الإنجليزية للموقع كاملًا، ونسبة اكتمال الترجمة لكل نوع محتوى. عند التعطيل يختفي مبدّل اللغة ولا تُفهرس مسارات /en.',
        'upcoming.backups_title': 'النسخ الاحتياطي',
        'upcoming.backups_body':
            'إنشاء نسخة الآن — قاعدة البيانات والوسائط — وجدولة يومية أو أسبوعية، وقائمة بالنسخ مع التنزيل والاستعادة.',
    },
    en: {
        nav_clients: 'Clients & integrations',
        nav_visibility: 'Visibility & measurement',
        nav_settings: 'Settings',
        soon: 'In progress',
        media_library: 'Media library',
        campaigns_landing: 'Campaigns & landing pages',
        crm_link: 'CRM connection',
        notifications: 'Notifications',
        confirmations: 'Confirmation messages',
        spam_guard: 'Spam protection',
        sitemap: 'Sitemap',
        activity_log: 'Activity log',
        users_roles: 'Users & permissions',
        languages: 'Languages',
        backups: 'Backups',
        'upcoming.phase': 'Phase :n',
        'upcoming.note':
            "This screen's place in the panel is agreed; its function is built in its phase. Nothing is missing from the system as it stands.",
        'upcoming.media_title': 'Media library',
        'upcoming.media_body':
            'One place for every image and file: multi-upload, search and folders, sizes generated automatically, and Arabic and English alt text on each image. Every image field in the panel picks from it.',
        'upcoming.crm_title': 'CRM connection',
        'upcoming.crm_body':
            'The active system and its credentials, a test-connection button, sync status, a log of every push attempt, and re-sending failed leads one by one or all at once.',
        'upcoming.notifications_title': 'Notifications',
        'upcoming.notifications_body':
            'Who is notified and of what — a new lead, a failed CRM push, the daily summary — with an editable email template. Sent on the queue, so the visitor never waits.',
        'upcoming.confirmations_title': 'Confirmation messages',
        'upcoming.confirmations_body':
            'The message shown after the form is sent, in Arabic and English, and the confirmation email template once it is switched on.',
        'upcoming.spam_title': 'Spam protection',
        'upcoming.spam_body':
            'A rate limit per IP, optional Turnstile keys, and a tab for leads marked as spam with a button to restore a genuine one. The honeypot already runs.',
        'upcoming.sitemap_title': 'Sitemap',
        'upcoming.sitemap_body':
            'A screen to check on it: the file, when it was last generated, how many URLs it carries, and which pages to exclude. The sitemap itself is already generated on every request.',
        'upcoming.activity_title': 'Activity log',
        'upcoming.activity_body':
            'Who did what and when — created, edited, published, exported, changed a setting, signed in — filtered by user, type and period. Read-only.',
        'upcoming.languages_title': 'Languages',
        'upcoming.languages_body':
            'A switch for the whole English site, and translation completeness per content type. Switched off, the language toggle disappears and /en is not indexed.',
        'upcoming.backups_title': 'Backups',
        'upcoming.backups_body':
            'Back up now — database and media — plus a daily or weekly schedule, and a list of backups to download or restore.',
    },
};

for (const [locale, labels] of Object.entries(ADDITIONS)) {
    const path = `resources/lang/${locale}/admin.php`;
    let source = await readFile(path, 'utf8');

    const rows = Object.entries(labels)
        .filter(([key]) => !source.includes(`'${key}' =>`))
        .map(([key, value]) => `    '${key}' => '${value.replace(/'/g, "\\'")}',`);

    if (rows.length === 0) {
        console.log(`${locale}: already complete`);
        continue;
    }

    // Inserted before the closing bracket of the returned array.
    const close = source.lastIndexOf('];');
    source = `${source.slice(0, close)}${rows.join('\n')}\n${source.slice(close)}`;

    await writeFile(path, source, 'utf8');
    console.log(`${locale}: +${rows.length} labels`);
}
