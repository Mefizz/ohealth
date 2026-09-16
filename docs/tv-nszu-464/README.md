# ТВ НСЗУ-464 — специфікація модулів Mefizz (3.8–3.22)

**Issue:** [#822](https://github.com/openhealths/nationHealth/issues/822)  
**Гілка:** `i822_tv_nszu_464_compliance_spec` (лише docs)  
**Норматив:** [39-MIS-Tehnichni-vymogy-…-25.08.2026.pdf](./39-MIS-Tehnichni-vymogy-v-redaktsii-nakazu-NSZU-464-vid-25.08.2026.pdf) (наказ НСЗУ №464 від 25.08.2026)

## Призначення

Цей каталог — **єдине місце в репозиторії** для:
1. PDF технічних вимог;
2. AI-промптів / handoff;
3. аудитів кодової бази;
4. **матриці кореляції** «ТВ ↔ зроблено / треба».

Application code тут **не** змінюється.

## Швидкий старт для агента

1. Прочитай [CORRELATION.md](./CORRELATION.md) — статус по твоїх пунктах.
2. Деталі аудиту — `audits/3.XX-*.md`.
3. Промпт — `prompts/00-system-context.md` + модульний файл.
4. Контекст останніх PR — [00-MASTER-CONTINUATION-SPEC.md](./00-MASTER-CONTINUATION-SPEC.md).

## Структура

```
docs/tv-nszu-464/
├── README.md                          ← цей файл
├── CORRELATION.md                     ← матриця зроблено / TODO
├── 00-MASTER-CONTINUATION-SPEC.md     ← handoff останніх робіт
├── 39-MIS-…464….pdf                   ← повний текст ТВ
├── audits/                            ← глибокі аудити (статичні зрізи)
└── prompts/                           ← промпти для окремих чатів
```

## Модулі відповідальності (Mefizz)

| ТВ | Модуль | Стор. у PDF (орієнтир) |
|----|--------|------------------------|
| 3.8 | Медичні висновки | ~91 |
| 3.9 | Облік ЕР на ЛЗ у СГуСОЗ | ~104 |
| 3.10 | План лікування | — |
| 3.17 | Електронні направлення | — |
| 3.20 | Е-запити на медичні вироби | — |
| 3.22 | Відпуск медичного виробу | — |

## Пов’язані PR / issues (код)

| Модуль | Issue / PR | Примітка |
|--------|------------|----------|
| 3.8 | #644 / [#645](https://github.com/openhealths/nationHealth/pull/645) | Потрібен ручний КЕП UAT |
| 3.10 | #809 / [#813](https://github.com/openhealths/nationHealth/pull/813) draft | P0 compliance WIP |
| 3.10 + eRx stack | [#792](https://github.com/openhealths/nationHealth/pull/792) | База для 3.9/3.20 (device без програми) |
| 3.22 runtime | #804 merged + #816 | Encounter dispense + based_on |
| 3.22 TV full | #806 / [#814](https://github.com/openhealths/nationHealth/pull/814) | Handover / scaffold |
