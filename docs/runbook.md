# سوقنا — كتيّب التشغيل (Runbook)

إطلاق مغلق: قاعدة PostgreSQL واحدة + Redis + S3/MinIO + worker + scheduler.

- `docker-compose.yml` للتطوير فقط (الكود كـvolume، كلمات مرور محلية، منافذ مكشوفة).
- **الإنتاج**: `docker-compose.prod.yml` + `docker/php/Dockerfile.prod` — الكود والتبعيات داخل الصورة، opcache، `restart: unless-stopped` وhealthcheck لكل خدمة، لا منافذ مكشوفة إلا nginx، الأسرار من `.env.production` (انسخ `.env.production.example`). التشغيل:

```sh
cp .env.production.example .env.production   # املأ CHANGE_ME و APP_KEY
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan db:seed --force   # الفهرس + AdminSeeder
```

TLS يُنهى أمام nginx (Caddy/Traefik/موازن المزوّد).

## الأسرار والإعداد

| المتغير | ملاحظة |
|---|---|
| `APP_KEY` | `php artisan key:generate` — يُولَّد لكل بيئة، لا يُخزَّن في المستودع |
| `APP_ENV=production`, `APP_DEBUG=false` | إلزامي قبل أي نشر |
| `DB_PASSWORD`, `REDIS_PASSWORD` | من مدير أسرار، ليس `sooqna_local` |
| `AWS_*` / MinIO | مفاتيح S3 خاصة بالبيئة |
| `CORS_ALLOWED_ORIGINS` | نطاق الواجهة فقط — لا `*` |
| `MAIL_*` | مزوّد بريد حقيقي للإنتاج (Mailpit للتطوير فقط) |
| `HORIZON_*`, `QUEUE_CONNECTION=redis` | الطوابير عبر Redis |

## النسخ الاحتياطي

- **PostgreSQL**: `pg_dump` مجدول (cron خارجي أو snapshot مُدار)، احتفاظ 14 يومًا + أسبوعي 8 أسابيع. اختبر الاستعادة شهريًا: `pg_restore` على قاعدة مؤقتة.
- **S3/MinIO**: نسخة دورية للحاوية `media` (mc mirror أو lifecycle+replication على S3 مُدار).
- **Redis**: AOF لا يكفي للنسخ — يُعاد بناء الطوابير؛ الفقد المقبول.

## الطوابير

- `failed_jobs` موجودة في قاعدة البيانات؛ راقبها: `php artisan queue:failed` و`queue:retry all`.
- Horizon على `/horizon` (إدارة فقط) — راقب throughput وfailed-per-hour.
- كل job له `tries` افتراضي من `config/queue.php` (retry_after 90s) — أعطال متكررة = تنبيه.

## حدود المعدل (Rate limits)

- عام على `/api/v1`: 120/دقيقة لكل مستخدم أو IP (`throttle:api`).
- كتابة حساسة (إعلان، رسالة، بلاغ، رفع وسائط): 30/دقيقة (`throttle:api-writes`).
- المصادقة: 5/دقيقة تسجيل ودخول، 3/ساعة إعادة تحقق.

## الصحة والمراقبة

- `GET /api/v1/health` — حياة العملية فقط.
- `GET /api/v1/ready` — DB + cache + queue؛ 503 عند التدهور.
- مجدول: `listings:expire` كل ساعة، `saved-searches:match` كل ساعة، `media:gc` يوميًا (وسائط يتيمة > 24 ساعة؛ `--dry-run` للمعاينة).

## الإطلاق المغلق

1. `APP_ENV=production` + مفاتيح حقيقية + `php artisan config:cache route:cache`.
2. `migrate --force` + `db:seed` (الفهرس فقط).
3. admin واحد (Filament): اضبط `ADMIN_EMAIL`/`ADMIN_PASSWORD` ثم `php artisan db:seed --class=AdminSeeder --force`؛ 2FA غير مفعّل بعد في Filament — احصر `/admin` خلف VPN/IP allowlist حتى تفعيله.
4. تحقق من `/ready` = ok و`queue:failed` فارغة قبل فتح الترافيك.
5. مستخدمون مدعوون فقط؛ راقب `audit_logs` و`moderation_actions`.
