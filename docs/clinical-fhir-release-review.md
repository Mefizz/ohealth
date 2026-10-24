# Clinical FHIR release review — 24.10.2026

Company main: `b70debf113ecfd4f06da167c57f50981d2f47e3f`. Verified application tree: `fb1352088210f0b59c02d7a339a233eda2623dc7`. All branch heads are in `Mefizz/ohealth`.

## Mandatory merge and deployment order

Merge #1022 → #998 → #1006 → #1011 → the TV 3.8 Composition umbrella. The last umbrella contains the entire reviewed release; deploy after the complete batch, with the normal backup and install/update migrations. Intermediate stacked slices are review boundaries, not independently accepted deployment candidates. Company PR diffs shrink as prerequisites merge.

## Module heads

- `i1008_preserve_legacy_coding_output`: `349397c45e3cd164a2a0fa0fbae67fe107f095f8` (before this documentation-only final commit).
- `i997_care_plan_fhir`: `e3a6cd4b467000516584f79caa893c2526e96d07` (before this documentation-only final commit).
- `i1005_referral_fhir`: `164f8fb67f8a1eea85ababe9b4f74f6a227615f6` (before this documentation-only final commit).
- `i1008_medication_fhir`: `e814df8b5e0f3c767c8c7c230d5c3724a40592ca` (before this documentation-only final commit).
- `i644_composition_registry_uat_fixes`: `e53816752ce608951078898bda40b3947808f0cc` (before this documentation-only final commit).

## Focused review blocks

- CarePlan: fork PR #17 mapping, #18 persistence, #19 ESOZ integration, #20 workflows. Design base includes the shared prerequisite. Their final tree matches #998.
- Referrals: fork PR #21 mapping, #22 persistence, #23 workflows. Design base includes #998. Their final tree matches #1006.
- Prescriptions: fork PR #24 mapping, #25 persistence, #26 signing/workflows. Base is #1006; #26 is also the company umbrella head.
- Medical Conclusions: [focused comparison against the prescription head](https://github.com/Mefizz/ohealth/compare/i1008_medication_fhir...i644_composition_registry_uat_fixes).

## Verification

- Disposable PostgreSQL 18 + PHP 8.5 containers, synthetic empty `testing` database. Dedicated internal Docker network blocks live external ESOZ. No dev/UAT database copy, session copy or credentials used.
- Fresh project install + update completed successfully, including shared and Composition precision migrations.
- Owned domain regression: **832 tests / 3248 assertions**, exit 0. Final changed route fixtures and added signing cases were then checked in the focused run below.
- Final focused regression: **92 tests / 363 assertions**, exit 0. Includes Encounter/CarePlan full MR hydration and GET failure, durable SIGNED acknowledgment, GET-only recovery, mismatched identity/patient, idempotence, terminal MR preservation, transfer lookup/flash, patient-scoped routes, Composition fidelity and configurable timezone/DST.
- PHP/PHPUnit emitted existing deprecation notices (1 runtime deprecation and 19 PHPUnit deprecations); no test failures in those successful runs.
- Shared and module snapshots reproduce the tested combined application tree exactly before this documentation-only commit. Active UAT branch retains its previous history and a recoverable backup reference.

## Remaining acceptance boundaries

Browser/KEP acceptance is distinct from automated tests. Finish PMD/SMD acceptance using `clinical-fhir-combined-uat.md`; the nocturnal date-only MRR/Encounter case, positive clinical transfer and one MR search 403 remain recorded there. Do not claim all TV requirements certified. Signing-duration investigation was explicitly deferred.

ClinicalImpression and unrelated standalone DeviceRequest work are excluded. The updated main frontend is retained; forms reuse project patterns. Canonical clinical writes use complete typed references, codings, periods and structured dosage, while native ESOZ signing payloads stay untouched. Short/list responses are not promoted to complete FHIR details.

No blanket UTC migration is claimed: `APP_DISPLAY_TIMEZONE` controls the current local UI bridge and day boundaries; native canonical timestamp fields use UTC, and date-only fields remain dates.

## CI corrections for the intermediate module heads

The first publication exposed failures on #998/#1006 while #1022/#1011/#1042 passed. These were real slice integration defects, not disabled checks:

- CarePlan prescription sync now calls the existing repository transaction that keeps SIGNED MRR and separate MR states; the old inline status overwrite was removed.
- The early CarePlan referral layer previews persisted draft fields without calling the canonical `formData` method introduced only by the later referral layer. Complete canonical import/serialization remains enforced in that later layer.
- CarePlan's early referral create/sign paths preserve full start/end instants instead of reducing them to `Y-m-d`; the medical-device date-only path is unchanged.
- Missing draft fixture construction and signature-period fixtures were completed. The early slice asserts the same persisted UTC moments; the referral slice retains its stronger complete `getResource` assertions. No tests or CI checks were skipped or weakened.
- Exact failing scenarios: 5 tests / 51 assertions passed. CarePlan scope: 40 / 255; referral scope: 40 / 256; related lifecycle/period units: 17 / 66. All exit 0 on isolated synthetic `testing`.
- Corrections propagate through ordinary forward merges; no published history was rewritten during the CI repair. The combined application's app/resources/routes/config/database tree is unchanged from the already successful full-release baseline; one malformed test-method whitespace boundary was cleaned up.

Fresh GitHub CI is the final gate for these corrected intermediate heads. See the live checks on #998/#1006 rather than treating the first failed runs as current results.
