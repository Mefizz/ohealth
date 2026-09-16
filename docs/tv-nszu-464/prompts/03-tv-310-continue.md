# Prompt — Continue TV 3.10 Care Plan compliance (#809 / PR #813)

Paste after `00-system-context.md`.

---

## Mission

Finish **TV 3.10** P0 server-side compliance on draft PR [#813](https://github.com/openhealths/nationHealth/pull/813). Do not re-audit from scratch — use the existing report.

## Sources of truth

1. `.cursor/tv-compliance/3.10-care-plan.md` (audit ~72%, P0/P1 lists)
2. `.cursor/tv-prompts/00-base-v3.md`
3. `.cursor/tv-prompts/310-care-plan-v3.md` (may say “no push until UAT” — **override:** push to update existing draft #813 unless user says stop)

## Branch

```bash
git fetch origin
git checkout i809_tv_310_care_plan_compliance
git pull origin i809_tv_310_care_plan_compliance
```

Base historically: `i768_i789_care_plan_erx_referral` (PR #792). Rebase carefully; preserve optional device-without-program if present in base.

Issue: **#809**.

## P0 still required (verify what’s already in `5db7cf3a` then close gaps)

1. Encounter **required**; `addresses` from primary diagnosis; `author` = encounter author `employee_id`; patient/encounter mismatch rejected.
2. **Draft-first** before eHealth submit; consolidate divergent `CarePlanManager::signPlan()` path.
3. Activity gates: `manage` + granted write approval + legal-entity checks on save/edit/delete/init/sign.
4. Kind enum validation; product rules by kind; medication program type=MEDICATION; device classification XOR with `product_reference`.

## P1 (if P0 green)

period.end warning before KEP; MED_COORDINATOR consistency; safe omit `scheduled_period` without program; sync before cancel/complete gates; requisition/get-by-id tests.

## Downstream warning

3.9 / 3.17 / 3.20 will branch from this. Flag **BREAKING FOR DOWNSTREAM** if you change public Care Plan activity manager contracts.

## Definition of Done

- Matrix section updated at end of `3.10-care-plan.md`
- Tests for each P0; `pint --dirty`
- Draft PR #813 updated with summary + test plan
- Message user: **ГОТОВО ДО UAT** + checklist (create draft → sign → approval → activity → complete)

## Do not

- Touch compositions PR #645 or employee #803 unless asked.
- Wipe `mis_dev`.
- Bypass KEP in browser automation.
