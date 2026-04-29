<?php

declare(strict_types=1);

namespace App\Livewire\Encounter;

use App\Classes\Cipher\Exceptions\ApiException as CipherApiException;
use App\Classes\eHealth\Api\Job;
use App\Classes\eHealth\EHealth;
use App\Classes\eHealth\Exceptions\ApiException as eHealthApiException;
use App\Classes\eHealth\PackageBuilders\EncounterPackageBuilder;
use App\Exceptions\EHealth\EHealthResponseException;
use App\Exceptions\EHealth\EHealthValidationException;
use App\Livewire\Encounter\Forms\Api\EncounterRequestApi;
use App\Livewire\Encounter\Traits\HandlesReasonReferences;
use App\Models\LegalEntity;
use App\Models\MedicalEvents\Sql\DiagnosticReport;
use App\Models\MedicalEvents\Sql\Episode;
use App\Models\MedicalEvents\Sql\Observation;
use App\Models\MedicalEvents\Sql\Procedure;
use App\Repositories\MedicalEvents\Repository;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use JsonException;
use Throwable;

class EncounterCreate extends EncounterComponent
{
    use HandlesReasonReferences;

    private EncounterPackageBuilder $packageBuilder;
    protected int $createdEncounterId;

    public function boot(): void
    {
        parent::boot();
        $this->packageBuilder = app(EncounterPackageBuilder::class);
    }

    public function mount(LegalEntity $legalEntity, int $personId): void
    {
        $this->initializeComponent($personId);

        // Select an employee for the current legal entity, prioritizing clinical roles (DOCTOR, SPECIALIST)
        $employee = Auth::user()->party->employees()
            ->where('legal_entity_id', $legalEntity->id)
            ->whereStatus('APPROVED')
            ->orderByRaw("CASE WHEN employee_type IN ('DOCTOR', 'SPECIALIST') THEN 1 ELSE 2 END")
            ->first();

        $uuid = $employee?->uuid ?? Auth::user()->party->employees()->whereStatus('APPROVED')->first()->uuid;

        $this->form->encounter['performer']['identifier']['value'] = $uuid;

        $this->setDefaultDate();
    }

    /**
     * Submit encounter to eHealth.
     *
     * @return void
     */
    public function sign(): void
    {
        $validated = $this->validate();

        $formattedData = $this->getFormattedData($validated['encounter']);

        if (!$this->createdEncounterId = $this->storeValidatedData($formattedData)) {
            return;
        }

        $payloadToSign = Arr::except($formattedData['encounter'], ['episode']);

        try {
            $signedContent = $this->cipherService->sign(
                $payloadToSign,
                $validated['knedp'],
                $validated['keyContainerUpload'],
                $validated['password'],
                Auth::user()->party->taxId
            );
        } catch (ConnectionException|CipherApiException|JsonException $exception) {
            $this->handleCipherExceptions($exception, 'Error when signing data with Cipher');

            return;
        }

        $signedSubmitEncounter = EncounterRequestApi::buildSubmitEncounterPackage(
            $payloadToSign,
            $signedContent->getBase64Data()
        );

        try {
            $response = EHealth::encounter()->submit($this->patientUuid, $signedSubmitEncounter);
            $data = $response->getData();
            
            if (isset($data['id'])) {
                $this->pollJobResult($data['id']);
            } else {
                $this->handleSuccessResponse($data);
            }
        } catch (ConnectionException|EHealthValidationException|EHealthResponseException $exception) {
            if ($exception instanceof EHealthValidationException || $exception instanceof EHealthResponseException) {
                $this->addError('ehealth_error', $exception->getMessage());
                Log::channel('e_health_errors')->error($exception->getMessage(), $exception->getData());
            }

            $this->handleEHealthExceptions($exception, 'Error while submitting encounter');

            return;
        }
    }

    /**
     * Poll job result until it is processed.
     *
     * @param  string  $jobId
     * @return void
     */
    protected function pollJobResult(string $jobId): void
    {
        $attempts = 0;
        $maxAttempts = 10;

        while ($attempts < $maxAttempts) {
            try {
                $job = EHealth::job()->getDetails($jobId);
                $status = $job->getData()['status'] ?? 'pending';

                if ($status === 'processed') {
                    $this->handleSuccessResponse($job->getData());
                    return;
                }

                if ($status === 'failed') {
                    $errorData = $job->getData();
                    Log::channel('e_health_errors')->error('Encounter submission job failed', $errorData);
                    $this->addError('ehealth_error', 'Помилка реєстрації взаємодії в ЕСОЗ. Перевірте лог.');
                    return;
                }
            } catch (Throwable $e) {
                Log::error('Error polling encounter job: ' . $e->getMessage());
            }

            $attempts++;
            sleep(2);
        }

        $this->addError('ehealth_error', 'Перевищено час очікування результату реєстрації. Спробуйте пізніше.');
    }

    /**
     * Handle success response from eHealth.
     *
     * @param  array  $data
     * @return void
     */
    protected function handleSuccessResponse(array $data): void
    {
        $this->redirectRoute('encounter.edit', [
            legalEntity(),
            'personId' => $this->personId,
            'encounterId' => $this->createdEncounterId,
        ]);
    }

    /**
     * Set default encounter period date.
     *
     * @return void
     */
    private function setDefaultDate(): void
    {
        $this->form->encounter['period']['date'] = now()->format('Y-m-d');
        $this->form->encounter['period']['start'] = now()->format('H:i');
        $this->form->encounter['period']['end'] = now()->addMinutes(20)->format('H:i');
    }

    /**
     * Format encounter data for eHealth request and local DB.
     *
     * @param  array  $validatedEncounter
     * @return array
     */
    private function getFormattedData(array $validatedEncounter): array
    {
        $encounterRepository = Repository::encounter();

        $package = [
            'encounter' => $encounterRepository->formatEncounterRequest(
                $validatedEncounter,
                $this->patientUuid
            )
        ];

        if ($this->episodeType === 'new' && !empty($this->form->episode['name'])) {
            $package['episode'] = Repository::episode()->formatEpisodeRequest(
                $this->form->episode,
                $this->patientUuid,
                $validatedEncounter['performer']['identifier']['value']
            );
        }

        $package['conditions'] = Repository::condition()->formatConditionsRequest(
            $this->form->conditions,
            $this->patientUuid
        );

        if (!empty($this->form->immunizations)) {
            $package['immunizations'] = Repository::immunization()->formatImmunizationsRequest(
                $this->form->immunizations
            );
        }

        if (!empty($this->form->diagnosticReports)) {
            $package['diagnosticReports'] = $encounterRepository->formatDiagnosticReportsRequest(
                $this->form->diagnosticReports,
                $validatedEncounter['divisionId'] ?? null
            );
        }

        if (!empty($this->form->observations)) {
            $package['observations'] = $encounterRepository->formatObservationsRequest($this->form->observations);
        }

        if (!empty($this->form->procedures)) {
            $package['procedures'] = $encounterRepository->formatProceduresRequest($this->form->procedures);
        }

        if (!empty($this->form->clinicalImpressions)) {
            $package['clinicalImpressions'] = $encounterRepository->formatClinicalImpressionsRequest(
                $this->form->clinicalImpressions
            );
        }

        return $package;
    }

    /**
     * Store validated formatted data into DB.
     *
     * @param  array  $formattedData
     * @return int|null
     * @throws Throwable
     */
    protected function storeValidatedData(array $formattedData): ?int
    {
        try {
            return DB::transaction(function () use ($formattedData) {
                $this->createdEncounterId = Repository::encounter()->store($formattedData['encounter'], $this->personId);

                if (isset($formattedData['episode'])) {
                    Repository::episode()->store($formattedData['episode'], $this->personId, $this->createdEncounterId);
                }

                Repository::condition()->store($formattedData['conditions'], $this->createdEncounterId, $this->personId);

                if (isset($formattedData['immunizations'])) {
                    Repository::immunization()->store(
                        $formattedData['immunizations'],
                        $this->personId,
                        $this->createdEncounterId
                    );
                }

                if (isset($formattedData['diagnosticReports'])) {
                    Repository::diagnosticReport()->store($formattedData['diagnosticReports'], $this->createdEncounterId);
                }

                if (isset($formattedData['observations'])) {
                    Repository::observation()->store(
                        $formattedData['observations'],
                        $this->personId,
                        $this->createdEncounterId
                    );
                }

                if (isset($formattedData['procedures'])) {
                    Repository::procedure()->store($formattedData['procedures'], $this->createdEncounterId);

                    // Save the selected condition and observation locally if they don't exist in our database.
                    foreach ($formattedData['procedures'] as $procedure) {
                        $this->processReasonReferences($procedure);
                        $this->processComplicationDetails($procedure);
                    }
                }

                if (isset($formattedData['clinicalImpressions'])) {
                    Repository::clinicalImpression()->store(
                        $formattedData['clinicalImpressions'],
                        $this->personId,
                        $this->createdEncounterId
                    );

                    // Save the selected episode_of_care, procedure, diagnostic_report, encounter locally if they don't exist in our database.
                    foreach ($formattedData['clinicalImpressions'] as $clinicalImpression) {
                        $this->processSupportingInfo($clinicalImpression);
                    }
                }

                return $this->createdEncounterId;
            });
        } catch (Throwable $exception) {
            $this->logDatabaseErrors($exception, 'Failed to store validated data');
            Session::flash('error', __('messages.database_error'));

            return null;
        }
    }

    /**
     * Handles details of procedure complications
     *
     * @param  array  $procedure
     * @return void
     */
    private function processComplicationDetails(array $procedure): void
    {
        if (!isset($procedure['complicationDetails'])) {
            return;
        }

        foreach ($procedure['complicationDetails'] as $complicationDetail) {
            $this->ensureConditionExists($complicationDetail['identifier']['value']);
        }
    }

    /**
     * Process supporting info of clinical impression.
     *
     * @param  array  $clinicalImpression
     * @return void
     */
    private function processSupportingInfo(array $clinicalImpression): void
    {
        if (!isset($clinicalImpression['supportingInfo'])) {
            return;
        }

        foreach ($clinicalImpression['supportingInfo'] as $supportingInfo) {
            if ($supportingInfo['identifier']['type']['coding'][0]['code'] === 'episode_of_care') {
                $this->ensureEpisodeExists($supportingInfo['identifier']['value']);
            }

            if ($supportingInfo['identifier']['type']['coding'][0]['code'] === 'procedure') {
                $this->ensureProcedureExists($supportingInfo['identifier']['value']);
            }

            if ($supportingInfo['identifier']['type']['coding'][0]['code'] === 'diagnostic_report') {
                $this->ensureDiagnosticReportExists($supportingInfo['identifier']['value']);
            }

            if ($supportingInfo['identifier']['type']['coding'][0]['code'] === 'encounter') {
                $this->ensureEncounterExist($supportingInfo['identifier']['value']);
            }
        }
    }

    /**
     * Search for episode and save if not founded in our DB.
     *
     * @param  string  $uuid
     * @return void
     */
    private function ensureEpisodeExists(string $uuid): void
    {
        if (Episode::whereUuid($uuid)->exists()) {
            return;
        }

        try {
            $episodeData = EHealth::episode()->getById($this->patientUuid, $uuid)->getData();

            try {
                Repository::episode()->store([Arr::toCamelCase($episodeData)], $this->personId);
            } catch (Throwable $exception) {
                $this->logDatabaseErrors($exception, 'Failed to store episode');
                Session::flash('error', __('messages.database_error'));

                return;
            }
        } catch (ConnectionException|EHealthValidationException|EHealthResponseException $exception) {
            $this->handleEHealthExceptions($exception, 'Failed while ensuring episode existence');

            return;
        }
    }

    /**
     * Search for procedure and save if not founded in our DB.
     *
     * @param  string  $uuid
     * @return void
     */
    private function ensureProcedureExists(string $uuid): void
    {
        if (Procedure::whereUuid($uuid)->exists()) {
            return;
        }

        try {
            $procedureData = EHealth::procedure()->getById($this->patientUuid, $uuid)->getData();

            try {
                Repository::procedure()->store([Arr::toCamelCase($procedureData)]);
            } catch (Throwable $exception) {
                $this->logDatabaseErrors($exception, 'Failed to store procedure');
                Session::flash('error', __('messages.database_error'));

                return;
            }
        } catch (ConnectionException|EHealthValidationException|EHealthResponseException $exception) {
            $this->handleEHealthExceptions($exception, 'Failed while ensuring procedure existence');

            return;
        }
    }

    /**
     * Search for diagnostic report and save if not founded in our DB.
     *
     * @param  string  $uuid
     * @return void
     */
    private function ensureDiagnosticReportExists(string $uuid): void
    {
        if (DiagnosticReport::whereUuid($uuid)->exists()) {
            return;
        }

        try {
            $diagnosticReportData = EHealth::diagnosticReport()->getById($this->patientUuid, $uuid)->getData();

            try {
                Repository::diagnosticReport()->store([Arr::toCamelCase($diagnosticReportData)]);
            } catch (Throwable $exception) {
                $this->logDatabaseErrors($exception, 'Failed to store diagnostic report');
                Session::flash('error', __('messages.database_error'));

                return;
            }
        } catch (ConnectionException|EHealthValidationException|EHealthResponseException $exception) {
            $this->handleEHealthExceptions($exception, 'Failed while ensuring diagnostic report existence');

            return;
        }
    }
}
