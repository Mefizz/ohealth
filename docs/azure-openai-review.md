# Автоматичне рев’ю через Azure OpenAI

Workflow `.github/workflows/azure-openai-review.yml` надсилає текст змін із PR до нашого deployment `gpt-6.1-sol` у Azure Foundry та публікує один коментар від `github-actions[bot]`. При оновленні PR цей коментар оновлюється.

## Коли запускається

- Автоматично: відкриття, оновлення, повторне відкриття або вихід із draft для PR у `main`, створених учасниками/власниками організації або collaborators.
- Вручну: Actions → Azure OpenAI PR review → Run workflow, вибрати `main`, вказати номер відкритого PR. Так можна перевірити вже наявний PR або PR зовнішнього автора.
- Draft і закриті PR пропускаються. PR в інші гілки не аналізуються.

## Що відбувається

1. GitHub завантажує тільки довірений скрипт із `main`. Код гілки PR не виконується; Composer/npm і тести PR в цьому workflow не запускаються.
2. OIDC надає тимчасовий доступ managed identity `nationhealth-github-review` до Azure. API key не потрібен.
3. PHP-скрипт читає metadata і patches PR через GitHub API. Ліміту кількості файлів немає: усі доступні текстові patches обробляються порціями до 60 000 байтів. Порядок: `app/`, `database/`, `routes/`, `tests/`, потім інші файли. Великі patches діляться зі збереженням номерів рядків. Lock-файли, бінарні/недоступні patches, `.env`, ключі та generated/vendor файли пропускаються. Наддовгі окремі рядки, що не поміщаються в одну порцію, пропускаються з повідомленням у коментарі.
4. Для кожної порції модель отримує patches як дані, без токенів доступу, та шукає конкретні функціональні помилки P1/P2. Коментарі й код PR не є інструкціями для моделі. Ліміт відповіді — 6000 токенів на кожен запит, включно з reasoning. Великий PR може потребувати кількох запитів; це збільшує тривалість і витрати Azure. Загальний timeout workflow — 30 хвилин.
5. Скрипт перевіряє JSON, назви файлів і номери рядків у кожній порції. Висновки об’єднуються в один коментар тільки якщо head і base PR залишилися тими самими. Повторний запуск для тієї самої пари commit пропускається; зміна commit між порціями зупиняє подальші запити. У звіті видно кількість переглянутих файлів і запитів; step summary показує сумарну кількість токенів. Якщо всі висновки не вміщаються у коментар GitHub, показуються перші за важливістю та кількість непоказаних висновків.

Модель не бачить весь репозиторій або одночасно всі порції: помилки у взаємодії файлів з різних запитів можуть залишитися непоміченими. Відсутність знайдених помилок не означає, що тести пройшли або PR безпечний. Бот не ставить approval, не змінює файли й не зливає PR. Висновки перевіряє людина. GitHub API повертає максимум 3000 змінених файлів; для PR понад цей обсяг кількість переглянутих файлів у звіті буде меншою за загальну.

Фільтр ключів виключає `.pem`, `.key`, `.ppk`, `.p12`, `.pfx`, `.jks`, `.keystore`, стандартні приватні SSH-файли `id_rsa`/`id_dsa`/`id_ecdsa`/`id_ed25519` (також `_sk` і резервні копії) та перевіряє попередню назву перейменованого файла. Patch із впізнаваним заголовком приватного PEM/OpenSSH або PuTTY-ключа також пропускається, навіть за іншої назви файла. Це консервативний фільтр відомих форматів, а не повний пошук секретів: довільні секрети, вставлені в звичайний код без цих ознак, він не гарантує виявити. Публічні SSH-файли `.pub` не виключаються за назвою.

## Налаштування

Repository variables в `openhealths/nationHealth`:

- `AZURE_CLIENT_ID`: Client ID identity.
- `AZURE_TENANT_ID`: Tenant ID.
- `AZURE_OPENAI_ENDPOINT`: HTTPS base URL ресурсу, без `/openai/v1`.
- `AZURE_OPENAI_DEPLOYMENT`: `gpt-6.1-sol`.

Identity має роль `Cognitive Services OpenAI User` на Foundry account із моделлю. Federated credential має issuer `https://token.actions.githubusercontent.com`, audience `api://AzureADTokenExchange` і subject `repo:openhealths/nationHealth:ref:refs/heads/main`.

Права workflow: `contents: read`, `pull-requests: write`, `id-token: write`. Checkout не зберігає GitHub credentials. Azure Login і Checkout закріплені за commit SHA. PHP із розширеннями curl і mbstring уже є на GitHub runner; application dependencies не потрібні.

Запити до моделі оплачуються в Azure у підписці Foundry-ресурсу. GitHub-hosted стандартний runner для цього public repo не потребує Azure VM. Паралельні запуски для одного PR скасовуються; workflow можна вимкнути в Actions, якщо потрібно зупинити рев’ю.

## Перевірка і діагностика

Локальні перевірки скрипта без мережі та API-ключів:

```sh
php -l .github/scripts/azure-openai-review.php
php .github/tests/azure-openai-review-test.php
```

Після злиття workflow у `main` запустити його вручну на відкритому PR. Перевірити зелений run і коментар бота з правильним commit.

При помилці workflow не публікує повідомлення про успішне рев’ю. HTTP 401/403: перевірити OIDC, роль identity і workflow permissions. HTTP 429: перевірити квоту Azure. Помилка незавершеної/невалідної відповіді: переглянути output limit, content filter і доступні параметри моделі. Не копіювати access tokens або API keys у логи чи коментарі.
