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

ServiceRequest/DeviceRequest: create/prequalify, багатоджерельний inbound, partial sync, draft/sign/print/SMS, взяття в роботу, qualify, complete/cancel usage та full search import. Успішний signed create зберігається до додаткового GET; його помилка не втрачає документ. Partial sync зберігає автора й Identifier-зв'язки; імпорт зберігає свої aliases, timestamps, quantity=0 та неповні references.

eRx: структуровані create/prequalify/dosage/fallback-sign, partial metadata sync, draft/sign/reject, raw-first signing, active UUID, block/unblock, друк і повідомлення. Standalone payload із рядковим dosage тепер також має власні DTO. Чотири незалежні fixtures з попереднього компонента перевіряють точний JSON і casts duration; feature-тести перевіряють validation до mapper/API та збереження невідомих raw-полів. Ownership, eligibility та quantity checks збережені; область транзакції quantity guard не змінювалася.

Видалено одинадцять сервісів: CarePlanLifecycleService, CarePlanActivityLifecycleService, EHealthJobResolver, CarePlanLifecycleGateService, CarePlanActivityEHealthGuard, InformWith, MedicalRequestOwnership, ReferralRequestLifecycleService, MedicationRequestLifecycleService, DeviceRequestLifecycleService, EHealthRequestLifecycleService. Прибрано lifecycle-інтерфейс, legacy static MedicationRequest API wrapper, ServiceRequestMapper, DeviceRequestMapper і MedicationRequestMapper. Workflow читається в Livewire та вузьких protected concerns; HTTP/job/verdict — у наявних API-класах, SQL — у Repository. Нового Actions/Manager шару немає.

## Перевірки

Остання медична регресія: **496 тестів / 1983 assertions**, без failures/errors/risky tests; одне попереднє PDO deprecation. Перевірено mapping/API/Repository/Livewire, care plan, referrals, eRx, device, registry, encounter, approvals та нові main device-dispense сценарії. Старі golden expectations не перегенеровано. Composer validation, Pint для 50 PHP-файлів і git diff --check проходять.

Окремо повний Division feature suite має **91 тест / 401 assertions, п'ять errors, один failure та чотири risky tests**. Ті самі збої підтверджено на незалежно завантаженому незміненому main `1cf8b92e`: обробка mapping exceptions і persistence Division. Вони не замовчуються й не включаються у твердження про успішну медичну регресію. Application-wide suite поки не є green.

Використано наявний isolated Docker mapper841, PHP 8.5.3/PostgreSQL; нових контейнерів не створено. Середовище залишено для відкритого draft PR. Робочу БД не очищено. MedicalEventAuthorizationTest потребує відсутнього Vite manifest; реальний КЕП/eHealth UAT ще не проведено.

## Що ще потрібно

1. Завершити care-plan/activity DTO та quantity/program/validation guards зі збереженням блокувань і перевіркою повторних/паралельних підписів.
2. Перенести approvals/OTP та dispense: HTTP — API, polling/UI — concerns, persistence — Repository, enum — app/Enums, result DTO — app/Dto. Зберегти async jobs і read-access.
3. Перенести решту encounter-маперів і package builder/loader; далі Composition/FHIR helpers. У Services/MedicalEvents лишається **27 PHP-файлів**: 14 маперів, 7 workflow/guard/package класів, 4 approval enum/result класи і 2 FHIR helpers.
4. Під час відповідних хвиль спростити складні care-plan/encounter source adapters і додати Model→Form лише для реальної hydration. Усі шість напрямків mapping не вважаються завершеними.
5. Перед ready провести КЕП/eHealth UAT, HTTP-перевірки з Vite manifest, підтримати актуальність main і окремо усунути підтверджені Division baseline failures.

SignatureService/DictionaryService і сервіси інших модулів не вважаються видаленими в межах цієї медичної хвилі. Issue залишається відкритою, PR — draft.
