# ObjectMapper refactor — #841

Issue: https://github.com/openhealths/nationHealth/issues/841

Full plan: [ehealth-object-mapper-plan.md](ehealth-object-mapper-plan.md).
Branch: `Mefizz/ohealth:i841_object_mapper_service_request`.
Base: upstream main `b2239108`, rebased 2026-09-30 after #792 merged on September 24.
Never push refactor commits to the #792 head branch.

## Implemented

- Symfony ObjectMapper 8.1.5, Laravel provider, explicit callable bindings and Serializer normalization.
- ServiceRequest prequalify and signed-create contracts, preserving the exact JSON passed to KEP.
  The source snapshot carries caller-supplied time and resolved UUID context. HTTP and persistence stay outside mapping.
- Encounter/care-plan prequalify and encounter/care-plan/patient-registry signing use these contracts.
  Legacy public outbound methods delegate until their remaining callers migrate.
- DeviceRequest outbound now maps to `app/Dto/DeviceRequest/Ehealth`, `EhealthCreate` and
  `EhealthPrequalify`. Signing callers in care plan/patient registry and the existing prequalify callers
  use the DTO mapping directly. Collections use `MapCollection`; small pure device transformations
  stay beside the DTO. The adapter only prepares native objects, supplies explicit UUID/time context
  and normalizes the wire contract; it performs no lifecycle operations.
- Device create remains flat, with a fixed active status, one of code/reference and optional program;
  prequalify uses its separate envelope. Exact legacy field order, integer quantities including zero,
  invalid-reference empty lists, UTC clock skew and occurrence-date rules are preserved.
  The old DeviceRequestMapper outbound methods delegate instead of retaining a second implementation.
- `ServiceRequestModelData` and `DeviceRequestModelData` accept both validated local fields (`ArrayObject`)
  and eHealth JSON (`stdClass`) through `SourceClass`. These are one-line adapters, not new source DTOs.
  Shared metadata lives in `ReferralModelData`; resource-specific fields stay on the corresponding target.
- Draft creation and inbound synchronization use these targets. Remote reference rows use `MapCollection`;
  already local array rows retain their form representation. Mapping performs no SQL or HTTP.
- `toSyncPatch()` preserves the old import contract: null/empty scalars and empty reference lists do not
  clear local fields. Zero remains an update; `inform_with: []` keeps its distinct previous behavior.
  Author and Identifier relationships come from the authorized caller and are resolved by Repository.
- Removed `CarePlanLifecycleService` and `CarePlanActivityLifecycleService`. Livewire calls the existing
  APIs through `createSignedAndResolve`, `cancelAndResolve`, `completeAndResolve`; GET callers use
  `getDetails()->getData()`. Existing low-level methods and response types remain unchanged.
- Removed `EHealthJobResolver`. Polling, 404 href fallback, timeout and verdict checks now belong to
  `Api/Job`. `Enums/EHealth/JobStatus` describes remote statuses separately from local queue statuses.
  Async approval/OTP jobs retain their existing workflow.
- Removed `CarePlanLifecycleGateService`. Request repositories find open documents through Identifier
  relationships; activity/document enums describe status properties. Protected Livewire methods produce
  the existing cancellation/completion blocking messages.
- Removed `CarePlanActivityEHealthGuard`. A narrow Livewire registration concern uses the existing API.
  Only a 404 becomes "activity absent"; authorization and server errors retain their transport exception.
- Removed `InformWith`. `AuthMethodId` extracts the identifier as a pure ObjectMapper transform;
  selection/display formatting stays in the encounter concern.
- Patient ServiceRequest/DeviceRequest APIs now own signed create/cancel and prequalify resolution.
  ServiceRequest owns recall resolution. Livewire callers choose the API explicitly; the referral
  lifecycle no longer exposes these transport wrappers. INVALID prequalify verdicts remain blocking.
- Removed `MedicalRequestOwnership`. Request repositories resolve UUIDs within the patient/encounter
  and explicit facility context; the shared query concern uses no session/container context. Approval
  lookup stays scoped to the current care plan. All Livewire callers pass the facility id explicitly.
- The encounter authentication select uses its prepared `raw` option value, with the UUID fallback;
  Blade no longer references the removed service. A rendered-view regression covers populated options.

## Compatibility with main

The September 30 rebase includes personal-data sync, separate specimen/diagnostic pages and eHealth
referral search (#865). It preserves the session-flash/x-message convention and does not restore the
removed `InteractsWithFlashMessages` trait. The old referral regression fixture was adjusted to the
current ACTIVE referral selection and diagnostic edit contract; diagnostic create now searches eHealth.

Other preserved contracts: complete contract pagination before transactional sync; explicit approval
success checks; multiple-prescription eRx sync; loading-state recovery; medication resource/source and
care-plan terms enums. The raw eRx document signing path, ownership and quantity protection are unchanged.

## Mapping boundaries

### Alignment with #907 (2026-10-04)

Validated Livewire Forms and prepared Eloquent models are supported direct sources. Put the mapping
attributes, allowlisted fields, property paths and SourceClass conditions on the target DTO. A class-level
source declaration alone does not redirect properties into a form's nested data array. Snapshots are
retained for explicit resolved UUID/time context and signing stability, not as a mandatory wrapper.
Align target DTO locations with app/Dto/<Resource> in a separate migration; do not duplicate DTOs.
Resource-specific static transforms may stay beside the DTO; shared pure transforms retain their home.
Use the existing ObjectMapperInterface Laravel binding rather than per-component mapper construction.

Do not adopt #907's generic EhealthMapping emptiness/key rewriting for medical documents: it removes
numeric zero and empty lists, preserves false, and rewrites literal dictionary keys. Contract-specific
normalization, signed byte order, raw eRx and partial synchronization remain required. merge-tree on
heads 45b8ac39 and 9eb61910 found only composer.json/composer.lock content conflicts; resolve package
requirements/lock together when integrating #907. No application PHP or lock changes were made in
this review. Implementation status and remaining work below are still the October 1 checkpoint.

The October 5 DeviceRequest increment follows the resource DTO layout. #907 has since advanced to
`b4c47459`, adding nominal Collection response sources and Division form/model DTOs. It is still open;
we have not imported its global `EHealthResponse::validate(): array|Collection` change. After merge,
re-check response-source handling and Composer compatibility against its actual merged head. The
earlier merge-tree result applies only to the October 4 heads, not the updated PR.

New targeted validation (2026-10-04): our 44 mapping tests / 126 assertions passed with one deprecation;
#907's 4 unit tests / 7 assertions passed on isolated mapper/serializer 8.1.8. A Form/Model/stdClass
probe and nine legacy Division comparisons were also executed. This is not a repeat of the full 286-test run.

Use one ModelData/EHealthData/FormData contract for each purpose, with multiple source classes where
useful. Do not create a DTO for every arrow, duplicate Write/ModelData classes, or add an Actions layer.
Separate prequalify envelopes and signed documents where their wire contracts differ.
FormData is introduced only when an actual form-hydration path is migrated.

Repository writes receive explicit arrays because Identifier relationships and aggregate persistence
already belong there. The outbound snapshot still carries resolved context and the operation clock;
simplifying it must preserve signed bytes and avoid lazy relation queries. `(object)` adapts the top level;
only collection rows need explicit object adaptation. No recursive JSON round-trip is required.

Laravel's PSR-11 `has()` does not advertise every autowirable class. Bind class-name transforms and
conditions explicitly; attribute-instantiated pure callables need no registration.

## Independent contract fixtures

- Outbound: eight cases captured from the unmodified #792 mapper at `4b1f0e7`, using a fixed clock and
  Europe/Kyiv. Expected prequalify, signed document and exact SignatureService JSON bytes are retained.
- Inbound: eight service-request and ten device-request cases captured from the original lifecycle on
  main `b2239108` before replacing its mapping. Cover aliases, partial/empty values, reference filtering,
  search-service fallback, device definitions/classification and zero quantities.
- Device outbound: eight cases captured before changing DeviceRequestMapper at `9eb61910`, with
  clock `2026-10-05T12:15:30Z` and Europe/Kyiv. Cover both product forms, explicit type precedence,
  program/no-program, encounter/episode/no context, zero/default quantity, sparse/incomplete references,
  expired/inverted/date-only/DST dates. Tests compare arrays and signed JSON and exercise SignatureService.
- Never regenerate expectations from the new mapper to make a failing test pass.

## Remaining work

- Split remaining referral workflow into narrow Livewire concerns, existing APIs and repositories,
  preserving ownership checks, quantity protection and operation order. The large ReferralRequestLifecycleService
  is still present and is not considered an acceptable final architecture.
- Migrate separate legacy `toFhir`/`fromFhir` callers before deleting their compatibility classes.
- eRx, care-plan/activity mapping, approvals and other medical workflows remain
  staged work. Do not mechanically copy a lifecycle service into a large trait or rename it into Actions.
- CarePlanActivityRepository still prepares activity fields for prequalify, now delegated to the device
  DTO mapping. Move that remaining preparation when migrating activity DTOs; it is not final architecture.
- Real KEP/eHealth UAT is still required before rollout.

## Validation

October 5 increment: **348 tests / 1477 assertions**, no failures, errors or risky tests, in isolated
mapper841 PHP 8.5.3/PostgreSQL. Includes device golden contracts, actual SignatureService bytes,
patient-registry/encounter/care-plan signing, API contracts, partial sync, ownership, eRx raw signing
and approvals. Project Pint passes for all 13 changed PHP files. One existing PDO deprecation remains.

An additional expanded run included older test files and reported 13 failing scenarios. All 13 also
fail on an immutable application snapshot of pre-change `9eb61910`: the two older MedicationRequest
lifecycle test files use stale Identifier FK fixtures/alias mocks and lack Cipher configuration;
MedicalEventAuthorizationTest's HTTP route test lacks a Vite manifest in the isolated environment.
These are tracked limitations, not a claim that the complete application test suite is green. The
348-test refactor regression excludes those three legacy files; no expectations were weakened.

Final regression (2026-10-01): **286 tests / 1151 assertions**, no failures, errors or risky tests.
Includes mapping and exact signing bytes, API job/prequalify contracts, partial sync/Identifier links,
explicit ownership scopes, rendered authentication options, eRx raw-signing and approval workflows.
One existing PHP 8.5 PDO constant deprecation remains. New mapping/enum/concern/API/test files pass
the project's PHP Pint rules; the Blade extension is disabled in the isolated PHP formatter.
Tests run only in isolated mapper841 PHP 8.5.3/PostgreSQL, never the user's application DB.
