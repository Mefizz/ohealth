# Звіт про рефактор ObjectMapper — 05.10.2026

Робота за [issue #841](https://github.com/openhealths/nationHealth/issues/841) залишається у [draft PR #898](https://github.com/openhealths/nationHealth/pull/898). База — main `1cf8b92e`, включно зі змердженим PR #907. Повне усунення медичного сервісного шару ще не завершено. Детальний [план](ehealth-object-mapper-plan.md) та [стан реалізації](object-mapper-refactor.md) актуалізовано.

## Узгодження з патерном тім ліда

- DTO знаходяться в `app/Dto/<Resource>`: `Model` для запису, `Ehealth` для API, окремі create/prequalify/draft класи лише там, де відрізняються wire-контракти. `app/Mapping/EHealth` прибрано без дублювання класів.
- Один Model описує кілька джерел через class/property Map і SourceClass: FormCollection, підготовлена модель та відповідь eHealth. Мінімальний use-import і повний search-import мають власні source collections у `Api/Responses/Collections`; Repository адаптує чинні validated arrays. Контракти всіх медичних API не змінювалися глобально.
- Standalone device та eRx передають валідований компонент прямо в mapper. UUID/час для складних care-plan/encounter сценаріїв поки передаються явно через підготовлене джерело; mapper не виконує SQL/HTTP/session lookup.
- Серіалізація належить DTO через спільний `EhealthMapping` із #907. Один protected hook зберігає чинну поведінку Division та дозволяє medical DTO залишати 0/false/[], literal dictionary keys і точний порядок JSON до КЕП.
- ServiceRequestPayloads видалено: callers використовують `map(..., Target::class)->toArray()`. DeviceRequestPayloads/MedicationRequestPayloads поки готують лише контекст і source; власної серіалізації більше немає. Їх спрощення належить міграції складних форм, а не перейменуванню сервісів.

Repository залишається відповідальним за Identifier/FK, SQL, aggregate persistence та транзакції. Відправлення raw-документа, прийнятого eHealth, не проходить повторно через allowlist create DTO: невідомі клінічні поля зберігаються.

## Що вже зроблено загалом

Care-plan create/update: валідована CarePlanForm прямо мапиться у `CarePlan/Model` для draft та `CarePlan/Ehealth` для підписання. UUID/author/encounter/timezone готує caller і передає у target; DTO не шукає їх у БД/session. Repository payload formatter видалено. Вісім незалежних контрактів зі старої реалізації перевіряють arrays і точні КЕП-байти, включно з DST і clipping до encounter. Локальні display snapshots та null-clearing збережено; password/key/UI поля не потрапляють у payload.

ServiceRequest/DeviceRequest: create/prequalify, багатоджерельний inbound, partial sync, draft/sign/print/SMS, взяття в роботу, qualify, complete/cancel usage та full search import. Успішний signed create зберігається до додаткового GET; його помилка не втрачає документ. Partial sync зберігає автора й Identifier-зв'язки; імпорт зберігає свої aliases, timestamps, quantity=0 та неповні references.

eRx: структуровані create/prequalify/dosage/fallback-sign, partial metadata sync, draft/sign/reject, raw-first signing, active UUID, block/unblock, друк і повідомлення. Standalone payload із рядковим dosage тепер також має власні DTO. Чотири незалежні fixtures з попереднього компонента перевіряють точний JSON і casts duration; feature-тести перевіряють validation до mapper/API та збереження невідомих raw-полів. Ownership, eligibility та quantity checks збережені; область транзакції quantity guard не змінювалася.

Видалено шістнадцять прикладних service/guard/helper класів: CarePlanLifecycleService, CarePlanActivityLifecycleService, EHealthJobResolver, CarePlanLifecycleGateService, CarePlanActivityEHealthGuard, InformWith, MedicalRequestOwnership, ReferralRequestLifecycleService, MedicationRequestLifecycleService, DeviceRequestLifecycleService, EHealthRequestLifecycleService, ActivityRemainingQuantityGuard, CarePlanActivityValidationService, DeviceProgramParticipationGuard, CarePlanApprovalService, MedicationDispenseLifecycleService. Прибрано lifecycle-інтерфейс, legacy static MedicationRequest API wrapper, ServiceRequestMapper, DeviceRequestMapper і MedicationRequestMapper. Workflow читається в Livewire та вузьких protected concerns; HTTP/job/verdict — у наявних API-класах, SQL — у Repository. Нового Actions/Manager шару немає.

CarePlan тепер також має remote→Model, Model→Form і окремий legacy model-source signPlan. Activity draft/sync/create/edit використовують Model/Ehealth/Form; period/product/quantity mapping чистий, relations і dictionary context готує protected Livewire concern. Cancel зберігає повний remote snapshot і додає лише status_reason; complete має власний unsigned PATCH DTO. Repository payload builders і невикористовувані signing helpers видалені.

Quantity lock і точні SQL status lists перенесені в Repository/enum; область транзакції збережена. Activity validation/program participation, approvals/OTP/async polling і pharmacy dispense розподілені між API, Repository, enum, DTO та вузькими protected concerns. Для договорів перевіряються всі сторінки до persist. Polling та edit/save/delete/sign activity обмежені поточним care plan; contract sync не підміняє явний заклад session-контекстом.

Додано незалежні старі baseline-контракти: чотири для care-plan Model/sign/hydration, десять для activity payload/hydration, п'ять для activity draft/sync і п'ять для pharmacy dispense. Golden expectations отримані до заміни, а не з нового mapper.

PaperReferralMapper також видалено. Procedure/DiagnosticReport використовують PaperReferral/Ehealth та Form; дев'ять старих baseline cases перевіряють точний wire JSON, missing/null і paper/electronic priority. Чиста SourceHasPath condition зберігає явний null; старий camelCase intermediate contract адаптують тільки невідрефакторені parent-мапери.

DetectedIssueMapper і DeviceAssociationMapper видалено разом із відповідними Fhir facade methods. EncounterPackageBuilder/Loader тепер використовують app/Dto/DetectedIssue та app/Dto/DeviceAssociation (Ehealth/Form). 29 незалежних baseline cases зі старого HEAD 1084b17e перевіряють точний JSON, missing/null, author={}, scalar zero/false, sparse lists і фактичних callers. UUID та час нових записів готує caller; різницю в одну хвилину для opening/closing pair і вже записані timestamps збережено. Спільні FhirReference/FhirCodeableConcept підтримують явно запитаний text, без зміни своїх defaults. Тимчасова адаптація snake_case DTO до старого camelCase package boundary залишається в builder і зникне разом із його міграцією. SQL persistence цих ресурсів залишається у чинних Repository.

## Перевірки

Остання медична регресія після DetectedIssue/DeviceAssociation: **594 тести / 2579 assertions**, без failures/errors/risky tests; одне попереднє PDO deprecation. Перевірено mapping/JSON/no-IO, реальні encounter builder/loader callers, API/job, Repository/Identifier links, care plan, referrals, eRx/device, registry, approvals і pharmacy dispense. Pint пройшов для 14 PHP-файлів цього інкременту; git diff --check проходить. Використано наявний isolated mapper841 PHP 8.5.3/PostgreSQL; нових контейнерів не створено. Composer не змінювався. Реальний КЕП/eHealth UAT та HTTP authorization suite із Vite assets ще потрібні.

Окремо повний Division feature suite має **91 тест / 401 assertions, п'ять errors, один failure та чотири risky tests**. Ті самі збої підтверджено на незалежно завантаженому незміненому main `1cf8b92e`: обробка mapping exceptions і persistence Division. Вони не замовчуються й не включаються у твердження про успішну медичну регресію. Application-wide suite поки не є green.

Використано наявний isolated Docker mapper841, PHP 8.5.3/PostgreSQL; нових контейнерів не створено. Середовище залишено для відкритого draft PR. Робочу БД не очищено. MedicalEventAuthorizationTest потребує відсутнього Vite manifest; реальний КЕП/eHealth UAT ще не проведено.

## Що ще потрібно

1. Окрема encounter/FHIR хвиля: 11 array-маперів, EncounterPackageBuilder/Loader і Fhir/FhirResource. У Services/MedicalEvents лишається **15 PHP-файлів**. Після останнього caller видалити facade/helpers/FhirMapperContract; Composition входить у цю хвилю.
2. Спростити DeviceRequest/MedicationRequest context adapters під час міграції складних encounter форм. Вони вже делегують серіалізацію DTO; не додавати порожні Form DTO без реального hydration caller.
3. Перед ready провести реальний КЕП/eHealth UAT, перевірити конкурентні issuance/sign операції й HTTP authorization suite з Vite assets. Збережений quantity lock сам по собі не робить весь issuance атомарним.
4. Підтримати актуальність main та окремо усунути підтверджені Division baseline failures до заяви про application-wide green.

SignatureService/DictionaryService і сервіси інших модулів не вважаються видаленими в межах цієї медичної хвилі. Issue залишається відкритою, PR — draft.
