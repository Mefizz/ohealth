# Prompt — Bootstrap: підхопити роботу за SPEC.md

Встав **першим** повідомленням у новий чат (разом із вказівкою WP).

---

Ти продовжуєш імплементацію ТВ OpenHealth MIS за операційною специфікацією.

## Обовʼязково прочитай (у цьому порядку)

1. `docs/tv-nszu-464/SPEC.md` — інваріанти, work packages, DoD, процес
2. `docs/tv-nszu-464/CORRELATION.md` — актуальний статус модулів
3. Відповідний `docs/tv-nszu-464/audits/3.XX-*.md` для обраного WP
4. Нормативний PDF у `docs/tv-nszu-464/` (підпункти ТВ — джерело формулювань UK)
5. Модульний промпт з `docs/tv-nszu-464/prompts/` (див. SPEC §5)

## Правила

- Fork only: гілки в `Mefizz/ohealth` (`origin`), PR у `openhealths/nationHealth`
- 1 WP = 1 issue = 1 гілка `i{N}_…` = 1 PR
- Sail only; camelCase models; КЕП не обходити; не wipe `mis_dev`
- Не роби повний rediscovery репо — починай з audit + вказаних PR у SPEC
- Після роботи онови `CORRELATION.md` і матрицю в audit

## Зараз у роботу

Користувач вкаже: **WP-A … WP-H** (або номер модуля 3.8 / 3.9 / …).

Підтверди коротко: який WP, яка базова гілка, які P0 на спринт; потім починай імплементацію.
