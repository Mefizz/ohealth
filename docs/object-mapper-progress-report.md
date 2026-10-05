# Звіт про рефактор ObjectMapper — 05.10.2026

Робота ведеться за [issue #841](https://github.com/openhealths/nationHealth/issues/841) у [draft PR #898](https://github.com/openhealths/nationHealth/pull/898). Направлення та основні eRx-сценарії вже працюють без своїх lifecycle-сервісів. Повне усунення медичного сервісного шару ще не завершено. Детальний [план](ehealth-object-mapper-plan.md) та [стан реалізації](object-mapper-refactor.md) актуалізовано.

## Що вже зроблено

- Підключено Symfony ObjectMapper через Laravel provider. DTO описують конкретні поля та правила для кількох джерел; mapper не виконує SQL, HTTP або читання session. Вкладені колекції використовують MapCollection, контекст UUID і час передаються явно.
- ServiceRequest і DeviceRequest: DTO для create/prequalify, багатоджерельні ModelData, partial sync, draft/sign/print/SMS. Взяття направлення в роботу, qualify, complete/cancel usage використовують спільні protected-методи для HTTP і Livewire. Успішний signed create зберігається до додаткового GET; його помилка не втрачає документ.
- eRx: структуровані create/prequalify/dosage/fallback-sign перенесено на DTO. Один ModelData приймає локальні поля, підготовлену модель або remote metadata. Завантаження relations та eligible Encounter queries належать Repository. Partial sync не перезаписує dosage, автора, клінічні зв'язки чи raw-документ.
- Видалено дев'ять класів: CarePlanLifecycleService, CarePlanActivityLifecycleService, EHealthJobResolver, CarePlanLifecycleGateService, CarePlanActivityEHealthGuard, InformWith, MedicalRequestOwnership, ReferralRequestLifecycleService, MedicationRequestLifecycleService. Також видалено старий статичний MedicationRequest API wrapper; employee/Identifier lookup перенесено до репозиторіїв.

## Що завершено в останньому інкременті

eRx draft/sign/reject, підготовка підпису, active UUID, друк і повідомлення розділено на вузькі protected-трейти. Наявні Livewire actions зберігають перевірку доступу, UI-стан та послідовність; API завершує job/verdict; Repository готує й записує дані. Нового сервісу або великого універсального трейта немає.

Збережено пріоритет підписання raw-документа, включно з невідомими mapper полями. Якщо create job повернув лише metadata, первинний прийнятий документ зберігається окремо. Помилка sign/reject не змінює локальний статус. Standalone prequalify/sign перевіряє verdict і завершення job до повідомлення про успіх. Block/unblock тепер використовують UUID активного рецепта. Наявні ownership, eligibility, multiple-prescription sync та quantity checks збережено; транзакційну область quantity guard не змінено.

## Перевірки

Існуюче ізольоване Docker-середовище mapper841, PHP 8.5.3/PostgreSQL: **481 тест, 1900 assertions**, без failures/errors/risky tests. Залишилося одне попереднє PDO deprecation. Незалежні старі fixtures перевіряють точні JSON-байти до КЕП; додані failure, raw-document, прямі Model→DTO та active-UUID тести. Pint проходить для 29 змінених PHP-файлів, git diff --check — також.

Це медична регресія, не весь application suite. HTTP-тест MedicalEventAuthorizationTest потребує відсутнього Vite manifest. Реальний КЕП/eHealth UAT ще не виконано. Нові Docker-контейнери для цього інкременту не створювалися; використано наявне тестове середовище.

## Що ще потрібно

1. Перенести живі legacy toFhir/fromFhir callers, окремий DeviceRequestLifecycleService і transport base. Після останнього caller видалити відповідні мапери; legacy device endpoints мають інший контракт підпису. Старий standalone eRx payload із рядковим dosage також потребує окремої перевірки перед DTO-уніфікацією.
2. Завершити care-plan/activity mapping і перенесення quantity/program/validation guards. Зберегти блокування та перевірити паралельні/повторні підписи; DTO не замінює резервування кількості.
3. Перенести approvals/OTP зі збереженням async jobs і read-access: HTTP — API, polling/UI — concerns, persistence — Repository, outcome enums — app/Enums, result DTO — app/Dto. Далі окремо — dispense.
4. Перенести решту encounter-маперів та package builder/loader. Composition і повне прибирання FHIR helpers — наступна хвиля. Зараз у Services/MedicalEvents лишилося **32 PHP-файли**: 17 маперів, 9 workflow/guard/base/package класів, 4 approval enum/result класи та 2 FHIR helpers.
5. Перед ready актуалізувати базу main, узгодити Composer і response sources з фактично змердженим PR #907 та провести КЕП/eHealth UAT. Поточна база — b2239108 після rebase 30.09; PR залишається draft.

SignatureService/DictionaryService та сервіси інших модулів не вважаються видаленими в межах цієї медичної хвилі. Для повного фізичного очищення app/Services потрібні наступні окремі міграції.
