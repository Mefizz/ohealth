# План рефактору eHealth mapping і app/Services

Оновлено: 22.09.2026. База: PR [#792](https://github.com/openhealths/nationHealth/pull/792), head `d91299f72ffa115de7a441d7cb6a8d3475f6fe94`; PR ще OPEN. Проаналізовано шість нових комітів після початкового baseline `4b1f0e7` — 52 файли. Окрема гілка `Mefizz/ohealth:i841_object_mapper_service_request` перенесена на новий head. Зміни рефактору не потрапляють у гілку #792.

Початковий аналіз виконано 19.09.2026 за кодом `review-source`, контекстом завдання «Проаналізувати PR 792», офіційною документацією та кодом Symfony ObjectMapper. Реалізація вже ведеться за [issue #841](https://github.com/openhealths/nationHealth/issues/841) в окремому worktree `mapper-refactor`; перевірки — в ізольованих Sail/PostgreSQL containers. Поточні результати та межі інкременту записуються у [object-mapper-refactor.md](object-mapper-refactor.md).

## Зміни бази та корективи від 22.09.2026

- `DeviceActivityReadinessAssessment` уже перенесено до `app/Dto/MedicalEvents`. Не переносити повторно; перевірити також посилання в старих feature tests.
- `CarePlanTermsOfService`, `Medication/RequestSource` і `Medication/RequestResourceType` вже в `app/Enums`. Майбутні eRx DTO використовують ці типи, зберігаючи окремі значення request/prescription та local/ehealth.
- Синхронізація контрактів перевіряє кожну сторінку й pagination, зберігає весь набір транзакційно та ставить COMPLETED лише після успіху. При виділенні sync Action зберегти ці інваріанти; неповний набір не є авторитетним списком програм.
- Approvals confirm/deactivate відхиляють неуспішний HTTP response. ObjectMapper не визначає успішність операції та не надає доступ. Patient-scoped approvals залишаються явним API-викликом.
- eRx тепер синхронізує кілька рецептів плану; UI скидає loading state після помилки. Зберегти зв'язки та поведінку partial response, не зводити результат до одного рецепта.
- AJAX використовує один toast target, redirect — session flash; повідомлення локалізовано. Не відновлювати передчасний success до завершення approval confirmation. Обидва публічні activity handlers залишаються потрібними Blade.
- `ServiceRequestMapper` між `4b1f0e7` і `d91299f` не змінився: вісім golden outbound fixtures залишаються оригінальними, перегенерація з нової реалізації заборонена.
- Upstream main `186ecd08` додатково містить #847/#820 (`PatientData.php`, personal data sync). Ця зміна не перетинається з mapping; незалежна гілка лишається на head #792 за вказівкою користувача. Після merge #792 — rebase на фактичний upstream main і повторні перевірки.
- Перший етап #841 розділено на перевірювані коміти: outbound DTO + усі create/prequalify callers; окремо inbound Write + partial-update tests. Успішний outbound не означає завершення вертикального spike чи підставу видаляти весь legacy mapper.

## 1. Рішення

Symfony ObjectMapper доцільний для перетворення структур даних на межах застосунку. Але заміна масивів на DTO сама по собі не прибере складність великих lifecycle-класів. Потрібні дві пов’язані роботи:

1. Відокремити mapping та серіалізацію від виконання операцій.
2. Розподілити решту логіки за її відповідальністю: HTTP → Api; SQL і транзакції → Repository; правила → невеликі предметні класи; послідовність кроків → Actions; відображення → Livewire/Blade; enum → Enums.

Не переносити цілий `LifecycleService` в `Action` зі збереженням усіх його обов’язків. Не робити ObjectMapper універсальним механізмом для HTTP, доступів, SQL, polling чи підписання.

Зберігаємо Livewire, існуючі API-класи, Eloquent і Repository. Не додаємо laravel-data, CQRS bus, generic repository, власний mapping framework або набір інтерфейсів із єдиною реалізацією.

## 2. Що є зараз

На head `d91299f` у `app/Services`: **71 PHP-файл, 12 091 фізичний рядок**; у `MedicalEvents`: **42 файли, 9 493 рядки**, із них 17 array-маперів. Підрахунок включає порожні рядки й коментарі; це характеристика обсягу, не оцінка якості. Зменшення кількості класів уже враховує перенесення readiness DTO.

### Основні джерела складності

- [ReferralRequestLifecycleService](../app/Services/MedicalEvents/ReferralRequestLifecycleService.php): чернетки, prequalify, HTTP, збереження, повторне mapping відповіді, КЕП-операції, HTML і barcode. `mapRemoteReferralFields()` додатково виправляє результат `fromFhir()`: контракт уже описаний у двох місцях.
- [MedicationRequestLifecycleService](../app/Services/MedicalEvents/MedicationRequestLifecycleService.php): create/sign/reject, пошук контексту, raw payload, fallback, друк, SMS, повідомлення UI, перевірки кількості. Заміна одного `MedicationRequestMapper` залишить більшу частину цього змішування.
- [CarePlanRepository](../app/Repositories/CarePlanRepository.php): окрім persistence, має `formatCarePlanRequest()` і правила періодів.
- [CarePlanActivityRepository](../app/Repositories/CarePlanActivityRepository.php): persistence, HTTP sync, форматування create/prequalify/cancel/complete, вибір device-коду, періоди та порівняння payload. Просто перемістити сюди сервіси означало б посилити проблему.
- [DeviceProgramParticipationGuard](../app/Services/MedicalEvents/DeviceProgramParticipationGuard.php): перевірка одночасно читає довідники, запитує eHealth і зберігає контракти. За назвою не видно цих побічних ефектів.
- [CarePlanApprovalService](../app/Services/MedicalEvents/CarePlanApprovalService.php): змішує payload, доступи, API, OTP та обробку асинхронного результату. Водночас явно типізовані outcomes/results — корисна частина дизайну, яку варто зберегти.
- [CarePlanLifecycleService](../app/Services/MedicalEvents/CarePlanLifecycleService.php) та activity-варіант значно простіші: API-виклик і очікування job. Вони не є основною проблемою; важливо зберегти порядок цих кроків при їх усуненні.

Оцінювати потрібно відповідальність і залежності класу. Сам суфікс `Service` не робить код поганим, а перейменування не покращує архітектуру.

## 3. Що виправити в початковому плані

### 3.1. Зафіксувати версію й перевірити інтеграцію

У проєкті PHP `^8.4`, Laravel `^12.64`; у lock уже є `symfony/property-access v8.1.0` і `symfony/serializer v8.1.3`. ObjectMapper відсутній. Вихідний кандидат — **`symfony/object-mapper:^8.1`**, із конкретною версією в lock; під час аналізу доступний стабільний `v8.1.5`. Пакет цієї гілки вимагає PHP `>=8.4.1`: мінімум `8.4.0`, формально дозволений поточним composer.json, недостатній.

Перед встановленням перевірити PHP в Sail/CI/production та виконати Composer dry-run. Не оновлювати весь стек заради мапера. Якщо середовища несумісні, окремо обрати сумісну гілку; приклади нижче орієнтовані на перевірений API 8.1.5.

Офіційні джерела: [ObjectMapper](https://symfony.com/doc/current/object_mapper.html), [composer.json 8.1.5](https://github.com/symfony/object-mapper/blob/v8.1.5/composer.json), [реалізація ObjectMapper 8.1.5](https://github.com/symfony/object-mapper/blob/v8.1.5/ObjectMapper.php).

### 3.2. Колекції потребують об’єктів

`MapCollection` викликає `map()` для кожного елемента й зберігає ключі. Тому:

- масиви об’єктів, які змінюють форму → `MapCollection` з явним target;
- масиви масивів із Livewire → спочатку підготовка елементів як об’єктів;
- списки рядків/кодів або вже готові дані → звичайне копіювання; примусовий MapCollection тут не потрібний;
- для JSON-списків після фільтрації забезпечити послідовні індекси; інакше PHP може закодувати їх як об’єкт.

Приклад конфігурації для 8.1.5: `#[Map(transform: new MapCollection(targetClass: EHealthDosageInstruction::class))]`. Одного PHPDoc із типом елемента недостатньо. [Джерело](https://github.com/symfony/object-mapper/blob/v8.1.5/Transform/MapCollection.php).

### 3.3. Не вводити універсальний UuidToLocalId

Після #792 `based_on_id` і `context_id` в request-таблицях — FK на **Identifier**, а не на `care_plan_activities`/`encounters`. Це явно реалізовано в [ResolvesRequestFhirRefs](../app/Repositories/MedicalEvents/Concerns/ResolvesRequestFhirRefs.php) та [MedicalRequestOwnership](../app/Services/MedicalEvents/MedicalRequestOwnership.php).

Рішення: `*Write` переносить UUID/reference як дані. Repository у транзакції знаходить/створює Identifier і записує FK. Реальні локальні FK employee/division/person розв’язуються за своїми правилами та scope, із пакетним завантаженням для списків. Не робити SQL/firstOrCreate всередині transform.

`HealthcareService::mapCreate()` — існуючий приклад, але не універсальний шаблон: він перетворює UUID конкретних таблиць, а не FHIR Identifier. Сам `mapMany()` уже використовує batch lookup; заміна його на SQL для кожного поля була б регресією.

### 3.4. ObjectMapper не формує JSON-контракт автоматично

Між DTO і SignatureService/API потрібна явна нормалізація до wire-array. Зафіксувати snake_case/camelCase, пропущене поле проти null, порожній список, тип числа, enum value, формат часу та порядок ключів.

Почати з наявного Symfony Serializer та вузької конфігурації для eHealth; якщо він не відтворює потрібну форму/порядок, додати невеликий ресурсний normalizer. Не створювати паралельний універсальний `toArray()` framework. І ObjectMapper, і normalizer залишаються чистими. Перший описує перетворення значень, другий — представлення wire-контракту; не дублювати в них правила та lookup.

`SignatureService::signData()` уже кодує JSON з `JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION`. До нього передається array, не заздалегідь закодований JSON-рядок. Перевірка байтів має стояти на реальному вході до підпису, а не лише на DTO. [Поточна реалізація](../app/Services/SignatureService.php).

### 3.5. Raw-документ для підпису — окремий шлях

`MedicationRequestLifecycleService::buildSignPayload()` спершу повертає `ehealthPayload`, далі пробує отримати документ від eHealth, і лише потім відновлює його локально. Не замінювати перші два шляхи `raw → DTO → JSON`: невідомі DTO поля зникнуть.

Для нових create/prequalify використовуємо DTO. Для отриманого документа зберігаємо поточний raw payload і дозволені точкові зміни. Якщо endpoint повертає вже підписаний blob, передаємо його за чинним механізмом без декодування/повторного mapping. Локальний fallback мігруємо окремо зі збереженням пріоритету джерел; його можливе видалення — окреме рішення про поведінку.

### 3.6. У CarePlan немає того setMapper, який пропонується замінити

У поточних `Api/CarePlan.php` і `Api/CarePlanActivity.php` є `setValidator()` і рекурсивний `replaceEHealthPropNames()`, але немає `setMapper()`. Частина inbound mapping живе в repositories. Реальні точки міграції — саме ці методи та їх call sites.

`EHealthResponse::getData()` зараз повертає array; не змінюємо цей контракт глобально. Для ObjectMapper готуємо object-source на межі конкретного ресурсу. `(object) $array` перетворює лише верхній рівень: вкладені об’єкти й списки обробляються явно. Raw response зберігаємо перед будь-яким перейменуванням.

### 3.7. Часткова відповідь не дорівнює повному запису

List/search може не містити автора, дозувань або зв’язків. `MedicationRequestRepository::upsertFromEHealth()` уже захищає їх від затирання та замінює передані масиви цілком. Новий Write-контракт має відрізняти відсутнє поле, явний null та `[]`. Практичний варіант: typed Write + `providedFields` для partial imports; Repository застосовує тільки дозволені передані поля. Не вважати всі nullable-властивості DTO командою стерти значення.

## 4. Цільові межі й каталоги

### Mapping

`app/Mapping/EHealth/{Referral,MedicationRequest,CarePlan,CarePlanActivity,Shared}` — source-об’єкти, DTO контрактів і їх Map-метадані. `app/Mapping/Transforms` — лише повторно використовувані чисті перетворення. Ресурсні перетворення залишаються поруч із контрактом. `app/Mapping/EHealth/Serialization` — вузька нормалізація DTO до wire-array.

Атрибути на target DTO, без `#[Map]` на Eloquent і без mapping-метаданих на Livewire components. Усі операції задають target явно. Не змішувати двонаправлене перетворення в одному класі DTO.

Джерелом не завжди є справжній `Livewire\Form`: у цих модулях є компоненти, масиви formData й локальні записи. Вводимо компактний source snapshot на ресурс/операцію з валідованими значеннями та явно переданим контекстом. Він не зберігається як публічний стан Livewire. Не копіюємо в нього всі UI-поля.

Context містить підготовлені person/employee/division/encounter/care-plan UUID, legal entity та зафіксований час/UUID операції, коли потрібні. Mapper не викликає `auth()`, `legalEntity()`, SQL, HTTP, `now()` або генерацію UUID. Наявні правила дат зберігаються, але отримують час як вхідний параметр.

### API, persistence, правила й orchestration

- **`app/Classes/eHealth/Api`**: HTTP endpoints, URL, request options, response validation та транспортні envelopes. Actions викликають ці класи; сирих `post('/api/...')` у бізнес-коді не лишається. Виклик API з Action не означає перенесення реалізації HTTP з Api.
- **`app/Repositories`**: scoped queries, upsert, зв’язки, Identifier, транзакції, блокування. Без HTML, складання підписуваного документа й запуску HTTP sync.
- **`app/Actions/MedicalEvents`**: тільки багатокрокові операції, наприклад `CreateReferralDraft`, `SubmitSignedReferral`, `SyncReferral`, `CreateMedicationRequestDraft`, `SignMedicationRequest`, `SyncCarePlanActivities`. Один public entrypoint на завершену операцію. Не створювати Action для простого перейменування поля або кожного API GET.
- **`app/Classes/MedicalEvents`**: предметні правила допустимості/переходів станів над підготовленими фактами. Не плутати їх з Laravel authorization policies. Правила не завантажують і не синхронізують дані приховано.
- **`app/Classes/eHealth/EHealthJobResolver`**: спільна інфраструктура очікування remote jobs. Зберігаємо чинну поведінку; це не mapper і не новий тип queue-job.
- **`app/Dto/MedicalEvents`**: наявні результати операцій/assessment, які не є eHealth wire DTO. У проєкті вже є namespace `App\Dto`.
- **`app/Enums`**: усі enum, включно з `CarePlanApprovalCreateOutcome` і `CarePlanApprovalJobOutcome`.
- **Livewire / Blade**: форма, відкриття КЕП-модалки, flash/toast, текст результату, друк. Дані для друку отримуються окремо від операції підпису.

Для одноразового простого UI-flow не потрібен додатковий Action. Для create/sign, які використовуються з care plan, encounter і patient registry, спільний Action запобігає розходженню логіки.

### Laravel binding

Один `ObjectMapperServiceProvider`: binding `ObjectMapperInterface`, PropertyAccessor за потреби вкладених шляхів, PSR-11 locators для transforms **і conditions**, якщо використовуються service-conditions. Використати наявний Laravel container після перевірки його PSR-11 контракту; окремий adapter потрібний лише за реальної несумісності. Symfony FrameworkBundle/DI compiler pass не встановлювати.

Трансформи без mutable patient/session state; не кешувати контекст пацієнта в singleton. Перевірити вкладені transforms і `MapCollection` через той самий configured mapper. Конструкторні defaults/readonly DTO та відсутні поля перевірити на обраній версії, не покладатися на приклади з іншого релізу.

## 5. Цільові контракти та потоки

### Направлення

Вихідні контракти: `EHealthServiceRequestPrequalify`, `EHealthServiceRequestCreate`, `EHealthDeviceRequestPrequalify`, `EHealthDeviceRequestCreate`. Назва Prequalify позначає envelope, Create — саме документ для підпису; blob-envelope лишається обов’язком API.

Не вводити один `EHealthPrequalifyEnvelope` з набором взаємовиключних nullable resource-полів: у medication і referral різна структура `programs`.

Inbound: `ServiceRequestWrite`, `DeviceRequestWrite`. Джерела care plan/encounter/registry зводяться до одного підготовленого source лише там, де їхня семантика справді однакова.

Послідовність: перевірений контекст → правила → source → ObjectMapper → контракт → wire-array → prequalify/підпис → Api → job verdict → resource extraction → Write → Repository. Локальна чернетка може існувати раніше; успішний clinical status не записується на підставі лише pending job.

### eRx

Назви мають відрізняти **MedicationRequestRequest** від підписаного **MedicationRequest**: наприклад `EHealthMedicationRequestRequestCreate` та `EHealthMedicationRequestRequestPrequalify`; для inbound — окремі request/prescription Write-контракти або спільні поля з обов’язковим discriminator. Не стирати різницю назвою `MedicationRequestCreate`.

Create/prequalify: source → mapper → API. Підпис: accepted raw draft → чинний SignatureService → sign API → job → persist. Локальний реконструйований payload залишається окремим fallback.

Зберігаємо `resource_type`, `source`, UUID чернетки й активного рецепта, дві вкладки реєстру, захист від перезапису локальної заявки імпортованим рецептом. Dosage DTO не має змінювати поточні відмінності між FHIR-формою і eHealth create-контрактом, зокрема форму `dose_and_rate`.

### Care plan і activities

`EHealthCarePlanCreate`, `EHealthCarePlanActivityCreate`, `CarePlanWrite`, `CarePlanActivityWrite`. Для cancel/complete — окремі контракти змін або вузькі patch-операції над оригінальним документом; не відновлювати отриманий документ через урізаний create DTO.

Винести `formatCarePlanRequest`, `formatCarePlanActivityRequest`, device prequalify, payload cancel/complete з repositories. Залишити правила періодів і вибору продукту явними й тестованими. HTTP-частину `syncActivities` перенести в sync Action; Repository приймає підготовлені записи і raw snapshots.

GET validation лишається в Api. Перейменування полів переноситься в mapping конкретного ресурсу; глобальний `replace id → uuid` рекурсивно не відтворювати без перевірки кожного вкладеного контракту.

## 6. Що робимо з рештою Services

Це кінцеві призначення відповідальностей. Перенесення виконується разом із відповідною операцією, а не масовим rename наперед.

- **17 `MedicalEvents/Mappers`**: спочатку ServiceRequest, DeviceRequest, MedicationRequest; решта 14 — окрема хвиля: ClinicalImpression, Condition, DetectedIssue, DeviceAssociation, DeviceDispense, Device, DiagnosticReport, Encounter, Episode, Immunization, Observation, PaperReferral, Procedure, Specimen. `Fhir` facade/`FhirMapperContract` видаляються тільки після останнього caller; не ламати encounter package заради перших трьох ресурсів.
- **`FhirResource`**: тимчасовий helper для legacy-маперів. Нові чисті Reference/Identifier/CodeableConcept/Coding/Quantity-контракти створювати за потребою spike, а не повний FHIR SDK. Не плутати bare Identifier і Reference з полем `identifier`.
- **`InformWith`**: parsing значення форми → чисте перетворення поруч із mapping; створення display/select value → UI helper. Зберегти різницю: service request використовує object, medication create — рядок auth-method ID.
- **`EHealthRequestLifecycleService` + `DeviceRequestLifecycleService`**: прибрати базове наслідування і pass-through wrappers після переведення callers на API/Actions. Обов’язково врахувати legacy request-request endpoints та `signed_device_request_request`; вони не тотожні patient createSigned API.
- **CarePlan/Activity lifecycle**: замінити багатокрокові write-операції явними Actions із job resolution; прості reads ідуть через наявний Api. Без додаткової generic lifecycle-ієрархії.
- **Referral/Medication lifecycle**: mapping → Mapping; queries/persist → Repository; orchestration → Actions; HTML/barcode → Blade та вузький print helper; flash/message → UI/lang. Не створювати один новий клас, який знову об’єднає все це.
- **`MedicationDispenseLifecycleService`**: наступний окремий flow — qualify/create/process в Api, sequence в dispense Action, DTO тільки для payload. Не запускати одночасно зі spike.
- **`ActivityRemainingQuantityGuard`**: розділити правило доступної кількості та repository-механізм транзакції/lock. Зберегти існуючу область блокування до доказу еквівалентності. Обчислений раніше DTO не резервує кількість; повторні/паралельні підписи — окремий тест. Не оголошувати наявний lock автоматично повним захистом remote issuance.
- **`CarePlanLifecycleGateService`**: запити відкритих документів → repositories; рішення про cancel/complete → `CarePlanLifecycleRules`; повідомлення → translations/UI.
- **`CarePlanActivityValidationService`**: `CarePlanActivityRules` над фактами; зберегти providing conditions, rehab reason references та category logic.
- **`CarePlanActivityEHealthGuard`**: наявність activity перевіряє Action через Api; тлумачення результату не є transform.
- **`DeviceProgramParticipationGuard`**: відокремити завантаження/оновлення контрактів та каталогу від оцінки доступності. У `DeviceProgramRules` передавати готові факти. Зберегти режим без програми, відмінність warning/blocking і поточний fallback при remote error.
- **`MedicalRequestOwnership`**: scoped repository lookups + явні перевірки доступу в Actions; не послаблювати person/legal entity/encounter/care plan scope. Окремий повторно використовуваний guard допустимий, якщо усуває дублювання перевірок.
- **`ResolvesEmployeeContext`**: запити employee/division → repository; підготовлений context → source. Зберегти поточний порядок fallback. Не підміняти acting employee автором без явного правила.
- **`CarePlanApprovalService`**: окрема хвиля після основних write-flows. Create/confirm/deactivate/sync → відповідні Actions/Api/Repository; OTP/read-access rules окремо. Поточний approval polling використовує `EhealthLink`/job/processingData та UI polling: не зводити його механічно до синхронного `EHealthJobResolver`.
- **2 approval enums** → `app/Enums`; **2 approval results** → `app/Dto/MedicalEvents`. `DeviceActivityReadinessAssessment` уже перенесено в #792; зберегти його namespace. Це реорганізація типів без зміни значень чи поведінки.
- **`EncounterPackageBuilder` / `EncounterPackageLoader`**: наступна хвиля. Розділити завантаження з БД, UUID/context generation та mapping пакета; зберегти порядок diagnoses/conditions і відмову при відсутній condition. Не перебудовувати цей граф у першій хвилі.

Поза MedicalEvents оглянуто структуру й ключові обов’язки; це не повний поведінковий аудит усіх сторонніх модулів:

- **SignatureService і DictionaryManager та dictionary infrastructure** — залишити. `DictionaryService` як окремого класу тут немає. Невеликі collections/dictionaries не потрібно перетворювати на DTO заради уніфікації.
- **ServiceSearch / ServiceProgramPicker** — пошук і вибір, не mapping. Залишити з dictionary-функціоналом; не втягувати алгоритм пошуку в transforms.
- **ImmunizationDictionaryMapper** — переважно предметна відповідність кодів; ObjectMapper не замінить таблицю відповідностей. Переглянути в хвилі immunization, зберегти чисті lookup-функції.
- **EmployeeRequestProcessor / Matcher** — пізніша окрема задача: remote sync і approved-only apply, repository persistence та чисте зіставлення. Mapping можливо винести; призначення ролей/транзакції мапером не замінюються.
- **MedData / VaccineLot** — інша інтеграція. Потенційно відокремити sync/cache від flatten mapping, але не включати до eHealth spike.
- **PartyVerificationBulkAccess / Cache** — scopes, cache і стан синхронізації; ObjectMapper майже нічого не спрощує. За потреби окремо розділити доступ і cache, зберігши scope-перевірки.
- **EmailService** — загальна mail-функція; залишити поза цим рефактором. Виграшу від ObjectMapper немає.

## 7. Послідовність інкрементів; merge у main після #792

### PR 0 — baseline та characterization

Користувач дозволив почати до merge: окрема гілка створена від #792 і вже оновлена до `d91299f`. Publish — тільки у `Mefizz/ohealth:i841_object_mapper_service_request`. Після merge перевірити фактичний merged commit і перенести гілку на upstream main; baseline змінювати тільки за підтвердженої зміни контракту. Не копіювати команду `fetch fork` сліпо: у цьому checkout `origin` указує на upstream.

Зібрати синтетичні fixtures create/prequalify/sign/import з реальних шляхів виклику. Зафіксувати clock, UUID, timezone, контекст, encoding. Перевірити міграції PR, зокрема `resource_type`. Визначити всі UI/API/Job callers; grep лише Services недостатній.

**Готово:** зафіксований baseline; regressions відрізняються від очікуваної поведінки. Попередні «210 tests / 791 assertions» із завдання про rebase — історичний результат, не заміна нового прогону після merge.

### PR 1 — мінімальна інтеграція + service referral spike

Додати package/binding, мінімальні shared value contracts, source snapshot, ServiceRequest prequalify/create, нормалізацію та inbound Write. Перевести один вертикальний сценарій повністю; старі callers поки можуть користуватися сумісною обгорткою.

**Готово:** array і фактичний JSON для підпису збігаються з baseline; mapping виконує 0 SQL і 0 HTTP; dependency injection працює для вкладених transforms; нетипізований `mixed ...$context` з цього шляху прибраний.

**Stop-критерій:** DTO тільки делегує старому `toFhir`, normalizer повторно будує весь payload або більшості полів потрібне процедурне розбирання unrelated даних. Тоді спершу спростити source/контракт; не масштабувати невдалий шаблон на весь каталог.

### PR 2 — device referral + решта referral callers

Додати device-контракти, розділити classification/reference, перенести локальний inbound mapping. Уніфікувати використання з care plan, encounter, patient registry через спільну операцію там, де вона однакова. Прибрати mapping/HTML із Referral lifecycle по частинах; охопити create/cancel/recall та sync.

**Готово:** безпрограмні device requests працюють; за наявності програми prequalify збережений; FHIR Identifier links і requisition відтворені; старі ServiceRequestMapper/DeviceRequestMapper видалені тільки після міграції всіх їхніх викликів.

### PR 3 — eRx mapping та імпорт

Мігрувати request-request create/prequalify, вкладений dosage, локальний fallback підпису, detail/list Write і import. Зберегти raw-first sign. Окремими малими комітами винести print/messages/queries та create/sign/reject orchestration з lifecycle.

**Готово:** raw невідомі поля не губляться; списки дозувань замінюються як ціле; partial search не затирає локальні поля; request/prescription/source не змішуються; mapper не читає БД.

### PR 4a — care plan

Винести `formatCarePlanRequest`, inbound shape/renaming і sync orchestration. Repository зберігає план/зв’язки, Api перевіряє відповідь, Action керує create/cancel/complete.

**Готово:** create, read, sync, cancel, complete працюють за тим самим контрактом; raw snapshots не обрізані.

### PR 4b — care plan activities

Мігрувати activity create, періоди/quantity/product, prequalify та cancel/complete payload; розвантажити великий ActivityRepository. Відокремити readiness facts від remote sync.

**Готово:** cancel/complete не втрачають поля оригінального документа, не повертають виключені технічні поля; gates, references, remaining quantity та API/job/persist sequence збережені.

### PR 5 — завершення розподілу відповідальностей

Перенести enums/results, завершити видалення порожніх lifecycle wrappers і базового контракту, перенести shared polling з Services без зміни поведінки. Approvals/OTP і dispense — окремі підзадачі цього етапу, не один великий merge. Перенесення типів можна виконувати раніше, якщо воно потрібне конкретному flow.

**Готово:** жоден перенесений flow не містить двох реалізацій mapping; Actions не генерують HTML і не будують великі payload-масиви; Repository не викликає HTTP; transforms не мають побічних ефектів.

### Наступна хвиля

Решта encounter-маперів, Composition/медичні висновки та інші модулі — окремі задачі після оцінки результату. Вона не блокує завершення першої хвилі направлення → eRx → care plan, але потрібна для повного очищення MedicalEvents/Mappers. Employee/MedData/Party не переписуємо автоматично слідом за medical flows.

## 8. Перевірки й критерії прийняття

1. **Контракт:** фіксовані clock/UUID; strict array comparison і byte comparison JSON перед КЕП. Перевіряти `0`, `0.0`, `false`, missing, null, `[]`, list keys, дати, порядок; не порівнювати сам PKCS#7 blob, який може змінюватися між підписами.
2. **Referral:** prequalify envelope проти flat signed create; `programs` проти `program`; ServiceRequest без `authored_on` у поточному signed content; DeviceRequest із його поточним `authored_on`/skew; `code` XOR `code_reference`; care plan/encounter/episode; програма є/відсутня; одиниці service/device.
3. **eRx:** accepted raw draft/fetched raw/local fallback; dosage, inform_with, request vs active prescription, частковий імпорт, detail refresh, unknown raw fields, повторний import.
4. **Persistence:** Identifier FK, person/legal entity scope, unknown required local reference, дві вкладки registry, заборона overwrite local request by prescription, заміна масивів без залишкових елементів.
5. **Операції:** sync/async success, failed/pending/timeout, INVALID prequalify, помилка після remote success перед local persist, retry без повторного створення вже прийнятого документа. Remote HTTP і DB не мають спільної транзакції; recovery має використовувати поточні UUID/job/raw, а не безумовно повторювати POST.
6. **Доступ і UI:** ownership, gates, quantity check, no-program devices, approval OTP/inpatient confirmation, locked state, toast, модалка, printout. Mapping не замінює жодної з цих перевірок.
7. **Ручний UAT:** реальне КЕП-підписання та eHealth sandbox для основних flow після автоматичних тестів. Unit snapshots не доводять прийняття документа remote API.

Почати з наявних `ServiceRequestMapperTest`, `DeviceRequestMapperTest`, `ReferralSignPayloadTest`, `MedicationRequestSignPayloadTest`, repository FHIR refs tests, `StandaloneRequestSigningTest`, care plan lifecycle/approval/gates та diagnostic сценаріїв. Оновлювати тести, прив’язані до старих назв класів, на перевірки поведінки; не втрачати їхні assertions. Запускати відповідні перевірки через прийняте в проєкті Sail-середовище.

Перемикати одну операцію за раз. Тимчасова обгортка делегує новій реалізації й видаляється разом з останнім caller; не утримувати дві довготривалі реалізації. Shadow comparison допустимий тільки для чистого mapping, без дублювання HTTP або записів. Відкат — revert відповідного PR; схема БД не повинна змінюватись у mapping-only PR.

## 9. Як зрозуміти, що підтримувати стало легше

- Поле eHealth змінюється в одному контракті та його contract test, без обходу Livewire + lifecycle + repository.
- З коду операції видно порядок перевірка → payload/підпис → API → job → persist.
- Mapper можна протестувати без Laravel session, HTTP і БД.
- Відсутнє поле не стирає дані, а невідомий raw field не губиться перед підписом.
- Немає нової ієрархії базових Mapper/Service/Action-класів і універсальних bags із десятками nullable-полів.
- Кількість класів може дещо зрости, але кількість місць, які треба змінювати для однієї вимоги, повинна зменшитися. Це корисніший критерій за вимогу «Services має стати порожньою».

Рекомендований перший крок після merge: PR 0 + невеликий service referral spike з оцінкою тімліда за реальним diff. Результат spike визначає деталізацію DTO та обсяг повторного використання для наступних ресурсів.
