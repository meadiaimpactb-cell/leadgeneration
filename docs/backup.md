# النسخ الاحتياطي والاستعادة

كُتب هذا الملف بعد حادثة حقيقية: أمر `php artisan migrate:fresh --env=testing`
مسح قاعدة بيانات التطوير `amadcraft_b2b` كاملة. استُعيد كل المحتوى من الـ seeders،
لكن **135 عميلًا محتملًا (leads) ضاعوا نهائيًا** لأن لا نسخة احتياطية كانت موجودة.

## الحمايتان القائمتان الآن

**1. `.env.testing`** — موجود. بدونه كان `--env=testing` يسقط إلى `.env` ويستهدف
البيانات الحيّة. أعدادات `phpunit.xml` لا تحمي: تنطبق عند تشغيل phpunit فقط، لا
على أوامر artisan.

**2. صمّام في `AppServiceProvider`** — يرفض `migrate:fresh` و`db:wipe` و`migrate:reset`
على أي قاعدة لا ينتهي اسمها بـ `_test`. مُثبَت عمليًا:

```
php artisan db:wipe --force          →  WARN  This command is prohibited…
php artisan migrate --env=testing    →  INFO  Nothing to migrate.
```

الصمّام لا يهتم كيف اختيرت الاتصالة، بل بما تشير إليه — وهذا ما يجعله يمسك الحالة
التي أفلتت من `.env.testing`.

## النسخ الاحتياطي — الأمر

Laragon يوفّر `mysqldump`. أنشئ نسخة قبل أي هجرة أو إعادة seed:

```bash
mysqldump -u root amadcraft_b2b > storage/backups/amadcraft_b2b_$(date +%F_%H%M).sql
```

على PowerShell:

```powershell
$stamp = Get-Date -Format 'yyyy-MM-dd_HHmm'
mysqldump -u root amadcraft_b2b | Out-File "storage/backups/amadcraft_b2b_$stamp.sql" -Encoding utf8
```

## الجدولة اليومية

أبسط ما يناسب البيئة: مهمة في **Task Scheduler** على ويندوز تشغّل الأمر أعلاه
يوميًا. البديل `spatie/laravel-backup` — لم أثبّته لأنه يضيف اعتمادية وإعدادات
بريد وتخزين لمشكلة يحلّها سطر واحد هنا، والقاعدة في `CLAUDE.md` ألا تُضاف مكتبة
إلا لضرورة.

**ما يجب أن يُنسخ ولا يُولّده أي seeder:**

| الجدول | لماذا |
|---|---|
| `leads` | **الأهم** — هو المقياس الوحيد للموقع (§1). لا يمكن توليده |
| `crm_sync_logs` | سجل ما وصل الـ CRM وما فشل |
| `users` | حسابات اللوحة |
| `media` + `storage/app/public` | ما رفعه العميل من اللوحة |
| `activity_log` | سجل من غيّر ماذا |

بقية الجداول (الصفحات، الأقسام، الإعدادات، القوائم) يعيد الـ seeders بناءها.

## الاستعادة

```bash
mysql -u root amadcraft_b2b < storage/backups/amadcraft_b2b_2026-08-12_1100.sql
php artisan cache:clear
php artisan permission:cache-reset
```

`permission:cache-reset` ليس اختياريًا: بعد استعادة الجداول تبقى ذاكرة صلاحيات
Spatie قديمة، فيفشل `RolesSeeder` بـ `PermissionDoesNotExist` — وهذا ما حدث فعلًا
أثناء الاستعادة، فأوقف `StructureSeeder` قبل أن يعمل، فأنشأ seeder آخر صفوف
الإعدادات بأعلام خاطئة، فاختفى زر «لنبدأ معًا» من كل الصفحات. سلسلة كاملة سببها
سطر واحد لم يُنفَّذ.

## إعادة البناء من الصفر

على قاعدة الاختبار فقط:

```bash
php artisan migrate:fresh --seed --env=testing
```

وترتيب الـ seeders يهمّ: `RolesSeeder` قبل `StructureSeeder` قبل `DemoContentSeeder`.
