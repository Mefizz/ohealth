# OpenHealth MIS — Continuation Spec (handoff for next agent)

**Date:** 2026-09-16  
**Author workspace:** `/home/mefizz/projects/ohealth`  
**User:** Mefizz (fork `Mefizz/ohealth` → PRs into `openhealths/nationHealth`)  
**Source chats (agent transcripts):**
- [Device dispense / based_on](bfe306f0-61e7-4fe7-92dc-c1ec557c8fa3) — Encounter Device Dispense + Care Plan device без програми
- [Employee #802/#803](92402710-c5c2-4564-8e6c-e2b86ed93de4) — EmployeeRequest apply-only-after-APPROVED
- [TV modules](6002d239-a086-473f-b7c2-853b8b5a2bb0) — ТВ 3.8 / 3.10 / 3.9 / 3.17 / 3.20 / 3.22 audits + WIP PRs

**How to use this pack:** read this file first, then open the matching prompt in `.cursor/handoff/prompts/`. Existing deep audits live in `.cursor/tv-compliance/` and older module prompts in `.cursor/tv-prompts/`.

---

## 0. Non-negotiable project rules (do not re-discover)

| Rule | Detail |
|------|--------|
| Runtime | Laravel Sail only: `vendor/bin/sail …` / `make`. Never host `php artisan`. |
| Stack | PHP 8.5, Laravel 12, Livewire 3, Tailwind 4, PHPUnit 12 |
| Models | `HasCamelCasing` → **camelCase** in PHP. snake_case only for DB/`$fillable`/eHealth payload |
| Git remotes | `origin` = fork `Mefizz/ohealth` (feature branches). `fork`/`upstream` = parent `openhealths/nationHealth`. **Never push feature branches to parent.** |
| PR shape | Draft PR to `openhealths/nationHealth` `main`, head `Mefizz:<branch>`, title `#N …` |
| Branch naming | `i{issue}_{slug}` |
| DB safety | Never wipe `mis_dev`. Tests only on `testing` after `config:clear`. Dump before any destructive DB op. |
| KEP | Never fake/bypass signature modal; stop and ask user to sign |
| Comments | Do not delete others’ comments; new comments English, explain *why* |
| Credentials | See `.cursor/rules/dev-test-credentials.mdc` + AGENTS.md primary/secondary care logins |
| Discord | `python3 .cursor/hooks/discord-notify.py <type> "<msg>"` on blockers/completions |

### Local UAT defaults

- Dev login: `http://localhost/dev/login`
- Secondary/outpatient (device dispense / compositions): `openhealthkopylets+outp35@gmail.com` / `JJt12rDYsefu5Xf` → **КОПИЛЕЦЬ… \<OUTPATIENT\>**
- Primary: `openhealthkopylets+pmd35@gmail.com` / same password → **\<PRIMARY_CARE\>**
- Alternate doctor login (workspace rule): `openhealthkopylets@gmail.com` + facility **БЕЗШЕЙКО…**, role Лікар
- UAT patient: DB id `3`, Якийсь / Пацієнт / 23.02.2001; care plans `…/persons/3/care-plans` (active ids 3, 4, 38)

---

## 1. Workstream map (what matters right now)

| Priority | Theme | Issue | Branch (fork) | PR | Status | Next action |
|---:|---|---|---|---|---|---|
| **P0** | Employee edit apply only after EmployeeRequest APPROVED | #802 | `i802_employee_request_apply_only_approved` | [#803](https://github.com/openhealths/nationHealth/pull/803) | Gate done; **Owner edit after email acceptance no longer auto-applies** (expected gap) | Decide: merge #803 as-is **or** add scoped login apply (getById) in #803 / tiny follow-up PR |
| **P0** | Care Plan stack: eRx + referral + **device activity without medical program** | #768/#789 (+ related) | `i768_i789_care_plan_erx_referral` @ `5b61f249` | [#792](https://github.com/openhealths/nationHealth/pull/792) | Feature green (16 tests); rebase done; UAT partially done | Keep as base for downstream; watch CI/schema noise unrelated to device |
| **P0** | Encounter Device Dispense `based_on` (colleague #804 merged) + our follow-up | #816 | `i816_encounter_device_dispense_based_on` | (open/check) | Loads active device_requests from eHealth; filters **with program** out of Encounter dropdown | Ensure no-program Device Request exists for UAT; tests still thin |
| **P1** | TV 3.10 Care Plan compliance (server gates) | #809 | `i809_tv_310_care_plan_compliance` | [#813](https://github.com/openhealths/nationHealth/pull/813) draft | P0 code pushed; tests/matrix incomplete | Finish tests + matrix; UAT |
| **P1** | TV 3.8 Compositions | #644 | `i644_composition_medical_conclusions` | [#645](https://github.com/openhealths/nationHealth/pull/645) | Code+tests; **blocked on manual KEP UAT** | User runs `.cursor/tv-prompts/38-uat-checklist.md` |
| **P2** | TV 3.22 Device Dispense (full TV) | #806 | `i806_tv_322_device_dispense` | [#814](https://github.com/openhealths/nationHealth/pull/814) draft | WIP scaffold / handover to colleague | Do not steal unless asked |
| **P2** | TV 3.9 / 3.17 / 3.20 | — | — | — | Audit only in `.cursor/tv-compliance/` | Start only after 3.10 base stable |
| — | Cleanup | — | — | — | `.worktrees/*`, `storage/mis-uat-runs` may be Docker-owned junk | Delete via root docker; **do not commit** |

Current checkout when handoff written: often `main` or feature; **do not assume** — run `git status -sb` and `git fetch origin fork`.

---

## 2. Workstream A — EmployeeRequest gate (#802 / PR #803)

### Problem (original bug)

On login, `EmployeeCreate` applied local **edit** revisions too early: it treated “APPROVED Employee exists in eHealth” as enough. For **edit**, the Employee was already APPROVED **before** email confirmation, so personal data changed in MIS DB before the user accepted the invite.

### Correct invariant

- Apply local revision **only** when **EmployeeRequest** remote status is **APPROVED** (not NEW / SIGNED).
- Do **not** call EmployeeRequest APIs from `EmployeeCreate` for roles that lack `employee_request:read` (TL Vitaliy hard rule — 403).

### What landed on the branch (evolution)

1. First approach: N× getById in loop → rejected (N+1 to eHealth).
2. List (`getMany` / page_size_max) + jobs fallback → debated; page 500 ceiling risk.
3. TL: **no EmployeeRequest API in that listener** because some roles lack scopes.
4. **Current (#803 tip `5c26d0bd`):** pending **edits skipped on login**; no Session flash in listener (Livewire/login flash unreliable). Apply deferred to **manual syncOne** / `EmployeeRequestActualize` under scoped role. Status handling in sync path cleaned to a single `switch`.

### UAT finding (colleague)

| Scenario | Result |
|----------|--------|
| Create employee | OK |
| Replace Owner | OK (`OwnerNewReplace` / employees details path) |
| **Edit Owner personal data** after email acceptance | Local request stays `NEW`; data **not** pulled on login |

This is **expected after the gate**, not a regression of create/replace. Missing piece = safe auto-apply **after** remote APPROVED **with** scope.

### Recommended next fix (P0.5 hotfix — preferred over big P1)

New listener (name suggestion: `EmployeePendingEditApply`) on login / after EmployeeCreate:

1. If user **cannot** `employee_request:read` → exit (no API).
2. For pending local edits of this email with uuid → `syncSinglePendingRequest` / **getById** (not list).
3. Remote APPROVED → apply; NEW/SIGNED → skip.
4. Never reintroduce blind apply based on Employee APPROVED alone.

**P1 (later):** cross-MIS / other employees’ requests, list pagination beyond 500, UX toasts for roles without scope.

### Key files

- `app/Listeners/eHealth/EmployeeCreate.php`
- `app/Services/Employee/EmployeeRequestMatcher.php` (if present)
- Jobs: `EmployeeRequestDetailsUpsert`, sync chain / `EmployeeRequestActualize`
- Tests under `tests/` related to employee request apply / login

### Do / Don’t

- **Do** keep #803 mergeable as “gate only” if TL wants clean PR; ship apply as follow-up.
- **Don’t** put `getMany` list back into `EmployeeCreate`.
- **Don’t** Session::flash from this listener without verifying login pipeline (already dropped).

Ready-to-paste prompt: `prompts/01-employee-802-hotfix.md`

---

## 3. Workstream B — Device Dispense + Care Plan device without program

### Context

Colleague PR [#804](https://github.com/openhealths/nationHealth/pull/804) (`i801_device_dispenses`) added Device Dispense into Encounter Package (merged into main). Analysis found early bugs (local DB status mismatch `active` vs `processed`, hard-coded UI, empty based_on). Colleague fixed loading from eHealth `GET …/device_requests?status=active`.

### Critical eHealth rule (Encounter Package)

Error seen in UAT:

`Device request with program can not be referenced`

→ In **Encounter** Device Dispense, `based_on` may reference only Device Requests **without** medical program. Program-linked prescriptions are for **pharmacy** path, not ЗОЗ Encounter Package.

### Our follow-up work

| Branch | Purpose |
|--------|---------|
| `i816_encounter_device_dispense_based_on` | Load based_on options from eHealth; filter program requests; empty-state UX |
| `i768_i789_care_plan_erx_referral` (PR #792) | Allow Care Plan **device_request activity without medical program** so UAT can create a based_on-eligible request |

### Changes on #792 for no-program device (must preserve)

- UI: program drawer option **«Без медичної програми»** (optional program).
- Search catalog without `medical_program_id`.
- Persist/sign payload **without** `program`.
- **Skip PreQualify** when no `program_id`; **still PreQualify** when program set (regression test exists).
- Removed auto-default glucose program for this path.

Feature tests group A: **16 passed** after rebase. Broader CarePlan/Referral suite had env failures (`intent` vs `intent_id` schema drift on `testing`, livewire-tmp permissions) — **not** treated as regression of this feature.

### Manual UAT chain (proven once)

1. On `i768_…`: Care Plan → device activity → **Без медичної програми** → sign.
2. Creates Device Request (no program) → appears in Encounter based_on.
3. Encounter → Device Dispense → select that request → KEP (user signs) → success.

Docs: eHealth Device dispenses wiki (Atlassian links in transcript).

Ready-to-paste prompt: `prompts/02-device-dispense-care-plan.md`

---

## 4. Workstream C — Technical requirements (ТВ) modules

### Already produced

| Artifact | Path |
|----------|------|
| Audits | `.cursor/tv-compliance/3.10-care-plan.md`, `3.8-…`, `3.9-…`, `3.17-…`, `3.20-…`, `3.22-…` |
| Module prompts v3 | `.cursor/tv-prompts/00-base-v3.md` + per-module |
| Draft PRs | #813 (3.10), #814 (3.22 handover), #645 (3.8 open) |

### Status snapshot

| Module | Coverage (audit) | Implementation |
|--------|------------------|----------------|
| 3.8 Compositions | high in PR | Needs **user KEP UAT** |
| 3.10 Care Plan | ~72% audit; P0 partially coded on #809 | Finish tests/PR #813 |
| 3.22 Device Dispense | NOT COMPLIANT historically; #804 + #816 partial runtime | Full TV still on #806/#814 for colleague |
| 3.9 / 3.17 / 3.20 | Audits only | Not started |

### Pattern for new module chat

1. Read `00-base-v3.md` + module audit.
2. 1 issue → 1 branch → 1 draft PR.
3. Server-side enforcement + UK lang strings + tests.
4. STOP before push if prompt says wait for UAT (base prompt); some prompts allow push when explicit.

Ready-to-paste prompts: `prompts/03-tv-310-continue.md`, `prompts/04-tv-38-uat.md`, `prompts/05-tv-new-module-template.md`

---

## 5. Architecture cheat-sheet (where code lives)

| Domain | Typical locations |
|--------|-------------------|
| Care Plan UI | `app/Livewire/CarePlan/*`, `resources/views/livewire/care-plan/*` |
| Care Plan services | `app/Services/MedicalEvents/CarePlan*`, repositories |
| Device Request / program guard | `DeviceProgramParticipationGuard`, `DeviceRequestMapper`, Care Plan activity managers |
| Encounter Package | `EncounterCreate`, `EncounterPackageBuilder`, `EncounterPackageLoader`, `resources/views/livewire/encounter/parts/*` |
| Device Dispense | Encounter parts + mappers; patient `DeviceDispenses` registry (may still be mock in places) |
| Employee login sync | `app/Listeners/eHealth/EmployeeCreate.php`, EmployeeRequest models/jobs |
| Scopes | `config/scopes/roles.php` |
| UK copy | `resources/lang/uk/*` |

eHealth clients: `app/Classes/eHealth/EHealth.php` (+ resource classes).

---

## 6. Testing recipe (always)

```bash
vendor/bin/sail artisan config:clear
# Confirm phpunit.xml → DB_DATABASE=testing
vendor/bin/sail artisan test --compact --filter='…'
vendor/bin/sail bin pint --dirty --format agent
```

Never `migrate:fresh` / wipe on `mis_dev` without dump + explicit user OK.

---

## 7. Open decisions for the human (not for agent to guess)

1. **#803:** merge gate-only now vs add scoped getById apply in same PR?
2. **#792:** ready for review/merge after product UAT of no-program device + eRx tabs?
3. **ТВ wave:** continue 3.10 (#813) vs pause for colleague’s 3.22?
4. **3.8:** schedule KEP UAT on #645.

---

## 8. Prompt index

| File | Use when |
|------|----------|
| `prompts/00-system-context.md` | Paste at start of **every** new chat |
| `prompts/01-employee-802-hotfix.md` | Continue EmployeeRequest apply fix |
| `prompts/02-device-dispense-care-plan.md` | Device dispense / #792 / #816 |
| `prompts/03-tv-310-continue.md` | Finish Care Plan TV #809/#813 |
| `prompts/04-tv-38-uat.md` | Guide user through compositions UAT |
| `prompts/05-tv-new-module-template.md` | Start 3.9 / 3.17 / 3.20 |
| `prompts/06-pr-review-colleague.md` | Review-only (no code) of colleague PRs |

Transcripts for deep context:  
`~/.cursor/projects/home-mefizz-projects-ohealth/agent-transcripts/{bfe306f0…,92402710…,6002d239…}/`
