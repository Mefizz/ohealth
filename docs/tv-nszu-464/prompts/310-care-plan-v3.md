# TV 3.10 Care Plan — implementation prompt v3

Прочитай спочатку: `.cursor/tv-prompts/00-base-v3.md`

## Контекст
- Звіт: `.cursor/tv-compliance/3.10-care-plan.md` (~72%)
- Issue: #809
- Worktree: `.worktrees/tv_310_care_plan`
- Branch: `i809_tv_310_care_plan_compliance`
- Base: `origin/i768_i789_care_plan_erx_referral` (стек у PR #792)
- Related open PR: https://github.com/openhealths/nationHealth/pull/792 — НЕ мержити туди чуже; працюй у своїй гілці від цього стеку.

## Критично для downstream
3.9 / 3.17 / 3.20 ребейзяться на цю гілку. Не ламай публічні контракти `ManagesCarePlanActivities` / `ManagesCarePlanReferrals` без блоку **BREAKING FOR DOWNSTREAM** у фінальному повідомленні.

## P0 (обовʼязково)
1. Encounter обовʼязковий у валідації; addresses = основний діагноз encounter; author = employee_id автора encounter; reject patient/encounter mismatch.
2. Draft-first: локальний draft + стабільний UUID до будь-якого eHealth submit. Прибрати/консолідувати дивергентний `CarePlanManager::signPlan()` (status=draft, без id/author).
3. Activity gates: authorize manage + granted write approval для конкретного employee + порівняння legal entity автора ПЛ / activity / поточного. `saveActivity` / `editActivity` / `deleteActivity` / `initActivityForm` авторизуються самі.
4. Kind `in:medication_request,device_request,service_request`; product за kind (device: `product_codeable_concept` може заміняти `product_reference`); medication program обовʼязкова type=MEDICATION; quantity/unit за kind.

## P1
- Warning period.end біля поля і ДО КЕП (не лише в approval modal).
- MED_COORDINATOR у role gate / author-selection.
- scheduled_period без програми — omit, без convert порожніх дат.
- Sync eHealth перед cancel/complete prechecks.
- Тести requisition search + get-by-id.

## Git
Локальні коміти `#809 …`. **Не push**, поки користувач не скаже після UAT.
