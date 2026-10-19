# Module 3.17 — Service Request FHIR migration

Issue: #1005. Initial baseline: company main 041a3346. Care Plan #998 remains a separate draft; this block must not merge or publish its branch to the company repository.

## Design before implementation

The existing ServiceRequestMapper is replaced in place. The canonical representation is the ESOZ Service Request profile with camelCase application keys, Reference for code (service/service_group), object category, Quantity, requesterEmployee/requesterLegalEntity, typed context, ordered basedOn and supporting/reason/permitted/specimen/focus reference collections. Generic HL7 Requester/quantityInteger/category-list output is retired. The existing Fhir facade remains the entry point. Flat form fields are projections and carry the original resource as conversion context so an unrepresented value survives an edit. UUID allocation belongs to draft orchestration; mapping never generates identity or reads the clock. Occurrence values are never shifted, clamped or padded during serialization.

Endpoint create and PreQualify share that resource mapping but have different envelopes. The current create signs the flat service request and singular program; PreQualify wraps service_request and programs. Server-owned fields are excluded from create. Cancel/Recall fetch a fresh raw GET snapshot and add only documented status_reason/explanatory_letter; no case conversion, field filtering, or empty/false removal may touch that snapshot. A failed or mismatching GET prevents signing. Clinical status is updated only after successful job resolution; pending/error is not success.

Persistence remains in ServiceRequestRequestRepository and uses the project's existing Identifier/CodeableConcept/Quantity/Period primitives. Reuse complete primitive methods introduced in #998 as identical shared code; no small new services. New schema changes are additive, guarded for either module installation order. Preserve full coding metadata and ordered reference collections; metadata holds unmodeled resource fields, not a second authoritative raw snapshot. Existing list/filter columns are compatibility projections of the canonical resource. Cross-patient UUID reuse is rejected. External executor caching is distinct from issuer author identity; never attribute an external author to the current employee.

No broad cleanup of the 1,000-line combined ServiceRequest/DeviceRequest lifecycle service: replace the Service Request mapping/integration seams and move newly introduced DB access into the existing repository. DeviceRequest behavior is outside this module. Do not change auth/scopes/shared job infrastructure or Blade/JavaScript.

## Review blocks

1. Mapper contracts and endpoint serialization (synthetic fixtures and regressions).
2. Repository storage, additive schema, import/form/sign context and data conversion.
3. Server-side caller cutover, exact-snapshot cancel/recall, UI specification and operational runbook.

One umbrella PR, three cumulative review PRs; release the complete module together. This continues the previously authorized module-by-module split, not a batch of atomic PRs.

## Source register and discrepancies

Public Confluence content re-fetched 2026-10-19 through its REST API:
- Create API-007-062-0002: page 17572102196, version 52, updated 2026-10-15.
- PreQualify API-007-062-0001: page 17572102185, version 72, updated 2026-10-15.
- Details API-007-062-0006: page 17572167722, version 34, updated 2026-10-15.
- Cancel API-007-062-0007: https://e-health-ua.atlassian.net/wiki/spaces/ESOZ/pages/17572266059, version 38, updated 2026-10-18.
- Recall API-007-062-0008: https://e-health-ua.atlassian.net/wiki/spaces/ESOZ/pages/17572200551, version 28, updated 2026-10-18.
- Use API-007-062-0012: page 17572167739, version 26, updated 2026-10-15.
- Complete API-007-062-0015: page 17572134964, version 32, updated 2026-10-15.

Current Cancel/Recall link to docs.ehealth.gov.ua; anonymous retrieval returned HTTP 403. Older APIary snapshots from the prior audit are structural corroboration, not proof of freshness of that blocked specification. Confluence explicitly requires the complete stored resource for signed lifecycle changes. Preserve raw signed snapshots even if they contain properties not represented by the form. The create JSON schema versus narrative authored_on rules need sandbox confirmation; do not fabricate authored_on. The technical requirements demand explanatory text for cancel/recall even where the API narrative calls it optional.

## Acceptance and cutover gates

Run synthetic mapper round-trip and API body/HTTP failure contracts, precision and idempotent persistence, cross-patient identity, old draft conversion, existing Encounter/Care Plan lifecycle regressions and integration with #998. Use Sail and DatabaseTransactions, fake HTTP, disposable testing database only. No production/development migration or real KEP during implementation.

Before release: backup and restore rehearsal; read-only preflight and complete reimport of registered historical records; explicit write conversion of recoverable drafts; repeat-run/reconciliation; manual KEP and ESOZ UAT; required UI specification items. Historical precision/unknown reference types cannot be guessed. Backend conversion is not 100% module certification.
