# Prompt — Device Dispense based_on + Care Plan device without program

Paste after `00-system-context.md`.

---

## Mission

Maintain and extend the **ЗОЗ Encounter Device Dispense** path that references Device Requests **without** medical program, and the Care Plan capability that creates such requests.

## Branches

| Branch | PR | Role |
|--------|-----|------|
| `i768_i789_care_plan_erx_referral` | [#792](https://github.com/openhealths/nationHealth/pull/792) | Care Plan + eRx/referral stack; **optional device program** |
| `i816_encounter_device_dispense_based_on` | check GH | Encounter based_on load/filter from eHealth |
| main (after #804) | [#804](https://github.com/openhealths/nationHealth/pull/804) merged | Colleague Device Dispense in Encounter Package |

Sync before work:

```bash
git fetch origin fork
git checkout i768_i789_care_plan_erx_referral   # or i816_… depending on task
git status -sb
```

## Domain rules (do not “fix”)

1. Encounter Package `device_dispenses[].based_on` **must not** reference a Device Request **with** `program` → eHealth: *Device request with program can not be referenced*.
2. Program-linked device prescriptions → **pharmacy** dispense, not Encounter based_on.
3. Care Plan device activity **may** omit medical program; then:
   - payload without `program`
   - **skip PreQualify** when no `program_id`
   - **run PreQualify** when program is set (regression covered)
4. UI must offer **«Без медичної програми»**; do not re-hardcode a default glucose program for this path.

## Proven UAT path

1. Secondary care login (OUTPATIENT credentials in AGENTS.md).
2. Patient with Care Plan → new **device** activity → leave program empty / “Без…” → sign (user KEP).
3. Confirm Device Request created without program.
4. New Encounter → tab Device Dispense → based_on dropdown shows that request → fill qty/performer → package sign (user KEP).
5. Logs: no `programs: required` after the no-program fix; package job succeeds.

## If based_on dropdown empty

Check Network: `GET /api/patients/{uuid}/device_requests?status=active`.  
If only program-linked requests → empty dropdown + pharmacy hint is **correct**.  
403 Access denied → scope/token problem for current role, not missing UI options.

## Tests focus

On `i768_…` after changes, prefer the **device without/with program** suite (previously **16 passed**).  
Ignore unrelated CarePlan/Referral failures from `intent`/`intent_id` schema drift on `testing` unless you are fixing env.

```bash
vendor/bin/sail artisan config:clear
vendor/bin/sail artisan test --compact --filter='DeviceProgram|DeviceRequest|CarePlanActivity'
```

## Do not

- Rewrite colleague’s Device Dispense domain wholesale here (full TV 3.22 is issue #806 / PR #814).
- Commit `.worktrees/` or `storage/mis-uat-runs/` artifacts.
- Force-push to parent remote; rebase conflicts: prefer preserving no-program + PreQualify behavior.

## Done when

- No-program create + Encounter based_on still works after any rebase.
- With-program Care Plan device still PreQualifies.
- PR #792 description mentions both behaviors; tests for both exist.
