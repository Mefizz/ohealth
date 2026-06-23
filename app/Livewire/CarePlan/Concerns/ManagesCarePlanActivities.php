<?php

declare(strict_types=1);

namespace App\Livewire\CarePlan\Concerns;

use App\Classes\eHealth\EHealth;
use App\Enums\CarePlanStatus;
use App\Repositories\CarePlanActivityRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

trait ManagesCarePlanActivities
{
    public string $deviceSelectionWarning = '';

    public function initActivityForm(string $kind): void
    {
        $this->deviceSelectionWarning = '';
        $this->activityForm = [
            'id' => null,
            'kind' => $kind,
            'program' => '',
            'quantity' => '',
            'quantity_system' => '',
            'quantity_code' => '',
            'daily_amount' => '',
            'reason_code' => '',
            'reason_reference' => '',
            'goal' => '',
            'description' => '',
            'scheduled_period_start' => now()->format('d.m.Y'),
            'scheduled_period_end' => '',
            'product_reference' => '',
            'product_codeable_concept' => '',
        ];
    }

    public function editActivity(int $activityId, CarePlanActivityRepository $repository): void
    {
        $activity = $repository->findById($activityId);
        if (!$activity) {
            return;
        }

        $this->selectedProgram = $activity->program ?? '';

        $this->activityForm = [
            'id' => $activity->id,
            'kind' => is_array($activity->kind) ? ($activity->kind['coding'][0]['code'] ?? ($activity->kind['text'] ?? '')) : ($activity->kindConcept?->coding?->first()?->code ?? $activity->kind),
            'program' => $activity->program ?? '',
            'quantity' => is_array($activity->quantity) ? ($activity->quantity['value'] ?? '') : $activity->quantity,
            'quantity_system' => is_array($activity->quantity) ? ($activity->quantity['unit'] ?? '') : $activity->quantity_system,
            'quantity_code' => $activity->quantity_code ?? '',
            'daily_amount' => $activity->daily_amount ?? '',
            'reason_code' => $activity->reason_code ?? '',
            'reason_reference' => $activity->reason_reference ?? '',
            'goal' => $activity->goal ?? '',
            'description' => $activity->description ?? '',
            'scheduled_period_start' => $activity->scheduled_period_start?->format('d.m.Y') ?? '',
            'scheduled_period_end' => $activity->scheduled_period_end?->format('d.m.Y') ?? '',
            'product_reference' => $activity->product_reference ?? '',
            'product_codeable_concept' => $activity->product_codeable_concept ?? '',
        ];

        // Load pre-selected product info
        $this->selectedProduct = null;
        if (!empty($activity->product_reference)) {
            try {
                $kindLower = strtolower($this->activityForm['kind']);
                if (str_contains($kindLower, 'service')) {
                    $response = EHealth::service()->getMany(['code' => $activity->product_reference]);
                    $data = $response->getData();
                    if (!empty($data)) {
                        $this->selectedProduct = $data[0];
                    }
                } elseif (str_contains($kindLower, 'medication')) {
                    $response = EHealth::drug()->getMany(['innm_id' => $activity->product_reference]);
                    $data = $response->getData();
                    if (!empty($data)) {
                        $this->selectedProduct = $data[0];
                    }
                } elseif (str_contains($kindLower, 'device')) {
                    $device = $this->findDeviceDefinitionByReference((string) $activity->product_reference);
                    if ($device !== null) {
                        $this->selectedProduct = $device;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('CarePlanShow: failed to preload product reference: ' . $e->getMessage());
            }
        }

        $this->refreshDeviceSelectionWarning();

        // Initialize linked justification grounds
        $this->linkedGrounds = [];
        if (!empty($activity->reason_reference)) {
            foreach ($activity->reason_reference as $ref) {
                $parts = explode('/', $ref);
                if (count($parts) === 2) {
                    $this->addLinkedGround($parts[0], $parts[1]);
                } else {
                    $uuid = $ref;
                    if (collect($this->availableConditions)->contains('uuid', $uuid)) {
                        $this->addLinkedGround('Condition', $uuid);
                    } elseif (collect($this->availableReports)->contains('uuid', $uuid)) {
                        $this->addLinkedGround('DiagnosticReport', $uuid);
                    } elseif (collect($this->availableObservations)->contains('uuid', $uuid)) {
                        $this->addLinkedGround('Observation', $uuid);
                    } else {
                        $this->addLinkedGround('Condition', $uuid);
                    }
                }
            }
        }

        $kindLower = strtolower($this->activityForm['kind']);
        if (str_contains($kindLower, 'service')) {
            $this->showServiceDrawer = true;
        } elseif (str_contains($kindLower, 'medication')) {
            $this->showMedicationFormDrawer = true;
        } elseif (str_contains($kindLower, 'device')) {
            $this->syncDeviceProductReferenceFromSelection();
            $this->showMedicalDeviceFormDrawer = true;
        } else {
            $this->showServiceDrawer = true;
        }
    }

    public function getSelectedMedicalDeviceLabel(): string
    {
        if (!empty($this->selectedProduct)) {
            $name = $this->selectedProduct['name'] ?? '';
            if ($name === '' && !empty($this->selectedProduct['device_names'][0]['name'])) {
                $name = $this->selectedProduct['device_names'][0]['name'];
            }
            if ($name === '') {
                $name = $this->selectedProduct['description'] ?? '';
            }
            $code = $this->resolveDeviceClassificationCode($this->selectedProduct);
            $label = trim(($code ? $code . ' - ' : '') . $name);

            return $label !== '' ? $label : __('care-plan.select_medical_device');
        }

        if (!empty($this->activityForm['product_reference'])) {
            return (string) $this->activityForm['product_reference'];
        }

        return __('care-plan.select_medical_device');
    }

    /**
     * @param  array<string, mixed>  $device
     */
    public function formatDeviceTypeLabel(array $device): string
    {
        $classificationTypes = $device['classification_types'] ?? [];
        if (is_array($classificationTypes) && $classificationTypes !== []) {
            $labels = [];
            foreach ($classificationTypes as $type) {
                if (!is_array($type)) {
                    continue;
                }
                $code = (string) ($type['code'] ?? '');
                $system = (string) ($type['system'] ?? '');
                if ($system === 'eHealth/assistive_devices') {
                    $labels[] = $this->dictionaries['eHealth/assistive_devices'][$code] ?? $code;
                } else {
                    $labels[] = $this->dictionaries['device_definition_classification_type'][$code] ?? $code;
                }
            }

            $label = implode(', ', array_filter($labels));

            return $label !== '' ? $label : '-';
        }

        return $device['type_name'] ?? $device['classification_type_name'] ?? '-';
    }

    /**
     * @param  array<string, mixed>  $device
     */
    public function formatDevicePackagingLabel(array $device): string
    {
        $packaging = $device['packaging'] ?? null;
        if (!is_array($packaging)) {
            return is_string($packaging) ? $packaging : '-';
        }

        $type = $this->dictionaries['device_definition_packaging_type'][$packaging['packaging_type'] ?? ''] ?? ($packaging['packaging_type'] ?? '');
        $unit = $this->dictionaries['device_unit'][$packaging['packaging_unit'] ?? ''] ?? ($packaging['packaging_unit'] ?? '');
        $count = $packaging['packaging_count'] ?? '';

        $label = trim(implode(' ', array_filter([(string) $count, (string) $type, (string) $unit])));

        return $label !== '' ? $label : '-';
    }

    public function refreshDeviceSelectionWarning(): void
    {
        $kindLower = strtolower($this->activityForm['kind'] ?? '');
        if (!str_contains($kindLower, 'device')) {
            $this->deviceSelectionWarning = '';

            return;
        }

        if (empty($this->selectedProgram)) {
            $this->deviceSelectionWarning = __('care-plan.device_program_required');

            return;
        }

        if (empty($this->selectedProduct) && !empty($this->activityForm['product_reference'])) {
            $this->deviceSelectionWarning = __('care-plan.device_product_reselect_required');

            return;
        }

        if (
            !empty($this->selectedProduct)
            && !empty($this->activityForm['program'])
            && $this->selectedProgram !== $this->activityForm['program']
        ) {
            $this->deviceSelectionWarning = __('care-plan.device_program_product_mismatch');

            return;
        }

        $this->deviceSelectionWarning = '';
    }

    public function getDeviceSignReadinessWarning(\App\Models\CarePlanActivity $activity): ?string
    {
        if (!str_contains(strtolower((string) $activity->kind), 'device')) {
            return null;
        }

        if (empty($activity->program)) {
            return __('care-plan.device_program_required_before_sign');
        }

        $allowedPrograms = array_keys($this->dictionaries['medical_programs_device'] ?? []);
        if (!empty($allowedPrograms) && !in_array($activity->program, $allowedPrograms, true)) {
            return __('care-plan.device_program_not_available_for_legal_entity');
        }

        return null;
    }

    public function openMedicalDeviceSearch(): void
    {
        if (empty($this->selectedProgram)) {
            Session::flash('error', __('care-plan.device_program_required'));

            return;
        }

        $this->showMedicalDeviceSearchDrawer = true;
    }

    public function selectMedicalDevice(int $index): void
    {
        $device = $this->searchResults[$index] ?? null;
        if (!is_array($device)) {
            return;
        }

        $this->selectProduct($device, 'device_request');
    }

    public function openMedicationSearch(): void
    {
        $this->showMedicationSearchDrawer = true;
    }

    public function saveActivity(CarePlanActivityRepository $repository): void
    {
        if (!empty($this->selectedProgram)) {
            $this->activityForm['program'] = $this->selectedProgram;
        }

        $this->syncDeviceProductReferenceFromSelection();

        $rules = [
            'activityForm.kind' => 'required|string',
            'activityForm.scheduled_period_start' => 'required|string',
            'activityForm.scheduled_period_end' => 'required|string',
            'activityForm.quantity' => 'nullable|numeric',
            'activityForm.quantity_system' => 'nullable|string',
            'activityForm.quantity_code' => 'nullable|string',
            'activityForm.daily_amount' => 'nullable|numeric',
            'activityForm.description' => 'nullable|string',
            'activityForm.product_reference' => 'nullable|string',
            'activityForm.program' => 'nullable|string',
            'activityForm.reason_code' => 'nullable|string',
        ];

        // Apply strict validation for device request positive integer quantities
        $kindLower = strtolower($this->activityForm['kind']);
        if (str_contains($kindLower, 'device')) {
            $rules['activityForm.quantity'] = 'required|integer|min:1';
            $rules['activityForm.program'] = 'required|string';
            $rules['activityForm.product_reference'] = 'required|uuid';

            $programId = $this->activityForm['program'] ?: $this->selectedProgram;
            $allowedCodeTypes = $this->resolveDeviceRequestAllowedCodeTypes($programId);
            $requiresClassificationOnly = in_array('CLASSIFICATION_TYPE', $allowedCodeTypes, true)
                && !in_array('DEVICE_DEFINITION', $allowedCodeTypes, true);

            if ($requiresClassificationOnly) {
                $rules['activityForm.product_codeable_concept'] = 'required|string';
            } else {
                $rules['activityForm.product_codeable_concept'] = 'nullable|string';
            }
        }

        try {
            $validated = $this->validate($rules);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());
            Session::flash('error', $exception->validator->errors()->first());

            return;
        }

        // Compile reason reference identifiers from linked justifications
        $reasonReferences = collect($this->linkedGrounds)->map(fn ($g) => $g['type'] . '/' . $g['uuid'])->toArray();

        $program = !empty($validated['activityForm']['program']) ? $validated['activityForm']['program'] : null;
        if (str_contains(strtolower($validated['activityForm']['kind']), 'medication') && empty($program)) {
            $program = '1318eabc-1a1a-42f6-8450-61e11c19eede'; // Default to "Prescription medical products"
        } elseif (str_contains(strtolower($validated['activityForm']['kind']), 'device') && empty($program)) {
            $devicePrograms = array_keys($this->dictionaries['medical_programs_device'] ?? []);
            $program = $devicePrograms[0] ?? 'c0ee515e-bdcc-4613-91cf-22d7d8e82efc';
        }

        $activityData = [
            'kind' => $validated['activityForm']['kind'],
            'quantity' => !empty($validated['activityForm']['quantity']) ? $validated['activityForm']['quantity'] : null,
            'quantity_system' => !empty($validated['activityForm']['quantity_system']) ? $validated['activityForm']['quantity_system'] : null,
            'quantity_code' => !empty($validated['activityForm']['quantity_code']) ? $validated['activityForm']['quantity_code'] : null,
            'daily_amount' => !empty($validated['activityForm']['daily_amount']) ? $validated['activityForm']['daily_amount'] : null,
            'description' => !empty($validated['activityForm']['description']) ? $validated['activityForm']['description'] : null,
            'product_reference' => !empty($validated['activityForm']['product_reference']) ? $validated['activityForm']['product_reference'] : null,
            'product_codeable_concept' => !empty($this->activityForm['product_codeable_concept']) ? $this->activityForm['product_codeable_concept'] : null,
            'program' => $program,
            'reason_code' => !empty($validated['activityForm']['reason_code']) ? $validated['activityForm']['reason_code'] : null,
            'reason_reference' => !empty($reasonReferences) ? $reasonReferences : null,
            'scheduled_period_start' => convertToYmd($validated['activityForm']['scheduled_period_start']),
            'scheduled_period_end' => convertToYmd($validated['activityForm']['scheduled_period_end']),
        ];

        if (!empty($this->activityForm['id'])) {
            $repository->updateById($this->activityForm['id'], $activityData);
            Session::flash('success', __('care-plan.activity_updated'));
            $createdActivity = $repository->findById($this->activityForm['id']);
        } else {
            $activityData['care_plan_id'] = $this->carePlan->id;
            $activityData['author_id'] = Auth::user()?->activeDoctorEmployee()?->id;
            $activityData['status'] = CarePlanStatus::DRAFT->value;

            $createdActivity = $repository->create($activityData);
            Session::flash('success', __('care-plan.activity_draft_saved'));
        }

        $this->refreshCarePlan();

        // Close drawers
        $this->dispatch('close-drawers');

        $this->afterActivitySaved($createdActivity ?? null);
    }

    protected function afterActivitySaved(?\App\Models\CarePlanActivity $activity = null): void
    {
    }

    public function searchServices(): void
    {
        if (empty($this->searchQuery)) {
            $this->searchResults = [];

            return;
        }

        try {
            $query = trim($this->searchQuery);
            $params = [
                'page' => $this->searchPage,
                'page_size' => 15,
            ];

            // If the query looks like a code (alphanumeric/hyphens/dots, contains digits, no spaces)
            if (preg_match('/^[\p{L}0-9\-\.]+$/u', $query) && preg_match('/[0-9]/', $query) && !str_contains($query, ' ')) {
                $params['code'] = $query;
            } else {
                $params['name'] = $query;
            }

            $response = EHealth::service()->getMany($params);

            $this->searchResults = $this->flattenServices($response->getData());
        } catch (\Exception $e) {
            Log::error("Failed to search services: " . $e->getMessage());
            $this->searchResults = [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeEHealthList(mixed $data): array
    {
        if (!is_array($data)) {
            return [];
        }

        if (isset($data['data']) && is_array($data['data'])) {
            return array_values($data['data']);
        }

        if (array_is_list($data)) {
            return $data;
        }

        return [$data];
    }

    private function syncDeviceProductReferenceFromSelection(): void
    {
        if (empty($this->selectedProduct)) {
            return;
        }

        $kindLower = strtolower($this->activityForm['kind'] ?? '');
        if (!str_contains($kindLower, 'device')) {
            return;
        }

        $deviceId = $this->selectedProduct['id'] ?? $this->selectedProduct['uuid'] ?? null;
        $classificationCode = $this->resolveDeviceClassificationCode($this->selectedProduct);

        if (!empty($deviceId)) {
            $this->activityForm['product_reference'] = (string) $deviceId;
        }

        if (!empty($classificationCode)) {
            $this->activityForm['product_codeable_concept'] = $classificationCode;
        }
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function resolveDeviceClassificationCode(array $device): ?string
    {
        if (!empty($device['classification_type_code'])) {
            return (string) $device['classification_type_code'];
        }

        if (!empty($device['code']) && !preg_match('/^[0-9a-f]{8}-/i', (string) $device['code'])) {
            return (string) $device['code'];
        }

        $classificationTypes = $device['classification_types'] ?? [];
        if (is_array($classificationTypes) && !empty($classificationTypes[0]['code'])) {
            return (string) $classificationTypes[0]['code'];
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function resolveDeviceRequestAllowedCodeTypes(?string $programId): array
    {
        if (empty($programId)) {
            return [];
        }

        try {
            $program = dictionary()->medicalPrograms()->firstWhere('id', $programId);
            $types = $program['medical_program_settings']['device_request_allowed_code_types'] ?? [];

            return is_array($types) ? $types : [];
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findDeviceDefinitionByReference(string $reference): ?array
    {
        if (!preg_match('/^[0-9a-f]{8}-/i', $reference)) {
            $devices = $this->normalizeEHealthList(
                EHealth::deviceDefinition()->getMany([
                    'classification_type_code' => $reference,
                ])->getData()
            );

            return $devices[0] ?? null;
        }

        $filters = ['page_size' => 50];
        if (!empty($this->selectedProgram)) {
            $filters['medical_program_id'] = $this->selectedProgram;
        }

        for ($page = 1; $page <= 20; $page++) {
            $filters['page'] = $page;
            $devices = $this->normalizeEHealthList(
                EHealth::deviceDefinition()->getMany($filters)->getData()
            );

            foreach ($devices as $device) {
                if (($device['id'] ?? null) === $reference) {
                    return $device;
                }
            }

            if (count($devices) < 50) {
                break;
            }
        }

        return null;
    }

    private function flattenServices(array $nodes): array
    {
        $services = [];
        foreach ($nodes as $node) {
            if (isset($node['request_allowed']) && $node['request_allowed'] && !empty($node['code'])) {
                $services[$node['id']] = $node;
            }

            if (!empty($node['services'])) {
                foreach ($node['services'] as $service) {
                    if (!empty($service['id'])) {
                        $services[$service['id']] = $service;
                    }
                }
            }

            if (!empty($node['groups'])) {
                $subServices = $this->flattenServices($node['groups']);
                foreach ($subServices as $id => $service) {
                    $services[$id] = $service;
                }
            }
        }

        return array_values($services);
    }

    public function searchMedications(): void
    {
        if (empty($this->searchQuery)) {
            $this->searchResults = [];

            return;
        }

        try {
            $filters = [
                'innm_name' => $this->searchQuery,
                'page' => $this->searchPage,
                'page_size' => 15,
            ];

            if (!empty($this->selectedProgram)) {
                $filters['medical_program_id'] = $this->selectedProgram;
            }

            $response = EHealth::drug()->getMany($filters);

            $this->searchResults = $response->getData();
        } catch (\Exception $e) {
            Log::error("Failed to search medications: " . $e->getMessage());
            $this->searchResults = [];
        }
    }

    public function searchMedicalDevices(): void
    {
        if (empty($this->searchQuery)) {
            $this->searchResults = [];

            return;
        }

        try {
            $filters = [
                'name' => $this->searchQuery,
                'page' => $this->searchPage,
                'page_size' => 15,
            ];

            if (!empty($this->selectedProgram)) {
                $filters['medical_program_id'] = $this->selectedProgram;
            }

            $response = EHealth::deviceDefinition()->getMany($filters);

            $this->searchResults = $this->normalizeEHealthList($response->getData());
        } catch (\Exception $e) {
            Log::error("Failed to search medical devices: " . $e->getMessage());
            $this->searchResults = [];
        }
    }

    public function selectProduct(array $product, string $kind): void
    {
        $this->selectedProduct = $product;

        if ($kind === 'device_request') {
            $this->activityForm['product_reference'] = (string) ($product['id'] ?? $product['uuid'] ?? '');
            $this->activityForm['product_codeable_concept'] = $this->resolveDeviceClassificationCode($product) ?? '';
        } else {
            $this->activityForm['product_reference'] = $product['id'] ?? $product['uuid'] ?? $product['code'] ?? '';
        }

        if ($kind === 'service_request') {
            $this->activityForm['product_codeable_concept'] = $product['code'] ?? '';
            $this->activityForm['quantity_system'] = 'SERVICE_UNIT';
            $this->activityForm['quantity_code'] = 'PIECE';
            $this->showServiceSearchDrawer = false;
            $this->showServiceDrawer = true;
        } elseif ($kind === 'medication_request') {
            $this->activityForm['quantity_system'] = 'MEDICATION_UNIT';
            $this->activityForm['quantity_code'] = $product['innm_dosage_form'] ?? 'ml';
            $this->activityForm['program'] = $this->selectedProgram;
            $this->showMedicationSearchDrawer = false;
            $this->showMedicationFormDrawer = true;
        } elseif ($kind === 'device_request') {
            $this->activityForm['quantity_system'] = 'device_unit';
            $this->activityForm['quantity_code'] = 'piece';
            $this->activityForm['program'] = $this->selectedProgram;

            $packaging = $product['packaging'] ?? null;
            if (is_array($packaging) && !empty($packaging['packaging_count'])) {
                $this->activityForm['quantity'] = (int) $packaging['packaging_count'];
            }

            $this->deviceSelectionWarning = '';
            $this->showMedicalDeviceSearchDrawer = false;
            $this->showMedicalDeviceFormDrawer = true;
        }
    }

    public function addLinkedGround(string $type, string $uuid): void
    {
        $exists = collect($this->linkedGrounds)->contains('uuid', $uuid);
        if ($exists) {
            return;
        }

        $name = 'Unknown Record';
        $date = '-';
        if ($type === 'Condition') {
            $item = collect($this->availableConditions)->firstWhere('uuid', $uuid);
            if ($item) {
                $name = $item['name'];
                $date = $item['date'];
            }
        } elseif ($type === 'DiagnosticReport') {
            $item = collect($this->availableReports)->firstWhere('uuid', $uuid);
            if ($item) {
                $name = $item['name'];
                $date = $item['date'];
            }
        } elseif ($type === 'Observation') {
            $item = collect($this->availableObservations)->firstWhere('uuid', $uuid);
            if ($item) {
                $name = $item['name'];
                $date = $item['date'];
            }
        }

        $this->linkedGrounds[] = [
            'type' => $type,
            'uuid' => $uuid,
            'name' => $name,
            'date' => $date,
        ];
    }

    public function removeLinkedGround(string $uuid): void
    {
        $this->linkedGrounds = collect($this->linkedGrounds)
            ->filter(fn ($g) => $g['uuid'] !== $uuid)
            ->values()
            ->toArray();
    }
}
