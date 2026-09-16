# Prompt — Colleague PR review only (no code changes)

Paste after `00-system-context.md`.

---

## Mission

Analyze a colleague’s pull request and produce **advice** for the author. **Do not** modify application code, push branches, or open competing PRs unless the user explicitly switches you to implementation mode.

## Inputs the user will provide

- PR URL (e.g. `https://github.com/openhealths/nationHealth/pull/NNN`)
- Symptom (“dropdown empty”, “403”, eHealth validation error)
- Optional: eHealth wiki / TV excerpt

## Method

1. `gh pr view` / `gh pr diff` / checkout their remote branch **read-only** if needed.
2. Trace data path: Livewire UI → form validation → mapper → package builder → eHealth client → jobs.
3. Compare against eHealth rules and local patterns (scopes, status enums, camelCase vs payload snake_case).
4. Separate: **bugs** vs **by-design filters** vs **missing scopes/env**.

## Output format (Ukrainian, concise)

1. **Вердикт** (1–3 sentences)
2. **Root cause** table (symptom → cause → evidence file:line)
3. **What already works**
4. **Advice to author** (prioritized patches; no full rewrite unless necessary)
5. **Manual UAT algorithm** for the user
6. **Test gaps** to suggest (do not commit tests into their branch)

## Known domain gotchas (Device Dispense)

- Local DB status `processed` ≠ query filter `active` if someone still reads local table.
- Device Request **with program** cannot be `based_on` in Encounter Package.
- 403 on `device_requests` → scope, not empty catalog.
- Alpine `@js(deviceRequests)` is a mount snapshot — ensure API load finishes in `initializeComponent`.

## Known domain gotchas (EmployeeRequest)

- Edit apply must key off **EmployeeRequest** status, not Employee APPROVED.
- Some roles lack `employee_request:read` — login must not hard-depend on that API.

## Done when

User has a review they can paste to the colleague. No local feature commits.
