# Специфікація робіт: ТВ НСЗУ-464 (модулі Mefizz 3.8–3.22)

**Версія:** 1.0  
**Дата:** 2026-09-16  
**Власник робіт:** Mefizz  
**Issue (docs):** [#822](https://github.com/openhealths/nationHealth/issues/822) · **PR (docs):** [#823](https://github.com/openhealths/nationHealth/pull/823)  
**Норматив:** [39-MIS-Tehnichni-vymogy-…-25.08.2026.pdf](./39-MIS-Tehnichni-vymogy-v-redaktsii-nakazu-NSZU-464-vid-25.08.2026.pdf)

Цей документ — **операційна специфікація для підхоплення роботи** наступним розробником або AI-агентом. Він описує *що робити*, *у якому порядку*, *де код*, *як перевіряти*, а не лише статус аудиту.

Супутні файли:
- Статус «зроблено / треба» → [CORRELATION.md](./CORRELATION.md)
- Глибокі аудити → [audits/](./audits/)
- Готові промпти чатів → [prompts/](./prompts/)
- Контекст останніх PR → [00-MASTER-CONTINUATION-SPEC.md](./00-MASTER-CONTINUATION-SPEC.md)

---

## 1. Мета

Довести модулі МІС OpenHealth до відповідності технічним вимогам (ТВ) наказу НСЗУ №464 (ред. 25.08.2026) у зоні відповідальності:

| ID | Модуль |
|----|--------|
| 3.8 | Медичні висновки (МВН / МВТН) |
| 3.9 | Облік електронних рецептів на ЛЗ |
| 3.10 | План лікування |
| 3.17 | Електронні направлення (ServiceRequest) |
| 3.20 | Е-запити на медичні вироби (DeviceRequest) |
| 3.22 | Відпуск (видача) медичного виробу (DeviceDispense) |

**Критерій успіху програми:** по кожному модулю є (1) issue, (2) гілка у форку, (3) draft/ready PR у `openhealths/nationHealth`, (4) серверні гейти + тести + ручний UAT з КЕП де потрібно, (5) оновлена матриця в `CORRELATION.md` / audit.

---

## 2. Обмеження та інваріанти (обовʼязково)

### 2.1 Репозиторій і git

| Правило | Деталь |
|---------|--------|
| Issues / base PR | `openhealths/nationHealth` |
| Feature branches | **лише** `origin` = `Mefizz/ohealth`, імʼя `i{N}_{slug}` |
| PR | draft → `main` parent, head `Mefizz:<branch>`, title `#N …` |
| Заборонено | push feature-гілок у parent (`fork` / `upstream`) |

### 2.2 Технології

- Laravel 12 + Livewire 3 + Sail; PHP 8.5
- Команди: `vendor/bin/sail …` (або make). **Не** host `php artisan`
- Моделі: **camelCase** (`HasCamelCasing`). snake_case — міграції / `$fillable` / eHealth payload
- Нові коментарі — англійською, *why*; чужі коментарі не чіпати

### 2.3 Безпека даних і тестів

- **Ніколи** `migrate:fresh` / wipe на `mis_dev` без dump + явного OK
- Перед тестами: `vendor/bin/sail artisan config:clear`; PHPUnit → `DB_DATABASE=testing`
- КЕП: **не** обходити модалку; зупинитись і попросити користувача підписати
- У логах/Discord/PR — без PHI, токенів, паролів

### 2.4 Якість імплементації ТВ

- Правила ТВ enforced **на сервері** (Policy / Form / Service / action), не лише приховуванням UI
- Точні українські формулювання з ТВ → `resources/lang/uk/*`
- 1 логічний модульний шматок = 1 issue = 1 гілка = 1 PR (не змішувати 3.8 з 3.17 в одному PR)
- Після змін: `vendor/bin/sail bin pint --dirty --format agent` + мінімальний набір тестів

### 2.5 Доменні правила (вже підтверджені UAT)

1. У **Encounter Package** `device_dispenses[].based_on` **не може** посилатись на Device Request **з** медичною програмою → eHealth: *Device request with program can not be referenced*. Програмні рецепти — шлях аптеки.
2. Для UAT 3.22 потрібен Device Request **без** програми → створити через Care Plan activity на гілці `#792` («Без медичної програми», PreQualify skip).
3. **Employee edit** (#802/#803): apply локальної ревізії лише після remote **EmployeeRequest APPROVED**; у login-listener **не** кликати EmployeeRequest API без scope `employee_request:read` (окремий трек, не блокує ТВ-модулі, але часто паралельний).

---

## 3. Середовище та облікові дані (локальний UAT)

| Призначення | Значення |
|-------------|----------|
| App | `http://localhost` (Sail) |
| Dev login | `http://localhost/dev/login` |
| Secondary / OUTPATIENT | `openhealthkopylets+outp35@gmail.com` / див. AGENTS.md · заклад **КОПИЛЕЦЬ… \<OUTPATIENT\>** |
| Primary care | `openhealthkopylets+pmd35@gmail.com` · **\<PRIMARY_CARE\>** |
| UAT пацієнт | id `3`, Якийсь / Пацієнт / 23.02.2001 · `/dashboard/1/persons/3` |
| Care plans | `/dashboard/1/persons/3/care-plans` (активні id 3, 4, 38) |

Якщо фронт не оновився після UI-змін — `vendor/bin/sail npm run build` або `dev`.

---

## 4. Карта артефактів коду (швидкий індекс)

| Домен | Де шукати |
|-------|-----------|
| Compositions 3.8 | `app/Livewire/Composition*`, policies, mappers, `resources/views/livewire/**/composition*` |
| Care Plan 3.10 | `app/Livewire/CarePlan/*`, `app/Services/MedicalEvents/CarePlan*`, repositories |
| Medication eRx 3.9 | `ManagesCarePlan*` / encounter eRx concerns, `PatientMedicationRequests`, MedicationRequest mappers |
| Referrals 3.17 | `ManagesCarePlanReferrals`, `ManagesEncounterReferrals`, `ReferralRequestLifecycleService`, `PatientReferrals`, `ReferralIndex` |
| Device Request 3.20 | `DeviceRequestMapper`, `DeviceProgramParticipationGuard`, care-plan device activity, `PatientReferrals` (device rows) |
| Device Dispense 3.22 | Encounter `parts/device-dispense*`, `EncounterPackageBuilder`, DeviceDispense mapper/API після #804 |
| Scopes | `config/scopes/roles.php` |
| UK copy | `resources/lang/uk/*` |

---

## 5. Робочі пакети (Work Packages)

Порядок рекомендований залежностями. Кожен WP = окремий issue+гілка+PR, якщо не сказано інакше.

---

### WP-A — 3.8 Медичні висновки (UAT + blockers)

| Поле | Значення |
|------|----------|
| Статус входу | Код у [#645](https://github.com/openhealths/nationHealth/pull/645); аудит `audits/3.8-compositions-pr645.md` |
| Промпт | `prompts/04-tv-38-uat.md` + `prompts/38-uat-checklist.md` |
| Мета | Підтвердити/полагодити create→KEP→async→sign→print→cancel→ERLN; пройти ручний UAT |
| P0 | (1) Перевірити tip гілки: чи є коректний signed create (`data` base64), чи лишився broken `submitComposition`. (2) Async cancel / ERLN retry: persist job id + poll. (3) Role×entity parity за ТВ. (4) Не деструктивна міграція compositions. |
| P1 | Pregnancy server-authoritative; clarification після ідентифікації preperson; hardening UI-only gates |
| DoD | Чекліст UAT пройдений користувачем з КЕП; критичні P0 закриті або заведені follow-up issues з посиланням у #645 |
| Залежності | Немає (можна паралельно з іншими) |

---

### WP-B — 3.10 План лікування (compliance P0)

| Поле | Значення |
|------|----------|
| Статус входу | Аудит ~72%; код P0 у [#813](https://github.com/openhealths/nationHealth/pull/813) (#809); функціональний стек [#792](https://github.com/openhealths/nationHealth/pull/792) |
| Промпт | `prompts/03-tv-310-continue.md` + `prompts/310-care-plan-v3.md` |
| Мета | Закрити сертифікаційні інваріанти створення ПЛ і activities |
| P0 | Encounter обовʼязковий; `author` = employee автора encounter; addresses = основний діагноз; draft-first до eHealth; activity authorize+granted write+LE; kind/product/program validation |
| P1 | period.end warning до КЕП; MED_COORDINATOR; omit scheduled_period без програми; sync перед cancel/complete |
| DoD | Матриця в кінці `audits/3.10-care-plan.md` оновлена; тести на кожен P0; #813 готовий до UAT |
| Залежності | Бажано не ламати публічні контракти activity managers (downstream 3.9/3.17/3.20). Device **без програми** з #792 зберігати |

---

### WP-C — База #792 (Care Plan + eRx/referral + device без програми)

| Поле | Значення |
|------|----------|
| Статус входу | [#792](https://github.com/openhealths/nationHealth/pull/792) @ `i768_i789_care_plan_erx_referral` |
| Промпт | `prompts/02-device-dispense-care-plan.md` |
| Мета | Стабільна база для 3.9 / 3.20 / 3.22 UAT |
| Обовʼязково зберегти | «Без медичної програми»; skip PreQualify без `program_id`; PreQualify з програмою; dual-tab eRx registry |
| DoD | Feature-тести device±program зелені; ручний UAT no-program DR → Encounter based_on |
| Залежності | Блокує якісний UAT 3.22 based_on |

---

### WP-D — 3.22 Відпуск МВ (runtime + TV)

| Поле | Значення |
|------|----------|
| Статус входу | #804 merged; based_on follow-up `#816`; повний TV scaffold [#814](https://github.com/openhealths/nationHealth/pull/814) (#806) |
| Промпт | `prompts/02-device-dispense-care-plan.md`; review-only: `prompts/06-pr-review-colleague.md` |
| Мета | Робочий Encounter Device Dispense за ТВ 3.22 + прибрати mock registry |
| P0 | based_on лише DR без program; quantity ≤ remaining; XOR device_code/definition; поля encounter/performer/location/when_handed_over у package; тести mapper + Livewire |
| P1 | pharmacy full-only (залежність 3.5.2); patient registry замість hard-code; supporting_info як references |
| DoD | UAT сценарії A/B/C з master handoff; матриця 3.22 оновлена; mock UI прибраний |
| Залежності | WP-C (#792) для тестових DR без програми |

---

### WP-E — 3.9 ЕР на ЛЗ

| Поле | Значення |
|------|----------|
| Статус входу | Сильний care-plan path на #792; аудит `audits/3.9-medication-request.md` |
| Промпт | `prompts/39-medication-v3.md` + `prompts/05-tv-new-module-template.md` |
| Мета | Дотягнути patient/standalone paths і авторизацію до рівня ТВ |
| P0 | Перевірки `medication_request:*` scopes на всіх діях; patient registry details/reject/resend; standalone today+self enforce |
| P1 | max period block; dosage warnings на standalone; price catalog якщо ТВ вимагає в MIS |
| DoD | Новий issue+PR; негативні тести на missing scope; UAT create→sign→print/SMS |
| Залежності | Бажано tip #792 або post-merge main |

---

### WP-F — 3.20 Е-запити на МВ

| Поле | Значення |
|------|----------|
| Статус входу | Care-plan path + **без програми** (#792); аудит `audits/3.20-device-request.md` частково застарів |
| Промпт | `prompts/320-device-request-v3.md` |
| Мета | Signed DeviceRequest містить усі обовʼязкові поля ТВ; executor-дії |
| P0 | Persist+map `inform_with`, reason, parameters; success messages за ТВ; не губити no-program path |
| P1 | Standalone same-day self encounter; revoke/complete; A5 printout; eHealth search/details |
| DoD | Unit mapper tests на нові поля; UAT OTP і print paths |
| Залежності | WP-C |

---

### WP-G — 3.17 Електронні направлення

| Поле | Значення |
|------|----------|
| Статус входу | Життєвий цикл create/sign/use/complete є; аудит `audits/3.17-service-request.md` |
| Промпт | `prompts/317-service-request-v3.md` |
| Мета | Закрити executor/security gaps ТВ |
| P0 | Server ban DOCTOR на counselling qualify/use; specimen/impersonal без PII в DOM |
| P1 | reuse minutes; complete + program + remaining_quantity warning; draft edit lifecycle; qualify policy vs PDF |
| DoD | Негативні тести counselling; impersonal projection test |
| Залежності | Слабкі; можна після WP-B/C |

---

### WP-H — (паралельно, не ТВ) EmployeeRequest #803

Див. `prompts/01-employee-802-hotfix.md` і master handoff. Не змішувати з ТВ-PR.

---

## 6. Процес виконання одного WP (чеклист агента)

```
1. Прочитати цей SPEC § відповідний WP + CORRELATION + audits/3.XX
2. Звірити підпункти з PDF (нормативне формулювання / UK тексти)
3. git fetch origin fork; checkout/create i{N}_… від правильної бази
4. Реалізувати P0 ітеративно; серверні гейти першими
5. Тести + pint
6. Оновити CORRELATION.md і post-implementation matrix в audits/
7. STOP для ручного UAT (особливо КЕП), якщо промпт вимагає
8. Після OK користувача: push origin + draft PR #N
9. Discord completed/blocker за потреби (без PHI)
```

### Шаблон issue

```markdown
## Опис
ТВ X.Y — <коротко gap з CORRELATION>.

## Scope (P0)
- [ ] …

## Out of scope
- …

## Evidence
- docs/tv-nszu-464/audits/…
- docs/tv-nszu-464/CORRELATION.md

## Test plan
- [ ] Unit/Feature …
- [ ] Manual UAT / KEP …
```

### Шаблон фінального повідомлення агента

```markdown
## Результат WP-…
- Закрито: …
- Залишилось / follow-up issue: …
- Файли: …
- Тести: `sail artisan test --compact --filter=…` → …
- PR: …
- ГОТОВО ДО UAT: <кроки>
```

---

## 7. Ручні UAT сценарії (мінімум)

### 7.1 3.8 — див. `prompts/38-uat-checklist.md`

### 7.2 3.10

1. Створити ПЛ з обовʼязковим encounter; перевірити author = автор encounter.
2. Save draft → sign (КЕП) → локальний UUID стабільний.
3. Approval write → activity medication/device/service.
4. Device activity **без програми** → успішний create (для 3.22).
5. Cancel/complete з prechecks.

### 7.3 3.22 (залежить від 3.20 no-program)

1. OUTPATIENT login → пацієнт з active DR **без** program.
2. Encounter → «Видачі МВ» → based_on видно → qty ≤ remaining → КЕП.
3. DR **з** program → based_on порожній / hint аптека (очікувано).
4. Негатив: qty > remaining → block.

### 7.4 3.9 / 3.17 / 3.20

За чеклістами в відповідних audits (P0 rows) + точні тексти з PDF.

---

## 8. Definition of Done (програма модулів)

- [ ] Кожен з 3.8 / 3.9 / 3.10 / 3.17 / 3.20 / 3.22 має актуальну матрицю в `CORRELATION.md`
- [ ] Немає відомих P0 без issue або з «забитим» UI-only обходом
- [ ] Усі відкриті PR мають test plan і посилання на цей SPEC / audit
- [ ] Ручний КЕП-UAT пройдений там, де ТВ вимагає підпис
- [ ] Немає секретів/PHI у docs PR

---

## 9. Що вже зроблено «до тебе» (не переробляти без причини)

| Артефакт | Зміст |
|----------|--------|
| Ця тека `docs/tv-nszu-464/` | PDF, аудити, промпти, кореляція, цей SPEC |
| PR #823 | Публікація docs у parent через fork branch |
| PR #792 | eRx/referral stack + device без програми |
| PR #813 | TV 3.10 P0 (WIP) |
| PR #645 | Compositions (чекає UAT/fix) |
| PR #804 + #816 | Device dispense Encounter + based_on load |
| PR #803 | EmployeeRequest gate (окремий трек) |

---

## 10. Швидкий старт наступного агента

Скопіюй в новий чат:

```text
Працюємо по docs/tv-nszu-464/SPEC.md (операційна специфікація ТВ 3.8–3.22).
Спочатку прочитай SPEC §2 інваріанти, §5 WP-… який я вкажу, і CORRELATION.md.
Норматив — PDF у тій же теці. Код не досліджуй з нуля: починай з audits/ і вказаних PR.
Один WP = один issue/гілка/PR у форку Mefizz. Sail only. КЕП не обходити.
Зараз бери в роботу: WP-___
```

Потім додай відповідний файл з `prompts/`.

---

## 11. Історія змін документа

| Версія | Дата | Зміна |
|--------|------|--------|
| 1.0 | 2026-09-16 | Перша операційна специфікація для підхоплення після handoff #822/#823 |
