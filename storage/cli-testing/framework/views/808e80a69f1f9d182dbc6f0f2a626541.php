
<div x-show="showMedicationSearchDrawer"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     x-cloak
     @click="showMedicationSearchDrawer = false"
     aria-controls="medication-search-drawer-right"
     class="fixed top-0 right-0 h-screen pt-20 w-4/5 bg-gray-900/50"
     style="z-index: 44;"
></div>


<div id="medication-search-drawer-right"
     x-show="showMedicationSearchDrawer"
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
     aria-labelledby="medication-search-drawer-label"
     x-data="{ showFilter: false }"
>
    <h3 class="modal-header" id="medication-search-drawer-label">
        <?php echo e(__('care-plan.new_medication_prescription')); ?>

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
                   placeholder="<?php echo e(__('care-plan.medication_search_placeholder')); ?>"
                   wire:model="searchQuery"
                   wire:keydown.enter="searchMedications"
            />
        </div>
    </div>

    
    <div class="flex flex-wrap gap-2 mb-6">
        <button type="button" wire:click="searchMedications" class="button-primary flex items-center gap-2">
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
                <?php echo e(__('care-plan.inn_name')); ?>

            </label>
            <select class="input-select peer w-full">
                <option selected value=""><?php echo e(__('care-plan.medication_search_placeholder')); ?></option>
            </select>
        </div>
        <div class="form-group group">
            <label class="label">
                <?php echo e(__('care-plan.atc_code')); ?>

            </label>
            <select class="input-select peer w-full">
                <option selected value=""><?php echo e(__('care-plan.code')); ?></option>
            </select>
        </div>
        <div class="form-group group">
            <label class="label">
                <?php echo e(__('care-plan.dosage_form')); ?>

            </label>
            <select class="input-select peer w-full">
                <option selected value=""><?php echo e(__('care-plan.tablets')); ?></option>
            </select>
        </div>
        <div class="form-group group">
            <label class="label">
                <?php echo e(__('care-plan.prescription_form_type')); ?>

            </label>
            <select class="input-select peer w-full">
                <option selected value=""><?php echo e(__('care-plan.type')); ?></option>
            </select>
        </div>
    </div>

    
    <div class="space-y-4 mb-6">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $searchResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $drug): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <fieldset class="fieldset">
                <legend class="legend">
                    <?php echo e($drug['name'] ?? 'Лікарський засіб'); ?>

                </legend>

                <div class="space-y-1 text-sm text-gray-700 dark:text-gray-300 mb-4">
                    <p><span class="text-gray-500"><?php echo e(__('care-plan.inn_basic')); ?>:</span> <?php echo e($drug['innm_name'] ?? 'МНН відсутнє'); ?></p>
                    <p><span class="text-gray-500"><?php echo e(__('care-plan.dosage_form')); ?>:</span> <?php echo e($drug['dosage_form'] ?? 'Форма випуску відсутня'); ?></p>
                    <p><span class="text-gray-500">Код АТХ:</span> <?php echo e($drug['medication_code_atc'] ?? '-'); ?></p>
                    <p><span class="text-gray-500">Одиниця виміру:</span> <?php echo e($drug['package_unit'] ?? '-'); ?></p>
                </div>

                <button type="button" wire:click="selectProduct(<?php echo e(json_encode($drug)); ?>, 'medication_request')" class="button-primary">
                    Обрати для призначення
                </button>
            </fieldset>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="text-center py-8 text-gray-400 italic">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(empty($searchQuery)): ?>
                    Введіть запит для пошуку лікарських засобів
                <?php else: ?>
                    Нічого не знайдено за запитом "<?php echo e($searchQuery); ?>"
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <div class="mt-6">
        <button type="button"
                class="button-minor"
                aria-controls="medication-search-drawer-right"
                @click="showMedicationSearchDrawer = false"
        >
            <?php echo e(__('forms.cancel')); ?>

        </button>
    </div>
</div>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/care-plan/parts/modals/medication-search-drawer.blade.php ENDPATH**/ ?>