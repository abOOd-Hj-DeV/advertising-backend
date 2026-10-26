# Modular Monolith — بنية الوحدات

كل ميزة وحدة مستقلة داخل `app/Modules/<Module>/`:

```
app/Modules/<Module>/
  Http/            # Controllers + Requests + Resources
  Models/          # Eloquent
  Policies/        # التخويل — لا قرار صلاحية خارجها
  Actions/         # حالات استعمال قابلة للاختبار (Transaction per Action)
  Events/          # أحداث النطاق → Outbox
  Jobs/            # معالجة Queue
  Notifications/
```

## الوحدات
| الوحدة | النطاق |
|---|---|
| Identity | تسجيل، جلسات Sanctum، أجهزة، موافقات، حذف حساب، business_profiles |
| Catalog | تصنيفات، خصائص، مواقع سوريا |
| Listings | إعلانات، حالات، مسودات، سجل أحداث الحالة |
| Media | رفع صور، معالجة، variants، رفض |
| Search | بحث/فلاتر، FTS عربي + trgm، موقع |
| Engagement | مفضلة، أبحاث محفوظة، متابعة، آخر مشاهدات |
| Messaging | محادثات، رسائل، قراءة، أرشفة، idempotency |
| Safety | حظر، بلاغات |
| Notifications | إشعارات داخلية + FCM |
| Moderation | قرارات الإشراف، audit_logs، لوحة Filament |
| Platform | idempotency_keys، outbox_events، exports، feature_flags |

## قواعد
- وحدة لا تستدعي داخل وحدة أخرى مباشرة؛ عبر Action أو Event عام.
- كل HTTP داخل `Modules/*/Http`؛ لا نماذج في `app/Models` خارج الوحدات.
- كل Action يفتح Transaction عند الحاجة ويسجل `outbox_events` ضمن نفس المعاملة.
