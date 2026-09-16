# Prompt template — New TV module (3.9 / 3.17 / 3.20)

Paste after `00-system-context.md`. Replace `{{MODULE}}` placeholders.

---

## Mission

Implement remaining gaps for **TV {{MODULE_ID}} — {{MODULE_NAME}}** so MIS can pass technical-requirements testing. One issue → one branch → one draft PR.

## Inputs (read in order)

1. `.cursor/tv-prompts/00-base-v3.md`
2. `.cursor/tv-compliance/{{COMPLIANCE_FILE}}` (existing audit — do not redo full discovery)
3. Exact TV text supplied by the user in this chat (authoritative wording for UK strings)

## Git setup

```bash
git fetch origin fork
# Prefer basing on latest stable Care Plan stack if module depends on plans/activities:
#   fork/main OR origin/i768_i789_care_plan_erx_referral OR origin/i809_… after merge
git checkout -b i{{ISSUE}}_tv_{{SLUG}} <chosen-base>
```

Create issue in `openhealths/nationHealth` if missing. Push only to `origin`. Open **draft** PR to parent `main`.

## Method

1. From audit: list P0 vs P1; implement P0 first.
2. Enforce rules **server-side** (Form Request / Livewire action / Policy / Service), not UI-only.
3. Ukrainian TV strings → `resources/lang/uk/…`
4. Mapper + repository + eHealth client patterns: copy sibling modules (Care Plan / Medication Request / Device Request).
5. Tests: happy path + rejection path per P0.
6. Append post-implementation matrix to the compliance markdown.

## Isolation

If using worktrees: only `.worktrees/tv_{{SLUG}}`. Do not edit other worktrees or unrelated PRs (#803, #645, #814) unless user asks.

## STOP gates

- No KEP bypass.
- No `mis_dev` wipe.
- Ask user before push if base prompt says wait for UAT; otherwise push draft and mark **ГОТОВО ДО UAT**.

## Module-specific cheat sheet

| TV | Compliance file | Notes |
|----|-----------------|-------|
| 3.9 Medication Request | `3.9-medication-request.md` | eRx; may depend on Care Plan activities |
| 3.17 Service Request | `3.17-service-request.md` | Referrals / SR lifecycle |
| 3.20 Device Request | `3.20-device-request.md` | Align with optional program work on #792 |
| 3.22 Device Dispense | `3.22-device-dispense.md` | Prefer colleague #806/#814 unless user reassigns |

## Final reply format

- % TV points closed
- Files touched
- Tests run
- Draft PR URL
- Manual UAT steps (incl. KEP)
