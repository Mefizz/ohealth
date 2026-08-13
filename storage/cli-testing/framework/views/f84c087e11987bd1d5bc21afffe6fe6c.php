<?php use \App\Livewire\CarePlan\CarePlanShow; ?>
<?php use \App\Enums\CarePlanStatus; ?>
<?php use \App\Enums\Status; ?>

<?php if (isset($component)) { $__componentOriginal6d9e3514a1cea5bcf1d55f6370d4b5a0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6d9e3514a1cea5bcf1d55f6370d4b5a0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.patient','data' => ['personId' => $carePlan->person_id,'uuid' => $carePlan->person?->uuid ?? null,'patientFullName' => $carePlan->person?->full_name ?? '','hideNavigation' => true,'breadcrumbs' => [
        ['label' => __('general.home') ?? 'Головна', 'url' => route('dashboard', [legalEntity()])],
        ['label' => $carePlan->person?->full_name ?? __('care-plan.patient') ?? 'Пацієнт', 'url' => route('persons.care-plans', [legalEntity(), $carePlan->person_id])],
        ['label' => __('care-plan.care_plan') . ' №' . ($carePlan->requisition ?? $carePlan->id)]
    ]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.patient'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['personId' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($carePlan->person_id),'uuid' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($carePlan->person?->uuid ?? null),'patientFullName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($carePlan->person?->full_name ?? ''),'hideNavigation' => true,'breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([
        ['label' => __('general.home') ?? 'Головна', 'url' => route('dashboard', [legalEntity()])],
        ['label' => $carePlan->person?->full_name ?? __('care-plan.patient') ?? 'Пацієнт', 'url' => route('persons.care-plans', [legalEntity(), $carePlan->person_id])],
        ['label' => __('care-plan.care_plan') . ' №' . ($carePlan->requisition ?? $carePlan->id)]
    ])]); ?>
     <?php $__env->slot('headerActions', null, []); ?>  <?php $__env->endSlot(); ?>

    <div
        class="shift-content mt-6 pl-4"
        x-data="{
            activeTab: 'info',
            openDropdown: false,
            showServiceDrawer: <?php if ((object) ('showServiceDrawer') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showServiceDrawer'->value()); ?>')<?php echo e('showServiceDrawer'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showServiceDrawer'); ?>')<?php endif; ?>,
            showServiceSearchDrawer: <?php if ((object) ('showServiceSearchDrawer') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showServiceSearchDrawer'->value()); ?>')<?php echo e('showServiceSearchDrawer'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showServiceSearchDrawer'); ?>')<?php endif; ?>,
            showMedicationDrawer: <?php if ((object) ('showMedicationDrawer') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showMedicationDrawer'->value()); ?>')<?php echo e('showMedicationDrawer'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showMedicationDrawer'); ?>')<?php endif; ?>,
            showMedicationSearchDrawer: <?php if ((object) ('showMedicationSearchDrawer') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showMedicationSearchDrawer'->value()); ?>')<?php echo e('showMedicationSearchDrawer'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showMedicationSearchDrawer'); ?>')<?php endif; ?>,
            showMedicationFormDrawer: <?php if ((object) ('showMedicationFormDrawer') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showMedicationFormDrawer'->value()); ?>')<?php echo e('showMedicationFormDrawer'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showMedicationFormDrawer'); ?>')<?php endif; ?>,
            showMedicalDeviceDrawer: <?php if ((object) ('showMedicalDeviceDrawer') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showMedicalDeviceDrawer'->value()); ?>')<?php echo e('showMedicalDeviceDrawer'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showMedicalDeviceDrawer'); ?>')<?php endif; ?>,
            showMedicalDeviceSearchDrawer: <?php if ((object) ('showMedicalDeviceSearchDrawer') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showMedicalDeviceSearchDrawer'->value()); ?>')<?php echo e('showMedicalDeviceSearchDrawer'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showMedicalDeviceSearchDrawer'); ?>')<?php endif; ?>,
            showMedicalDeviceFormDrawer: <?php if ((object) ('showMedicalDeviceFormDrawer') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showMedicalDeviceFormDrawer'->value()); ?>')<?php echo e('showMedicalDeviceFormDrawer'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showMedicalDeviceFormDrawer'); ?>')<?php endif; ?>,
            showReferralDrawer: <?php if ((object) ('showReferralDrawer') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showReferralDrawer'->value()); ?>')<?php echo e('showReferralDrawer'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showReferralDrawer'); ?>')<?php endif; ?>.live,
            showEPrescriptionDrawer: <?php if ((object) ('showEPrescriptionDrawer') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showEPrescriptionDrawer'->value()); ?>')<?php echo e('showEPrescriptionDrawer'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showEPrescriptionDrawer'); ?>')<?php endif; ?>.live
         }"
        @close-drawers.window="
            showServiceDrawer = false;
            showServiceSearchDrawer = false;
            showMedicationDrawer = false;
            showMedicationSearchDrawer = false;
            showMedicationFormDrawer = false;
            showMedicalDeviceDrawer = false;
            showMedicalDeviceSearchDrawer = false;
            showMedicalDeviceFormDrawer = false;
            showReferralDrawer = false;
            showEPrescriptionDrawer = false;
        "
        wire:key="care-plan-show-container"
    >
        <div class="w-full max-w-screen-xl">
            <?php
                $status = is_array($carePlan->status) ? ($carePlan->status['coding'][0]['code'] ?? ($carePlan->status['text'] ?? '')) : $carePlan->status;
                $statusEnum = CarePlanStatus::tryFrom(strtolower(str_replace('_', '-', (string) $status))) ?? CarePlanStatus::UNKNOWN;

                $categoryLabel = $carePlan->categoryConcept?->text ?? $carePlan->categoryConcept?->coding?->first()?->display;
                if (!$categoryLabel) {
                    $categoryCode = is_array($carePlan->category) ? ($carePlan->category['coding'][0]['code'] ?? ($carePlan->category['text'] ?? '')) : $carePlan->category;
                    $categoryLabel = $dictionaries['care_plan_categories'][$categoryCode] ?? $categoryCode;
                }

                $intent = 'order'; // In eHealth plans always have intent 'order'
                $tos = is_array($carePlan->terms_of_service) ? ($carePlan->terms_of_service['coding'][0]['code'] ?? ($carePlan->terms_of_service['text'] ?? '')) : $carePlan->terms_of_service;
            ?>

            <!-- Tabs Navigation -->
            <div class="mb-6 flex items-center justify-between">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <button
                        @click="activeTab = 'info'"
                        type="button"
                        :class="activeTab === 'info'
                            ? 'border-blue-500 text-blue-600 dark:text-blue-500 font-bold'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 font-medium'"
                        class="cursor-pointer border-b-2 px-1 pb-4 text-sm whitespace-nowrap transition-all"
                    >
                        <?php echo e(__('care-plan.plan_info') ?? 'Інформація про план'); ?>

                    </button>
                    <button
                        @click="activeTab = 'activities'"
                        type="button"
                        :class="activeTab === 'activities'
                            ? 'border-blue-500 text-blue-600 dark:text-blue-500 font-bold'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 font-medium'"
                        class="cursor-pointer border-b-2 px-1 pb-4 text-sm whitespace-nowrap transition-all"
                    >
                        <?php echo e(__('care-plan.activities') ?? 'Призначення'); ?> (<?php echo e($carePlan->activities->count()); ?>)
                    </button>
                </nav>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array(strtolower((string)$status), [CarePlanStatus::ACTIVE->value, CarePlanStatus::DRAFT->value, 'new', 'pending'])): ?>
                    <div class="relative pb-2">
                        <button
                            type="button"
                            @click="openDropdown = ! openDropdown"
                            @click.away="openDropdown = false"
                            class="button-primary flex items-center gap-2"
                        >
                            <span>+ <?php echo e(__('care-plan.new_prescription')); ?></span>
                            <?php
                // Parse arguments from the directive
                $iconArgs = ['chevron-down', 'w-4 h-4'];
                $iconName = trim($iconArgs[0], "'\"");
                $iconClass = $iconArgs[1] ?? '';
                $iconFile = resource_path('icons/' . $iconName . '.svg');
                $svgContent = file_exists($iconFile) ? file_get_contents($iconFile) : '';
                if ($iconClass && $svgContent) {
                    // Inject class attribute into SVG tag
                    $svgContent = preg_replace(
                        '/<svg(.*?)(class=".*?")?(.*?)>/',
                        '<svg$1 class="' . e($iconClass) . '"$3>',
                        $svgContent
                    );
                }
                echo $svgContent;
            ?>
                        </button>

                        <div
                            x-show="openDropdown"
                            x-transition
                            style="display: none"
                            class="ring-opacity-5 absolute right-0 z-10 mt-2 w-56 origin-top-right rounded-md border border-gray-100 bg-white shadow-lg ring-1 ring-black focus:outline-none dark:border-gray-600 dark:bg-gray-700"
                        >
                            <div class="py-1" role="none">
                                <button
                                    type="button"
                                    @click="
                                        openDropdown = false;
                                        showServiceDrawer = true;
                                    "
                                    wire:click="initActivityForm('service_request')"
                                    class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-600"
                                >
                                    <?php echo e(__('care-plan.service_prescription')); ?>

                                </button>
                                <button
                                    type="button"
                                    @click="
                                        openDropdown = false;
                                        showMedicationDrawer = true;
                                    "
                                    wire:click="initActivityForm('medication_request')"
                                    class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-600"
                                >
                                    <?php echo e(__('care-plan.medication_prescription')); ?>

                                </button>
                                <button
                                    type="button"
                                    @click="
                                        openDropdown = false;
                                        showMedicalDeviceDrawer = true;
                                    "
                                    wire:click="initActivityForm('device_request')"
                                    class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-600"
                                >
                                    <?php echo e(__('care-plan.medical_device_prescription')); ?>

                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <!-- Info Tab Content -->
            <div x-show="activeTab === 'info'">
                
                <fieldset class="fieldset !mb-6 !max-w-full !rounded-xl !border-gray-100 bg-white !p-6 !shadow-none dark:!border-gray-700 dark:bg-gray-800">
                    <legend class="legend"><?php echo e(__('care-plan.doctors') ?? 'Лікарі'); ?></legend>

                    <div class="form">
                        <div class="form-row-2">
                            <div class="form-group group">
                                <input
                                    type="text"
                                    class="input peer"
                                    value="<?php echo e($carePlan->author?->party?->full_name ?? '-'); ?>"
                                    readonly
                                />
                                <label class="label"> <?php echo e(__('care-plan.author') ?? 'Автор'); ?> </label>
                            </div>
                            <div class="form-group group">
                                <input
                                    type="text"
                                    class="input peer"
                                    value="<?php echo e($carePlan->author?->party?->full_name ?? '-'); ?>"
                                    readonly
                                />
                                <label class="label"> <?php echo e(__('care-plan.managing_doctor') ?? 'Керуючий лікар'); ?> </label>
                            </div>
                        </div>
                    </div>
                </fieldset>

                
                <fieldset class="fieldset !mb-6 !max-w-full !rounded-xl !border-gray-100 bg-white !p-6 !shadow-none dark:!border-gray-700 dark:bg-gray-800">
                    <legend class="legend"><?php echo e(__('care-plan.patient_data') ?? 'Дані пацієнта'); ?></legend>

                    <div class="form">
                        <div class="form-row-2">
                            <div class="form-group group">
                                <input
                                    type="text"
                                    class="input peer"
                                    value="<?php echo e($carePlan->person?->full_name ?? '-'); ?>"
                                    readonly
                                />
                                <label class="label"> <?php echo e(__('care-plan.patient') ?? 'Пацієнт'); ?> </label>
                            </div>

                            <div class="form-group group">
                                <input
                                    type="text"
                                    class="input peer"
                                    value="<?php echo e($carePlan->medical_number ?? ($carePlan->encounter_id ? (string)$carePlan->encounter_id : '-')); ?>"
                                    readonly
                                />
                                <label class="label">
                                    <?php echo e(__('care-plan.medical_number') ?? 'Медичний запис №'); ?>

                                </label>
                            </div>
                        </div>
                    </div>
                </fieldset>

                
                <fieldset class="fieldset !mb-6 !max-w-full !rounded-xl !border-gray-100 bg-white !p-6 !shadow-none dark:!border-gray-700 dark:bg-gray-800">
                    <legend class="legend"><?php echo e(__('care-plan.care_plan_data') ?? 'Дані плану лікування'); ?></legend>

                    <div class="form">
                        <div class="form-row-2">
                            <div class="form-group group">
                                <input
                                    type="text"
                                    class="input peer font-mono text-sm"
                                    value="<?php echo e($carePlan->uuid ?? '-'); ?>"
                                    readonly
                                />
                                <label class="label"> <?php echo e(__('care-plan.ehealth_id')); ?> </label>
                            </div>

                            <div class="form-group group flex items-center">
                                <div class="mt-2.5">
                                    <span class="<?php echo e($statusEnum->color()); ?>"> <?php echo e($statusEnum->label()); ?> </span>
                                </div>
                                <label class="label"> <?php echo e(__('care-plan.status_in_ehealth')); ?> </label>
                            </div>
                        </div>

                        <div class="form-row-2 mt-5">
                            <div class="form-group group">
                                <input type="text" class="input peer" value="<?php echo e($categoryLabel ?: '-'); ?>" readonly />
                                <label class="label"> <?php echo e(__('care-plan.category')); ?> </label>
                            </div>

                            <div class="form-group group">
                                <input type="text" class="input peer" value="<?php echo e($carePlan->title); ?>" readonly />
                                <label class="label"> <?php echo e(__('care-plan.name_care_plan')); ?> </label>
                            </div>
                        </div>

                        <div class="form-row-2 mt-5">
                            <div class="form-group group">
                                <input
                                    type="text"
                                    class="input peer"
                                    value="<?php echo e(__('care-plan.assignment') ?? $intent); ?>"
                                    readonly
                                />
                                <label class="label"> <?php echo e(__('care-plan.intention')); ?> </label>
                            </div>

                            <div class="form-group group">
                                <?php
                                    $tosLabel = $carePlan->care_provision_conditions ?? $tos ?? '-';
                                ?>
                                <input type="text" class="input peer" value="<?php echo e($tosLabel); ?>" readonly />
                                <label class="label">
                                    <?php echo e(__('forms.providing_condition') ?? __('care-plan.terms_of_service')); ?>

                                </label>
                            </div>
                        </div>

                        <div class="form-row-2 mt-5">
                            <div class="form-group group">
                                <div class="datepicker-wrapper">
                                    <input
                                        type="text"
                                        class="datepicker-input with-leading-icon input peer"
                                        value="<?php echo e($carePlan->period_start?->format(config('app.date_format') ?? 'd.m.Y') ?? '-'); ?>"
                                        readonly
                                    />
                                    <label class="wrapped-label"> <?php echo e(__('care-plan.period_start_date')); ?> </label>
                                </div>
                            </div>

                            <div class="form-group group">
                                <div class="datepicker-wrapper">
                                    <input
                                        type="text"
                                        class="datepicker-input with-leading-icon input peer"
                                        value="<?php echo e($carePlan->period_end ? $carePlan->period_end->format(config('app.date_format') ?? 'd.m.Y') : __('care-plan.no_end_date')); ?>"
                                        readonly
                                    />
                                    <label class="wrapped-label"> <?php echo e(__('care-plan.period_end_date')); ?> </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </fieldset>

                
                <fieldset class="fieldset !mb-6 !max-w-full !rounded-xl !border-gray-100 bg-white !p-6 !shadow-none dark:!border-gray-700 dark:bg-gray-800">
                    <legend class="legend"><?php echo e(__('care-plan.condition_diagnosis') ?? 'Стан/діагноз'); ?></legend>

                    <div class="index-table-wrapper mt-4">
                        <table class="index-table w-full">
                            <thead class="index-table-thead">
                                <tr>
                                    <th class="index-table-th w-40"><?php echo e(__('care-plan.date')); ?></th>
                                    <th class="index-table-th"><?php echo e(__('care-plan.name')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $carePlan->addresses ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $address): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <?php
                                        $condId = is_array($address['reference'] ?? null) ? ($address['reference']['identifier']['value'] ?? null) : ($address['reference'] ?? null);
                                        if (str_contains($condId ?? '', '/')) {
                                            $condId = last(explode('/', $condId));
                                        }
                                        $condition = null;
                                        if ($condId) {
                                            $condition = \App\Models\MedicalEvents\Sql\Condition::where('uuid', $condId)->first();
                                        }
                                    ?>
                                    <tr class="index-table-tr">
                                        <td class="index-table-td">
                                            <?php echo e($condition?->onset_date?->format('d.m.Y') ?? '-'); ?>

                                        </td>
                                        <td class="index-table-td-primary">
                                            <?php echo e($condition ? ($condition->typeConcept?->text ?? $condition->typeConcept?->coding->first()?->display ?? '-') : '-'); ?>

                                        </td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <tr>
                                        <td colspan="2" class="index-table-td !py-3 text-center text-gray-400">
                                            <?php echo e(__('care-plan.no_diagnoses')); ?>

                                        </td>
                                    </tr>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </fieldset>

                
                <fieldset class="fieldset !mb-6 !max-w-full !rounded-xl !border-gray-100 bg-white !p-6 !shadow-none dark:!border-gray-700 dark:bg-gray-800">
                    <legend class="legend"><?php echo e(__('care-plan.supporting_information')); ?></legend>

                    <div class="mt-4 space-y-8">
                        <?php
                            $episodes = $carePlan->supporting_info['episodes'] ?? [];
                            $medical_records = $carePlan->supporting_info['medical_records'] ?? [];
                        ?>

                        <div class="space-y-3">
                            <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                <?php echo e(__('care-plan.episodes') ?? 'Епізоди'); ?>

                            </div>
                            <div class="index-table-wrapper overflow-x-auto">
                                <table class="index-table w-full">
                                    <thead class="index-table-thead">
                                        <tr>
                                            <th class="index-table-th w-32"><?php echo e(__('care-plan.date')); ?></th>
                                            <th class="index-table-th"><?php echo e(__('care-plan.name_episode')); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $episodes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                            <?php
                                                $ref = $item['name'] ?? ($item['reference'] ?? ($item['uuid'] ?? '-'));
                                                $date = $item['date'] ?? \Carbon\CarbonImmutable::now()->format('d.m.Y');
                                            ?>
                                            <tr class="index-table-tr">
                                                <td class="index-table-td"><?php echo e($date); ?></td>
                                                <td class="index-table-td-primary"><?php echo e($ref); ?></td>
                                            </tr>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                            <tr>
                                                <td colspan="2" class="index-table-td !py-3 text-center text-gray-400">
                                                    <?php echo e(__('care-plan.no_episodes') ?? 'Немає пов\'язаних епізодів'); ?>

                                                </td>
                                            </tr>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                <?php echo e(__('care-plan.medical_records') ?? 'Медичні записи'); ?>

                            </div>
                            <div class="index-table-wrapper overflow-x-auto">
                                <table class="index-table w-full">
                                    <thead class="index-table-thead">
                                        <tr>
                                            <th class="index-table-th w-32"><?php echo e(__('care-plan.date')); ?></th>
                                            <th class="index-table-th"><?php echo e(__('care-plan.medical_record')); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $medical_records; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                            <?php
                                                $ref = $item['name'] ?? ($item['reference'] ?? ($item['uuid'] ?? '-'));
                                                $date = $item['date'] ?? \Carbon\CarbonImmutable::now()->format('d.m.Y');
                                            ?>
                                            <tr class="index-table-tr">
                                                <td class="index-table-td"><?php echo e($date); ?></td>
                                                <td class="index-table-td-primary"><?php echo e($ref); ?></td>
                                            </tr>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                            <tr>
                                                <td colspan="2" class="index-table-td !py-3 text-center text-gray-400">
                                                    <?php echo e(__('care-plan.no_records') ?? 'Немає пов\'язаних медичних записів'); ?>

                                                </td>
                                            </tr>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </fieldset>

                
                <fieldset class="fieldset !mb-6 !max-w-full !rounded-xl !border-gray-100 bg-white !p-6 !shadow-none dark:!border-gray-700 dark:bg-gray-800">
                    <legend class="legend"><?php echo e(__('forms.additional_info')); ?></legend>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                            <?php echo e(__('care-plan.extended_description')); ?>

                        </label>
                        <div class="min-h-[90px] rounded-lg border border-gray-100 bg-gray-50 p-4 text-sm whitespace-pre-line text-gray-700 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-300">
                            <?php echo e($carePlan->description ?: __('care-plan.no_description')); ?>

                        </div>
                    </div>

                    <div class="mt-6">
                        <label class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                            <?php echo e(__('care-plan.notes')); ?>

                        </label>
                        <div class="min-h-[90px] rounded-lg border border-gray-100 bg-gray-50 p-4 text-sm whitespace-pre-line text-gray-700 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-300">
                            <?php echo e($carePlan->note ?: __('care-plan.no_notes')); ?>

                        </div>
                    </div>
                </fieldset>

                
                <div class="mb-6">
                    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('care-plan.care-plan-approvals', ['carePlan' => $carePlan]);

$__key = null;

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-2285668653-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key);

echo $__html;

unset($__html);
unset($__key);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
                </div>

                
                <div class="mt-8 flex items-center gap-4 pt-4 pb-12">
                    <a
                        href="<?php echo e(route('persons.care-plans', [legalEntity(), $carePlan->person_id])); ?>"
                        class="button-minor flex items-center gap-2"
                        wire:navigate
                    >
                        <?php
                // Parse arguments from the directive
                $iconArgs = ['arrow-left', 'w-4 h-4'];
                $iconName = trim($iconArgs[0], "'\"");
                $iconClass = $iconArgs[1] ?? '';
                $iconFile = resource_path('icons/' . $iconName . '.svg');
                $svgContent = file_exists($iconFile) ? file_get_contents($iconFile) : '';
                if ($iconClass && $svgContent) {
                    // Inject class attribute into SVG tag
                    $svgContent = preg_replace(
                        '/<svg(.*?)(class=".*?")?(.*?)>/',
                        '<svg$1 class="' . e($iconClass) . '"$3>',
                        $svgContent
                    );
                }
                echo $svgContent;
            ?>
                        <span><?php echo e(__('forms.back')); ?></span>
                    </a>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$carePlan->uuid && in_array(strtoupper($status), [Status::NEW->value, 'DRAFT', 'PENDING'])): ?>
                        <button
                            type="button"
                            class="button-primary-outline"
                            @click="$wire.openSignatureModal('sign_plan')"
                        >
                            <?php echo e(__('care-plan.sign_and_send_plan')); ?>

                        </button>
                    <?php elseif($carePlan->uuid && strtoupper($status) === 'NEW'): ?>
                        <button type="button" class="button-primary" wire:click="openMethodSelectionModal">
                            <?php echo e(__('care-plan.activate_plan_patient_approval')); ?>

                        </button>
                    <?php elseif($carePlan->uuid && in_array(strtoupper($status), [Status::ACTIVE->value])): ?>
                        <button
                            type="button"
                            class="button-primary-outline"
                            @click="$wire.openSignatureModal('cancel')"
                        >
                            <?php echo e(__('care-plan.cancel_care_plan')); ?>

                        </button>
                        <button type="button" class="button-primary" @click="$wire.openSignatureModal('complete')">
                            <?php echo e(__('care-plan.complete_care_plan')); ?>

                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <!-- Activities Tab Content -->
            <div x-show="activeTab === 'activities'" style="display: none">
                <fieldset class="fieldset !mb-6 !max-w-full !rounded-xl !border-gray-100 bg-white !p-6 !shadow-none dark:!border-gray-700 dark:bg-gray-800">
                    <legend class="legend"><?php echo e(__('care-plan.activities')); ?></legend>

                    <div class="index-table-wrapper mt-4">
                        <table class="index-table w-full">
                            <thead class="index-table-thead">
                                <tr>
                                    <th class="index-table-th w-[35%]"><?php echo e(__('care-plan.kind')); ?></th>
                                    <th class="index-table-th w-[15%]"><?php echo e(__('care-plan.quantity')); ?></th>
                                    <th class="index-table-th w-[20%]"><?php echo e(__('forms.start_date')); ?></th>
                                    <th class="index-table-th w-[15%]"><?php echo e(__('forms.status.label')); ?></th>
                                    <th class="index-table-th w-[15%] text-right"><?php echo e(__('forms.actions')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $carePlan->activities ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $activity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <tr class="index-table-tr">
                                        <td class="index-table-td">
                                            <?php
                                                $resolvedKind = $activity->resolvedKind();
                                                $kindTranslationKey = 'care-plan.activity_kind.' . $resolvedKind;
                                                $translatedKind = \Illuminate\Support\Facades\Lang::has($kindTranslationKey) ? __($kindTranslationKey) : $resolvedKind;
                                            ?>
                                            <div class="font-medium text-gray-900 dark:text-white">
                                                <?php echo e($translatedKind ?: '-'); ?>

                                            </div>
                                            <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activity->uuid): ?>
                                                    ID:
                                                    <span class="font-mono"><?php echo e($activity->uuid); ?></span>
                                                <?php else: ?>
                                                    ID:
                                                    <span class="font-mono"><?php echo e($activity->id); ?> (<?php echo e(__('care-plan.status.draft')); ?>)</span>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="index-table-td">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_array($activity->quantity)): ?>
                                                <?php echo e($activity->quantity['value'] ?? '-'); ?> <?php echo e($activity->quantity['unit'] ?? ''); ?>

                                            <?php else: ?>
                                                <?php echo e($activity->quantity ?? '-'); ?>

                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </td>
                                        <td class="index-table-td">
                                            <?php echo e($activity->scheduled_period_start?->format('d.m.Y')); ?>

                                        </td>
                                        <td class="index-table-td">
                                            <?php
                                                $activityStatus = is_array($activity->status) ? ($activity->status['coding'][0]['code'] ?? ($activity->status['text'] ?? '')) : $activity->status;
                                                $activityStatusEnum = CarePlanStatus::tryFrom(strtolower(str_replace('_', '-', (string) $activityStatus)));
                                                $statusKey = 'care-plan.status.' . strtolower($activityStatus);
                                                $activityStatusDisplay = $activityStatusEnum?->label()
                                                    ?? (\Illuminate\Support\Facades\Lang::has($statusKey) ? __($statusKey) : (is_array($activity->status) ? ($activity->status['text'] ?? ($activity->status['coding'][0]['display'] ?? $activityStatus)) : $activityStatus));
                                                $activityBadgeColor = $activityStatusEnum?->color()
                                                    ?? (in_array(strtoupper($activityStatus), ['NEW', 'DRAFT']) ? 'badge-yellow' : 'badge-green');
                                            ?>
                                            <span class="<?php echo e($activityBadgeColor); ?>">
                                                <?php echo e($activityStatusDisplay); ?>

                                            </span>
                                        </td>
                                        <td class="index-table-td text-right">
                                            <div
                                                x-data="{
                                                    open: false,
                                                    toggle() {
                                                        if (this.open) {
                                                            return this.close();
                                                        }
                                                        this.$refs.button.focus();
                                                        this.open = true;
                                                    },
                                                    close(focusAfter) {
                                                        if (! this.open) return;
                                                        this.open = false;
                                                        focusAfter && focusAfter.focus();
                                                    },
                                                }"
                                                @keydown.escape.prevent.stop="close($refs.button)"
                                                @focusin.window="! $refs.panel.contains($event.target) && close()"
                                                x-id="['dropdown-button']"
                                                class="relative inline-block text-left"
                                            >
                                                <button
                                                    @click="toggle()"
                                                    x-ref="button"
                                                    :aria-expanded="open"
                                                    :aria-controls="$id('dropdown-button')"
                                                    type="button"
                                                    class="record-inner-action-btn inline-flex cursor-pointer items-center justify-center rounded-lg p-2 transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                                >
                                                    <?php
                // Parse arguments from the directive
                $iconArgs = ['edit-user-outline', 'w-6 h-6 text-gray-700 dark:text-gray-300'];
                $iconName = trim($iconArgs[0], "'\"");
                $iconClass = $iconArgs[1] ?? '';
                $iconFile = resource_path('icons/' . $iconName . '.svg');
                $svgContent = file_exists($iconFile) ? file_get_contents($iconFile) : '';
                if ($iconClass && $svgContent) {
                    // Inject class attribute into SVG tag
                    $svgContent = preg_replace(
                        '/<svg(.*?)(class=".*?")?(.*?)>/',
                        '<svg$1 class="' . e($iconClass) . '"$3>',
                        $svgContent
                    );
                }
                echo $svgContent;
            ?>
                                                </button>

                                                <div
                                                    x-show="open"
                                                    x-cloak
                                                    x-ref="panel"
                                                    x-transition.origin.top.right
                                                    @click.outside="close($refs.button)"
                                                    :id="$id('dropdown-button')"
                                                    class="absolute right-0 z-50 mt-2 w-56 rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-600 dark:bg-gray-700"
                                                >
                                                    <a
                                                        href="<?php echo e(route('care-plans.activities.show', [legalEntity(), $carePlan->id, $activity->id])); ?>"
                                                        class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-600"
                                                        wire:navigate
                                                    >
                                                        <?php
                // Parse arguments from the directive
                $iconArgs = ['eye', 'w-5 h-5 text-gray-500'];
                $iconName = trim($iconArgs[0], "'\"");
                $iconClass = $iconArgs[1] ?? '';
                $iconFile = resource_path('icons/' . $iconName . '.svg');
                $svgContent = file_exists($iconFile) ? file_get_contents($iconFile) : '';
                if ($iconClass && $svgContent) {
                    // Inject class attribute into SVG tag
                    $svgContent = preg_replace(
                        '/<svg(.*?)(class=".*?")?(.*?)>/',
                        '<svg$1 class="' . e($iconClass) . '"$3>',
                        $svgContent
                    );
                }
                echo $svgContent;
            ?>
                                                        <?php echo e(__('patients.view_details')); ?>

                                                    </a>

                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array(strtoupper($activityStatus), ['NEW', 'DRAFT'])): ?>
                                                        <button
                                                            type="button"
                                                            @click="close()"
                                                            wire:click="editActivity(<?php echo e($activity->id); ?>)"
                                                            class="flex w-full cursor-pointer items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-600"
                                                        >
                                                            <?php
                // Parse arguments from the directive
                $iconArgs = ['edit', 'w-5 h-5 text-gray-500'];
                $iconName = trim($iconArgs[0], "'\"");
                $iconClass = $iconArgs[1] ?? '';
                $iconFile = resource_path('icons/' . $iconName . '.svg');
                $svgContent = file_exists($iconFile) ? file_get_contents($iconFile) : '';
                if ($iconClass && $svgContent) {
                    // Inject class attribute into SVG tag
                    $svgContent = preg_replace(
                        '/<svg(.*?)(class=".*?")?(.*?)>/',
                        '<svg$1 class="' . e($iconClass) . '"$3>',
                        $svgContent
                    );
                }
                echo $svgContent;
            ?>
                                                            <?php echo e(__('forms.edit')); ?>

                                                        </button>
                                                        <button
                                                            type="button"
                                                            @click="close()"
                                                            wire:click="openSignatureModal('sign_activity', <?php echo e($activity->id); ?>)"
                                                            class="flex w-full cursor-pointer items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-600"
                                                        >
                                                            <?php
                // Parse arguments from the directive
                $iconArgs = ['check', 'w-5 h-5 text-gray-500'];
                $iconName = trim($iconArgs[0], "'\"");
                $iconClass = $iconArgs[1] ?? '';
                $iconFile = resource_path('icons/' . $iconName . '.svg');
                $svgContent = file_exists($iconFile) ? file_get_contents($iconFile) : '';
                if ($iconClass && $svgContent) {
                    // Inject class attribute into SVG tag
                    $svgContent = preg_replace(
                        '/<svg(.*?)(class=".*?")?(.*?)>/',
                        '<svg$1 class="' . e($iconClass) . '"$3>',
                        $svgContent
                    );
                }
                echo $svgContent;
            ?>
                                                            <?php echo e(__('care-plan.sign_activity')); ?>

                                                        </button>
                                                        <button
                                                            type="button"
                                                            @click="close()"
                                                            wire:click="confirmDeleteActivity(<?php echo e($activity->id); ?>)"
                                                            class="flex w-full cursor-pointer items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-600"
                                                        >
                                                            <?php
                // Parse arguments from the directive
                $iconArgs = ['trash', 'w-5 h-5 text-gray-500'];
                $iconName = trim($iconArgs[0], "'\"");
                $iconClass = $iconArgs[1] ?? '';
                $iconFile = resource_path('icons/' . $iconName . '.svg');
                $svgContent = file_exists($iconFile) ? file_get_contents($iconFile) : '';
                if ($iconClass && $svgContent) {
                    // Inject class attribute into SVG tag
                    $svgContent = preg_replace(
                        '/<svg(.*?)(class=".*?")?(.*?)>/',
                        '<svg$1 class="' . e($iconClass) . '"$3>',
                        $svgContent
                    );
                }
                echo $svgContent;
            ?>
                                                            <?php echo e(__('forms.delete')); ?>

                                                        </button>
                                                    <?php elseif(in_array(strtoupper($activityStatus), ['ACTIVE', 'SCHEDULED', 'IN-PROGRESS', 'IN_PROGRESS', 'ON-HOLD', 'PROCESSED'])): ?>
                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolvedKind === 'medication_request'): ?>
                                                            <button
                                                                type="button"
                                                                @click="
                                                                    close();
                                                                    activeTab = 'activities';
                                                                "
                                                                wire:click="initEPrescriptionForm(<?php echo e($activity->id); ?>)"
                                                                class="flex w-full cursor-pointer items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-600"
                                                            >
                                                                <?php
                // Parse arguments from the directive
                $iconArgs = ['check', 'w-5 h-5 text-gray-500'];
                $iconName = trim($iconArgs[0], "'\"");
                $iconClass = $iconArgs[1] ?? '';
                $iconFile = resource_path('icons/' . $iconName . '.svg');
                $svgContent = file_exists($iconFile) ? file_get_contents($iconFile) : '';
                if ($iconClass && $svgContent) {
                    // Inject class attribute into SVG tag
                    $svgContent = preg_replace(
                        '/<svg(.*?)(class=".*?")?(.*?)>/',
                        '<svg$1 class="' . e($iconClass) . '"$3>',
                        $svgContent
                    );
                }
                echo $svgContent;
            ?>
                                                                <?php echo e(__('care-plan.issue_eprescription')); ?>

                                                            </button>
                                                        <?php elseif($resolvedKind === 'device_request'): ?>
                                                            <button
                                                                type="button"
                                                                @click="
                                                                    close();
                                                                    activeTab = 'activities';
                                                                "
                                                                wire:click="initReferralForm(<?php echo e($activity->id); ?>)"
                                                                class="flex w-full cursor-pointer items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-600"
                                                            >
                                                                <?php
                // Parse arguments from the directive
                $iconArgs = ['check', 'w-5 h-5 text-gray-500'];
                $iconName = trim($iconArgs[0], "'\"");
                $iconClass = $iconArgs[1] ?? '';
                $iconFile = resource_path('icons/' . $iconName . '.svg');
                $svgContent = file_exists($iconFile) ? file_get_contents($iconFile) : '';
                if ($iconClass && $svgContent) {
                    // Inject class attribute into SVG tag
                    $svgContent = preg_replace(
                        '/<svg(.*?)(class=".*?")?(.*?)>/',
                        '<svg$1 class="' . e($iconClass) . '"$3>',
                        $svgContent
                    );
                }
                echo $svgContent;
            ?>
                                                                <?php echo e(__('care-plan.issue_device_eprescription')); ?>

                                                            </button>
                                                        <?php elseif($resolvedKind === 'service_request'): ?>
                                                            <button
                                                                type="button"
                                                                @click="
                                                                    close();
                                                                    activeTab = 'activities';
                                                                "
                                                                wire:click="initReferralForm(<?php echo e($activity->id); ?>)"
                                                                class="flex w-full cursor-pointer items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-600"
                                                            >
                                                                <?php
                // Parse arguments from the directive
                $iconArgs = ['check', 'w-5 h-5 text-gray-500'];
                $iconName = trim($iconArgs[0], "'\"");
                $iconClass = $iconArgs[1] ?? '';
                $iconFile = resource_path('icons/' . $iconName . '.svg');
                $svgContent = file_exists($iconFile) ? file_get_contents($iconFile) : '';
                if ($iconClass && $svgContent) {
                    // Inject class attribute into SVG tag
                    $svgContent = preg_replace(
                        '/<svg(.*?)(class=".*?")?(.*?)>/',
                        '<svg$1 class="' . e($iconClass) . '"$3>',
                        $svgContent
                    );
                }
                echo $svgContent;
            ?>
                                                                <?php echo e(__('care-plan.create_referral')); ?>

                                                            </button>
                                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                        <button
                                                            type="button"
                                                            @click="close()"
                                                            wire:click="openSignatureModal('cancel_activity', <?php echo e($activity->id); ?>)"
                                                            class="flex w-full cursor-pointer items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-600"
                                                        >
                                                            <?php
                // Parse arguments from the directive
                $iconArgs = ['close', 'w-5 h-5 text-gray-500'];
                $iconName = trim($iconArgs[0], "'\"");
                $iconClass = $iconArgs[1] ?? '';
                $iconFile = resource_path('icons/' . $iconName . '.svg');
                $svgContent = file_exists($iconFile) ? file_get_contents($iconFile) : '';
                if ($iconClass && $svgContent) {
                    // Inject class attribute into SVG tag
                    $svgContent = preg_replace(
                        '/<svg(.*?)(class=".*?")?(.*?)>/',
                        '<svg$1 class="' . e($iconClass) . '"$3>',
                        $svgContent
                    );
                }
                echo $svgContent;
            ?>
                                                            <?php echo e(__('care-plan.cancel_activity')); ?>

                                                        </button>
                                                        <button
                                                            type="button"
                                                            @click="close()"
                                                            wire:click="openSignatureModal('complete_activity', <?php echo e($activity->id); ?>)"
                                                            class="flex w-full cursor-pointer items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-600"
                                                        >
                                                            <?php
                // Parse arguments from the directive
                $iconArgs = ['check-circle', 'w-5 h-5 text-gray-500'];
                $iconName = trim($iconArgs[0], "'\"");
                $iconClass = $iconArgs[1] ?? '';
                $iconFile = resource_path('icons/' . $iconName . '.svg');
                $svgContent = file_exists($iconFile) ? file_get_contents($iconFile) : '';
                if ($iconClass && $svgContent) {
                    // Inject class attribute into SVG tag
                    $svgContent = preg_replace(
                        '/<svg(.*?)(class=".*?")?(.*?)>/',
                        '<svg$1 class="' . e($iconClass) . '"$3>',
                        $svgContent
                    );
                }
                echo $svgContent;
            ?>
                                                            <?php echo e(__('care-plan.complete_activity')); ?>

                                                        </button>
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <tr>
                                        <td colspan="5" class="index-table-td !py-8 text-center text-gray-400 italic">
                                            <?php echo e(__('care-plan.no_activities')); ?>

                                        </td>
                                    </tr>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </fieldset>

                <div class="mt-8 flex items-center justify-between pt-4 pb-12">
                    <a
                        href="<?php echo e(route('persons.care-plans', [legalEntity(), $carePlan->person_id])); ?>"
                        class="button-minor flex items-center gap-2"
                        wire:navigate
                    >
                        <?php
                // Parse arguments from the directive
                $iconArgs = ['arrow-left', 'w-4 h-4'];
                $iconName = trim($iconArgs[0], "'\"");
                $iconClass = $iconArgs[1] ?? '';
                $iconFile = resource_path('icons/' . $iconName . '.svg');
                $svgContent = file_exists($iconFile) ? file_get_contents($iconFile) : '';
                if ($iconClass && $svgContent) {
                    // Inject class attribute into SVG tag
                    $svgContent = preg_replace(
                        '/<svg(.*?)(class=".*?")?(.*?)>/',
                        '<svg$1 class="' . e($iconClass) . '"$3>',
                        $svgContent
                    );
                }
                echo $svgContent;
            ?>
                        <span><?php echo e(__('forms.back')); ?></span>
                    </a>
                </div>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($actionType === 'cancel'): ?>
            <?php echo $__env->make('livewire.care-plan.parts.modals.cancel-care-plan-modal', ['method' => 'sign'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php elseif($actionType === 'complete'): ?>
            <?php echo $__env->make('livewire.care-plan.parts.modals.complete-care-plan-modal', ['method' => 'sign'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php elseif($actionType === 'cancel_activity'): ?>
            <?php echo $__env->make('livewire.care-plan.parts.modals.cancel-activity-modal', ['method' => 'sign'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php elseif($actionType === 'complete_activity'): ?>
            <?php echo $__env->make('livewire.care-plan.parts.modals.complete-activity-modal', ['method' => 'sign'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php else: ?>
            <?php echo $__env->make('components.signature-modal', ['method' => 'sign'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isPolling): ?>
            <div wire:poll.3s.keep-alive="checkApprovalJobStatus" class="hidden"></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showAuthModal): ?>
            <?php echo $__env->make('livewire.care-plan.modals.authentication', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showMethodSelectionModal): ?>
            <?php echo $__env->make('livewire.care-plan.modals.method-selection', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php echo $__env->make('livewire.care-plan.parts.modals.services-drawer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('livewire.care-plan.parts.modals.service-search-drawer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('livewire.care-plan.parts.modals.medications-drawer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('livewire.care-plan.parts.modals.medication-search-drawer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('livewire.care-plan.parts.modals.medication-form-drawer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('livewire.care-plan.parts.modals.medical-devices-drawer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('livewire.care-plan.parts.modals.medical-device-search-drawer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('livewire.care-plan.parts.modals.medical-device-form-drawer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('livewire.care-plan.parts.modals.referral-form-drawer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('livewire.care-plan.parts.modals.eprescription-form-drawer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <?php if (isset($component)) { $__componentOriginal5b8b2d0f151a30be878e1a760ec3900c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5b8b2d0f151a30be878e1a760ec3900c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.confirmation-modal','data' => ['wire:model.live' => 'confirmingActivityDeletion']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('confirmation-modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model.live' => 'confirmingActivityDeletion']); ?>
             <?php $__env->slot('title', null, []); ?> <?php echo e(__('care-plan.confirm_delete_activity_title')); ?> <?php $__env->endSlot(); ?>

             <?php $__env->slot('content', null, []); ?> <?php echo e(__('care-plan.confirm_delete_activity')); ?> <?php $__env->endSlot(); ?>

             <?php $__env->slot('footer', null, []); ?> 
                <?php if (isset($component)) { $__componentOriginal3b0e04e43cf890250cc4d85cff4d94af = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3b0e04e43cf890250cc4d85cff4d94af = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.secondary-button','data' => ['wire:click' => 'cancelDeleteActivity','wire:loading.attr' => 'disabled']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('secondary-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:click' => 'cancelDeleteActivity','wire:loading.attr' => 'disabled']); ?>
                    <?php echo e(__('forms.cancel')); ?>

                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3b0e04e43cf890250cc4d85cff4d94af)): ?>
<?php $attributes = $__attributesOriginal3b0e04e43cf890250cc4d85cff4d94af; ?>
<?php unset($__attributesOriginal3b0e04e43cf890250cc4d85cff4d94af); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3b0e04e43cf890250cc4d85cff4d94af)): ?>
<?php $component = $__componentOriginal3b0e04e43cf890250cc4d85cff4d94af; ?>
<?php unset($__componentOriginal3b0e04e43cf890250cc4d85cff4d94af); ?>
<?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activityToDelete): ?>
                    <?php if (isset($component)) { $__componentOriginal656e8c5ea4d9a4fa173298297bfe3f11 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal656e8c5ea4d9a4fa173298297bfe3f11 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.danger-button','data' => ['class' => 'ms-3','wire:click' => 'deleteActivity('.e($activityToDelete).')','wire:loading.attr' => 'disabled']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('danger-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'ms-3','wire:click' => 'deleteActivity('.e($activityToDelete).')','wire:loading.attr' => 'disabled']); ?>
                        <?php echo e(__('forms.delete')); ?>

                     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal656e8c5ea4d9a4fa173298297bfe3f11)): ?>
<?php $attributes = $__attributesOriginal656e8c5ea4d9a4fa173298297bfe3f11; ?>
<?php unset($__attributesOriginal656e8c5ea4d9a4fa173298297bfe3f11); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal656e8c5ea4d9a4fa173298297bfe3f11)): ?>
<?php $component = $__componentOriginal656e8c5ea4d9a4fa173298297bfe3f11; ?>
<?php unset($__componentOriginal656e8c5ea4d9a4fa173298297bfe3f11); ?>
<?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
             <?php $__env->endSlot(); ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5b8b2d0f151a30be878e1a760ec3900c)): ?>
<?php $attributes = $__attributesOriginal5b8b2d0f151a30be878e1a760ec3900c; ?>
<?php unset($__attributesOriginal5b8b2d0f151a30be878e1a760ec3900c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5b8b2d0f151a30be878e1a760ec3900c)): ?>
<?php $component = $__componentOriginal5b8b2d0f151a30be878e1a760ec3900c; ?>
<?php unset($__componentOriginal5b8b2d0f151a30be878e1a760ec3900c); ?>
<?php endif; ?>

        <?php if (isset($component)) { $__componentOriginala21f49a74cfebdbb98a47509c8a19010 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala21f49a74cfebdbb98a47509c8a19010 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.forms.loading','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('forms.loading'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala21f49a74cfebdbb98a47509c8a19010)): ?>
<?php $attributes = $__attributesOriginala21f49a74cfebdbb98a47509c8a19010; ?>
<?php unset($__attributesOriginala21f49a74cfebdbb98a47509c8a19010); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala21f49a74cfebdbb98a47509c8a19010)): ?>
<?php $component = $__componentOriginala21f49a74cfebdbb98a47509c8a19010; ?>
<?php unset($__componentOriginala21f49a74cfebdbb98a47509c8a19010); ?>
<?php endif; ?>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6d9e3514a1cea5bcf1d55f6370d4b5a0)): ?>
<?php $attributes = $__attributesOriginal6d9e3514a1cea5bcf1d55f6370d4b5a0; ?>
<?php unset($__attributesOriginal6d9e3514a1cea5bcf1d55f6370d4b5a0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6d9e3514a1cea5bcf1d55f6370d4b5a0)): ?>
<?php $component = $__componentOriginal6d9e3514a1cea5bcf1d55f6370d4b5a0; ?>
<?php unset($__componentOriginal6d9e3514a1cea5bcf1d55f6370d4b5a0); ?>
<?php endif; ?>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/care-plan/care-plan-show.blade.php ENDPATH**/ ?>