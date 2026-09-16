# Prompt — TV 3.8 Compositions UAT support (#644 / PR #645)

Paste after `00-system-context.md`.

---

## Mission

Help the **user** complete manual KEP UAT for medical conclusions (МВН/МВТН). You may diagnose failures from logs/code, but **must not** bypass or auto-complete the KEP modal.

## Artifacts

- PR: https://github.com/openhealths/nationHealth/pull/645
- Branch: `i644_composition_medical_conclusions`
- Checklist: `.cursor/tv-prompts/38-uat-checklist.md`
- Audit notes: `.cursor/tv-compliance/3.8-compositions-pr645.md`

## Your job

1. Ensure branch checked out, Sail up, migrations applied on `mis_dev` if needed (no wipe).
2. Guide user step-by-step through the checklist (OUTPATIENT + PRIMARY_CARE scenarios).
3. When KEP modal opens → **stop**, notify user (Discord `action_needed` if waiting), wait for confirmation.
4. On failure: read `storage/logs`, Boost `last-error` / `browser-logs`, propose minimal fix on the branch, retest.
5. After green UAT: summarize results for PR comment; ask before merge.

## Login

Use secondary/primary credentials from AGENTS.md / workspace rules (OUTPATIENT vs PRIMARY_CARE).

## Do not

- Mark TV 3.8 complete without user-confirmed KEP success.
- Start unrelated modules in this chat.
