# النسخ الاحتياطي والاستعادة

كُتب هذا الملف بعد حادثة حقيقية: أمر `php artisan migrate:fresh --env=testing`
مسح قاعدة بيانات التطوير `amadcraft_b2b` كاملة. استُعيد كل المحتوى من الـ seeders،
لكن **135 عميلًا محتملًا (leads) ضاعوا نهائيًا** لأن لا نسخة احتياطية كانت موجودة.

§15.3 يطلب «نسخًا احتياطية مجدولة مع اختبار استعادة موثّق». هذا الملف هو ذلك
التوثيق، ومخرجاته أدناه من تشغيل فعلي بتاريخ 2026-08-12، لا وصف لما ينبغي أن يحدث.

---

## الحمايتان القائمتان من قبل

**1. `.env.testing`** — موجود. بدونه كان `--env=testing` يسقط إلى `.env` ويستهدف
البيانات الحيّة. إعدادات `phpunit.xml` لا تحمي: تنطبق عند تشغيل phpunit فقط، لا
على أوامر artisan.

**2. صمّام في `AppServiceProvider`** — يرفض `migrate:fresh` و`db:wipe` و`migrate:reset`
على أي قاعدة لا ينتهي اسمها بـ `_test`. مُثبَت عمليًا:

```
php artisan db:wipe --force          →  WARN  This command is prohibited…
php artisan migrate --env=testing    →  INFO  Nothing to migrate.
```

الصمّام لا يهتم كيف اختيرت الاتصالة، بل بما تشير إليه — وهذا ما يجعله يمسك الحالة
التي أفلتت من `.env.testing`.

---

## ما بُني في هذه الجلسة

| الملف | وظيفته |
|---|---|
| `scripts/backup-db.ps1` | نسخة واحدة بـ `mysqldump`، خارج المشروع، مع تدوير |
| `scripts/install-backup-task.ps1` | يسجّل مهمة يومية في Task Scheduler |
| `scripts/restore-db.ps1` | يستعيد نسخة ويُثبت أمانتها — وهو نفسه اختبار الاستعادة |

الثلاثة في المستودع لا في إعدادات الجهاز، لأنها جزء من التسليم (§19): يجب أن يقدر
أمد الحرف على إقامة هذا كله على خادمهم دون الرجوع لأحد.

### القرارات الثلاثة المضمَّنة في السكربت

**الوجهة خارج شجرة المشروع** — `C:\Backups\amadcraft-b2b`. نسخة داخل `storage/`
تموت مع المجلد الذي يُفترض أن تحميه، وتموت مع الـ checkout الفاشل ومع الحذف
الخاطئ. الحادثة التي بدأ منها كل هذا كانت أمرًا داخل المشروع.

**بيانات الدخول تُقرأ من `.env` وقت التشغيل** وتُمرَّر عبر ملف `defaults-file`
مؤقت يُحذف في `finally`، لا على سطر الأوامر — سطر الأوامر يقرأه أي عملية على
الجهاز (§22.9).

**`--result-file` لا أنبوب PowerShell.** توجيه مخرجات `mysqldump` عبر `>` أو
`Out-File` يعيد ترميز التدفق، فتُستعاد نسخة UTF-16 أو ذات BOM كنص مشوّه — وتكتشف
ذلك في اليوم الذي تحتاجها فيه. (الأمر المكتوب في النسخة السابقة من هذا الملف كان
يفعل ذلك بالضبط؛ استُبدل.)

---

## الجدولة اليومية — ومصيدة الإعدادات الافتراضية

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\install-backup-task.ps1
```

المهمة: `AmadCraft B2B - daily DB backup` — يوميًا 03:00.

`schtasks /Create` بإعداداته الافتراضية ينتج مهمة بهذه الخصائص:

```
DisallowStartIfOnBatteries : True
StopIfGoingOnBatteries     : True
StartWhenAvailable         : False
```

**على جهاز محمول هذا يعني أن النسخة لا تؤخذ إطلاقًا على البطارية، وأن جهازًا كان
نائمًا في الثالثة فجرًا لا يعوّض النسخة أبدًا.** والمهمة لا تُبلّغ عن خطأ — ببساطة
لا تعمل. وهذا ليس فشلًا أهون من عدم وجود نسخة، بل هو نفسه مع طبقة طمأنينة كاذبة.

ورأيته يقع فعلًا: أول تسجيل للمهمة عبر `schtasks` بقي في حالة `Queued` ولم يكتب
سطرًا واحدًا في السجل. لذلك يسجّل `install-backup-task.ps1` المهمة عبر
`Register-ScheduledTask` مع تصحيح الثلاثة:

```
DisallowStartIfOnBatteries : False
StopIfGoingOnBatteries     : False
StartWhenAvailable         : True
```

**إثبات التشغيل الفعلي عبر المجدول** (لا عبر سطر الأوامر يدويًا):

```
LastRunTime    : 8/12/2026 1:00:48 PM
LastTaskResult : 0

2026-08-12 13:00:49  backup start  db=amadcraft_b2b  ->  …\amadcraft_b2b_2026-08-12_130049.sql
2026-08-12 13:00:51  backup ok     267,951 bytes
2026-08-12 13:00:51  rotated out   amadcraft_b2b_2026-08-12_125749.sql
2026-08-12 13:00:51  retained      7 of 7 allowed
```

---

## التدوير — سبع نسخ، مُثبَت

يحتفظ السكربت بأحدث سبع نسخ ويحذف ما قبلها. الترتيب بالاسم لا بتاريخ التعديل،
لأن الطابع الزمني داخل الاسم فلا يفسده لمس الملف.

شُغّل تسع مرات متتالية للتحقق:

```
2026-08-12 12:58:03  retained      7 of 7 allowed
2026-08-12 12:58:07  rotated out   amadcraft_b2b_2026-08-12_124645.sql
2026-08-12 12:58:07  retained      7 of 7 allowed
2026-08-12 12:58:10  rotated out   amadcraft_b2b_2026-08-12_124732.sql
2026-08-12 12:58:10  retained      7 of 7 allowed
```

العدد يثبت عند 7، والأقدم يخرج واحدًا واحدًا. ويرفض السكربت نسخة أصغر من 1KB
باعتبارها فشلًا — `mysqldump` قد يخرج بصفر وقد كتب ملفًا فارغًا.

---

## اختبار الاستعادة — تنفيذ فعلي

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\restore-db.ps1
```

بلا وسائط: يأخذ أحدث نسخة، ويستعيدها في `amadcraft_b2b_test`، ويقارن.

**الصمّام:** يرفض أي قاعدة لا ينتهي اسمها بـ `_test` ما لم تُمرَّر `-Force`. مُثبَت:

```
> restore-db.ps1 -Database amadcraft_b2b
Refusing to restore over 'amadcraft_b2b': the name does not end in _test.
Pass -Force if you mean it.
exit=1
```

هذا تكرار متعمّد لصمّام `AppServiceProvider`. الحادثة كانت أمرًا مدمّرًا صُوّب إلى
القاعدة الخطأ، وصمّام واحد في مكان واحد صمّام ناقص.

**والقاعدة تُحذف وتُنشأ من جديد قبل الاستعادة**، لا يُستعاد فوقها: جدول باقٍ لا
تذكره النسخة كان سيبقى ويجعل الاستعادة تبدو أغنى مما هي عليه فعلًا.

### المخرجات — 2026-08-12

```
restoring C:\Backups\amadcraft-b2b\amadcraft_b2b_2026-08-12_130049.sql
      into amadcraft_b2b_test

restored. verifying:

tables restored: 56
  ok        activity_log                     38 rows
  ok        cache                            13 rows
  ok        campaign_translations            2 rows
  ok        campaigns                        1 rows
  ok        crm_sync_logs                    0 rows
  ok        impact_metric_translations       12 rows
  ok        impact_metrics                   6 rows
  ok        lead_field_translations          14 rows
  ok        lead_fields                      7 rows
  ok        leads                            0 rows
  ok        media                            19 rows
  ok        media_translations               38 rows
  ok        migrations                       24 rows
  ok        model_has_roles                  1 rows
  ok        navigation_item_translations     38 rows
  ok        navigation_items                 19 rows
  ok        navigations                      4 rows
  ok        page_translations                20 rows
  ok        pages                            10 rows
  ok        partner_translations             10 rows
  ok        partners                         5 rows
  ok        permissions                      28 rows
  ok        redirects                        8 rows
  ok        report_translations              4 rows
  ok        reports                          2 rows
  ok        role_has_permissions             28 rows
  ok        roles                            4 rows
  ok        section_translations             186 rows
  ok        sections                         93 rows
  ok        sector_translations              8 rows
  ok        sectors                          4 rows
  ok        sessions                         40 rows
  ok        settings                         55 rows
  ok        solution_translations            8 rows
  ok        solutions                        4 rows
  ok        stories                          3 rows
  ok        story_translations               6 rows
  ok        training_program_translations    6 rows
  ok        training_programs                3 rows
  ok        users                            1 rows

compared 56 tables against amadcraft_b2b: all row counts match

arabic md5 source  : c223b6b4278753619d1fa12530977e93
arabic md5 restored: c223b6b4278753619d1fa12530977e93
arabic text identical (section_translations.heading + body, locale=ar)
```

**`leads` و`crm_sync_logs` تُطبعان حتى بصفر صف** — عمدًا. `leads` هو مقياس الموقع
الوحيد (§1) والجدول الذي أتلفته الحادثة، فوجوده في المخرجات شرط لقراءتها. وصفر
اليوم حقيقة يجب أن تُرى، لا أن تختفي بين الجداول الممتلئة.

**وفحص المطابقة العربية ليس زينة.** عدّ الصفوف لا يرى النص المشوّه: نسخة أُعيد
ترميزها تُستعاد بالعدد الصحيح من الصفوف مملوءة بعلامات استفهام، ويمرّ فحص العدد
بنجاح. لذلك يُحسب MD5 لنص عربي حقيقي على الطرفين. هذا هو الفحص الذي كان سيمسك
خطأ الترميز الذي وُجد `--result-file` لتفاديه.

---

## ما لا يُغطّى بعد — بصراحة

**1. الوسائط غير مشمولة.** السكربت ينسخ قاعدة البيانات وحدها. ما رفعه العميل في
`storage/app/public` لا يعيده أي seeder ولا تحويه هذه النسخ. جدول `media` يُستعاد
بسجلاته فتشير إلى ملفات غير موجودة. **يجب أن يُغطّى قبل الإطلاق.**

**2. النسخ على القرص `C:` نفسه.** هي تحمي من الحادثة التي وقعت (أمر مدمّر)، ولا
تحمي من عطل قرص أو ضياع الجهاز. النسخة الحقيقية هي نسخة خارج الجهاز.

**3. لا تنبيه عند الفشل.** الفشل يُكتب في `C:\Backups\amadcraft-b2b\backup.log`
ويعود برمز خروج ≠ 0، ولا أحد يقرأه. مهمة فاشلة لسبعة أيام تبدو كمهمة ناجحة.

الثلاثة مقترحات لا أنفّذها الآن (§22.2: يُقترح ولا يُبنى). وأثبّت هنا أنني **لم
أثبّت `spatie/laravel-backup`**: يضيف اعتمادية وإعدادات بريد وتخزين لمشكلة يحلّها
سطر `mysqldump` واحد، والقاعدة في `CLAUDE.md` ألا تُضاف مكتبة إلا لضرورة. لكن إن
قررتم تغطية البنود الثلاثة أعلاه (وسائط + وجهة خارجية + تنبيه)، فالمكتبة تصبح
مبرَّرة وأوصي بها عندئذ.

---

## ما يجب أن يُنسخ ولا يُولّده أي seeder

| الجدول | لماذا |
|---|---|
| `leads` | **الأهم** — هو المقياس الوحيد للموقع (§1). لا يمكن توليده |
| `crm_sync_logs` | سجل ما وصل الـ CRM وما فشل |
| `users` | حسابات اللوحة |
| `media` + `storage/app/public` | ما رفعه العميل من اللوحة — والملفات **غير** مشمولة، انظر أعلاه |
| `activity_log` | سجل من غيّر ماذا |

بقية الجداول (الصفحات، الأقسام، الإعدادات، القوائم) يعيد الـ seeders بناءها.

---

## الاستعادة اليدوية إلى قاعدة حيّة

```bash
mysql -u root amadcraft_b2b < C:/Backups/amadcraft-b2b/amadcraft_b2b_2026-08-12_130049.sql
php artisan cache:clear
php artisan permission:cache-reset
```

`permission:cache-reset` ليس اختياريًا: بعد استعادة الجداول تبقى ذاكرة صلاحيات
Spatie قديمة، فيفشل `RolesSeeder` بـ `PermissionDoesNotExist` — وهذا ما حدث فعلًا
أثناء الاستعادة، فأوقف `StructureSeeder` قبل أن يعمل، فأنشأ seeder آخر صفوف
الإعدادات بأعلام خاطئة، فاختفى زر «لنبدأ معًا» من كل الصفحات. سلسلة كاملة سببها
سطر واحد لم يُنفَّذ.

> الحلقة الأخيرة من تلك السلسلة أُغلقت الآن: نمط `firstOrCreate` الذي سمح للعلم
> الخاطئ بالبقاء استُبدل في كل مواضعه — انظر `database/seeders/Concerns/SeedsRows.php`
> و`docs/dynamic-audit.md`.

---

## إعادة البناء من الصفر

على قاعدة الاختبار فقط:

```bash
php artisan migrate:fresh --seed --env=testing
```

وترتيب الـ seeders يهمّ: `RolesSeeder` قبل `StructureSeeder` قبل `DemoContentSeeder`.
