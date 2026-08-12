# النشر — من مستودع فارغ إلى موقع يعمل

هذا الملف يفترض أن من ينفّذه لم يبنِ المشروع. كل أمر هنا مكتوب ليُنسخ كما هو،
وكل خطوة تذكر **لماذا** — لأن الخطوة التي لا يُفهم سببها هي التي تُنسى في المرة
الثانية.

---

## 1. متطلبات الخادم

| المكوّن | الحد الأدنى | ملاحظة |
|---|---|---|
| PHP | **8.3** | مع `pdo_mysql`, `mbstring`, `gd`, `exif`, `fileinfo`, `zip`, `intl` |
| MySQL | **8.0** | أو MariaDB 10.6+ |
| Node | **20+** | للبناء فقط، لا يعمل وقت التشغيل |
| Composer | 2.x | |
| خادم الويب | Nginx أو Apache | الجذر على `public/` |

**`gd` و`exif` ليسا اختياريين:** مكتبة الوسائط تولّد المصغّرات بهما، وبدونهما
ترفع الصور بنجاح ولا تظهر لها معاينة في اللوحة.

**عمليتان دائمتان** إضافة إلى PHP-FPM:

```
php artisan queue:work --tries=3 --backoff=30    # دفع العملاء إلى CRM + تحويلات الصور
php artisan inertia:start-ssr                     # العرض من الخادم (§7.2 — إلزامي)
```

كلاهما يُدار بـ **supervisor** أو **systemd**. الموقع بدون SSR يعمل، لكنه يقدّم
HTML فارغًا لمحركات البحث — وهو ما يجعله غير قابل للنشر أصلًا (§7.2).

---

## 2. النشر — بالترتيب

```bash
# 1. الكود
git clone <repo> /var/www/amadcraft && cd /var/www/amadcraft
git checkout main

# 2. اعتماديات PHP بلا أدوات التطوير
composer install --no-dev --optimize-autoloader

# 3. البيئة
cp .env.example .env
nano .env          # املأ ما في القسم 3 أدناه
php artisan key:generate

# 4. الأصول (Node مطلوب هنا فقط)
npm ci
npm run build      # يبني حزمة المتصفح وحزمة SSR معًا

# 5. قاعدة البيانات
php artisan migrate --force

# 6. البيانات البنيوية — آمنة في الإنتاج، ولا تكتب أي محتوى تسويقي
php artisan db:seed --force

# 7. حساب المدير — تفاعلي، ولا يُزرع أبدًا بكلمة مرور معروفة
php artisan amad:create-admin

# 8. رابط التخزين، وإلا لم تظهر أي صورة مرفوعة
php artisan storage:link

# 9. صلاحيات Spatie — بعد أي seed أو استعادة
php artisan permission:cache-reset

# 10. الكاش الإنتاجي (بهذا الترتيب)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# 11. تشغيل العمليتين الدائمتين
sudo supervisorctl restart amadcraft-queue amadcraft-ssr
```

### ملاحظتان تُنسيان فتُكلّفان وقتًا

**`php artisan db:seed --force` يشغّل `DatabaseSeeder` وحده**، وهو بنيوي بالكامل:
الأدوار، الصفحات الفارغة، الشرائح الأربع، حقول النموذج، القوائم، التحويلات
والإعدادات. **`DemoContentSeeder` و`DemoExtrasSeeder` لا يعملان في الإنتاج**
(كلاهما يتحقق من `app()->environment('production')` ويخرج).

**`config:cache` يجعل `env()` تعيد `null` خارج ملفات `config/`.** المشروع لا
يستدعي `env()` خارج `config/` أصلًا، لكن لا تُضِف استدعاءً كهذا لاحقًا.

---

## 3. متغيّرات البيئة الإنتاجية

الحد الأدنى الذي يجب تغييره عن `.env.example`:

```env
APP_ENV=production
APP_DEBUG=false                 # الأهم: true يعرض آثار الاستثناءات للزوار
APP_URL=https://amadcraft.sa

DB_DATABASE=…
DB_USERNAME=…
DB_PASSWORD=…

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database            # أو redis إن توفّر

MAIL_MAILER=smtp                # وإلا لم يصل تنبيه أي عميل محتمل
MAIL_HOST=… MAIL_USERNAME=… MAIL_PASSWORD=…
MAIL_FROM_ADDRESS=no-reply@amadcraft.sa

IP_HASH_SALT=<عشوائي طويل>      # يُولَّد مرة ولا يُغيَّر بعدها
CRM_DRIVER=odoo                 # أو zid — القرار في §20.1
PAGE_CACHE_TTL=300
```

**مفاتيح التتبّع (GA4، GTM، Meta Pixel، Clarity) ليست هنا عمدًا** — تُدار من
شاشة الإعدادات في اللوحة (§14.1)، فيغيّرها العميل بلا نشر.

---

## 4. النسخ الاحتياطي على لينكس — من PowerShell إلى cron

`scripts/backup-db.ps1` كُتب لويندوز (Laragon). على خادم لينكس المكافئ سطر واحد
في cron، بنفس القرارات الثلاثة: **خارج مجلد المشروع**، **بلا كلمة مرور على سطر
الأوامر**، و**تدوير سبع نسخ**.

```bash
sudo mkdir -p /var/backups/amadcraft && sudo chown www-data /var/backups/amadcraft
sudo -u www-data nano /usr/local/bin/amadcraft-backup.sh
```

```bash
#!/usr/bin/env bash
# نسخة يومية من قاعدة أمد الحرف، خارج شجرة المشروع، مع تدوير سبع نسخ.
set -euo pipefail

APP=/var/www/amadcraft
DEST=/var/backups/amadcraft
KEEP=7

# بيانات الدخول من .env مباشرة — لا تُكرَّر في مكان ثانٍ فتتناقض عند تغييرها.
DB=$(grep -E '^DB_DATABASE=' "$APP/.env" | cut -d= -f2-)
USER=$(grep -E '^DB_USERNAME=' "$APP/.env" | cut -d= -f2-)
PASS=$(grep -E '^DB_PASSWORD=' "$APP/.env" | cut -d= -f2-)

# ملف إعدادات مؤقت بصلاحية 600: كلمة المرور على سطر الأوامر يقرأها كل من على
# الخادم عبر `ps`. يُحذف في كل الحالات بـ trap.
CNF=$(mktemp); chmod 600 "$CNF"
trap 'rm -f "$CNF"' EXIT
printf '[client]\nuser=%s\npassword=%s\n' "$USER" "$PASS" > "$CNF"

mkdir -p "$DEST"
FILE="$DEST/${DB}_$(date +%F_%H%M%S).sql.gz"

mysqldump --defaults-file="$CNF" --single-transaction --quick \
          --routines --events --triggers --default-character-set=utf8mb4 \
          "$DB" | gzip -9 > "$FILE"

# نسخة أصغر من 1KB فشلٌ صامت: mysqldump قد يخرج بصفر وقد كتب ملفًا فارغًا.
[ "$(stat -c%s "$FILE")" -gt 1024 ] || { echo "backup too small: $FILE" >&2; exit 1; }

# التدوير بالاسم لا بتاريخ التعديل — الطابع الزمني داخل الاسم فلا يفسده لمس الملف.
ls -1 "$DEST/${DB}_"*.sql.gz | sort -r | tail -n +$((KEEP + 1)) | xargs -r rm --

# الوسائط: قاعدة البيانات وحدها لا تكفي — سجلات `media` تشير إلى ملفات.
tar -czf "$DEST/media_$(date +%F).tar.gz" -C "$APP" storage/app/public
ls -1 "$DEST/media_"*.tar.gz | sort -r | tail -n +$((KEEP + 1)) | xargs -r rm --

echo "$(date '+%F %T')  ok  $(basename "$FILE")"
```

```bash
sudo chmod +x /usr/local/bin/amadcraft-backup.sh
sudo -u www-data crontab -e
```

```cron
# 03:00 يوميًا. المخرجات إلى سجل، ورمز خروج ≠ 0 يصل بالبريد إن كان MAILTO مضبوطًا.
MAILTO=ops@amadcraft.sa
0 3 * * * /usr/local/bin/amadcraft-backup.sh >> /var/backups/amadcraft/backup.log 2>&1
```

### ثلاثة فروق عن نسخة ويندوز، مقصودة

1. **`gzip`** — نسخة ويندوز تكتب SQL خامًا لأن القاعدة صغيرة هناك؛ على خادم
   حقيقي الضغط يوفّر أضعاف حجمه.
2. **الوسائط مشمولة** — هذا ما كان ناقصًا في نسخة ويندوز وسُجّل في
   `docs/backup.md` كفجوة. سجلات `media` تُستعاد بلا ملفاتها فتشير إلى العدم.
3. **`MAILTO`** — cron يرسل مخرجات أي تشغيل يفشل. مهمة نسخ تفشل سبعة أيام
   بصمت تبدو كمهمة ناجحة، وهي الفجوة الثالثة المسجّلة في `docs/backup.md`.

### الاستعادة

```bash
gunzip -c /var/backups/amadcraft/amadcraft_2026-08-12_030000.sql.gz \
  | mysql --defaults-file=<(printf '[client]\nuser=%s\npassword=%s\n' "$USER" "$PASS") "$DB"
tar -xzf /var/backups/amadcraft/media_2026-08-12.tar.gz -C /var/www/amadcraft
php artisan cache:clear && php artisan permission:cache-reset
```

`permission:cache-reset` **ليس اختياريًا** — القصة كاملة في `docs/backup.md`.

**واختبروا استعادة واحدة على الأقل قبل الإطلاق.** نسخة لم تُستعد قط ليست نسخة
احتياطية، بل تخمين.

---

## 5. النشر التالي (تحديث)

```bash
cd /var/www/amadcraft
php artisan down --render="errors::503"     # صفحة الصيانة بهوية الموقع
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
sudo supervisorctl restart amadcraft-queue amadcraft-ssr   # SSR يحمل الحزمة عند الإقلاع فقط
php artisan up
```

> **إعادة تشغيل SSR ليست تفصيلًا.** العملية تقرأ `bootstrap/ssr/ssr.js` مرة
> واحدة عند الإقلاع. بناء جديد بلا إعادة تشغيل يعني حزمة متصفح جديدة وHTML
> مخدوم من حزمة قديمة — وقع هذا مرتين أثناء التطوير وأنتج نتائج اختبار مضلّلة.

---

## 6. قائمة التحقق بعد النشر

- [ ] `/ar` و`/en` يستجيبان بـ200، والمحتوى **مرسوم في HTML** لا في `data-page` وحده (`curl -s … | grep -c '<h1'`).
- [ ] `APP_DEBUG=false` — افتح مسارًا غير موجود وتأكد أن الصفحة هي 404 المصمَّمة لا أثر استثناء.
- [ ] HTTPS مفروض + HSTS، والشهادة صالحة (§15.3).
- [ ] أرسل طلبًا تجريبيًا: يظهر في «العملاء المحتملون»، ويصل تنبيه بالبريد، وحالة CRM تتحوّل عن `pending`.
- [ ] `php artisan queue:work` يعمل فعلًا (اقرأ `failed_jobs` بعد ساعة).
- [ ] رفع صورة من اللوحة يظهر فورًا (يعني `storage:link` نجح).
- [ ] النسخة الاحتياطية الأولى موجودة في `/var/backups/amadcraft` صباح اليوم التالي.
- [ ] `sitemap.xml` و`robots.txt` يستجيبان، و`robots` لا يمنع الفهرسة.
- [ ] التحويلات القديمة تعمل (§22.7): افتح رابطًا من `redirects` وتأكد من 301.
- [ ] الفافيكون يظهر في التبويب بعد مسح الكاش.
