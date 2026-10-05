# ObjectMapper refactor — #841

Issue: https://github.com/openhealths/nationHealth/issues/841

Full plan: [ehealth-object-mapper-plan.md](ehealth-object-mapper-plan.md).
Branch: `Mefizz/ohealth:i841_object_mapper_service_request`.
Base: upstream main `b2239108`, rebased 2026-09-30 after #792 merged on September 24.

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
- ServiceRequest completion/cancel usage no longer call the lifecycle service. HTTP controller,
  referral search UI and encounter completion share two protected methods in
  `app/Traits/MedicalEvents/UpdatesReferralExecution`, with explicit arguments and no component state.
  `EhealthComplete` maps the allowlisted completion reference collection; supported resource types
  live in `Enums/MedicalEvents/ReferralCompletionResourceType`. Repository keeps the existing
  same-patient/resource-exists checks and local status writes. Patient ServiceRequest API owns
  completion job resolution; status is persisted only after that API call succeeds.
  Cancel usage retains its existing synchronous response semantics. Both old lifecycle methods and
  its duplicated completion ownership helper have been deleted, without compatibility delegates.
- Removed `MedicalRequestOwnership`. Request repositories resolve UUIDs within the patient/encounter
  and explicit facility context; the shared query concern uses no session/container context. Approval
  lookup stays scoped to the current care plan. All Livewire callers pass the facility id explicitly.
- The encounter authentication select uses its prepared `raw` option value, with the UUID fallback;
  Blade no longer references the removed service. A rendered-view regression covers populated options.
- Removed `ReferralRequestLifecycleService` after migrating every caller. HTTP processing, referral
  search and encounter selection share protected take/qualify methods with explicit arguments.
  `EhealthProcess` allowlists executor UUID references/program; API resolves qualify/process before
  Repository persists in_progress. A failed qualify/process cannot advance local status.
- Existing care-plan/encounter concerns create drafts and retain their quantity/prequalify checks.
  `PreparesReferralSigning`, `SynchronizesReferrals` and `PrintsReferrals` share only the repeated steps.
  SMS uses the existing patient API; print markup belongs to Blade. Signed-create metadata persists
  before best-effort enrichment; job envelope statuses never overwrite clinical active status.
- Referral ModelData targets also accept prepared Eloquent models via SourceClass and ArrayAccess
  paths for nullable HasCamelCasing fields. Callers preload relations before mapping; DTOs issue no
  queries. `UseResponse` reuses ServiceRequestModelData with use-specific minimal import defaults,
  independently of the existing partial GET policy. Employee/Identifier lookups belong to repositories.
- eRx create/prequalify/fallback-sign uses `app/Dto/MedicationRequest` and MapCollection for dosage
  and references. Its partial ModelData sync preserves zero/empty scalar updates and excludes nulls;
  raw payload, dosage, author and links keep their existing Repository semantics. The accepted raw
  document signing path is unchanged. Legacy outbound methods delegate to the same DTO adapter.
- Removed `MedicationRequestLifecycleService` and the unused static `Api/MedicationRequest` wrapper.
  Existing care-plan/encounter actions use narrow protected draft, sign, reject, signing-document,
  active-identity, signature-validation, message and print concerns. Patient API owns prequalify/job
  resolution and reject-document fetching; Repository owns eligible encounters and loaded signing context.
  There is no replacement lifecycle class or universal workflow trait.
- eRx ModelData now accepts validated local fields, a preloaded MedicationRequestRequest and remote
  stdClass metadata. DosageModelData maps local dosage through MapCollection. The seven-field remote
  patch allowlist keeps these new local fields out of partial synchronization. Mapping issues no SQL.
- Raw-first signing/reject preserves unknown clinical fields. DraftResult retains the original accepted
  document when a create job returns only metadata. Failure cannot persist active/rejected status.
  Standalone prequalify/sign now validates verdict and waits for the job before showing success.
- SMS, print and dispense history retain active-resource identity resolution; block/unblock now also
  use the active UUID rather than the local draft UUID. The local status changes only after API success.
  Print markup is in Blade. Multiple-prescription sync, ownership, eligibility and quantity checks remain.
  Existing quantity guard transaction scope was not redesigned during this migration.

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
this review. The implementation sections below include the subsequent October 5 increments.

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
- eRx outbound: seven cases captured from unmodified MedicationRequestMapper at `2ea796ca`, with
  clock `2026-10-05T12:15:30+03:00` and Europe/Kyiv. Cover minimal/full dosage, program/care-plan,
  zero/defaults, sparse instructions, raw/tuple container dosage, empty lists and numeric strings.
  Tests compare create/prequalify/sign arrays and exact SignatureService JSON with zero fractions.
- Never regenerate expectations from the new mapper to make a failing test pass.

## Remaining work

- Migrate separate legacy `toFhir`/`fromFhir` callers before deleting their compatibility classes.
- Migrate DeviceRequestLifecycleService and the shared transport base only after preserving the distinct
  legacy request-request endpoints and their signing envelopes. MedicationRequestLifecycleService is gone.
- The older standalone eRx form still prepares its distinct simple string-dosage payload directly.
  Verify that wire contract independently before unifying it with structured DTO mapping.
- Care-plan/activity mapping, quantity/program guards, approvals and other medical workflows remain
  staged work. Existing quantity protection was retained; its transaction scope was not redesigned here.
- CarePlanActivityRepository still prepares activity fields for prequalify, now delegated to the device
  DTO mapping. Move that remaining preparation when migrating activity DTOs; it is not final architecture.
- Real KEP/eHealth UAT is still required before rollout.
- Rebase/integrate current main and the actual merged #907 response-source/Composer changes before ready.
  The refactor still has 32 PHP files in Services/MedicalEvents: 17 mappers, nine workflow/guard/base/
  package classes, four approval result/enum classes and two FHIR helpers. The whole folder is not retired.

Completion report: [object-mapper-progress-report.md](object-mapper-progress-report.md).

## Validation

October 5 eRx lifecycle removal: **481 tests / 1900 assertions**, no failures, errors or risky tests,
in isolated mapper841 PHP 8.5.3/PostgreSQL. This extends the medical regression with Unit API/Job
contracts, the older standalone MedicationRequest tests and new lifecycle failure/identity cases.
One existing PHP 8.5 PDO deprecation remains. All 29 changed PHP files pass the project's PHP Pint
rules; the isolated formatter disables the Blade extension. git diff --check passes.
New coverage includes direct preloaded Model/dosage to exact golden signing JSON without SQL,
opaque document envelopes, original create document when the job returns metadata, failed sign/reject,
INVALID prequalify preventing creation, cached active identity and block/unblock success/failure.

October 5 take/draft/sign/sync/eRx increment: **432 tests / 1753 assertions**, no failures, errors or
risky tests, in isolated mapper841 PHP 8.5.3/PostgreSQL. Includes mapping, API, repositories, enums,
care-plan/referral/eRx Livewire, encounter diagnostics and approvals. Project Pint passes for all 58
changed PHP files (the isolated formatter disables the Blade extension). One existing PDO deprecation remains.
Tests cover blocking qualify/process, local status after failure, minimal use import including zero,
direct Eloquent sources without SQL, signed-create persistence before failed enrichment, clinical/job
status separation and independent eRx signed JSON contracts.

October 5 completion/cancel increment: **363 tests / 1520 assertions**, no failures, errors or risky
tests, in isolated mapper841 PHP 8.5.3/PostgreSQL. Project Pint passes for all 16 changed PHP files.
Targeted tests cover all three completion resource types, cross-patient rejection, missing/unsupported
resources, completion job timeout, HTTP payload allowlisting/error translation and cancellation
success/failure persistence. One existing PDO deprecation remains.

Earlier October 5 device checkpoint: **348 tests / 1477 assertions**, no failures, errors or risky tests, in isolated
mapper841 PHP 8.5.3/PostgreSQL. Includes device golden contracts, actual SignatureService bytes,
patient-registry/encounter/care-plan signing, API contracts, partial sync, ownership, eRx raw signing
and approvals. Project Pint passes for all 13 changed PHP files. One existing PDO deprecation remains.

An earlier expanded run exposed stale Identifier FK fixtures/alias mocks in two older MedicationRequest
lifecycle files. These fixtures now use actual Identifier rows and instance API mocks; all 24 tests /
87 assertions pass and both files are included in the current 481-test regression. Their behavior
assertions remain intact. MedicalEventAuthorizationTest's HTTP route test still lacks a Vite manifest
in the isolated environment and is excluded. This is a medical regression, not a claim that the
complete application suite is green; real KEP/eHealth UAT has not been run.

Final regression (2026-10-01): **286 tests / 1151 assertions**, no failures, errors or risky tests.
Includes mapping and exact signing bytes, API job/prequalify contracts, partial sync/Identifier links,
explicit ownership scopes, rendered authentication options, eRx raw-signing and approval workflows.
One existing PHP 8.5 PDO constant deprecation remains. New mapping/enum/concern/API/test files pass
the project's PHP Pint rules; the Blade extension is disabled in the isolated PHP formatter.
Tests run only in isolated mapper841 PHP 8.5.3/PostgreSQL, never the user's application DB.
