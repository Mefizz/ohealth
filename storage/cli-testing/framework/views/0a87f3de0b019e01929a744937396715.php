<fieldset class="fieldset !mb-6 !max-w-full !rounded-xl !border-gray-100 bg-white !p-6 !shadow-none dark:!border-gray-700 dark:bg-gray-800">
    <legend class="legend"><?php echo e(__('care-plan.patient_data') ?? 'Дані пацієнта'); ?></legend>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($allowsPatientChange && $personId <= 0): ?>
        <div>
            <div class="mb-4 flex items-center gap-1.5 font-semibold text-gray-900 dark:text-white">
                <?php
                // Parse arguments from the directive
                $iconArgs = ['search-outline', 'w-4.5 h-4.5'];
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
                <p><?php echo e(__('patients.patient_search')); ?></p>
            </div>

            <div class="form-row-3">
                <div class="form-group group">
                    <input
                        wire:model="patientSearch.firstName"
                        type="text"
                        name="searchFirstName"
                        id="searchFirstName"
                        class="input peer <?php $__errorArgs = ['patientSearch.firstName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> input-error <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                        placeholder=" "
                        autocomplete="off"
                    />
                    <label for="searchFirstName" class="label"> <?php echo e(__('forms.first_name')); ?> </label>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['patientSearch.firstName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="text-error"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="form-group group">
                    <input
                        wire:model="patientSearch.lastName"
                        type="text"
                        name="searchLastName"
                        id="searchLastName"
                        class="input peer <?php $__errorArgs = ['patientSearch.lastName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> input-error <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                        placeholder=" "
                        autocomplete="off"
                    />
                    <label for="searchLastName" class="label"> <?php echo e(__('forms.last_name')); ?> </label>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['patientSearch.lastName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="text-error"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="form-group group">
                    <div class="datepicker-wrapper">
                        <input
                            wire:model="patientSearch.birthDate"
                            datepicker-max-date="<?php echo e(now()->format(config('app.date_format'))); ?>"
                            type="text"
                            name="searchBirthDate"
                            id="searchBirthDate"
                            class="datepicker-input with-leading-icon input peer <?php $__errorArgs = ['patientSearch.birthDate'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> input-error <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                            placeholder=" "
                            autocomplete="off"
                        />
                        <label for="searchBirthDate" class="wrapped-label"> <?php echo e(__('forms.birth_date')); ?> </label>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['patientSearch.birthDate'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="text-error"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <div class="mt-4">
                <button
                    wire:click.prevent="searchForPatient"
                    type="button"
                    class="button-primary flex items-center gap-2"
                    wire:loading.attr="disabled"
                    wire:target="searchForPatient"
                >
                    <?php
                // Parse arguments from the directive
                $iconArgs = ['search', 'w-4 h-4'];
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
                    <span><?php echo e(__('patients.search') ?? __('care-plan.search') ?? 'Пошук'); ?></span>
                </button>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($patientSearchResults)): ?>
                <div class="index-table-wrapper mt-6 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="index-table w-full table-auto">
                        <thead class="index-table-thead">
                            <tr>
                                <th scope="col" class="index-table-th"><?php echo e(__('care-plan.patient')); ?></th>
                                <th scope="col" class="index-table-th"><?php echo e(__('forms.birth_date')); ?></th>
                                <th scope="col" class="index-table-th text-center"><?php echo e(__('forms.actions')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $patientSearchResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $result): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr class="index-table-tr" wire:key="patient-search-<?php echo e($result['id']); ?>">
                                    <td class="index-table-td font-medium text-gray-900 dark:text-white">
                                        <?php echo e($result['name']); ?>

                                    </td>
                                    <td class="index-table-td text-gray-600 dark:text-gray-300">
                                        <?php echo e($result['birthDate']); ?>

                                    </td>
                                    <td class="index-table-td text-center">
                                        <button
                                            type="button"
                                            wire:click="selectPatient(<?php echo e($result['id']); ?>)"
                                            class="button-primary-outline px-4 py-1.5 text-sm"
                                        >
                                            <?php echo e(__('forms.select')); ?>

                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="form-row-2">
            <div class="form-group group relative">
                <input
                    type="text"
                    name="patient"
                    id="patient"
                    class="input peer"
                    placeholder=" "
                    autocomplete="off"
                    wire:model="form.patient"
                    readonly
                    required
                />

                <label for="patient" class="label"> <?php echo e(__('care-plan.patient')); ?> </label>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.patient'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-error" id="error-form-patient"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="form-group group">
                <input
                    type="text"
                    name="medical_number"
                    id="medical_number"
                    class="input peer"
                    placeholder=" "
                    autocomplete="off"
                    wire:model="form.medical_number"
                    readonly
                    required
                />

                <label for="medical_number" class="label">
                    <?php echo e(__('care-plan.medical_number') ?? 'Медичний запис №'); ?>

                </label>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.medical_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-error" id="error-form-medical_number"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($allowsPatientChange && $personId > 0): ?>
            <div class="mt-2">
                <button
                    type="button"
                    wire:click="clearSelectedPatient"
                    class="text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400"
                >
                    <?php echo e(__('patients.change')); ?>

                </button>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($personId > 0): ?>
        <div class="form-row-2 mt-5">
            <div class="form-group group">
                <select
                    id="encounter_select"
                    name="encounter_select"
                    class="input-select peer"
                    wire:model.live="form.encounter"
                >
                    <option value=""><?php echo e(__('forms.select')); ?> ...</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $availableEncounters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $enc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($enc['uuid']); ?>"><?php echo e($enc['label']); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
                <label for="encounter_select" class="label"> <?php echo e(__('care-plan.encounter') ?? 'Взаємодія'); ?> </label>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(empty($availableEncounters)): ?>
                    <p class="mt-1 text-sm text-amber-600">
                        ⚠️ <?php echo e(__('care-plan.no_ehealth_encounters') ?? 'У пацієнта немає підтверджених ЕСОЗ взаємодій. Спочатку створіть та підпишіть взаємодію.'); ?>

                    </p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.encounter'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-error"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</fieldset>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/care-plan/parts/patient_data.blade.php ENDPATH**/ ?>