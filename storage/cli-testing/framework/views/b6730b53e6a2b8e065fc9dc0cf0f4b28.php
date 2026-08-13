
<div
    x-show="showMedicalDeviceFormDrawer"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    x-cloak
    @click="showMedicalDeviceFormDrawer = false"
    class="fixed top-0 right-0 h-screen bg-gray-900/50 pt-20"
    style="z-index: 46; width: calc(80% - 30px)"
></div>


<div
    id="medical-device-form-drawer-right"
    x-show="showMedicalDeviceFormDrawer"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="translate-x-full"
    x-transition:enter-end="translate-x-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="translate-x-0"
    x-transition:leave-end="translate-x-full"
    x-cloak
    class="fixed top-0 right-0 h-screen overflow-y-auto bg-white p-4 pt-20 shadow-2xl dark:bg-gray-800"
    style="z-index: 47; width: calc(80% - 60px)"
    tabindex="-1"
>
    <h3 class="modal-header">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($activityForm['id']) && $activityForm['id']): ?>
            <?php echo e(__('care-plan.edit_medical_device_prescription')); ?>

        <?php else: ?>
            <?php echo e(__('care-plan.new_medical_device_prescription')); ?>

        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </h3>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($deviceParticipationWarning)): ?>
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100">
            <?php echo e($deviceParticipationWarning); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($deviceSelectionWarning)): ?>
        <div class="mb-4 rounded-lg border border-blue-300 bg-blue-50 px-4 py-3 text-sm text-blue-900 dark:border-blue-700 dark:bg-blue-950/40 dark:text-blue-100">
            <?php echo e($deviceSelectionWarning); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <form wire:submit.prevent="saveActivity">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('error')): ?>
            <div
                x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'center' })"
                class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-gray-800 dark:text-red-400"
                role="alert"
            >
                <div class="flex items-center gap-2">
                    <?php
                // Parse arguments from the directive
                $iconArgs = ['alert-circle', 'w-5 h-5 text-red-500'];
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
                    <span class="font-bold">Увага!</span>
                </div>
                <div class="mt-2"><?php echo e(session('error')); ?></div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
            <div
                x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'center' })"
                class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-gray-800 dark:text-red-400"
                role="alert"
            >
                <div class="flex items-center gap-2">
                    <?php
                // Parse arguments from the directive
                $iconArgs = ['alert-circle', 'w-5 h-5 text-red-500'];
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
                    <span class="font-bold">Будь ласка, виправте помилки:</span>
                </div>
                <ul class="mt-2 list-inside list-disc">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li><?php echo e($error); ?></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </ul>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <fieldset class="fieldset">
            <legend class="legend"><?php echo e(__('care-plan.main_data')); ?></legend>

            
            <div class="mb-4 grid grid-cols-1 gap-6 md:grid-cols-2">
                <div class="form-group group">
                    <label class="label" for="device_program_edit"> <?php echo e(__('care-plan.program')); ?> </label>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($activityForm['id'])): ?>
                        <select id="device_program_edit" class="input-select peer" wire:model.live="selectedProgram">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ($dictionaries['medical_programs_device'] ?? $dictionaries['medical_programs'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $id => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($id); ?>"><?php echo e($name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </select>
                    <?php else: ?>
                        <input
                            type="text"
                            class="input cursor-not-allowed bg-gray-50 dark:bg-gray-700"
                            value="<?php echo e(!empty($activityForm['program']) ? ($dictionaries['medical_programs'][$activityForm['program']] ?? $activityForm['program']) : __('care-plan.medical_guarantees_program')); ?>"
                            disabled
                        />
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="form-group group">
                    <label class="label"> <?php echo e(__('care-plan.medical_device')); ?>* </label>
                    <div class="relative">
                        <?php
                            $deviceDisplayName = !empty($selectedProduct)
                                ? ($selectedProduct['display_name']
                                    ?? $selectedProduct['name']
                                    ?? ($selectedProduct['device_names'][0]['name'] ?? null)
                                    ?? $selectedProduct['description']
                                    ?? '')
                                : '';
                            if ($deviceDisplayName === '') {
                                $deviceDisplayName = $activityForm['product_reference']
                                    ?: ($activityForm['product_codeable_concept'] ?? '');
                            }
                        ?>
                        <input
                            type="text"
                            class="input bg-gray-50 dark:bg-gray-700 <?php echo e(empty($activityForm['id']) ? 'cursor-not-allowed' : 'pr-12'); ?> font-medium text-gray-900 dark:text-white w-full"
                            value="<?php echo e($deviceDisplayName); ?>"
                            disabled
                        />
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($activityForm['id'])): ?>
                            <button
                                type="button"
                                class="absolute top-1/2 right-2 -translate-y-1/2 text-sm whitespace-nowrap text-blue-600 hover:text-blue-800"
                                aria-controls="medical-device-search-drawer-right"
                                wire:click="openMedicalDeviceSearch"
                            >
                                <?php echo e(__('care-plan.change_product')); ?>

                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <input type="hidden" wire:model="activityForm.product_reference" />
                </div>
            </div>

            
            <div class="mb-4 grid grid-cols-1 gap-6 md:grid-cols-3">
                <div class="form-group group">
                    <label for="device_quantity" class="label"> <?php echo e(__('care-plan.quantity')); ?> </label>
                    <div class="flex gap-2">
                        <input
                            type="number"
                            id="device_quantity"
                            class="input peer w-full"
                            wire:model="activityForm.quantity"
                        />
                        <select class="input-select peer w-20" wire:model="activityForm.quantity_system">
                            <option value="device_unit"><?php echo e(__('care-plan.units')); ?></option>
                        </select>
                    </div>
                </div>
                <div class="form-group group">
                    <label class="label"> <?php echo e(__('care-plan.start_date')); ?>: <span class="text-red-500">*</span> </label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                            <?php
                // Parse arguments from the directive
                $iconArgs = ['calendar-month', 'w-4 h-4 text-gray-500'];
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
                        </div>
                        <input
                            type="text"
                            class="input peer datepicker-input ps-10"
                            placeholder="02.04.2025"
                            datepicker-autohide
                            datepicker-button="false"
                            wire:model.live="activityForm.scheduled_period_start"
                        />
                    </div>
                </div>
                <div class="form-group group">
                    <label class="label">&nbsp;</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                            <svg class="h-4 w-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <input type="text" class="input timepicker-uk ps-10" placeholder="02:30 PM" />
                    </div>
                </div>
            </div>

            
            <div class="mb-4 grid grid-cols-1 gap-6 md:grid-cols-3">
                <div class="form-group group">
                    <label for="device_quantity_per_time" class="label">
                        <?php echo e(__('care-plan.quantity_per_time')); ?>

                    </label>
                    <div class="flex gap-2">
                        <input
                            type="number"
                            id="device_quantity_per_time"
                            name="device_quantity_per_time"
                            class="input peer w-full"
                            value="1"
                        />
                        <select class="input-select peer w-20">
                            <option selected value="units"><?php echo e(__('care-plan.units')); ?></option>
                        </select>
                    </div>
                </div>
                <div class="form-group group">
                    <label class="label"> <?php echo e(__('care-plan.end_date')); ?>: <span class="text-red-500">*</span> </label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                            <?php
                // Parse arguments from the directive
                $iconArgs = ['calendar-month', 'w-4 h-4 text-gray-500'];
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
                        </div>
                        <input
                            type="text"
                            class="input peer datepicker-input ps-10"
                            placeholder="02.08.2025"
                            datepicker-autohide
                            datepicker-button="false"
                            wire:model.live="activityForm.scheduled_period_end"
                        />
                    </div>
                </div>
                <div class="form-group group">
                    <label class="label">&nbsp;</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                            <svg class="h-4 w-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <input type="text" class="input timepicker-uk ps-10" placeholder="02:30 PM" />
                    </div>
                </div>
            </div>

            
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <div class="form-group group">
                    <label for="device_number_of_times" class="label"> <?php echo e(__('care-plan.number_of_times')); ?> </label>
                    <div class="flex gap-2">
                        <input
                            type="number"
                            id="device_number_of_times"
                            name="device_number_of_times"
                            class="input peer w-full"
                            value="1"
                        />
                        <select class="input-select peer w-28">
                            <option selected value="per_day"><?php echo e(__('care-plan.per_day')); ?></option>
                        </select>
                    </div>
                </div>
                <div class="form-group group">
                    <label for="device_duration" class="label"> <?php echo e(__('care-plan.duration')); ?> </label>
                    <input
                        type="number"
                        id="device_duration"
                        name="device_duration"
                        class="input peer w-full"
                        value="10"
                    />
                </div>
                <div class="form-group group">
                    <label class="label">&nbsp;</label>
                    <select class="input-select peer w-full">
                        <option selected value="days"><?php echo e(__('care-plan.days')); ?></option>
                    </select>
                </div>
            </div>
        </fieldset>
        
        <fieldset class="fieldset" x-data="{ selectedGround: '' }">
            <legend class="legend"><?php echo e(__('care-plan.grounds_for_prescription')); ?></legend>

            <div class="mb-6 flex items-end gap-4">
                <div class="flex-1">
                    <label class="label">Оберіть клінічний запис пацієнта</label>
                    <select
                        x-model="selectedGround"
                        @change="
                            if (selectedGround) {
                                let parts = selectedGround.split('|');
                                $wire.addLinkedGround(parts[0], parts[1]);
                                selectedGround = '';
                            }
                        "
                        class="input-select peer w-full"
                    >
                        <option value="">-- Оберіть запис --</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($availableConditions)): ?>
                            <optgroup label="Діагнози (Стани)">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $availableConditions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cond): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="Condition|<?php echo e($cond['uuid']); ?>">
                                        <?php echo e($cond['name']); ?> (від <?php echo e($cond['date']); ?>)
                                    </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </optgroup>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($availableReports)): ?>
                            <optgroup label="Діагностичні звіти">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $availableReports; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $report): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="DiagnosticReport|<?php echo e($report['uuid']); ?>">
                                        <?php echo e($report['name']); ?> (від <?php echo e($report['date']); ?>)
                                    </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </optgroup>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($availableObservations)): ?>
                            <optgroup label="Спостереження">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $availableObservations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $obs): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="Observation|<?php echo e($obs['uuid']); ?>">
                                        <?php echo e($obs['name']); ?> (від <?php echo e($obs['date']); ?>)
                                    </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </optgroup>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <h4 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">
                    <?php echo e(__('care-plan.justification_of_grounds')); ?>

                </h4>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="thead-input">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium"><?php echo e(__('care-plan.date')); ?></th>
                                <th scope="col" class="px-4 py-3 font-medium"><?php echo e(__('care-plan.name')); ?></th>
                                <th scope="col" class="px-4 py-3 text-right font-medium">Дія</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $linkedGrounds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ground): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                        <?php echo e($ground['date']); ?>

                                    </td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-white">
                                        <span class="mr-2 inline-flex items-center rounded bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                            <?php echo e($ground['type'] === 'Condition' ? 'Діагноз' : ($ground['type'] === 'DiagnosticReport' ? 'Діагн. звіт' : 'Спостереження')); ?>

                                        </span>
                                        <?php echo e($ground['name']); ?>

                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            wire:click="removeLinkedGround('<?php echo e($ground['uuid']); ?>')"
                                            class="text-red-500 transition-colors hover:text-red-700"
                                        >
                                            <?php
                // Parse arguments from the directive
                $iconArgs = ['delete', 'w-5 h-5'];
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
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-gray-400 italic">
                                        Немає доданих обґрунтувань
                                    </td>
                                </tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </fieldset>

        
        <fieldset class="fieldset">
            <legend class="legend"><?php echo e(__('care-plan.additional_info')); ?></legend>

            <div class="form-row-3">
                <div class="form-group group">
                    <label for="device_expected_result" class="label"> <?php echo e(__('care-plan.expected_result')); ?> </label>
                    <select id="device_expected_result" name="device_expected_result" class="input-select peer w-full">
                        <option selected value=""><?php echo e(__('care-plan.select_result')); ?></option>
                    </select>
                </div>
            </div>

            <div class="form-group group mt-4">
                <label for="device_description" class="label mb-2"> <?php echo e(__('care-plan.extended_description')); ?> </label>
                <textarea
                    id="device_description"
                    class="block w-full rounded-2xl border border-gray-200 bg-white p-4 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 dark:focus:border-blue-500 dark:focus:ring-blue-500"
                    rows="5"
                    placeholder="<?php echo e(__('care-plan.description')); ?>"
                    wire:model="activityForm.description"
                ></textarea>
            </div>
        </fieldset>

        <div class="mt-6 flex justify-start gap-3">
            <button type="button" class="button-minor" @click="showMedicalDeviceFormDrawer = false">
                <?php echo e(__('forms.cancel')); ?>

            </button>

            <button type="submit" class="button-primary"><?php echo e(__('forms.save')); ?></button>
        </div>
    </form>
</div>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/care-plan/parts/modals/medical-device-form-drawer.blade.php ENDPATH**/ ?>