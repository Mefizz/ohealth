# TV 3.9 Medication Request — implementation prompt v3

Прочитай: `.cursor/tv-prompts/00-base-v3.md`  
**Запускати лише ПІСЛЯ мержу/ребейзу гілки 3.10 (#809) у базу цього worktree.**

## Контекст
- Звіт: `.cursor/tv-compliance/3.9-medication-request.md`
- Issue: створити `TV 3.9: medication eRx authorization, details and validation parity`
- Base: гілка 3.10 після W1

## P0
1. `createEncounterDraft()` — серверно: encounter сьогодні + performer = поточний employee.
2. Кожна дія перевіряє свій medication scope (не `care_plan:write`). ASSISTANT не має write/sign/reject MRR.
3. Patient registry — medication scope, не лише `person:read`.

## P1
Standalone паритет з care-plan: max_request_dosage, daily dosage + `(!)`, request_max_period_day block, patient details/reject/resend/printout, price catalog, access-matrix tests.

## Git
Не push до OK користувача після UAT.
