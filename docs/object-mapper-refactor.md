# ObjectMapper refactor — #841

Issue: https://github.com/openhealths/nationHealth/issues/841

Full plan: [ehealth-object-mapper-plan.md](ehealth-object-mapper-plan.md).
Branch: `Mefizz/ohealth:i841_object_mapper_service_request`.
Base: upstream main `1cf8b92e`, integrated 2026-10-05 including merged #907; #792 is already in main.

## Implemented

- Symfony ObjectMapper/Serializer 8.1.8 from merged #907, Laravel provider and explicit callable bindings.
- ServiceRequest prequalify and signed-create contracts, preserving the exact JSON passed to KEP.
  The source snapshot carries caller-supplied time and resolved UUID context. HTTP and persistence stay outside mapping.
- Encounter/care-plan prequalify and encounter/care-plan/patient-registry signing use these contracts.
  Callers map directly into EhealthCreate/EhealthPrequalify and call toArray(); ServiceRequestPayloads is removed.
- DeviceRequest outbound now maps to `app/Dto/DeviceRequest/Ehealth`, `EhealthCreate` and
  `EhealthPrequalify`. Signing callers in care plan/patient registry and the existing prequalify callers
  use the DTO mapping directly. Collections use `MapCollection`; small pure device transformations
  stay beside the DTO. The adapter only prepares native objects, supplies explicit UUID/time context
  and invokes the DTO; wire serialization belongs to the DTO. It performs no lifecycle operations.
- Device create remains flat, with a fixed active status, one of code/reference and optional program;
  prequalify uses its separate envelope. Exact legacy field order, integer quantities including zero,
  invalid-reference empty lists, UTC clock skew and occurrence-date rules are preserved.
  The old DeviceRequestMapper has been removed after the last production caller migrated.
- `app/Dto/ServiceRequest/Model` and `app/Dto/DeviceRequest/Model` accept validated local fields (`FormCollection`)
  and eHealth JSON (`stdClass`) through `SourceClass`. These are one-line adapters, not new source DTOs.
  Shared metadata lives in `app/Dto/Referral/Model`; resource-specific fields stay on the corresponding target.
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
  queries. `ServiceRequestUse` reuses ServiceRequestModelData with use-specific minimal import defaults,
  independently of the existing partial GET policy. Employee/Identifier lookups belong to repositories.
- eRx create/prequalify/fallback-sign uses `app/Dto/MedicationRequest` and MapCollection for dosage
  and references. Its partial ModelData sync preserves zero/empty scalar updates and excludes nulls;
  raw payload, dosage, author and links keep their existing Repository semantics. The accepted raw
  document signing path is unchanged. The old MedicationRequestMapper has now been removed.
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
- Removed DeviceRequestLifecycleService, the now-unused EHealthRequestLifecycleService base and
  EHealthRequestLifecycleContract. The standalone DeviceRequestForm maps directly to EhealthDraft
  and EhealthDraftPrequalify; the existing standalone API resolves prequalify/create/sign jobs.
  Its /api/device_requests endpoint, SNOMED coding, integer quantity and signed_device_request_request
  envelope remain separate from the Patient API. DraftResult retains raw content independently of job metadata.
  A response containing only job metadata cannot enable signing without an accepted clinical document.
- Removed DeviceRequestMapper and MedicationRequestMapper after confirming no production callers
  remained, including dynamic/config/route references. Retired their unused FHIR facade methods.
  Existing tests of active wire contracts now use DTO adapters with an explicit clock; independent
  golden fixtures and signing-byte assertions remain. Obsolete wrapper-only tests and four tests of
  uncalled toFhir contracts were retired.
- Both external ServiceRequest search-import callers now map ServiceRequestSearch into the same
  ServiceRequestModelData target. SourceClass preserves full-import alias priority, original timestamps,
  incomplete Identifier rows and zero separately from use defaults and partial GET patch rules.
  Repositories still resolve links and select the author/patient; blank, existing and duplicate documents
  retain their previous skip/first-author behavior. ServiceRequestMapper and its unused facade method
  are removed. Ten independent full-import baselines were captured before deletion at c8e5f5ce;
  tests verify mapping has no SQL/HTTP and that the same JSON selects different import/sync rules.


- CarePlan remote sync uses the same Model target as validated CarePlanForm, with SourceClass and
  explicit fallback context. Model-to-Form hydration and the distinct legacy model-source signPlan
  now have actual Form/EhealthDraft targets; four independent old model contracts preserve JSON.
- CarePlanActivity draft, remote sync, preloaded-model create and edit hydration use resource DTOs.
  Ten old create/hydration contracts and five write contracts retain product priority, date clipping,
  quantity casts, whole-object alias precedence and local null-clearing. Dictionary/relation preparation
  is a protected Livewire concern; ActivityRepository retains persistence/sync rather than payload builders.
  Cancel changes only detail.status_reason in the complete raw remote snapshot. Complete maps the
  validated component to its own unsigned PATCH DTO; outcome lists use MapCollection. Dead, uncalled
  signing/prequalify helpers and their obsolete implementation-only tests were removed.
- ActivityRemainingQuantityGuard is removed. Repository owns the existing transaction/row lock;
  RequestQuantityStatus owns the exact excluded SQL statuses. The lock scope is unchanged: this is
  not a claim that concurrent issuance became atomic. Validation/program guards are removed;
  protected concerns own UI decisions, API owns complete pagination/catalog lookup, Repository owns
  contract persistence, and ActivityRequirements/DeviceDefinition Program map explicit data without IO.
- CarePlanApprovalService is removed. Protected request/access/queue/poll concerns preserve async jobs,
  inpatient/read access, OTP and resend throttling. Approval HTTP confirmation/deactivation checks belong
  to the API. Repository owns pending approval/link persistence, scoped polling and provisional UUID
  replacement. Outcomes live in Enums and results in Dto; no lifecycle service was renamed.
- MedicationDispenseLifecycleService is removed. The pharmacy component maps validated quantity into
  its Ehealth DTO, qualifies through the existing API, signs the accepted raw document and resolves the
  process job. Employee SQL lookup is in Repository. Five old independent contracts retain minimum
  quantity, fixed price defaults, exact JSON and unknown-field/raw signing behavior.
- Activity edit/save/delete/sign lookups are scoped to the current care plan. Approval polling cannot
  read or replace a UUID through a foreign care-plan link. Contract sync uses the explicitly supplied
  legal entity even when the session is bound to another entity; tests exercise both contexts.

- PaperReferralMapper is removed. Procedure/DiagnosticReport map the shared Ehealth/Form targets.
  Nine old a6618f05 contracts preserve exact wire JSON, missing versus explicit null and paper/electronic
  priority. SourceHasPath checks plain input presence without IO. Only the legacy parent adapters
  restore camelCase; that intermediate conversion disappears with their own DTO migration.

- DetectedIssueMapper and DeviceAssociationMapper are removed with both facade methods. The actual
  package builder/loader map Ehealth/Form DTOs. Twenty-nine independent old 1084b17e baseline cases
  preserve exact JSON, null versus missing fields, empty author objects, sparse lists and timestamp
  order. The caller owns IDs and the clock, including the minute separating an opening/closing pair.
  Existing timestamps remain untouched. Shared transforms add opt-in text without changing defaults.
  Only the remaining builder adapts DTO keys to its camelCase package contract; repository persistence
  remains unchanged. No new workflow service was introduced.

## Compatibility with main

The September 30 rebase includes personal-data sync, separate specimen/diagnostic pages and eHealth
referral search (#865). It preserves the session-flash/x-message convention and does not restore the
removed `InteractsWithFlashMessages` trait. The old referral regression fixture was adjusted to the
current ACTIVE referral selection and diagnostic edit contract; diagnostic create now searches eHealth.

Other preserved contracts: complete contract pagination before transactional sync; explicit approval
success checks; multiple-prescription eRx sync; loading-state recovery; medication resource/source and
care-plan terms enums. The raw eRx document signing path, ownership and quantity protection are unchanged.

## Mapping boundaries

### Alignment with merged #907 (2026-10-05)

The actual merged PR head is b4c47459; main integration is 1cf8b92e. Composer requirements were
reconciled with its lock, updating only mapper/serializer to 8.1.8 in the isolated environment.
The merged Division DTOs, FormCollection, response Collection family and EHealthResponse union
are present. Division workflow classes are unchanged by this refactor.

All former app/Mapping/EHealth targets now live in app/Dto/<Resource>. ServiceRequest, DeviceRequest
and MedicationRequest use Model DTOs with explicit class-level sources and property-level SourceClass.
Prepared local values use the upstream FormCollection. Search/use JSON is adapted into distinct
ServiceRequestSearch/ServiceRequestUse response collections under Api/Responses/Collections.
The same Model target preserves full-import, minimal-use and partial-GET policies.

ServiceRequestPayloads has been removed: care-plan, encounter and registry callers use
map(source, EhealthCreate/EhealthPrequalify::class)->toArray(). Input remains only for the resolved
UUID/time snapshot needed by signing. Standalone device and eRx map their validated screen directly;
eRx EhealthDraft/EhealthDraftPrequalify keep string dosage, selected programs and duration conversion.
UI state, password, key files and accepted raw clinical documents are excluded from mapping.

The shared EhealthMapping trait from #907 now delegates its post-normalization rules to one protected
hook. Its default Division behavior is unchanged. Medical DTOs reuse the same serializer through
PreservesEhealthDocumentValues, preserving zero, false, explicit lists and literal nested keys.
Each signed resource owns its established key order; medical golden expectations are unchanged.
Device/eRx context adapters retain source preparation only and no longer own serializer instances.

Repository receives explicitly projected DTO arrays because Identifier/FK resolution and relational
persistence belong there. A toModel() method that silently dropped unresolved links would not preserve
this contract; no unused model conversion or SQL inside DTOs is added. Model-to-form hydration remains
implemented for CarePlan and CarePlanActivity through Form targets with actual hydration callers.

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

- Care-plan form create: eight payloads captured from `CarePlanRepository::formatCarePlanRequest`
  at `17955764` before replacement. Cover sparse lists, Unicode, empty optional fields, null author,
  same-day encounter clipping, exact encounter midnight and both DST boundaries. Arrays and exact
  signing JSON are compared independently, including SignatureService's call to the mocked Cipher.
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
- Standalone eRx: four inputs captured from the unchanged component at `a58ca2c1` before replacing
  its payload construction. Cover string dosage, Unicode, quotes, newlines and duration casts
  (normal, fractional, scientific and leading zero). Tests compare arrays and exact create JSON;
  feature tests also cover validation before mapping and preservation of unknown accepted raw fields.
- External ServiceRequest import: ten inputs captured from ServiceRequestMapper::fromFhir at
  `c8e5f5ce` before deletion. Cover camel/flat/null/empty alias priorities, raw dates, zero and fractional
  quantity, incomplete/scalar reference rows, ignored local reference aliases and absent fields.
  The fixture is distinct from partial GET baselines; Repository tests cover persisted author/patient,
  Identifier links, defaults, existing-document protection and first-duplicate selection.

- CarePlan model-source sign and hydration: four contracts captured from 606d2c94 before replacement.
- CarePlanActivity: ten payload/JSON/hydration contracts and five draft/remote write contracts captured
  from 606d2c94. The old field order, clock, null-clearing and whole-object alias priority remain fixed.
- Pharmacy dispense: five old 606d2c94 contracts cover defaults, qualified minimum quantity, zero,
  exact create JSON and completion without an extra signature. Browser price keys remain ignored.
- Activity transitions: existing raw-cancel assertions are retained; complete is checked against the
  previous component PATCH builder, including sparse reference keys and the false-like outcome code.

- DeviceMapper and Fhir::device() are removed. Device/Ehealth and Form map typed Name, Identifier
  and Property collections through MapCollection. Sixteen old b6d85983 contracts preserve exact
  JSON, sparse lists, null/zero/false and nullable quantity/range metadata without mapping IO.
  Two actual PostgreSQL builder/store/loader tests cover Person/Preperson, ownership, FHIR links
  and all six property variants. Existing Quantity float casts remain unchanged. The temporary
  camelCase package adapter handles nested DTO fields; Repository still owns persistence.

## Remaining work

- The encounter/FHIR wave still has 14 PHP files in Services/MedicalEvents: 10 array mappers,
  EncounterPackageBuilder/Loader and Fhir/FhirResource. Their callers must migrate before the facade,
  helper and FhirMapperContract can be retired. Composition remains part of that separate wave.
  Next: DeviceDispense and the remaining clinical resources, with independent old baselines.
  Builder/loader retirement follows all callers.
- Simplify remaining DeviceRequest/MedicationRequest context adapters while migrating the complex
  encounter forms. Their DTOs already own serialization; do not hide SQL or orchestration in a mapper.
- Real KEP/eHealth UAT, concurrency assessment and the HTTP authorization suite with built Vite assets
  are required before ready. This refactor preserves the old quantity transaction scope.
- Keep main current and address the independently reproduced Division baseline failures separately.
  Nonmedical Services and the agreed Signature/Dictionary infrastructure exceptions are outside #841.

Completion report: [object-mapper-progress-report.md](object-mapper-progress-report.md).

## Validation

October 6 Device increment: **613 tests / 2819 assertions**, no failures, errors, skipped or risky tests. One existing PDO deprecation remains. Nineteen new tests cover independent old wire/hydration contracts and real PostgreSQL persistence. Pint passes all 14 PHP files; git diff --check passes. Existing mapper841 containers were restarted and their empty disposable database restored using install migrations; no application migrations changed. The temporary bootstrap was removed and user ohealth environments were preserved. The remaining inventory is 14 files, including 10 array mappers.

October 5 detected-issue/device-association increment: **594 tests / 2579 assertions**,
no failures, errors or risky tests. One existing PDO deprecation remains. Includes 31 new tests for
independent old wire/hydration contracts, actual builder/loader callers, sparse lists, missing/null
IDs, no mapping IO and caller-owned opening/closing timestamps. Pint passes all 14 PHP files in this
increment; git diff --check passes. Isolated mapper841 PHP 8.5.3/PostgreSQL is retained for the open
draft PR. The remaining medical service inventory is 15 files, including 11 array mappers.

October 5 paper-referral increment: **563 tests / 2331 assertions**, no failures, errors or risky tests.
Adds nine independent PaperReferral contracts, both actual parent mapper adapters and ProcedureRepository
coverage to the expanded medical regression. Pint passes the seven changed/new PHP files (the prior
care-plan/activity/approval/dispense increment passed all 84 PHP files). Git diff --check passes.
One existing PDO deprecation remains. The remaining Services/MedicalEvents inventory is 17 files:
13 array mappers, two package classes and two FHIR helpers; UAT and the separate encounter wave remain open.


October 5 care-plan/activity/approval/dispense completion: **553 tests / 2249 assertions**, no failures,
errors or risky tests in retained mapper841 PHP 8.5.3/PostgreSQL. Includes multi-source CarePlan/activity
mapping, actual Form hydration and legacy signPlan, old activity/dispense JSON, raw cancel and unsigned
complete, scoped activity/polling operations, explicit contract entity context, complete pagination,
quantity transaction/rollback behavior and the expanded referral/eRx/device/encounter medical suite.
PHP Pint passes all 84 changed/new PHP files; git diff --check passes. One existing PDO deprecation
remains. No Composer change or new Docker environment; real KEP/eHealth UAT and concurrency assessment
remain pending. The quantity lock scope is unchanged. The separate encounter/FHIR wave remains open.


October 5 care-plan form increment: **518 tests / 2076 assertions**, no failures, errors or risky
tests, in retained mapper841 PHP 8.5.3/PostgreSQL. This adds eight independent outbound care-plan
contracts, exact SignatureService bytes, local draft/null-clearing contracts and CarePlan Repository/
activity unit coverage to the previous expanded medical suite. Actual Livewire create/update/sign
and existing sync pass. PHP Pint passes all nine changed PHP files; git diff --check passes.
The final direct shared-trait DTOs also passed 11 tests / 51 assertions after simplification.
One existing PDO deprecation remains. Composer dependencies were unchanged after the previous
successful validation; no new containers were created. Remote care-plan and activity DTO migration
remain pending, and the independently reproduced Division baseline failures remain separate.

October 5 alignment with merged #907: **496 tests / 1983 assertions**, no failures, errors or risky
tests, in the retained mapper841 PHP 8.5.3/PostgreSQL environment. Covers medical mapping, API,
repositories, care plan, referral, eRx, device, registry, encounter and approvals, including the new
standalone eRx contracts and main's device-dispense tests. One existing PDO deprecation remains.
Composer validate --strict --no-check-publish, PHP Pint for all 50 changed PHP files and git diff
--check pass. Division payload mapping also passes in the targeted suite.

The full Division feature suite has **91 tests / 401 assertions, five errors, one failure and four
risky tests** on an independently loaded, unchanged `origin/main` archive at `1cf8b92e`, with the same
failures seen in the refactor worktree. These concern mapping-exception handling and Division
persistence; they are recorded separately rather than treated as a green application-wide baseline.
No new Docker containers were created or existing application data removed.

October 5 external ServiceRequest import: **486 tests / 1941 assertions**, no failures, errors or risky
tests, in the same disposable mapper841 PHP 8.5.3/PostgreSQL environment. Covers full-import baselines,
distinct SourceClass import/sync policies, no SQL/HTTP during mapping, actual persisted links and author,
zero/default quantity, existing-document protection and first-duplicate selection. The expanded medical
suite retains referral/eRx/device/care-plan/encounter/approval coverage. PHP Pint passes all 12 changed
PHP files; git diff --check passes. One existing PDO deprecation remains. Eight wrapper-only cases were
removed with the last mapper; active outbound tests and previous golden expectations are unchanged.
No new containers were created; the open draft PR still needs the retained test environment and UAT.

October 5 device lifecycle/mapper retirement: **480 tests / 1902 assertions**, no failures, errors or
risky tests, in isolated mapper841 PHP 8.5.3/PostgreSQL. The same expanded medical suite includes
direct standalone Form mapping, distinct device endpoints/signing envelopes, INVALID and failed-job
UI behavior, raw-document preservation and refusal to sign job metadata without a clinical document.
Current contract tests use DTO adapters; obsolete wrapper/dead-contract tests were removed, while
independent golden expectations remain unchanged. Project PHP Pint passes all 16 changed PHP files;
git diff --check passes. One existing PDO deprecation remains. Real KEP/eHealth UAT is still pending.

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
87 assertions passed at that checkpoint and both files remain in the current expanded regression. Their behavior
assertions remain intact. MedicalEventAuthorizationTest's HTTP route test still lacks a Vite manifest
in the isolated environment and is excluded. This is a medical regression, not a claim that the
complete application suite is green; real KEP/eHealth UAT has not been run.

Final regression (2026-10-01): **286 tests / 1151 assertions**, no failures, errors or risky tests.
Includes mapping and exact signing bytes, API job/prequalify contracts, partial sync/Identifier links,
explicit ownership scopes, rendered authentication options, eRx raw-signing and approval workflows.
One existing PHP 8.5 PDO constant deprecation remains. New mapping/enum/concern/API/test files pass
the project's PHP Pint rules; the Blade extension is disabled in the isolated PHP formatter.
Tests run only in isolated mapper841 PHP 8.5.3/PostgreSQL, never the user's application DB.
