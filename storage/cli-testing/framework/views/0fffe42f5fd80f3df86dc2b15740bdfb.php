
<div x-show="showMedicalDeviceSearchDrawer"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     x-cloak
     @click="showMedicalDeviceSearchDrawer = false"
     class="fixed top-0 right-0 h-screen pt-20 w-4/5 bg-gray-900/50"
     style="z-index: 48;"
></div>


<div id="medical-device-search-drawer-right"
     x-show="showMedicalDeviceSearchDrawer"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="translate-x-full"
     x-transition:enter-end="translate-x-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="translate-x-0"
     x-transition:leave-end="translate-x-full"
     x-cloak
     class="fixed top-0 right-0 h-screen pt-20 p-4 overflow-y-auto bg-white dark:bg-gray-800 shadow-2xl"
     style="z-index: 49; width: calc(80% - 30px);"
     tabindex="-1"
     x-data="{ showFilter: false }"
>
    <h3 class="modal-header">
        <?php echo e(__('care-plan.medical_device_search')); ?>

    </h3>

    <?php
        $deviceProgramId = $selectedProgram ?: ($activityForm['program'] ?? '');
        $deviceProgramName = $deviceProgramId !== ''
            ? ($dictionaries['medical_programs_device'][$deviceProgramId] ?? $dictionaries['medical_programs'][$deviceProgramId] ?? $deviceProgramId)
            : '';
    ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($deviceProgramName !== ''): ?>
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
            <?php echo e(__('care-plan.program')); ?>: <span class="font-medium"><?php echo e($deviceProgramName); ?></span>
        </p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="mb-4">
        <div class="relative">
            <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                <?php
                // Parse arguments from the directive
                $iconArgs = ['search-outline', 'w-5 h-5 text-gray-500'];
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
                   class="input peer ps-10 w-full"
                   placeholder="<?php echo e(__('care-plan.test_strips')); ?>"
                   wire:model.live.debounce.400ms="searchQuery"
                   wire:keydown.enter="searchMedicalDevices"
            />
        </div>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            <?php echo e(__('care-plan.device_search_hint')); ?>

        </p>
    </div>

    
    <div class="flex flex-wrap gap-2 mb-6">
        <button type="button" wire:click="searchMedicalDevices" class="button-primary flex items-center gap-2">
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
            <span><?php echo e(__('forms.search')); ?></span>
        </button>
        <button type="button" wire:click="resetDeviceSearchFilters" class="button-primary-outline-red">
            <?php echo e(__('forms.reset_all_filters')); ?>

        </button>
        <button type="button"
                class="button-minor flex items-center gap-2"
                @click="showFilter = !showFilter"
        >
            <?php
                // Parse arguments from the directive
                $iconArgs = ['adjustments', 'w-4 h-4'];
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
            <span><?php echo e(__('forms.additional_search_parameters')); ?></span>
        </button>
    </div>

    
    <div x-show="showFilter" x-cloak x-transition class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="form-group group">
            <label for="device_search_model_number" class="label">
                <?php echo e(__('care-plan.medical_device_model_number')); ?>

            </label>
            <input type="text"
                   id="device_search_model_number"
                   class="input peer w-full"
                   placeholder="<?php echo e(__('care-plan.medical_device_model_number')); ?>"
                   wire:model.live.debounce.400ms="deviceSearchModelNumber"
                   wire:keydown.enter="searchMedicalDevices"
            />
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($deviceSearchTotalEntries > 0): ?>
        <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
            <?php echo e(__('care-plan.device_search_results_count', ['count' => $deviceSearchTotalEntries])); ?>

        </p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="overflow-x-auto mb-6">
        <table class="w-full text-sm text-left">
            <thead class="thead-input">
                <tr>
                    <th scope="col" class="px-4 py-3 font-medium"><?php echo e(__('care-plan.name')); ?></th>
                    <th scope="col" class="px-4 py-3 font-medium"><?php echo e(__('care-plan.type')); ?></th>
                    <th scope="col" class="px-4 py-3 font-medium"><?php echo e(__('care-plan.packaging')); ?></th>
                    <th scope="col" class="px-4 py-3 font-medium"><?php echo e(__('care-plan.code')); ?></th>
                    <th scope="col" class="px-4 py-3 font-medium text-right">Дія</th>
                </tr>
            </thead>
            <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $searchResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $device): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50" wire:key="device-search-<?php echo e($device['id'] ?? $loop->index); ?>">
                        <td class="px-4 py-3">
                            <span class="font-medium text-gray-900 dark:text-white"><?php echo e($device['display_name'] ?? ''); ?></span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($device['model_number'])): ?>
                                <div class="text-xs text-gray-500"><?php echo e(__('care-plan.medical_device_model_number')); ?>: <?php echo e($device['model_number']); ?></div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-gray-500">
                            <?php echo e($device['display_type'] ?? '-'); ?>

                        </td>
                        <td class="px-4 py-3 text-gray-500">
                            <?php echo e($device['display_packaging'] ?? '-'); ?>

                        </td>
                        <td class="px-4 py-3 font-mono text-xs">
                            <?php echo e($device['display_code'] ?? '-'); ?>

                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" wire:click="selectProduct(<?php echo e(json_encode($device)); ?>, 'device_request')" class="button-primary-outline text-xs">
                                Обрати
                            </button>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400 italic">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($deviceProgramId === ''): ?>
                                <?php echo e(__('care-plan.select_program_first')); ?>

                            <?php elseif(empty($searchQuery) && empty($deviceSearchModelNumber)): ?>
                                <?php echo e(__('care-plan.device_search_no_catalog')); ?>

                            <?php else: ?>
                                <?php echo e(__('care-plan.device_search_no_results', ['query' => $searchQuery ?: $deviceSearchModelNumber])); ?>

                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($deviceSearchTotalPages > 1): ?>
        <div class="mb-6 flex flex-wrap items-center justify-center gap-2">
            <button type="button"
                    class="button-minor text-sm"
                    wire:click="goToDeviceSearchPage(<?php echo e(max(1, $searchPage - 1)); ?>)"
                    <?php if($searchPage <= 1): echo 'disabled'; endif; ?>
            >
                <?php echo e(__('pagination.previous')); ?>

            </button>
            <span class="text-sm text-gray-600 dark:text-gray-300">
                <?php echo e($searchPage); ?> / <?php echo e($deviceSearchTotalPages); ?>

            </span>
            <button type="button"
                    class="button-minor text-sm"
                    wire:click="goToDeviceSearchPage(<?php echo e(min($deviceSearchTotalPages, $searchPage + 1)); ?>)"
                    <?php if($searchPage >= $deviceSearchTotalPages): echo 'disabled'; endif; ?>
            >
                <?php echo e(__('pagination.next')); ?>

            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="mt-6">
        <button type="button"
                class="button-minor"
                @click="showMedicalDeviceSearchDrawer = false"
        >
            <?php echo e(__('forms.cancel')); ?>

        </button>
    </div>
</div>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/care-plan/parts/modals/medical-device-search-drawer.blade.php ENDPATH**/ ?>