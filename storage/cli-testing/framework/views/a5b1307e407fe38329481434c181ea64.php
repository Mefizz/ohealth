
<template x-teleport="body">
    <div x-show="showServiceDrawer"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak
         class="fixed inset-0"
         style="z-index: 39;"
         role="dialog"
         aria-modal="true"
         aria-labelledby="services-drawer-label"
    >
        
        <div class="absolute inset-0 bg-gray-900/50"
             aria-hidden="true"
             @click="showServiceDrawer = false"
        ></div>

        <div id="services-drawer-right"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full"
             class="absolute top-0 right-0 z-10 h-screen pt-20 p-4 overflow-y-auto bg-white w-4/5 dark:bg-gray-800 shadow-2xl"
             tabindex="-1"
        >
        <h3 class="modal-header" id="services-drawer-label">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($activityForm['id']) && $activityForm['id']): ?>
                <?php echo e(__('care-plan.edit_service_prescription')); ?>

            <?php else: ?>
                <?php echo e(__('care-plan.new_service_prescription')); ?>

            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </h3>

        
        <form wire:submit.prevent="saveActivity">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('error')): ?>
                <div x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'center' })" class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400 border border-red-200 dark:border-red-900" role="alert">
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
                <div x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'center' })" class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400 border border-red-200 dark:border-red-900" role="alert">
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
                    <ul class="mt-2 list-disc list-inside">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <fieldset class="fieldset">
                <legend class="legend">
                    <?php echo e(__('care-plan.main_data')); ?>

                </legend>

                
                <div class="form-row-3">
                    <div class="form-group group">
                        <label for="service" class="label">
                            <?php echo e(__('care-plan.service')); ?>*
                        </label>
                        <div class="relative">
                            <button type="button"
                                    class="input-select peer pr-12 w-full text-left <?php echo e(!empty($selectedProduct) ? 'text-gray-900 dark:text-white font-medium' : 'text-gray-500'); ?>"
                                    aria-controls="service-search-drawer-right"
                                    @click="showServiceSearchDrawer = true"
                            >
                                <?php echo e(!empty($selectedProduct) ? (($selectedProduct['code'] ?? '') . ' - ' . ($selectedProduct['name'] ?? '')) : __('care-plan.select_service')); ?>

                            </button>
                            <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linejoin="round" stroke-width="2" d="M9 8v3a1 1 0 0 1-1 1H5m11 4h2a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1h-7a1 1 0 0 0-1 1v1m4 3v10a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-7.13a1 1 0 0 1 .24-.65L7.7 8.35A1 1 0 0 1 8.46 8H13a1 1 0 0 1 1 1Z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="form-group group">
                        <label for="program" class="label">
                            <?php echo e(__('care-plan.program')); ?>

                        </label>
                        <select id="program"
                                name="program"
                                class="input-select peer"
                        >
                            <option selected value=""><?php echo e(__('care-plan.state_financial_guarantees')); ?></option>
                        </select>
                    </div>
                </div>

                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-4">
                    <div class="form-group group">
                        <label for="quantity" class="label">
                            <?php echo e(__('care-plan.quantity')); ?>

                        </label>
                        <div class="flex gap-2">
                            <input type="number"
                                   id="quantity"
                                   class="input peer w-full"
                                   wire:model="activityForm.quantity"
                            >
                            <select class="input-select peer w-20" wire:model="activityForm.quantity_system">
                                <option value="SERVICE_UNIT"><?php echo e(__('care-plan.units')); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group group">
                        <label class="label">
                            <?php echo e(__('care-plan.start_date')); ?>: <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
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
                            <input type="text"
                                   class="input peer ps-10 datepicker-input"
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
                            <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                </svg>
                            </div>
                            <input type="text"
                                   class="input timepicker-uk ps-10"
                                   placeholder="02:30 PM"
                            />
                        </div>
                    </div>
                </div>

                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-4">
                    <div class="form-group group">
                        <label for="quantity_per_time" class="label">
                            <?php echo e(__('care-plan.quantity_per_time')); ?>

                        </label>
                        <div class="flex gap-2">
                            <input type="number"
                                   id="quantity_per_time"
                                   name="quantity_per_time"
                                   class="input peer w-full"
                                   value="1"
                            >
                            <select class="input-select peer w-20">
                                <option selected value="units"><?php echo e(__('care-plan.units')); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group group">
                        <label class="label">
                            <?php echo e(__('care-plan.end_date')); ?>: <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
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
                            <input type="text"
                                   class="input peer ps-10 datepicker-input"
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
                            <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                </svg>
                            </div>
                            <input type="text"
                                   class="input timepicker-uk ps-10"
                                   placeholder="02:30 PM"
                            />
                        </div>
                    </div>
                </div>

                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="form-group group">
                        <label for="number_of_times" class="label">
                            <?php echo e(__('care-plan.number_of_times')); ?>

                        </label>
                        <div class="flex gap-2">
                            <input type="number"
                                   id="number_of_times"
                                   name="number_of_times"
                                   class="input peer w-full"
                                   value="1"
                            >
                            <select class="input-select peer w-28">
                                <option selected value="per_day"><?php echo e(__('care-plan.per_day')); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group group">
                        <label for="duration" class="label">
                            <?php echo e(__('care-plan.duration')); ?>

                        </label>
                        <input type="number"
                               id="duration"
                               name="duration"
                               class="input peer w-full"
                               value="10"
                        >
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
                <legend class="legend">
                    <?php echo e(__('care-plan.grounds_for_prescription')); ?>

                </legend>

                <div class="flex gap-4 items-end mb-6">
                    <div class="flex-1">
                        <label class="label">Оберіть клінічний запис пацієнта</label>
                        <select x-model="selectedGround"
                                @change="if(selectedGround) {
                                    let parts = selectedGround.split('|');
                                    $wire.addLinkedGround(parts[0], parts[1]);
                                    selectedGround = '';
                                }"
                                class="input-select peer w-full">
                            <option value="">-- Оберіть запис --</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($availableConditions)): ?>
                                <optgroup label="Діагнози (Стани)">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $availableConditions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cond): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="Condition|<?php echo e($cond['uuid']); ?>"><?php echo e($cond['name']); ?> (від <?php echo e($cond['date']); ?>)</option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </optgroup>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($availableReports)): ?>
                                <optgroup label="Діагностичні звіти">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $availableReports; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $report): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="DiagnosticReport|<?php echo e($report['uuid']); ?>"><?php echo e($report['name']); ?> (від <?php echo e($report['date']); ?>)</option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </optgroup>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($availableObservations)): ?>
                                <optgroup label="Спостереження">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $availableObservations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $obs): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="Observation|<?php echo e($obs['uuid']); ?>"><?php echo e($obs['name']); ?> (від <?php echo e($obs['date']); ?>)</option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </optgroup>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <h4 class="text-base font-semibold text-gray-900 dark:text-white mb-4">
                        <?php echo e(__('care-plan.justification_of_grounds')); ?>

                    </h4>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="thead-input">
                                <tr>
                                    <th scope="col" class="px-4 py-3 font-medium"><?php echo e(__('care-plan.date')); ?></th>
                                    <th scope="col" class="px-4 py-3 font-medium"><?php echo e(__('care-plan.name')); ?></th>
                                    <th scope="col" class="px-4 py-3 font-medium text-right">Дія</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $linkedGrounds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ground): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                            <?php echo e($ground['date']); ?>

                                        </td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-white">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 mr-2">
                                                <?php echo e($ground['type'] === 'Condition' ? 'Діагноз' : ($ground['type'] === 'DiagnosticReport' ? 'Діагн. звіт' : 'Спостереження')); ?>

                                            </span>
                                            <?php echo e($ground['name']); ?>

                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button type="button" wire:click="removeLinkedGround('<?php echo e($ground['uuid']); ?>')" class="text-red-500 hover:text-red-700 transition-colors">
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
                <legend class="legend">
                    <?php echo e(__('care-plan.additional_info')); ?>

                </legend>

                <div class="form-row-3">
                    <label for="expected_result" class="label">
                        <?php echo e(__('care-plan.expected_result')); ?>

                    </label>
                    <select id="expected_result"
                            name="expected_result"
                            class="input-select peer w-full"
                    >
                        <option selected value=""><?php echo e(__('care-plan.select_result')); ?></option>
                    </select>
                </div>

                <div class="form-row">
                    <label for="description" class="label">
                        <?php echo e(__('care-plan.extended_description')); ?>

                    </label>
                    <textarea id="description"
                              class="input peer w-full"
                              rows="4"
                              placeholder="<?php echo e(__('care-plan.description')); ?>"
                              wire:model="activityForm.description"
                    ></textarea>
                </div>
            </fieldset>

            <div class="mt-6 flex justify-start gap-3">
                <button type="button"
                        class="button-minor"
                        aria-controls="services-drawer-right"
                        @click="showServiceDrawer = false"
                >
                    <?php echo e(__('forms.cancel')); ?>

                </button>

                <button type="submit"
                        class="button-primary"
                >
                    <?php echo e(__('forms.save')); ?>

                </button>
            </div>
        </form>
        </div>
    </div>
    </div>
</template>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/care-plan/parts/modals/services-drawer.blade.php ENDPATH**/ ?>