# سوقنا — كتيّب التشغيل (Runbook)

إطلاق مغلق: قاعدة PostgreSQL واحدة + Redis + S3/MinIO + worker + scheduler. كل خدمة في `docker-compose.yml` لها `restart: unless-stopped` وhealthcheck.

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
- مجدول: `listings:expire` كل ساعة، `saved-searches:match` كل ساعة، `media:gc` يوميًا.

## الإطلاق المغلق

1. `APP_ENV=production` + مفاتيح حقيقية + `php artisan config:cache route:cache`.
2. `migrate --force` + `db:seed` (الفهرس فقط).
3. admin واحد (Filament) بدور `admin` + 2FA.
4. تحقق من `/ready` = ok و`queue:failed` فارغة قبل فتح الترافيك.
5. مستخدمون مدعوون فقط؛ راقب `audit_logs` و`moderation_actions`.
