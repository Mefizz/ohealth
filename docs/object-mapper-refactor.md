# ObjectMapper refactor — #841

Issue: https://github.com/openhealths/nationHealth/issues/841

Branch: `Mefizz/ohealth:i841_object_mapper_service_request`.
Base: PR #792 head `4b1f0e732700f860a3f1a0ad402bc8ae79b874db`.
This is an independent branch. Rebase onto upstream main after #792 is merged;
never push these commits to the #792 head branch.

## First increment

- Symfony ObjectMapper 8.1.5 with a Laravel provider and explicit callable bindings.
- A typed ServiceRequest input snapshot with caller-supplied time and resolved UUID context.
- Separate prequalify envelope and flat signed-create targets, with shared body fields.
- Explicit mapping of object collections and pure FHIR value transforms.
- Symfony Serializer normalization; stable legacy field order at the KEP boundary.
- The encounter signing caller uses the new payload contracts directly.
- Other ServiceRequest callers use temporary compatibility methods that delegate to the same implementation.
- Eight synthetic baseline cases captured from the unmodified #792 mapper, using `Europe/Kyiv` and a fixed clock.

No changes to HTTP endpoints, database schema, job verdicts, ownership, quantity gates or signature handling.
The raw-document eRx signing path is not involved in this increment.

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
- Move the remaining direct ServiceRequest callers to the typed entrypoints where appropriate.
- Exercise the complete create/sign/sync flow, then assess whether this pattern reduces maintenance work.
- Preserve legacy `toFhir`/`fromFhir` until their separate contracts and callers are migrated.

Do not infer that a successful mapping spike completes the full service refactor.
Follow-up stages are DeviceRequest, eRx, care plan, activities, and then lifecycle responsibilities.
Approvals/OTP, dispense, other encounter mappers and Composition remain separate changes.

## Validation

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
