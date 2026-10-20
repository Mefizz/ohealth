# Модуль 3.9: MedicationRequest — дизайн переходу

Issue: https://github.com/openhealths/nationHealth/issues/1008

База: main `39d4a6b7ca06317b762b69f666fca3225fb9536b`. Дизайн зафіксовано до зміни реалізації.

## Контракти та джерела

Перевірено 19.10.2026: [PROD OpenAPI GENERAL MIS 9.15.3](https://docs.ehealth.gov.ua/scalar-docs/general-mis.json). Перевага цьому актуальному контракту над застарілими Apiary прикладами.

- [Create MRR, API-005-044-0002](https://e-health-ua.atlassian.net/wiki/spaces/ESOZ/pages/17570693379), версія сторінки 51, 15.10.2026.
- [PreQualify MRR](https://e-health-ua.atlassian.net/wiki/spaces/ESOZ/pages/17570660609), версія 42, 15.10.2026.
- [Get MRR Details](https://e-health-ua.atlassian.net/wiki/spaces/ESOZ/pages/17570529446), версія 37, 15.10.2026.
- [Sign MRR](https://e-health-ua.atlassian.net/wiki/spaces/ESOZ/pages/17570824338), версія 39, 18.10.2026.
- [Get MR Details](https://e-health-ua.atlassian.net/wiki/spaces/ESOZ/pages/17570103587), версія 38, 15.10.2026.
- [Reject MR](https://e-health-ua.atlassian.net/wiki/spaces/ESOZ/pages/17570758873), версія 31, 15.10.2026.

Дати вище — час редакції сторінки Confluence, а не обіцянка дати впровадження API. Зберігати native API casing і форму запитів: IL використовує `medication_id`, `medication_qty`, `dosage_instruction`, `dose_and_rate` як object. Замінювати цей transport загальним HL7 JSON не можна.

## Спільне представлення

Використати існуючий MedicationRequestMapper. `toFhir` / `fromFhir` описують внутрішній ESOZ FHIR profile з Reference, CodeableConcept, Quantity, Ratio, Range, Timing і Dosage. Це профіль застосунку для ESOZ, а не твердження про валідацію незміненим HL7 R4 validator: ESOZ має власні статуси заявок та додаткові поля `program`, `division`, `period`, `containerDosage`, `informWith`.

- Medication UUID — typed `medicationReference`; patient — `subject`, author — `requester`, Encounter — `context`. Відмовитися від старого `requester.agent` і UUID препарату як термінологічного коду.
- Зберігати всі `basedOn` з типами та порядком: CarePlan і Activity мають різні ідентичності. Не визначати Activity за першим елементом.
- Не генерувати UUID, поточну дату, route, одиниці, текст, asNeeded або знаменник Ratio у mapper. Нові значення створює тільки явна команда користувача / workflow.
- Повні Dosage: додаткові інструкції, site, route, method, Timing, обидва asNeeded варіанти, dose Quantity/Range, rate Quantity/Range/Ratio, всі максимальні дози. `false` і `0` не видаляти.
- Повторне редагування форми застосовувати над переданим оригінальним `resource`; непредставлені поля не втрачати. Явне очищення має окрему семантику від відсутнього поля.
- Календарні `created_at`, `started_at`, `ended_at` не перетворювати між часовими поясами. Неповні / неоднозначні старі дозування блокувати перед відправленням, а не домислювати.
- Native IL ↔ profile адаптери повинні зберігати всі відомі поля та metadata; підписаний native snapshot ніколи не проходить через mapper.

## Зберігання

Існуючі MedicationRequestRepository та моделі залишаються точкою входу. Нові сервіси для атомарних завдань не потрібні.

Репозиторій володіє транзакціями, patient-scoping, блокуванням ідентичності, записом та вибірками. Reference зберігати з усіма coding у впорядкованих owned зв'язках; використовувати ті самі primitive helper methods, що в 3.10/3.17, без залежності від їхніх незлитих гілок. Dosage зберігати як повний складений FHIR datatype в окремому рядку з позицією; оновлення за позицією не має видаляти незмінені рядки. Застарілі проєкції зберігати для чинних читачів.

Кількість — decimal(24,10), без округлення до двох знаків. Дані поза підтримуваною точністю відхиляти до запису. Додаткова metadata зберігається окремо від нормалізованих полів. `ehealth_payload` — лише native cache для відображення/діагностики, не джерело підпису.

List import лишається partial: він не стирає клінічні поля повного ресурсу й не перетворює MRR на MR. Для перезапису повного клінічного ресурсу потрібен явний detail import. Ідентичності MRR і виданого MR не підміняти одна одною.

## Workflow та підписання

Інтеграція — у `app/Classes/eHealth`, наявний lifecycle залишається сумісним фасадом для caller-ів. Перед КЕП зробити GET `/api/persons/{person_id}/medication_request_requests/{id}`; перевірити resource id, patient id, NEW status і непорожній `seed`; підписати рівно свіжий `$.data`. Немає fallback на list, cache чи локальну реконструкцію. Після 401/403/404/422, помилкового id/person, відсутнього seed або недопустимого статусу signer і PATCH не викликаються.

Create/Sign/Reject не переводять локальний запис у кінцевий стан на порожній / pending / error відповіді. Після Sign MRR стає SIGNED; окремий MR має свій id і ACTIVE. Подальші дії MR використовують тільки підтверджений id MR.

## Три блоки для рев'ю

1. Mapper/profile/native адаптери + synthetic contract tests.
2. Нормалізоване зберігання, guarded forward migration, preflight / правила старих даних + repository tests.
3. Підпис / endpoint cutover, сумісність CarePlan, UI specification / runbook + workflow tests.

Одна company umbrella draft PR та три review PR у персональному fork. Кожний наступний блок базується на попередньому. Не зливати автоматично.

## Перевірка і межі

Sail, окрема тимчасова БД testing; DatabaseTransactions, synthetic fixtures, Http::fake, без реальних КЕП/пацієнтів. Перевірити native round trip, false/zero, багато Dosage/codings/references, Range/Ratio/Timing, точність, повторний запис, partial import, cross-patient rejection, fresh signing і failure-before-signer. Сумісність з 3.10/3.17 перевірити окремим непублікованим combined checkout.

Frontend не реалізується: специфікація повинна описати введення структурованих одиниць/Ratio, відображення повних дозувань, legacy warnings, reimport та окремі стани MRR/MR. Реальна UAT/КЕП і рішення щодо міграції старих даних — release gates, які тести не замінюють.

Поза цим модулем: pharmacy dispense 3.5; standalone DeviceRequest; Medical Conclusions 3.8; OAuth/scope/refresh/shared-job refactor; решта функціонального аудиту, не потрібна для переходу FHIR.
