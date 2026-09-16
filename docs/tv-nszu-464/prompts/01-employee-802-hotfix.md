# Prompt — EmployeeRequest apply hotfix (#802 / PR #803)

Paste after `00-system-context.md`.

---

## Mission

Close the **UX gap** left by PR [#803](https://github.com/openhealths/nationHealth/pull/803): after email acceptance, **edit** of Owner (and similar edits) no longer auto-applies on login. Create/replace already work. Implement a **narrow, scoped** auto-apply without reintroducing the original early-apply bug.

## Branch

```bash
cd /home/mefizz/projects/ohealth
git fetch origin fork
git checkout i802_employee_request_apply_only_approved
git pull origin i802_employee_request_apply_only_approved
```

Issue: **#802**. PR: **#803**. Tip commit family: `#802 …` (latest includes dropping Session flash from `EmployeeCreate`).

## Invariants (must not violate)

1. **Never** apply a local edit revision unless remote **EmployeeRequest** status is **APPROVED**.
2. **Never** call EmployeeRequest APIs from login code unless the user `can` / has scope **`employee_request:read`**.
3. **Do not** put `getMany` / list pagination back into `EmployeeCreate` (TL rejected N+1 and scope-less list).
4. Create + Owner replace paths must keep working (different code paths).

## Preferred design (already agreed in prior chat)

Add a dedicated listener (e.g. `EmployeePendingEditApply`) fired on the same login event chain **after** or beside `EmployeeCreate`:

1. No `employee_request:read` → return immediately.
2. Collect **pending edit** EmployeeRequests for this user email (local `NEW`/`SIGNED` + uuid + `employee_id` present).
3. For each: **getById** / existing `syncSinglePendingRequest` helper — **not** list.
4. If remote APPROVED → apply revision; if NEW/SIGNED → skip.
5. No Session flash unless you prove the login HTTP response can show it; prefer log + optional later UX toast in a Livewire page.

## Explicit non-goals (P1 later)

- Syncing other employees / cross-MIS requests
- Fixing 500-item list pagination for Actualize
- Roles without `employee_request:read` (they stay on HR sync / Actualize)

## Files to start from

- `app/Listeners/eHealth/EmployeeCreate.php` — current gate: skip pending edits; **no** EmployeeRequest API
- Employee request sync/apply helpers used by index “sync one”
- `EmployeeRequestActualize` / related jobs — reference only; do not overload with login list

## Tests (required)

- Unit/feature: edit pending + remote NEW → no apply
- Edit pending + remote APPROVED + scope → apply
- No scope → no API call / no apply
- Create / replace Owner paths not broken (existing tests or minimal add)

```bash
vendor/bin/sail artisan config:clear
vendor/bin/sail artisan test --compact --filter='Employee'
vendor/bin/sail bin pint --dirty --format agent
```

## Process

1. Confirm with `git log` / PR #803 comments that tip matches “no API in EmployeeCreate”.
2. Implement hotfix; keep commits `#802 …` or open **new issue + branch** if user asks to keep #803 gate-only.
3. Push to `origin` only; update draft PR or open follow-up draft against `openhealths/nationHealth`.
4. Write a short TL comment: gate stays; apply is scoped getById after APPROVED.

## Done when

- Colleague UAT: edit Owner → accept email → login with scoped role → data updates; without acceptance → no update.
- Create + replace still OK.
- Tests green on `testing`.
