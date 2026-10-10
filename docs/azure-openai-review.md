# Автоматичне рев’ю через Azure OpenAI

Workflow `.github/workflows/azure-openai-review.yml` надсилає текст змін із PR до нашого deployment `gpt-6.1-sol` у Azure Foundry та публікує один коментар від `github-actions[bot]`. При оновленні PR цей коментар оновлюється.

## Коли запускається

- Автоматично: відкриття, оновлення, повторне відкриття або вихід із draft для PR у `main`, створених учасниками/власниками організації або collaborators.
- Вручну: Actions → Azure OpenAI PR review → Run workflow, вибрати `main`, вказати номер відкритого PR. Так можна перевірити вже наявний PR або PR зовнішнього автора.
- Draft і закриті PR пропускаються. PR в інші гілки не аналізуються.

## Що відбувається

1. GitHub завантажує тільки довірений скрипт із `main`. Код гілки PR не виконується; Composer/npm і тести PR в цьому workflow не запускаються.
2. OIDC надає тимчасовий доступ managed identity `nationhealth-github-review` до Azure. API key не потрібен.
3. PHP-скрипт читає metadata і patches PR через GitHub API. Він вибирає максимум 20 файлів і 60 000 байтів patches. Lock-файли, бінарні, `.env`, ключі та generated/vendor файли пропускаються. Великі patches пропускаються повністю.
4. Модель отримує patches як дані, без токенів доступу, та шукає конкретні функціональні помилки P1/P2. Коментарі й код PR не є інструкціями для моделі. Для відповіді встановлено ліміт 6000 токенів, включно з reasoning.
5. Скрипт перевіряє JSON, назви файлів і номери рядків. Висновки публікуються тільки якщо head і base PR залишилися тими самими. Повторний запуск для тієї самої пари commit пропускається.

Модель не бачить весь репозиторій: це обмежене рев’ю patches. Відсутність знайдених помилок не означає, що тести пройшли або PR безпечний. Бот не ставить approval, не змінює файли й не зливає PR. Висновки перевіряє людина.

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
