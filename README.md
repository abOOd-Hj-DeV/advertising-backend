# سوقنا — Backend

Backend تطبيق الإعلانات المبوّبة للسوق السوري (سوقنا / Sooqna). الواجهة في مستودع [`advertising`](https://github.com/abOOd-Hj-DeV/advertising) (Flutter).

## الحالة
المرحلة 0 — عقود التنفيذ. لا كود تنفيذي للميزات بعد.

## الوثائق (docs/)
| الملف | المحتوى |
|---|---|
| [docs/backend-plan.md](docs/backend-plan.md) | الخطة الكاملة: نطاق MVP، القرارات، التسلسل، المخاطر |
| [docs/erd.md](docs/erd.md) | نموذج البيانات النهائي: 35 جدولًا، الأعمدة، القيود، الفهارس، مخطط Mermaid |
| [docs/api/openapi.yaml](docs/api/openapi.yaml) | عقد API v1 (≈83 endpoint، RFC 9457، Idempotency-Key، If-Match) |
| [docs/threat-model.md](docs/threat-model.md) | نموذج التهديدات والتخفيفات |
| [docs/compliance-checklist.md](docs/compliance-checklist.md) | الالتزام القانوني والثقة للسوق السوري |
| [docs/service-objectives.md](docs/service-objectives.md) | أهداف الخدمة (SLO) ومعايير قبول الإطلاق |
| [docs/identity.md](docs/identity.md) | الاسم المؤقت والاتجاه اللوني والطباعة العربية |

## البنية المخططة
Laravel (PHP 8.2+) + PostgreSQL 16 + Redis + تخزين S3-متوافق + Sanctum + Filament + Horizon. Modular Monolith.

## التسلسل
بعد اعتماد العقود: تأسيس المشروع (Docker/CI/الأساس) ← الهوية والحساب ← الإعلانات والصور ← البحث والتفاعل ← المراسلة ← الإشعارات ← الإشراف والتحقق ← ربط Flutter ← التصليب ← إطلاق تجريبي مغلق.
