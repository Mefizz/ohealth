# Care Plan FHIR migration — module 3.10

Tracking: openhealths/nationHealth#997. Baseline: main `78e53572ed3e4068b9745c3a5a4abd5dacbd3f71`.

## Boundaries

This is a sequential backend migration of Care Plan and Care Plan Activity. The independent DeviceRequest flow created from an Encounter without a Care Plan belongs to a separate change. Referral and prescription payloads are not rewritten here. Blade, JavaScript and visual changes are specified separately; server-side Livewire adapters may change without changing their public fields/actions.

The target is the project's FHIR-shaped model, not generic HL7 JSON. ESOZ resource names, cardinalities, action envelopes and signature contracts take precedence on the wire. The existing FhirMapperContract and Fhir facade remain the extension points. No new dependencies or atomic service layer are needed.

## Internal contract

- Resource `id` is an allocated UUID, distinct from the SQL primary key. Mapping never allocates a replacement identity. A draft UUID is not proof of remote registration.
- Field names are camelCase internally; physical SQL and ESOZ JSON keys retain their contracts.
- Reference is `{identifier: {type: {coding: [...]}, value: uuid}}`. Do not discard the resource type or infer that an untyped reference denotes a Condition.
- CodeableConcept retains its full coding list and text. Goals are concepts, not identifier references.
- Activity author, reasonCode, reasonReference, goal and outcomes retain their collection cardinality.
- Activity detail contains kind/status/product/quantity/schedule; outcomeReference and outcomeCodeableConcept are resource-level fields.
- Quantity retains value/system/code/unit/comparator, including zero when supplied. Validity and positivity are separate validation concerns.
- ISO dateTime values retain their instant and available precision. A form round trip must not reset time to midnight or move it by a safety interval.
- Form conversion accepts the original resource in context. Unrepresented fields must survive; explicit null and empty collections represent an intentional clearing operation.
- A full resource import and a summary metadata update are distinct operations. Omitted summary fields cannot erase a full resource.

## Review blocks

1. Pure mappers, facade, kind enum and synthetic fixtures. No production caller switches yet.
2. Repositories, relation completeness, precision, preflight/backfill and schema migration.
3. API serialization/normalization and sync orchestration outside database transactions.
4. Server-side Livewire integration, removal of obsolete paths and actionable UI specification.

Each child PR targets the personal fork's umbrella branch. The complete umbrella targets company main. Child blocks are cumulative and are released together, not independently to production.

## Sources

Checked 2026-10-19. The source register captured on 2026-10-17 records these PROD page versions (updated 2026-10-15); API examples alone are not a substitute for semantic method rules.

- [Create Care Plan, API-007-005-0001](https://e-health-ua.atlassian.net/wiki/spaces/ESOZ/pages/17571905622), page version 60.
- [Create Care Plan Activity, API-007-006-0002](https://e-health-ua.atlassian.net/wiki/spaces/ESOZ/pages/17571643554), page version 53.
- [Get Care Plan Activity, API-007-006-0003](https://e-health-ua.atlassian.net/wiki/spaces/ESOZ/pages/17571709053), page version 37.
- [Cancel Care Plan Activity, API-007-006-0005](https://e-health-ua.atlassian.net/wiki/spaces/ESOZ/pages/17570759126), page version 35.
- [Complete Care Plan Activity, API-007-006-0006](https://e-health-ua.atlassian.net/wiki/spaces/ESOZ/pages/17571905639), page version 34.

Fixtures under tests/Fixtures/CarePlan contain generated identifiers and synthetic dictionary codes. They test structure and lossless conversion; they are not valid clinical documents for submission or substitutes for ESOZ UAT.

## Release gate

Do not migrate the development/production database during implementation. Rehearse installation and upgrade in a disposable environment, then run preflight, backfill, repeat-run and comparison checks. Report ambiguous ownership, untyped references, duplicate identities and irrecoverable historical precision. Do not guess missing clinical values.

One deployment window: backup, suspend module writes/jobs, preflight, schema migration, repository-backed backfill, reconciliation, deploy compatible callers, smoke test, resume. A required UI change identified by the specification blocks cutover until implemented. Old columns may remain for controlled recovery but must not become a second active source of truth.

Tests require explicit approval under repository database safety rules. Use DatabaseTransactions; never add RefreshDatabase or LazilyRefreshDatabase. Real KEP signing requires the user's participation.
