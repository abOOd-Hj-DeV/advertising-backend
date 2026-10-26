# خطة Backend لتطبيق الإعلانات المبوّبة — MVP اعتمادي

> وثيقة تخطيط فقط، بلا كود. مبنية على الواجهات والرحلات الموجودة فعلًا في تطبيق Flutter الحالي (فرع `devin/1792264162-offline-marketplace-complete`)، وعلى سوق ألماني مثل المرجع.

---

## 0. الخلاصة التنفيذية

- **الشكل المعماري:** Modular Monolith واحد بـ **Laravel 12 (PHP 8.3)** + **PostgreSQL 16** + **Redis** + **تخزين صور S3-compatible**، مع Queue Workers. لا Microservices في MVP.
- **السوق واللغة:** سوريا، واجهة عربية بالكامل (RTL)، العملة الافتراضية الليرة السورية (SYP) مع دعم USD لكل إعلان. **اسم عمل مؤقت:** «سوقنا» — يُستبدل عند اعتماد الاسم والهوية الجديدة.
- **حجم النظام:** ثلاثة أدوار بدورة عمل كاملة (مستهلك ↔ تاجر ↔ إدارة)، **35 جدولًا تجاريًا** + جداول البنية التحتية في Laravel، وحوالي **83 Endpoint** في 12 وحدة (Module).
- **ترتيب التنفيذ:** العقد والقرارات أولًا ← الأساس والهوية ← الإعلانات والصور ← البحث ← المراسلة ← الإشعارات ← الأمان والإشراف والالتزام القانوني ← ربط Flutter ← التصليب ← الإطلاق التجريبي.
- **التقدير الخام:** 10–13 أسبوع عمل لمهندس Backend خبير واحد للـBackend مع لوحة الإدارة، ويمكن ربط Flutter بالتوازي بدءًا من المرحلة 3. تقدير أولي يتغير بعد حسم القرارات المفتوحة (القسم 15).
- **ملاحظتان قبل الإطلاق العام:** (1) لا يجوز الإطلاق باسم وشعار **kleinanzeigen** — الاسم والألوان الجديدة خطوة لازمة؛ (2) السوق السوري لا يلزمه DSGVO/DSA، لكن تحتاج مراجعة قانونية محلية خفيفة (علامة تجارية، شروط استخدام، خصوصية)، وآليات البلاغ والتوثيق تبقى ميزة ثقة وليست التزامًا أوروبيًا بعد الآن.

---

## 1. المبادئ الحاكمة

1. **السيرفر مصدر الحقيقة:** كل تحقق وصلاحية وانتقال حالة يُنفَّذ في السيرفر؛ تحقق Flutter للتجربة فقط.
2. **العقد قبل التنفيذ (Contract-first):** مواصفة OpenAPI 3.1 تُعتمد قبل كتابة الـEndpoints، ويُختبر التنفيذ ضدها في CI.
3. **حدود واضحة بين الوحدات:** Identity, Catalog, Listings, Media, Search, Engagement, Messaging, Safety, Notifications, Moderation, Compliance, Platform. لا تقرأ وحدة جداول وحدة أخرى مباشرة إلا عبر Service/Event.
4. **كل أثر جانبي غير متزامن وموثوق:** الإشعارات وتنبيهات البحث ومعالجة الصور عبر Outbox + Queue، لا داخل الطلب.
5. **عمليات قابلة للتكرار بأمان (Idempotent):** النشر وإرسال الرسائل ورفع الصور تقبل `Idempotency-Key`، فلا تتكرر عند إعادة المحاولة بعد انقطاع الشبكة.
6. **الخصوصية افتراضيًا:** أقل بيانات ممكنة، موقع تقريبي، إزالة EXIF/GPS من الصور، لا PII في السجلات، استضافة داخل الاتحاد الأوروبي.
7. **لا تحسين مبكر:** PostgreSQL للبحث في MVP، ومحرك بحث منفصل فقط عند قياس حاجة فعلية.

---

## 2. نطاق MVP

### داخل النطاق
| # | المجال | الميزة |
|---|---|---|
| 1 | الهوية | تسجيل بالبريد وكلمة المرور، تأكيد البريد، دخول/خروج، خروج من كل الأجهزة، استعادة كلمة المرور، تغيير كلمة المرور |
| 2 | الملف | اسم العرض، نوع الحساب (خاص أو تجاري)، المدينة الافتراضية، تاريخ الانضمام، ملف عام للبائع |
| 3 | الإعدادات | تفضيلات الإشعارات لكل نوع وقناة، الموافقات (Consent) المسجّلة بإصدارها |
| 4 | التصنيفات | شجرة تصنيفات + خصائص لكل تصنيف (النوع، الحالة، المقاس...) تُدار من لوحة الإدارة |
| 5 | الإعلان | إنشاء (عرض/طلب)، عنوان، وصف، سعر ثابت/VB/مجاني، تصنيف وخصائص، موقع (محافظة/مدينة مع إحداثيات تقريبية اختيارية)، شحن وناقل، حتى 10 صور مع ترتيب وغلاف |
| 6 | المسودات | حفظ واستئناف ومسودة تعديل لإعلان منشور، ثم نشر/حفظ مع تحقق كامل |
| 7 | دورة الحياة | Aktiv / Reserviert / Verkauft / إعادة تفعيل / حذف / انتهاء صلاحية بعد 60 يومًا وتمديد |
| 8 | الاكتشاف | الرئيسية (أحدث + حسب التصنيف)، بحث نصي ألماني، فلاتر (تصنيف، سعر وعملة، محافظة/مدينة + نطاق، خصائص، شحن، عرض/طلب، خاص/تاجر موثّق)، فرز (موصى/أحدث/أرخص/أغلى)، تقسيم صفحات |
| 9 | التفاعل | المفضلة، الأبحاث المحفوظة مع تنبيهات، متابعة البائعين، آخر المشاهدات، عدّاد مشاهدات الإعلان |
| 10 | المراسلة | محادثة لكل (إعلان، مشترٍ)، رسائل نصية، مقروء/غير مقروء، أرشفة، حذف من جهتي، إشعار فوري |
| 11 | الأمان | حظر مستخدم (يمنع المراسلة بالاتجاهين)، الإبلاغ عن إعلان/مستخدم/رسالة |
| 12 | الإشعارات | داخل التطبيق + Push (FCM) + بريد للأحداث المهمة: رسالة جديدة، تطابق بحث محفوظ، انتهاء إعلان، قرار إشراف |
| 13 | الإشراف | لوحة إدارة: البلاغات، حجب/استعادة إعلان، تعليق مستخدم، إدارة التصنيفات، سجل تدقيق |
| 14 | الالتزام | تصدير بيانات المستخدم، حذف الحساب بفترة سماح، سبب قرار الإشراف للمتأثر، مسار بلاغات موثّق بقراراته |
| 15 | التجار | ملف تجاري ببيانات المتجر، مراجعة إدارية، شارة «تاجر موثّق» وبيانات الاتصال تلقائيًا على إعلاناته، إعادة تحقق عند تغيير البيانات الجوهرية |

### خارج MVP (مقصود)
الدفع والشراء المباشر، الشحن الفعلي وملصقاته، الإعلانات المميزة والاشتراكات، التسجيل بالهاتف والتحقق عبر SMS، متاجر التجار المميزة واشتراكاتهم وتحليلات المبيعات، المكالمات والمرفقات في المحادثة، التوصيات الذكية، تعدد اللغات، الترجمة الآلية، مؤشرات الكتابة.

---

## 3. خريطة الواجهة الحالية ← Backend

| ما يفعله Flutter اليوم محليًا (`DemoStore`) | المقابل في Backend |
|---|---|
| `signIn` / `registerLocal` / `signOut` / `updateProfile` | Identity: Sanctum tokens، `/auth/*`، `PATCH /me` |
| `publish` / `editListing` / `setListingStatus` / `deleteListing` | Listings + State machine + Policies |
| `saveDraft` / `deleteDraft` (مع `editingId`) | `listing_drafts` مع `listing_id` اختياري |
| `MediaRepository.importImage` (JPEG/PNG، حدود حجم وأبعاد) | رفع إلى Media + معالجة في Queue + Variants |
| `search` / `changeFilters` / `FilterState` | `GET /listings` بمعاملات فلترة موحدة |
| `toggleFavorite` / `saveSearch` / `toggleSearchAlerts` / `toggleFollow` / `viewListing` | Engagement |
| `ensureConversation` / `sendMessage` / `markRead` / `archiveChat` / `deleteChat` | Messaging |
| `setChatDraft` | يبقى محليًا على الجهاز (لا داعي لمزامنته في MVP) |
| `toggleBlock` / `report` | Safety + Moderation |
| `LocalNotice` / `readNotice` / `dismissNotice` / `setNotifications` | Notifications + Preferences |
| `setTheme` / سجل البحث | يبقى محليًا |
| `resetLocalData` | يبقى لوضع Demo فقط؛ المقابل الحقيقي هو حذف الحساب |
| التاجر: تقديم ملف تجاري ومراجعة إدارية | جديد في الواجهة: نموذج بيانات تجارية + حالة تحقق + شارة «تاجر موثّق»؛ لا يوجد مقابل محلي اليوم |

---

## 4. القرارات المعمارية (ADR مختصرة)

| القرار | الاختيار | السبب | البديل المؤجل |
|---|---|---|---|
| اللغة والإطار | Laravel 12 | نضج، Queues/Policies/Notifications/Validation جاهزة، يناسب خبرتك السابقة | — |
| قاعدة البيانات | PostgreSQL 16 | FTS ألماني، `pg_trgm`، JSONB، قيود قوية، PITR | — |
| البحث الجغرافي | محافظة/مدينة من قائمة سورية منسّقة + lat/lng اختياري مع `earthdistance`/`cube` | لا يوجد نظام رموز بريدية معتمد في سوريا | PostGIS عند الحاجة لمضلعات |
| البحث النصي | `tsvector` (arabic) + `pg_trgm` للأخطاء الإملائية والتطبيع (همزات/تاء مربوطة) | بلا خدمة إضافية؛ stemming العربي ضعيف في Postgres فنعوّض بالتطبيع وtrgm | Meilisearch/OpenSearch عند تجاوز ~500 ألف إعلان أو بطء مقاس |
| المصادقة | Laravel Sanctum: Personal Access Tokens للموبايل، Cookie SPA للويب | بسيط وآمن، إبطال فوري | OAuth/Social login لاحقًا |
| الكاش والطوابير وحدود المعدل | Redis + Laravel Horizon | مراقبة الطوابير وإعادة المحاولة | — |
| الصور | S3-compatible (MinIO محليًا) + CDN + معالجة Intervention/libvips في Queue | فصل الملفات عن السيرفر | — |
| الزمن الحقيقي | MVP: Push + Polling خفيف عند فتح المحادثة؛ المرحلة اللاحقة: Laravel Reverb | يقلل نقاط الفشل في البداية | WebSockets كاملة |
| Push | Firebase Cloud Messaging (Android/Web) | معيار | APNs عند iOS |
| البريد | مزود معاملات داخل EU (Postmark/Brevo/SES Frankfurt) | تسليم موثوق، SPF/DKIM/DMARC | — |
| لوحة الإدارة | Filament 3 | سرعة بناء CRUD آمن داخل نفس التطبيق | — |
| المعرّفات | ULID للموارد العامة | لا يكشف العدد ولا يسهل التخمين، قابل للترتيب | — |
| اللغة والاتجاه | واجهة عربية RTL، `Accept-Language: ar`، رسائل أخطاء وإشعارات عربية | السوق سوريا | تعدد اللغات مؤجل |
| العملة | SYP افتراضي وUSD اختياري لكل إعلان (`*_cents` + `currency`) | واقع السوق السوري | عملات إضافية لاحقًا |
| الاستضافة | بداية EU (مثل Hetzner/DigitalOcean Frankfurt) + Postgres مُدار بـPITR | موثوقية وتكلفة؛ زمن الاستجابة لسوريا مقبول ونقيم منطقة أقرب لاحقًا | منطقة الخليج (AWS Bahrain) عند قياس حاجة |

---

## 5. نموذج البيانات

اصطلاحات عامة: `id` من نوع ULID، `created_at`/`updated_at` بتوقيت UTC، المبالغ بالسنت `*_cents` (integer) مع `currency` افتراضيًا `'SYP'` وقيمة بديلة `'USD'`، الحذف الناعم `deleted_at` حيث يلزم الاحتفاظ لأسباب أمان/قانون، وكل FK مع `ON DELETE` محدد صراحة.

### 5.1 Identity & Account — 8 جداول
| الجدول | أهم الأعمدة | قيود وفهارس |
|---|---|---|
| `users` | email (citext)، password (argon2id)، display_name، account_type (`private`/`commercial`)، role (`user`/`moderator`/`admin`)، status (`active`/`suspended`/`pending_deletion`/`deleted`)، email_verified_at، default_location_id، locale (`ar`)، last_login_at، deleted_at | unique(email) حيث غير محذوف |
| `personal_access_tokens` | tokenable، name (اسم الجهاز)، abilities، last_used_at، expires_at | قياسي Sanctum |
| `password_reset_tokens` | email، token (hash)، created_at | صلاحية 60 دقيقة، استعمال واحد |
| `user_devices` | user_id، platform، fcm_token، app_version، last_seen_at | unique(fcm_token) |
| `user_consents` | user_id، purpose (`terms`/`privacy`/`marketing`/`analytics`)، version، granted، ip_hash، created_at | سجل إلحاقي (append-only) للإثبات |
| `notification_preferences` | user_id، type (`message`/`search_alert`/`listing_expiry`/`moderation`/`marketing`)، channel (`push`/`email`/`in_app`)، enabled | PK(user_id, type, channel) |
| `account_deletion_requests` | user_id، requested_at، scheduled_for (+30 يوم)، cancelled_at، completed_at | واحد نشط لكل مستخدم |
| `business_profiles` | user_id (unique)، legal_name، store_name، address، governorate، city، public_phone، public_email، register_number (سجل تجاري اختياري)، compliance_confirmed، status (`pending`/`verified`/`rejected`/`suspended`)، payload (jsonb)، review_note، verified_by، verified_at | واحد لكل مستخدم؛ تعديل البيانات الجوهرية يعيد `pending`؛ الاحتفاظ: نهاية العلاقة + سنة |

### 5.2 Catalog & Location — 4 جداول
| الجدول | أهم الأعمدة | ملاحظات |
|---|---|---|
| `categories` | parent_id، slug، name_de، icon، sort_order، is_active، allows_shipping، allows_wanted | شجرة بعمقين أو ثلاثة؛ unique(slug) |
| `category_attributes` | category_id، key (`type`/`condition`/`size`...)، label_de، data_type (`enum`/`int`/`decimal`/`bool`/`text`)، is_required، is_filterable، sort_order | الخصائص تُورث من الأب |
| `category_attribute_options` | attribute_id، value، label_de، sort_order | قيم الـenum (مثل Cityräder) |
| `locations` | governorate، city، district، lat، lng | قائمة منسّقة لمحافظات سوريا الأربع عشرة وأهم المدن والأحياء؛ فهرس على governorate+city وtrgm على المدينة، وفهرس GiST جغرافي للإحداثيات الموجودة |

### 5.3 Listings & Media — 6 جداول
| الجدول | أهم الأعمدة | قيود وفهارس |
|---|---|---|
| `listings` | user_id، category_id، offer_type (`offer`/`wanted`)، title (≤70)، description (≤4000)، price_cents (nullable)، price_type (`fixed`/`negotiable`/`free`)، status، location_id (محافظة/مدينة)، lat/lng **مقرّبة ~1 كم** اختيارية، shipping_available، carrier، published_at، expires_at، reserved_at، sold_at، views_count، favorites_count، version (للتعارض)، search_vector (tsvector)، moderation_state، deleted_at | فهارس: (status, published_at desc)، (category_id, status, published_at)، (user_id, status)، price_cents، GIN(search_vector)، GIN trgm(title)، GiST(earth(lat,lng)) |
| `listing_attribute_values` | listing_id، attribute_id، option_id أو value_text/value_number/value_bool | PK(listing_id, attribute_id)؛ فهرس (attribute_id, option_id) للفلترة |
| `media` | owner_id، disk، original_path، mime، bytes، width، height، sha256، status (`uploaded`/`processing`/`ready`/`rejected`)، variants (jsonb: thumb/card/full)، rejected_reason | الصورة يتيمة حتى ترتبط؛ تنظيف بعد 24 ساعة |
| `listing_images` | listing_id، media_id، position، is_cover | unique(listing_id, position)؛ غلاف واحد بقيد جزئي |
| `listing_drafts` | user_id، listing_id (null = إعلان جديد)، payload (jsonb)، media_ids، version، updated_at | unique(user_id, listing_id) للتعديل؛ حد أقصى للمسودات لكل مستخدم |
| `listing_status_events` | listing_id، from_status، to_status، actor_id، actor_type (`owner`/`system`/`moderator`)، reason، created_at | سجل إلحاقي لكل انتقال |

**لماذا المسودات جدول منفصل وليس `status = draft`؟** لأن التطبيق يدعم «مسودة تعديل» لإعلان منشور، ولا يجوز أن تمس المسودة نسخة الإعلان الظاهرة للعامة قبل التأكيد. الـpayload يُتحقق منه بالكامل فقط عند النشر.

### 5.4 Engagement — 5 جداول
| الجدول | أهم الأعمدة | قيود |
|---|---|---|
| `favorites` | user_id، listing_id، created_at | PK(user_id, listing_id) |
| `saved_searches` | user_id، name، query، filters (jsonb بنفس مخطط `GET /listings`)، alerts_enabled، alert_frequency (`instant`/`daily`)، last_alerted_at | حد 50 لكل مستخدم |
| `saved_search_matches` | saved_search_id، listing_id، notified_at | unique(saved_search_id, listing_id) لمنع تكرار التنبيه |
| `follows` | follower_id، seller_id، created_at | PK؛ check(follower_id <> seller_id) |
| `recently_viewed` | user_id، listing_id، viewed_at | PK(user_id, listing_id)؛ Upsert، والاحتفاظ بآخر 30 |

### 5.5 Messaging — 3 جداول
| الجدول | أهم الأعمدة | قيود وفهارس |
|---|---|---|
| `conversations` | listing_id، seller_id، buyer_id، listing_title_snapshot، last_message_id، last_message_at | unique(listing_id, buyer_id)؛ check(seller_id <> buyer_id) |
| `conversation_participants` | conversation_id، user_id، last_read_message_id، archived_at، deleted_at، unread_count | PK(conversation_id, user_id)؛ فهرس (user_id, archived_at, last_message_at) |
| `messages` | conversation_id، sender_id، body (≤2000)، client_message_id، created_at، moderation_state | unique(sender_id, client_message_id) للـIdempotency؛ فهرس (conversation_id, id desc) |

الحذف «من جهتي» يضبط `deleted_at` للمشارك فقط؛ الرسائل نفسها تبقى حسب سياسة الاحتفاظ (قرار مفتوح، مقترح 12 شهرًا) لأغراض البلاغات.

### 5.6 Safety & Moderation — 4 جداول
| الجدول | أهم الأعمدة | ملاحظات |
|---|---|---|
| `user_blocks` | blocker_id، blocked_id، created_at | PK؛ يُفحص عند إنشاء محادثة وإرسال رسالة بالاتجاهين |
| `reports` | reporter_id (nullable لبلاغات الضيوف من غير المسجلين)، target_type (`listing`/`user`/`message`)، target_id، reason (enum)، details، evidence_snapshot (jsonb)، status (`open`/`in_review`/`actioned`/`dismissed`)، assigned_to، resolved_at، resolution_note | snapshot يحفظ المحتوى وقت البلاغ |
| `moderation_actions` | moderator_id، target_type، target_id، action (`block_listing`/`restore_listing`/`suspend_user`/`warn`/`delete_message`)، reason_code، statement_of_reasons، report_id، created_at | يُرسل سبب القرار للمتأثر (سبب القرار) |
| `audit_logs` | actor_id، action، subject_type، subject_id، changes (jsonb)، ip_hash، created_at | إلحاقي؛ لكل أفعال الإدارة وتغييرات الحساب الحساسة |

### 5.7 Notifications — جدول واحد
| `notifications` | id (uuid)، notifiable، type، data (jsonb: listing_id/thread_id)، read_at، created_at | جدول Laravel القياسي؛ فهرس (notifiable_id, read_at, created_at) |
|---|---|---|

### 5.8 Platform & Compliance — 4 جداول + جداول Laravel
| الجدول | الغرض |
|---|---|
| `idempotency_keys` | key، user_id، endpoint، request_hash، response_status، response_body، expires_at (24 ساعة) |
| `outbox_events` | event_type، aggregate_id، payload، available_at، processed_at، attempts — يُكتب في نفس Transaction الحدث، وWorker ينشره |
| `data_exports` | user_id، status، file_path، expires_at — تصدير بيانات المستخدم كملف ZIP مؤقت |
| `feature_flags` | key، enabled، rollout — لتفعيل Reverb أو التنبيهات تدريجيًا |
| قياسية | `jobs`، `failed_jobs`، `job_batches`، `migrations` (الكاش والجلسات في Redis) |

**المجموع:** 35 جدولًا مُعرَّفًا أعلاه + 4 قياسية.

---

## 6. قواعد العمل وآلات الحالة

### 6.1 حالة الإعلان
```
            ┌────────────── reactivate ───────────────┐
            ▼                                         │
publish ─► active ──reserve──► reserved ──sell──► sold ┘
            │  ▲                  │
            │  └──── unreserve ───┘
            ├── expire (60 يوم) ─► expired ──extend──► active
            ├── delete (المالك) ─► deleted (soft)
            └── block (مشرف) ────► blocked ──restore──► active
```
- الانتقالات المسموحة مُعرّفة في مكان واحد (State machine)، وكل انتقال يُسجل في `listing_status_events`.
- `sold`/`reserved`: يبقى ظاهرًا مع شارة؛ لا يظهر في البحث الافتراضي إلا بفلتر.
- `deleted`/`blocked`: صفحة التفاصيل تعيد `410 Gone` بنفس شكل «Anzeige nicht verfügbar» في التطبيق.
- التعديل يتطلب `version` الحالية؛ عدم التطابق ← `409 Conflict` (منع الكتابة فوق تعديل من جهاز آخر).
- تعديل العنوان/الوصف/الصور بعد النشر يعيد فحص المحتوى الآلي.

### 6.2 المراسلة
- لا محادثة مع إعلانك، ولا مع إعلان `deleted`/`blocked`/`expired`، ولا عند وجود حظر بأي اتجاه.
- المحادثة الموجودة على إعلان صار `sold` تبقى قابلة للقراءة، والإرسال مسموح لمدة محددة (قرار مفتوح).
- حد معدل: مثلًا 30 رسالة/دقيقة و5 محادثات جديدة/ساعة للحسابات الجديدة؛ يُشدد للحسابات دون تأكيد بريد.
- الرسالة الأولى تحتاج بريدًا مؤكدًا.

### 6.3 التنبيهات المحفوظة
- عند `listing.published` يُكتب Outbox event ← Job يطابق الأبحاث المحفوظة المرشحة (حسب التصنيف والمنطقة أولًا ثم الشرط الكامل) ← `saved_search_matches` بقيد التفرد ← إشعار فوري أو ملخص يومي حسب التفضيل.
- لا تنبيه لصاحب الإعلان نفسه، ولا من بائع محظور.

### 6.4 حذف الحساب
طلب ← فترة سماح 30 يومًا (إلغاء بالدخول) ← إخفاء الإعلانات فورًا ← بعد المهلة: إخفاء الهوية (anonymize) في الرسائل، حذف الصور والأجهزة والتوكنات والمفضلات، الإبقاء على سجلات الإشراف وبيانات التاجر المطلوبة للاحتفاظ فقط.

### 6.5 دورة العمل للأدوار الثلاثة

**المستهلك (حساب خاص):**
تسجيل ← تأكيد البريد ← يبيع ويشتري: نشر إعلانات ومسودات وحالات، بحث ومفضلة وأبحاث محفوظة ومتابعة، مراسلة البائعين، حظر وبلاغات، تصدير بياناته وحذف حسابه. نفس حدود المعدل والمحتوى على الجميع.

**التاجر (حساب تجاري):**
1. يسجّل ويختار «حساب تجاري» ← يقدّم `business_profiles` (اسم المتجر، بيانات اتصال علنية، المحافظة والمدينة، سجل تجاري اختياري) ← `pending`.
2. يستخدم التطبيق فورًا بكامل الميزات (نشر ومراسلة) لكن بلا شارة؛ القرار المعتمد: عدم حجب النشر في سوق جديد (القسم 15).
3. الإدارة تراجع الطلب ← `verified` أو `rejected` مع سبب وإشعار للتاجر.
4. بعد التحقق: كل إعلاناته تُوسم تلقائيًا `seller_type = commercial` وتظهر شارة «تاجر موثّق» وبيانات الاتصال على صفحة البائع والإعلان.
5. تعديل بيانات جوهرية في الملف التجاري ← `pending` مجددًا حتى إعادة المراجعة.
6. يخضع لنفس دورة الإشراف؛ تعليق حسابه يخفي إعلاناته.

**الإدارة (moderator / admin):**
- طابور بلاغات المنصة: فحص أول خلال SLA مقترح 24–48 ساعة ← قرار (حجب/استعادة/تجاهل/تحذير) مع `statement_of_reasons` ← إشعار المبلّغ والمتأثر.
- طابور تحقق التجار: مراجعة البيانات والمستندات ← توثيق القرار.
- المستخدمون: تعليق/استعادة مع سبب مسجل.
- التصنيفات والخصائص وخياراتها.
- الحوكمة على الإدارة نفسها: جلسة منفصلة + 2FA إلزامي، moderator ≠ admin (admin فقط يدير المشرفين والتصنيفات والإعدادات)، كل فعل في `audit_logs`.

**مصفوفة الصلاحيات (مختصرة):**
| الفعل | ضيف | مستهلك | تاجر pending | تاجر موثّق | مشرف | إدارة |
|---|---|---|---|---|---|---|
| تصفح وبحث | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| مراسلة/مفضلة/بلاغ | — | ✓ | ✓ | ✓ | ✓ | ✓ |
| نشر إعلان | — | ✓ | ✓ (بدون شارة) | ✓ | ✓ | ✓ |
| شارة تجارية | — | — | — | تلقائي | — | — |
| قرارات الإشراف/التحقق | — | — | — | — | ✓ | ✓ |
| إدارة المشرفين/التصنيفات | — | — | — | — | — | ✓ |

---

## 7. عقد الـAPI

### 7.1 اصطلاحات
- المسار `/api/v1`، JSON، `Accept-Language: ar`، وكل نصوص الأخطاء والإشعارات بالعربية.
- ترقيم بالمؤشر (Cursor) لكل القوائم، حد افتراضي 20 وأقصى 50.
- الأخطاء بصيغة **RFC 9457 Problem Details** مع `errors` للحقول، ورموز ثابتة (`listing.not_owner`، `chat.blocked`...) يترجمها Flutter.
- `Idempotency-Key` إلزامي على: إنشاء إعلان، نشر مسودة، إنشاء محادثة، إرسال رسالة، رفع صورة.
- `If-Match: <version>` على تعديل الإعلان والمسودة.
- `429` مع `Retry-After` لحدود المعدل.
- التواريخ ISO-8601 UTC، والمبالغ بالسنت.

### 7.2 قائمة الـEndpoints (≈83)
| الوحدة | Endpoints |
|---|---|
| Auth (9) | `POST /auth/register`، `POST /auth/login`، `POST /auth/logout`، `POST /auth/logout-all`، `POST /auth/email/verify`، `POST /auth/email/resend`، `POST /auth/password/forgot`، `POST /auth/password/reset`، `PUT /auth/password` |
| Me (10) | `GET/PATCH /me`، `GET/PUT /me/notification-preferences`، `GET/POST /me/consents`، `POST/DELETE /me/devices`، `POST /me/deletion`، `DELETE /me/deletion`، `POST /me/exports`، `GET /me/exports/{id}` |
| Merchant (3) | `GET /me/business`، `PUT /me/business` (تقديم أو تحديث ← يعيد `pending`)، `GET /me/business/status` |
| Catalog (4) | `GET /categories`، `GET /categories/{id}`، `GET /categories/{id}/attributes`، `GET /locations?q=` |
| Listings (11) | `GET /listings` (بحث وفلاتر)، `GET /listings/{id}`، `POST /listings`، `PATCH /listings/{id}`، `DELETE /listings/{id}`، `POST /listings/{id}/reserve`، `/unreserve`، `/sell`، `/reactivate`، `/extend`، `GET /listings/{id}/similar` |
| My listings (2) | `GET /me/listings?status=`، `GET /me/listings/stats` |
| Drafts (6) | `GET /drafts`، `POST /drafts`، `GET/PUT/DELETE /drafts/{id}`، `POST /drafts/{id}/publish` |
| Media (3) | `POST /media` (multipart)، `GET /media/{id}` (الحالة)، `DELETE /media/{id}` |
| Sellers (3) | `GET /users/{id}` (يتضمن الشارة التجارية وبيانات الاتصال القانونية عند `verified`)، `GET /users/{id}/listings`، `GET /users/{id}/stats` |
| Engagement (13) | `GET /favorites`، `PUT/DELETE /favorites/{listingId}`، `GET/POST /saved-searches`، `PATCH/DELETE /saved-searches/{id}`، `GET /follows`، `PUT/DELETE /follows/{userId}`، `GET /recently-viewed`، `DELETE /recently-viewed` |
| Messaging (8) | `GET /conversations?archived=`، `POST /conversations`، `GET /conversations/{id}`، `GET /conversations/{id}/messages?before=&after=`، `POST /conversations/{id}/messages`، `POST /conversations/{id}/read`، `PATCH /conversations/{id}` (أرشفة)، `DELETE /conversations/{id}` |
| Safety (4) | `GET /blocks`، `PUT/DELETE /blocks/{userId}`، `POST /reports` |
| Notifications (5) | `GET /notifications`، `GET /notifications/unread-count`، `POST /notifications/{id}/read`، `POST /notifications/read-all`، `DELETE /notifications/{id}` |
| Ops (2) | `GET /health` (حيّ)، `GET /ready` (قاعدة البيانات/Redis/التخزين) |
| Admin | عبر Filament (جلسة منفصلة + 2FA): البلاغات، تحقق التجار، الإعلانات، المستخدمون، التصنيفات والخصائص، سجل التدقيق، إعادة تشغيل المهام الفاشلة |

### 7.3 معاملات البحث — مطابقة لـ`FilterState` الحالية
`q`، `category`، `attributes[type]=…`، `price_min`، `price_max`، `currency` (`SYP`/`USD`)، `governorate` و`city` أو `lat`+`lng`، `radius_km` (1–100)، `shipping` (`any`/`only`)، `carrier`، `seller_type` (`private`/`commercial`)، `offer_type`، `sort` (`recommended`/`newest`/`price_asc`/`price_desc`)، `cursor`. نفس الكائن يُحفظ في `saved_searches.filters` حتى لا يختلف البحث عن التنبيه.

---

## 8. الأمان والخصوصية

| المجال | الإجراء |
|---|---|
| الصلاحيات | Policy لكل مورد (Listing/Draft/Conversation/Message/Media)؛ ممنوع الاعتماد على إخفاء ID. اختبار IDOR لكل Endpoint يملك مالكًا |
| كلمات المرور | argon2id، حد أدنى 10 أحرف، فحص مقابل كلمات مسربة (k-anonymity)، إبطال كل التوكنات عند تغييرها |
| التوكنات | انتهاء صلاحية (مثلًا 90 يومًا بلا استخدام)، اسم جهاز، شاشة «الأجهزة» وخروج منها |
| حدود المعدل | Redis: الدخول (5/دقيقة لكل IP+بريد)، التسجيل، الاستعادة، الرسائل، إنشاء الإعلانات، الرفع، البلاغات |
| الصور | فحص النوع الحقيقي (magic bytes)، حجم ≤ 8 MB قبل المعالجة، ≤ 25 MP، **إعادة ترميز** وإزالة EXIF/GPS، توليد أحجام، رفض الصور التالفة، عدم تقديم الأصل للعامة |
| الموقع | تخزين وعرض موقع مقرّب للإعلان؛ لا عنوان دقيق |
| المحتوى | تعقيم النصوص (plain text فقط)، كشف روابط وأرقام هواتف وبريد في الإعلانات والرسائل الأولى (وسم للمراجعة لا منع تلقائي)، قائمة كلمات احتيال شائعة بالعربية |
| مكافحة الإساءة | Captcha غير مزعج (Turnstile/hCaptcha) على التسجيل والاستعادة للويب، حدود أقوى للحسابات الجديدة |
| الإدارة | مسار وجلسة منفصلة، 2FA إلزامي، أقل صلاحيات (moderator ≠ admin)، كل فعل في `audit_logs` |
| النقل والتخزين | HTTPS فقط + HSTS، CORS لأصل الويب فقط، تشفير النسخ الاحتياطية، أسرار في مدير أسرار لا في المستودع |
| السجلات | لا كلمات مرور/توكنات/نصوص رسائل في السجلات؛ إخفاء البريد وIP (hash) |
| المرجع | قائمة تحقق OWASP ASVS المستوى 2 للأجزاء المعنية + OWASP API Top 10 |

### الالتزام القانوني (سوريا) — يحتاج مراجعة محامٍ محلي خفيفة
- القوانين الأوروبية (DSGVO/DSA/TDDDG/Impressum) **لا تنطبق** على سوق سوريا، فتسقط كمتطلبات إطلاق؛ مع ذلك نُبقي آلياتها كميزات ثقة: سياسة خصوصية وشروط استخدام، بلاغات موثّقة، سبب قرار الإشراف، تصدير البيانات وحذف الحساب.
- **التحقق التجاري** يصبح ميزة ثقة داخلية بحقول أخف (لا تتبّع تاجر أوروبي): مراجعة إدارية وشارة «تاجر موثّق».
- **العلامة التجارية:** اسم وشعار مستقلان بدل «kleinanzeigen»، وتسجيل محلي للعلامة حسب الإمكان.
- **المراجعة المحلية:** محامٍ سوري يتحقق من شروط الاستخدام والخصوصية وأي متطلبات ترخيص قبل الإطلاق العام — عنصر تحقق، ليس مانعًا معماريًا.

---

## 9. الاعتمادية والتشغيل

| المجال | الهدف/الإجراء |
|---|---|
| أهداف الخدمة (MVP) | توافر 99.5%، p95 ≤ 300 ms للقراءة و ≤ 600 ms للبحث، تسليم الرسالة للمستلم المتصل ≤ 5 ث |
| النزاهة | Transactions لكل عملية متعددة الجداول، قيود DB (unique/check/FK) كخط دفاع أخير، Optimistic locking عبر `version` |
| الآثار الجانبية | Outbox + Queue + Retry أُسّي + Dead letter (`failed_jobs`) + Horizon للمراقبة |
| الإعادة الآمنة | `idempotency_keys` + `client_message_id` |
| العدادات | `views_count` يُجمع في Redis ويُفرغ دوريًا؛ `favorites_count` و`unread_count` تُحدث في نفس Transaction |
| المهام المجدولة | انتهاء الإعلانات، تذكير قبل الانتهاء، تنظيف الصور اليتيمة والمسودات القديمة ومفاتيح Idempotency، تنفيذ حذف الحسابات، ملخص التنبيهات اليومي |
| المراقبة | Sentry للأخطاء، سجلات JSON منظمة مع `request_id`، مقاييس (زمن الاستجابة، عمق الطوابير، فشل Push)، تنبيهات على الطوابير المتأخرة و5xx |
| النسخ الاحتياطي | Postgres مُدار مع PITR (RPO ≤ 15 دقيقة)، نسخة يومية مشفرة خارج المزود، **تجربة استعادة فعلية** قبل الإطلاق (RTO ≤ 4 ساعات) |
| الترحيلات | Migrations متوافقة رجوعًا (expand → migrate → contract)، لا قفل طويل على الجداول الكبيرة |
| السعة المفترضة | حتى ~20 ألف مستخدم و~100 ألف إعلان: سيرفر تطبيق + Worker + Postgres مُدار + Redis كافية |

---

## 10. الاختبارات

| المستوى | ما يُختبر | البوابة |
|---|---|---|
| Unit | State machine، قواعد التسعير والتحقق، مطابقة الأبحاث المحفوظة، حدود الصور | كل PR |
| Feature/HTTP | كل Endpoint: النجاح، التحقق، 401/403/404/409/410/422/429 | كل PR |
| Authorization | مصفوفة: (مالك، غير مالك، محظور، معلّق، ضيف، مشرف) × كل مورد | كل PR |
| Contract | استجابات مطابقة لـOpenAPI | كل PR |
| Database | Postgres حقيقي في CI (لا SQLite) لاختبار FTS والقيود والجغرافيا | كل PR |
| Queue/Integration | Outbox ← Job ← إشعار، فشل وإعادة محاولة، عدم التكرار | كل PR |
| Flutter E2E | الرحلات المسجلة سابقًا نفسها لكن ضد Staging | قبل كل إصدار |
| Load | k6: البحث، فتح الإعلان، إرسال الرسائل بأحمال متوقعة ×3 | قبل الإطلاق |
| Security | فحص تبعيات، SAST، اختبار IDOR/Rate-limit يدوي، مراجعة ASVS | قبل الإطلاق |
| Recovery | استعادة نسخة احتياطية إلى بيئة منفصلة والتحقق منها | قبل الإطلاق ثم كل ربع سنة |

---

## 11. البيئات والتسليم

- **محلي:** Docker Compose (PHP-FPM/Nginx، Postgres، Redis، MinIO، Mailpit) + بيانات Seed تعيد استخدام الإعلانات الوهمية الحالية.
- **Staging:** نسخة مطابقة للإنتاج ببيانات اصطناعية، يُختبر عليها Flutter.
- **Production:** داخل EU، نشر بدون توقف، Migrations تلقائية محمية.
- **CI/CD (GitHub Actions):** Pint/PHPStan (level 8) ← الاختبارات مع Postgres ← فحص العقد ← بناء الصورة ← نشر Staging تلقائي ← Production بموافقة.
- **المستودع:** مستودع Backend منفصل (مثل `advertising-backend`)، ومستودع Flutter الحالي يبقى للواجهة.

---

## 12. ربط Flutter (يبدأ بالتوازي من المرحلة 3)

1. استخراج واجهات Repository من `DemoStore` (Auth، Listings، Drafts، Media، Search، Engagement، Messaging، Notifications).
2. تنفيذان: `LocalDemo*` (الموجود، يبقى لوضع العرض والاختبارات) و`Api*` (Dio + Interceptors للتوكن وإعادة المحاولة وIdempotency).
3. Flavors: `demo` / `staging` / `production` باختيار وقت البناء.
4. تخزين التوكن في `flutter_secure_storage` (لا SharedPreferences).
5. حالات واجهة جديدة إلزامية: تحميل، فشل شبكة مع إعادة محاولة، تعارض 409، حساب معلّق، إعلان محذوف 410.
6. كاش محلي للمفضلة والمحادثات وقائمة البحث الأخيرة، مع مسودات الرسائل محليًا كما هي.
7. Push: تسجيل جهاز، فتح المحادثة/الإعلان من الإشعار.
8. واجهات جديدة مطلوبة: نموذج الملف التجاري وحالة التحقق، شارة «تاجر موثّق» وبيانات الاتصال في صفحة البائع والإعلان، وشاشة «بانتظار التحقق / حساب معلّق».
9. **تحويل الواجهة كاملة إلى العربية RTL** — الواجهة الحالية ألمانية LTR: اتجاه، نصوص، تنسيق الأسعار (SYP/USD وأرقام لاتينية)، قوائم المحافظات والمدن، اسم وألوان جديدة. تُعامل كمرور UI حقيقي لا مجرد ترجمة نصوص.
10. **لا ترحيل للبيانات الوهمية المحلية** إلى السيرفر؛ عند أول دخول حقيقي تُعرض مسودات المستخدم المحلية فقط كخيار استيراد.

---

## 13. التسلسل الصحيح للتنفيذ

| المرحلة | المحتوى | المخرجات | معيار الإنهاء | تقدير |
|---|---|---|---|---|
| **0. القرارات والعقد** | حسم القسم 15، الهوية والاسم، مخطط ERD نهائي، OpenAPI v1، نموذج التهديدات، قائمة المتطلبات القانونية، تعريف أهداف الخدمة | وثائق معتمدة | موافقتك على العقد والنطاق | 3–5 أيام |
| **1. الأساس** | المستودع، Docker، CI، هيكل الوحدات، صيغة الأخطاء، السجلات، Sentry، Health، Rate limiting، Idempotency، Outbox | هيكل قابل للنشر على Staging | CI أخضر + نشر Staging | 4–6 أيام |
| **2. الهوية والحساب** | التسجيل والتأكيد والدخول والاستعادة، الأجهزة، الملف، تقديم الملف التجاري، التفضيلات، الموافقات | `/auth/*`، `/me/*` | اختبارات أمان الدخول والحدود | 5–6 أيام |
| **3. التصنيفات والإعلانات والصور** | Catalog + Locations seed، Media pipeline، الإعلانات، المسودات، State machine، My listings، الملف العام | جوهر المنتج | رحلة إنشاء ← تعديل ← حالات ← حذف كاملة عبر API | 8–10 أيام |
| **4. البحث والتفاعل** | FTS ألماني، الفلاتر والجغرافيا والفرز، المفضلة، الأبحاث المحفوظة، المتابعة، آخر المشاهدات | `GET /listings` نهائي | أداء البحث ضمن الهدف على 100 ألف إعلان اصطناعي | 5–7 أيام |
| **5. المراسلة** | المحادثات والرسائل والقراءة والأرشفة والحظر، Push للرسائل، Polling | Messaging كامل | عزل المشاركين، الحظر بالاتجاهين، عدم تكرار الرسائل | 6–8 أيام |
| **6. الإشعارات والتنبيهات** | إشعارات داخلية، Push/Email، تنبيهات البحث، انتهاء الإعلانات، الملخص اليومي | Jobs مجدولة | لا تنبيه مكرر، احترام التفضيلات | 4–5 أيام |
| **7. الأمان والإشراف والالتزام** | البلاغات، Filament، قرارات الإشراف وبيان الأسباب، طابور تحقق التجار، فحص المحتوى، التصدير وحذف الحساب، سجل التدقيق | لوحة إدارة | مسار بلاغ ← قرار ← إشعار المتأثر مكتمل + دورة تحقق تاجر مكتملة | 7–9 أيام |
| **8. ربط Flutter** | Repositories، Flavors، حالات الشبكة، Push، تحويل RTL والعربية والهوية الجديدة، E2E على Staging | تطبيق متصل | كل رحلات الاختبار السابقة تنجح ضد Staging بالعربية RTL | 10–14 يومًا (بالتوازي من المرحلة 3) |
| **9. التصليب** | اختبار حمل، مراجعة أمنية، تجربة استعادة، مراجعة قانونية، توثيق التشغيل (Runbooks) | تقرير جاهزية | لا عيوب حرجة مفتوحة | 4–6 أيام |
| **10. إطلاق تجريبي مغلق** | 20–100 مستخدم، مراقبة، إصلاحات | إصدار Beta | أسبوعان بلا حوادث حرجة | 2 أسبوع تشغيل |

ترتيب لا يُكسر: لا مراسلة قبل اكتمال الهوية والحظر في نموذج البيانات؛ لا تنبيهات قبل تثبيت مخطط الفلاتر؛ لا إطلاق قبل الإشراف والحذف والتصدير والنسخ الاحتياطي المجرَّب.

---

## 14. المخاطر الرئيسية

| الخطر | الأثر | التخفيف |
|---|---|---|
| الاحتيال والرسائل المزعجة (أكبر خطر في أسواق الإعلانات) | فقدان ثقة المستخدمين | تأكيد البريد قبل المراسلة، حدود المعدل، كشف الروابط/الأرقام، البلاغات، تعليق سريع |
| محتوى مخالف أو صور غير لائقة | مسؤولية قانونية | مسار بلاغات المنصة، مراجعة سريعة، إمكانية فحص صور آلي لاحقًا |
| تضخم النطاق | تأخير بلا نهاية | تجميد نطاق MVP في المرحلة 0، وكل إضافة تمر بقرار |
| تباعد العقد بين Flutter وBackend | أعطال تكامل متأخرة | OpenAPI + اختبارات عقد + Staging مبكر |
| بطء البحث مع النمو | تجربة سيئة | فهارس صحيحة، قياس مبكر، مسار ترقية لمحرك بحث |
| ضعف تسليم البريد/Push | فقد رسائل مهمة | مزود معاملات، SPF/DKIM/DMARC، مراقبة الفشل، الإشعار داخل التطبيق كمرجع دائم |
| ضعف الشبكات والأجهزة في السوق | تجربة بطيئة وفشل متكرر | صور مضغوطة بأحجام، حدود تحميل صغيرة، كاش محلي، إعادة محاولة وIdempotency، Polling خفيف |
| تحقق التجار اليدوي بطيء أو ناقص قانونيًا | نقص ثقة السوق وتأخر دخول التجار | الاقتصار على الحد الأدنى من الحقول القانونية، مراجعة محامٍ، SLA واضح، إعلام التاجر بحالة التحقق |
| العلامة التجارية والقانون | منع الإطلاق | اسم وهوية مستقلان ومراجعة قانونية محلية قبل Beta |

---

## 15. القرارات — اعتمدتها بناءً على توجيهك (تستطيع قلب أي منها)

| # | القرار | المعتمد |
|---|---|---|
| 1 | السوق واللغة | سوريا، واجهة عربية RTL بالكامل |
| 2 | الاسم والهوية | اسم عمل مؤقت «سوقنا» واتجاه لوني جديد بعيد عن أخضر المرجع — يُثبتان في مرور الهوية خلال المرحلة 0 |
| 3 | الإشراف | نشر فوري + فحص آلي للمحتوى + مراجعة عند البلاغ؛ لا مراجعة يدوية قبل النشر |
| 4 | بوابة النشر التجاري | التاجر يعمل فورًا كحساب عادي؛ شارة «تاجر موثّق» وبيانات الاتصال تظهر بعد توثيق الإدارة (لا حجب للنشر في سوق جديد) |
| 5 | المراسلة | Polling + Push في MVP؛ WebSockets (Reverb) لاحقًا |
| 6 | الاستضافة | Hetzner أو DigitalOcean فرانكفورت + Postgres مُدار بـPITR + Redis؛ مزود بريد معاملات؛ نُقيّم منطقة أقرب عند قياس زمن الاستجابة |
| 7 | الاحتفاظ | رسائل 12 شهرًا، إعلانات محذوفة 6 أشهر، بيانات التجار طوال العلاقة + سنة |
| 8 | المستودع | مستودع Backend منفصل (`advertising-backend`)، ومستودع `advertising` للواجهة |
| 9 | العملة | SYP افتراضي + USD اختياري لكل إعلان، أرقام لاتينية |
| 10 | تسجيل الهاتف | تأكيد البريد هو البوابة؛ SMS مؤجل (كلفة وموثوقية)، ورقم الهاتف حقل اختياري في الملف التجاري |

لم يبقَ قرار مفتوحًا؛ ما لم تعترض، هذه هي الخطة المعتمدة للمرحلة 0.
