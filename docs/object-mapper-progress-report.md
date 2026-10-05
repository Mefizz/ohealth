# Звіт про рефактор ObjectMapper — 05.10.2026

Робота ведеться за [issue #841](https://github.com/openhealths/nationHealth/issues/841) у [draft PR #898](https://github.com/openhealths/nationHealth/pull/898). Направлення та основні eRx-сценарії вже працюють без своїх lifecycle-сервісів. Повне усунення медичного сервісного шару ще не завершено. Детальний [план](ehealth-object-mapper-plan.md) та [стан реалізації](object-mapper-refactor.md) актуалізовано.

## Що вже зроблено

- Підключено Symfony ObjectMapper через Laravel provider. DTO описують конкретні поля та правила для кількох джерел; mapper не виконує SQL, HTTP або читання session. Вкладені колекції використовують MapCollection, контекст UUID і час передаються явно.
- ServiceRequest і DeviceRequest: DTO для create/prequalify, багатоджерельні ModelData, partial sync, draft/sign/print/SMS. Взяття направлення в роботу, qualify, complete/cancel usage використовують спільні protected-методи для HTTP і Livewire. Успішний signed create зберігається до додаткового GET; його помилка не втрачає документ.
- eRx: структуровані create/prequalify/dosage/fallback-sign перенесено на DTO. Один ModelData приймає локальні поля, підготовлену модель або remote metadata. Завантаження relations та eligible Encounter queries належать Repository. Partial sync не перезаписує dosage, автора, клінічні зв'язки чи raw-документ.
- Видалено одинадцять сервісних класів: CarePlanLifecycleService, CarePlanActivityLifecycleService, EHealthJobResolver, CarePlanLifecycleGateService, CarePlanActivityEHealthGuard, InformWith, MedicalRequestOwnership, ReferralRequestLifecycleService, MedicationRequestLifecycleService, DeviceRequestLifecycleService, EHealthRequestLifecycleService. Прибрано невикористовуваний lifecycle-інтерфейс, старий статичний MedicationRequest API wrapper, ServiceRequestMapper, DeviceRequestMapper і MedicationRequestMapper; employee/Identifier lookup перенесено до репозиторіїв.

## Попередній eRx інкремент

eRx draft/sign/reject, підготовка підпису, active UUID, друк і повідомлення розділено на вузькі protected-трейти. Наявні Livewire actions зберігають перевірку доступу, UI-стан та послідовність; API завершує job/verdict; Repository готує й записує дані. Нового сервісу або великого універсального трейта немає.

Збережено пріоритет підписання raw-документа, включно з невідомими mapper полями. Якщо create job повернув лише metadata, первинний прийнятий документ зберігається окремо. Помилка sign/reject не змінює локальний статус. Standalone prequalify/sign перевіряє verdict і завершення job до повідомлення про успіх. Block/unblock тепер використовують UUID активного рецепта. Наявні ownership, eligibility, multiple-prescription sync та quantity checks збережено; транзакційну область quantity guard не змінено.

## Попередній device інкремент

Standalone device-форма передається безпосередньо в ObjectMapper, який читає тільки allowlist полів. Окремі DTO зберігають її старі create/prequalify payloads. Вона використовує наявний standalone API з `/api/device_requests` і `signed_device_request_request`, а не Patient API для підписаних направлень. API перевіряє verdict/job; INVALID і невдалий sign не стають UI success. Прийнятий документ із невідомими полями зберігається для КЕП окремо від job metadata. Відповідь лише з metadata без документа не дозволяє підпис.

Device lifecycle і його базовий клас більше не потрібні. Перевірка callers також дозволила видалити два старі device/eRx array-мапери. Тести чинних payloads переведено на DTO зі збереженням assertions і golden fixtures. Прибрано лише тести видалених wrappers та чотири тести невикористовуваних toFhir контрактів; тому абсолютна кількість тестів не є порівнянням обсягу підтримуваної поведінки.

## Поточний інкремент: імпорт направлень

Обидва callers імпорту з пошуку перейшли на ObjectMapper. `ExternalResponse` позначає повний документ, а той самий `ServiceRequestModelData` обирає правила за SourceClass. Нового target DTO або workflow-сервісу немає. Повний імпорт зберігає свій пріоритет aliases, початкові timestamps, quantity=0 та неповні reference rows; partial GET продовжує лише доповнювати локальний запис за попередніми правилами. Identifier/FK і вибір автора/пацієнта залишилися в Repository.

До видалення старого мапера збережено незалежний baseline із десяти входів. Додано перевірки відсутності SQL/HTTP у mapping, відмінності import/sync для однакових даних і фактичного запису в PostgreSQL. Наявні документи не оновлюються; blank UUID пропускається; для дубліката batch зберігається перший автор. `ServiceRequestMapper` і dead facade метод видалено. Чинні outbound-тести перенесено на DTO; видалено лише вісім wrapper-only тестів та assertions невикористовуваного signed envelope.

## Перевірки

Поточний прогін після міграції зовнішнього імпорту: **486 тестів / 1941 assertions**, без failures/errors/risky tests. У тому самому disposable Docker перевірено новий повний import разом із mapping/API/Repository/Livewire, care plan, направленнями, eRx, device, patient registry, encounter та approvals. Pint проходить для 12 PHP-файлів інкременту; git diff --check — також. Одне попереднє PDO deprecation залишається. Новий full-import baseline отримано зі старої реалізації до її видалення; попередні golden expectations не змінювалися.

Попередній device прогін у тому самому Docker: **480 тестів / 1902 assertions**, без failures/errors/risky tests. Перевірено пряме картування форми, старі standalone endpoints/envelopes, INVALID/failed job, збереження raw та відмову підписувати metadata без документа. Pint проходить для 16 змінених PHP-файлів; git diff --check — також. Незалежні golden expectations не змінювалися.

Попередній eRx прогін в ізольованому Docker mapper841, PHP 8.5.3/PostgreSQL: **481 тест, 1900 assertions**, без failures/errors/risky tests. Залишилося одне попереднє PDO deprecation. Незалежні старі fixtures перевіряють точні JSON-байти до КЕП; додані failure, raw-document, прямі Model→DTO та active-UUID тести. Тоді Pint пройшов для 29 змінених PHP-файлів, git diff --check — також.

Це медична регресія, не весь application suite. HTTP-тест MedicalEventAuthorizationTest потребує відсутнього Vite manifest. Реальний КЕП/eHealth UAT ще не виконано. Нові Docker-контейнери для цього інкременту не створювалися; використано наявне тестове середовище.

## Що ще потрібно

1. Перевірити й перенести старий standalone eRx payload із рядковим dosage на окремий DTO-контракт. Request-мапери та referral/eRx/device/transport lifecycle вже видалені. Окремим інкрементом узгодити referral target namespaces із `app/Dto/<Resource>` без дублювання реалізацій.
2. Завершити care-plan/activity mapping і перенесення quantity/program/validation guards. Зберегти блокування та перевірити паралельні/повторні підписи; DTO не замінює резервування кількості.
3. Перенести approvals/OTP зі збереженням async jobs і read-access: HTTP — API, polling/UI — concerns, persistence — Repository, outcome enums — app/Enums, result DTO — app/Dto. Далі окремо — dispense.
4. Перенести решту encounter-маперів та package builder/loader. Composition і повне прибирання FHIR helpers — наступна хвиля. Зараз у Services/MedicalEvents лишилося **27 PHP-файлів**: 14 маперів, 7 workflow/guard/package класів, 4 approval enum/result класи та 2 FHIR helpers.
5. Перед ready актуалізувати базу main, узгодити Composer і response sources з фактично змердженим PR #907 та провести КЕП/eHealth UAT. Поточна база — b2239108 після rebase 30.09; PR залишається draft.

SignatureService/DictionaryService та сервіси інших модулів не вважаються видаленими в межах цієї медичної хвилі. Для повного фізичного очищення app/Services потрібні наступні окремі міграції.
