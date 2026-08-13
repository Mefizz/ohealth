
<div x-show="showServiceSearchDrawer"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     x-cloak
     @click="showServiceSearchDrawer = false"
     aria-controls="service-search-drawer-right"
     class="fixed top-0 right-0 h-screen pt-20 w-4/5 bg-gray-900/50"
     style="z-index: 44;"
></div>


<div id="service-search-drawer-right"
     x-show="showServiceSearchDrawer"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="translate-x-full"
     x-transition:enter-end="translate-x-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="translate-x-0"
     x-transition:leave-end="translate-x-full"
     x-cloak
     class="fixed top-0 right-0 h-screen pt-20 p-4 overflow-y-auto bg-white dark:bg-gray-800 shadow-2xl"
     style="z-index: 45; width: calc(80% - 30px);"
     tabindex="-1"
     aria-labelledby="service-search-drawer-label"
     x-data="{ showFilter: false }"
>
    <h3 class="modal-header" id="service-search-drawer-label">
        <?php echo e(__('care-plan.search_service')); ?>

    </h3>

    
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
                   placeholder="Киснева терапія"
                   wire:model="searchQuery"
                   wire:keydown.enter="searchServices"
            />
        </div>
    </div>

    
    <div class="flex flex-wrap gap-2 mb-6">
        <button type="button" wire:click="searchServices" class="button-primary flex items-center gap-2">
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
        <button type="button" wire:click="$set('searchQuery', '')" class="button-primary-outline-red">
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
            <label class="label">
                <?php echo e(__('care-plan.service_category')); ?>

            </label>
            <select class="input-select peer w-full">
                <option selected value=""><?php echo e(__('care-plan.procedures_on_nervous_system')); ?></option>
            </select>
        </div>
        <div class="form-group group">
            <label class="label">
                <?php echo e(__('care-plan.service_group_active')); ?>

            </label>
            <select class="input-select peer w-full">
                <option selected value="yes"><?php echo e(__('care-plan.yes')); ?></option>
            </select>
        </div>
        <div class="form-group group">
            <label class="label">
                <?php echo e(__('care-plan.service_active')); ?>

            </label>
            <select class="input-select peer w-full">
                <option selected value="yes"><?php echo e(__('care-plan.yes')); ?></option>
            </select>
        </div>
        <div class="form-group group">
            <label class="label">
                <?php echo e(__('care-plan.allowed_in_em')); ?>

            </label>
            <select class="input-select peer w-full">
                <option selected value="yes"><?php echo e(__('care-plan.yes')); ?></option>
            </select>
        </div>
    </div>

    
    <div class="overflow-x-auto mb-6">
        <table class="w-full text-sm text-left">
            <thead class="thead-input">
                <tr>
                    <th scope="col" class="px-4 py-3 font-medium"><?php echo e(__('care-plan.name')); ?></th>
                    <th scope="col" class="px-4 py-3 font-medium"><?php echo e(__('care-plan.allowed_in_em_short')); ?></th>
                    <th scope="col" class="px-4 py-3 font-medium"><?php echo e(__('care-plan.code')); ?></th>
                    <th scope="col" class="px-4 py-3 font-medium"><?php echo e(__('care-plan.status_title') ?? 'Статус'); ?></th>
                    <th scope="col" class="px-4 py-3 font-medium text-right">Дія</th>
                </tr>
            </thead>
            <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $searchResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-3">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white"><?php echo e($service['name'] ?? ''); ?></p>
                                <p class="text-xs text-gray-500"><?php echo e($service['code'] ?? ''); ?></p>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-500">
                            <?php echo e(($service['request_allowed'] ?? true) ? '+' : '-'); ?>

                        </td>
                        <td class="px-4 py-3 font-mono text-xs">
                            <?php echo e($service['code'] ?? ''); ?>

                        </td>
                        <td class="px-4 py-3">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($service['is_active'] ?? true): ?>
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800"><?php echo e(__('care-plan.active')); ?></span>
                            <?php else: ?>
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800"><?php echo e(__('care-plan.inactive')); ?></span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" wire:click="selectProduct(<?php echo e(json_encode($service)); ?>, 'service_request')" class="button-primary-outline text-xs">
                                Обрати
                            </button>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400 italic">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(empty($searchQuery)): ?>
                                Введіть запит для пошуку послуг
                            <?php else: ?>
                                Нічого не знайдено за запитом "<?php echo e($searchQuery); ?>"
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        <button type="button"
                class="button-minor"
                aria-controls="service-search-drawer-right"
                @click="showServiceSearchDrawer = false"
        >
            <?php echo e(__('forms.cancel')); ?>

        </button>
    </div>
</div>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/care-plan/parts/modals/service-search-drawer.blade.php ENDPATH**/ ?>