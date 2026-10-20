# Combined clinical FHIR UAT

Integration branch: `i1008_clinical_fhir_uat`, issue #1008. This branch is for combined review and manual acceptance; it is not a deployment approval or a replacement for the module umbrellas.

## Included changes

Based on company `main` at `6b074395bf5e514d2f7bf8acc42042921034c357` (verified against the remote on 2026-10-20). Includes the final integrated trees of:

- 3.10 CarePlan / CarePlan Activity: #998, source `765d2302d8ce6905441f0ecf3b9029317514f026`.
- 3.17 ServiceRequest: #1006, source `93e867618bfccc4db34af03e0f935345424c04da`.
- 3.9 MedicationRequest: #1011, source `95bccb1e0d4458d65b890941df8b4413102d3999`.
- Integration corrections from `62868a68a0a80e7cfedc7e086331558c0f5654bd`: canonical shared primitives, linked MRR/MR quantity accounting, patient/plan/clinic activity ownership and compatible regression fixtures.

The module branches remain separate. The combined tree is squashed over main to keep the UAT branch history reviewable.

## Known incomplete work

Medical Conclusions (3.8) use the main implementation. Their existing tests pass, but four additional round-trip probes fail: explicit false extension value, Identifier type coding, Event code and fractional timestamp precision. These are open defects, not fixes included here.

Standalone DeviceRequest changes from another task are not imported. Changes already merged into the stated main baseline remain included.

No new frontend is implemented by this branch. Mandatory form changes are described in `care-plan-fhir-ui-spec.md`, `service-request-fhir-ui-spec.md` and `medication-request-fhir-rollout.md`. In particular, old scalar dosage controls cannot supply the complete structured prescription contract. Record such a path as blocked; do not invent units, timing or dose denominators to make it pass.

## Test environment

Use a separate local application and database copied from the authorized preprod development environment. Apply schema changes only to that copy. Do not run automated tests against the UAT copy: use the disposable PostgreSQL database named `testing`. Do not replay copied jobs or reuse copied sessions. Keep UAT results until testing is complete.

Target accounts: `+pmd35` (PMD) and `+outp35` (SMD). Check the actual clinic and employee role after each login. Use designated synthetic preprod patients only. Every KEP operation is a handoff to the human tester; do not enter a key password or sign on their behalf. After a signature, reconcile the remote result before repeating an operation whose outcome is uncertain.

## Manual acceptance sequence for each clinic

Record scenario, clinic, role, patient fixture, local/remote identifiers, expected result, actual result and status (pass/fail/blocked/not run). Keep personal data and tokens out of the shared report.

1. Login, verify clinic/role, patient access and lists. Refresh complete remote details where historical resources require reconciliation; summary lists are insufficient.
2. CarePlan: create a draft, save/reopen/edit without losing fields, check exact period boundaries and references. Hand off creation signing. Refresh registered plan; check status and identity. Exercise approvals with the user's OTP participation where required.
3. Activities: medication, service/service group and device kinds. Check product/reference types, units, precise quantity, schedule, reason/goal/outcome preservation and linked parent. Check remaining quantity after linked documents and refresh. Test completion and user-signed cancellation only on designated synthetic resources.
4. Referrals: create from Encounter and Activity; save/reopen, prequalify, hand off signing, refresh status, inspect actual requester/context/program/quantity. Verify execution state is distinct from clinical status. Check cancel/recall reasons and explanation controls; hand off the signature. Unsupported controls/actions remain blocked according to the UI specification.
5. Prescriptions: check Encounter and Activity flows, full structured Dosage, program/product and container units. Before signing, require fresh matching NEW MRR details including seed. After the user signs, verify separate SIGNED MRR and ACTIVE MR identities and count quantity once. Record missing structured dosage controls or missing remote seed as blockers.
6. Cross-clinic/patient isolation: a foreign UUID must not permit a mutation or launch the signer. Expired session, denied request, validation failure and unresolved job must not show successful clinical state or create duplicates.
7. Medical Conclusions: smoke-test the existing workflow and user-assisted signing on synthetic records, but keep the four known fidelity defects open. A successful browser flow alone does not close them.

Automated synthetic regression results and browser acceptance are separate evidence. Do not mark an unexecuted browser scenario or a blocked signature as passed.
