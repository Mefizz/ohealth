# ObjectMapper refactor — #841

Issue: https://github.com/openhealths/nationHealth/issues/841

Full staged plan: [ehealth-object-mapper-plan.md](ehealth-object-mapper-plan.md).

Branch: `Mefizz/ohealth:i841_object_mapper_service_request`.
Base: PR #792 head `d91299f72ffa115de7a441d7cb6a8d3475f6fe94` (updated 2026-09-22).
This is an independent branch. Rebase onto upstream main after #792 is merged;
never push these commits to the #792 head branch.

## First increment

- Symfony ObjectMapper 8.1.5 with a Laravel provider and explicit callable bindings.
- A typed ServiceRequest input snapshot with caller-supplied time and resolved UUID context.
- Separate prequalify envelope and flat signed-create targets, with shared body fields.
- Explicit mapping of object collections and pure FHIR value transforms.
- Symfony Serializer normalization; stable legacy field order at the KEP boundary.
- Encounter/care-plan prequalify and encounter/care-plan/patient-registry signing use the new contracts directly.
- Legacy public mapper methods remain compatibility delegates; their outbound construction is removed.
- Eight synthetic baseline cases captured from the unmodified #792 mapper, using `Europe/Kyiv` and a fixed clock.

No changes to HTTP endpoints, database schema, job verdicts, ownership, quantity gates or signature handling.
The raw-document eRx signing path is not involved in this increment.

## Updated base and next increments

The six new #792 commits preserve the outbound ServiceRequest mapper unchanged; golden fixtures still
record their original `4b1f0e7` capture. They were checked against `d91299f` without regeneration.
The rebase retained the localized toast behavior and the explicit ownership imports in the signing UI.
Upstream main `186ecd08` additionally changes personal-data sync in `PatientData.php`; it is outside this
branch's base until #792 is merged and the final rebase is performed.

Preserve these newer contracts in subsequent stages:

- `DeviceActivityReadinessAssessment` already lives in `App\Dto\MedicalEvents`.
- Medication source/resource type and care-plan terms already have enums under `app/Enums`.
- Contract sync validates all pages before a transaction and COMPLETED status; partial lists are not authoritative.
- Approval confirm/deactivate require successful responses before the UI grants access or reports success.
- Care-plan eRx sync handles multiple prescriptions; failed UI requests clear loading state.
- AJAX emits one localized toast, while redirects use session flash. Both public activity handlers remain in use.

Finish #841 in two reviewable increments: outbound mapping/callers, then inbound Write with a captured
baseline for partial updates and Identifier relationships. Only after the complete flow passes should
DeviceRequest, eRx and care-plan mapping adopt this pattern. Do not rename whole lifecycle services into Actions.

Regression preparation also corrected old referral fixtures that used activity/encounter primary keys
as Identifier foreign keys, updated the readiness DTO namespace, and isolated certificate-authority lookup.
A separate fix removes the repeated `#[Locked]` on the standalone eRx form; one lock remains in place.

## Integration findings

Laravel's container supports PSR-11, but its `has()` does not advertise every autowirable class.
Class-name transforms must be explicitly bound: the mapper checks `has()` before `get()`.
The same rule applies to future class-name conditions. Attribute-instantiated callables need no binding.

`MapCollection` maps objects, not associative arrays. The legacy-input boundary prepares each reference
as an object and reindexes list entries. It preserves the existing distinction between an omitted list
and a supplied list whose incomplete references are filtered out.

Missing optional DTO values normalize to omitted keys. An explicit empty list and `0.0` remain intact.
`SignatureService` continues to apply its existing JSON flags. Contract tests compare its actual JSON
argument to Cipher, not the nondeterministic PKCS#7 signature.

Date calculations consume the supplied time in the application timezone, including DST rules.
Transform methods avoid names that PropertyAccessor could interpret as accessors for mapped fields.

`EHealthServiceRequestBody` shares the wire fields between create and prequalify; it is not a generic
mapping base class. The field-order list only preserves signed bytes; it does not transform values.

## Boundaries

- Mapping: no SQL, HTTP, session, UUID generation or current-clock lookup.
- Api: `app/Classes/eHealth/Api`; request execution remains there.
- Repository: persistence, scoped lookup, Identifier links and transactions.
- Enums: `app/Enums`.
- Livewire/Blade: user interaction, signature modal and presentation.
- Future Actions: multi-step operations shared by multiple callers, not replacement service buckets.

## Remaining scope of #841

- Consolidate inbound ServiceRequest mapping into a Write contract without losing partial-field semantics.
- Exercise the complete create/sign/sync flow, then assess whether this pattern reduces maintenance work.
- Preserve legacy `toFhir`/`fromFhir` until their separate contracts and callers are migrated.

Do not infer that a successful mapping spike completes the full service refactor.
Follow-up stages are DeviceRequest, eRx, care plan, activities, and then lifecycle responsibilities.
Approvals/OTP, dispense, other encounter mappers and Composition remain separate changes.

## Validation

2026-09-22, on the rebased branch in isolated Sail PHP 8.5.3/PostgreSQL:

- 124 tests / 608 assertions, no failures or errors. One existing PHP 8.5 PDO deprecation remains.
- Coverage: mapping/KEP bytes, referral create/sign/sync/registry, FHIR references, standalone signing,
  contract pagination, approval response contracts/resend/inpatient confirmation, care-plan actions/toasts,
  medication registry sync and encounter standalone flows.
- PHP Pint: all 22 changed PHP mapping/caller/test/config files pass, using the project's rules with only
  the Blade formatter disabled (its npm plugins are absent from the isolated runtime; no Blade files changed).
- `composer validate --no-check-publish`, `composer check-platform-reqs` and `git diff --check` pass.

Run in the project's Sail PHP environment with an isolated `testing` database:

```sh
php artisan config:clear
php vendor/bin/phpunit tests/Unit/Mapping/ServiceRequestPayloadsTest.php \
  tests/Unit/Services/MedicalEvents/ServiceRequestMapperTest.php \
  tests/Unit/Services/MedicalEvents/DeviceRequestMapperTest.php \
  tests/Unit/Services/MedicalEvents/ReferralRequestLifecycleWriteTest.php
```

The mapping suite covers exact arrays/JSON, actual SignatureService JSON input, injected-time determinism,
no database/HTTP work, and reuse of Laravel transform bindings inside nested collections.
Database-backed regression tests must additionally cover encounter signing, care-plan referrals,
FHIR Identifier relationships and the patient registry. Actual KEP/eHealth UAT is still required before rollout.

The fixture baseline records its source commit, timestamp and timezone. Do not regenerate expected
fixtures from the new mapper to make a failing contract test pass.
