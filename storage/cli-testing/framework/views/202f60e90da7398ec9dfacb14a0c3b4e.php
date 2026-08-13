<?php use \App\Livewire\CarePlan\CarePlanIndex; ?>

<section class="section-form">
    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('components.x-message', []);

$__key = time();

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1373905458-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key);

echo $__html;

unset($__html);
unset($__key);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
    <?php if (isset($component)) { $__componentOriginal66cfe0cbbf6c425a3bd889176e755171 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal66cfe0cbbf6c425a3bd889176e755171 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.header-navigation','data' => ['class' => 'items-start','xData' => '{ showFilter: false }']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('header-navigation'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'items-start','x-data' => '{ showFilter: false }']); ?>
         <?php $__env->slot('title', null, []); ?> 
            <?php echo e(__('care-plan.care_plans')); ?>

         <?php $__env->endSlot(); ?>

        <div class="mt-3 ml-0 flex flex-col sm:flex-row sm:flex-wrap gap-2 self-start">
            <a href="<?php echo e(route('care-plans.create', legalEntity())); ?>" class="button-primary">
                + <?php echo e(__('care-plan.new_care_plan')); ?>

            </a>

            <button wire:click.prevent="sync"
                    type="button"
                    class="button-sync flex items-center gap-2 whitespace-nowrap px-5 py-2 text-sm shadow-sm"
                    wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="sync">
                    <?php
                // Parse arguments from the directive
                $iconArgs = ['refresh', 'w-4 h-4'];
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
                </span>
                <span wire:loading wire:target="sync" class="animate-spin">
                    <?php
                // Parse arguments from the directive
                $iconArgs = ['refresh', 'w-4 h-4'];
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
                </span>
                <span><?php echo e(__('forms.synchronise_with_eHealth')); ?></span>
            </button>
        </div>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal66cfe0cbbf6c425a3bd889176e755171)): ?>
<?php $attributes = $__attributesOriginal66cfe0cbbf6c425a3bd889176e755171; ?>
<?php unset($__attributesOriginal66cfe0cbbf6c425a3bd889176e755171); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal66cfe0cbbf6c425a3bd889176e755171)): ?>
<?php $component = $__componentOriginal66cfe0cbbf6c425a3bd889176e755171; ?>
<?php unset($__componentOriginal66cfe0cbbf6c425a3bd889176e755171); ?>
<?php endif; ?>

    <div class="form shift-content">
        
        <div class="w-full mb-6" x-data="{ showAdditionalParams: $wire.entangle('showAdditionalParams') }">
            <div class="mb-4 flex items-center gap-1 font-semibold text-gray-900 dark:text-gray-100">
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
                <p><?php echo e(__('care-plan.search_care_plan')); ?></p>
            </div>

            <div class="form-row-4 mb-6">
                <div class="form-group group">
                    <div class="relative">
                        <input wire:model="searchRequisition"
                               wire:keydown.enter="search"
                               type="text"
                               name="searchRequisition"
                               id="searchRequisition"
                               class="input peer w-full"
                               placeholder=" "
                               autocomplete="off"
                        />
                        <label for="searchRequisition" class="label">
                            <?php echo e(__('care-plan.medical_number')); ?>

                        </label>
                        <button type="button" wire:click="$set('searchRequisition', '')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                x-show="$wire.searchRequisition">
                            <?php
                // Parse arguments from the directive
                $iconArgs = ['close', 'w-4 h-4'];
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
                    </div>
                </div>

                <div class="form-group group">
                    <select wire:model="filterStatus"
                            name="filterStatus"
                            id="filterStatus"
                            class="input-select peer w-full"
                    >
                        <option value=""><?php echo e(__('forms.select')); ?></option>
                        <option value="draft"><?php echo e(__('care-plan.status.draft')); ?></option>
                        <option value="new"><?php echo e(__('care-plan.status.new')); ?></option>
                        <option value="active"><?php echo e(__('care-plan.status.active')); ?></option>
                        <option value="on-hold"><?php echo e(__('care-plan.status.on-hold')); ?></option>
                        <option value="completed"><?php echo e(__('care-plan.status.completed')); ?></option>
                        <option value="revoked"><?php echo e(__('care-plan.status.revoked')); ?></option>
                        <option value="entered-in-error"><?php echo e(__('care-plan.status.entered-in-error')); ?></option>
                    </select>
                    <label for="filterStatus" class="label">
                        <?php echo e(__('forms.status.label')); ?>

                    </label>
                </div>
            </div>

            <div class="mb-9 flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="search"
                            class="flex items-center gap-2 button-primary px-5 py-2.5 text-sm shadow-sm"
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
                        <span><?php echo e(__('forms.search')); ?></span>
                    </button>
                    <button type="button" wire:click="resetFilters"
                            class="button-primary-outline-red px-5 py-2.5 text-sm"
                    >
                        <?php echo e(__('patients.reset_filters')); ?>

                    </button>
                    <button type="button"
                            class="flex items-center gap-2 button-minor px-5 py-2.5 text-sm whitespace-nowrap"
                            @click.prevent="showAdditionalParams = !showAdditionalParams"
                    >
                        <?php
                // Parse arguments from the directive
                $iconArgs = ['adjustments', 'w-4 h-4 text-gray-500'];
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
            </div>

            <div x-show="showAdditionalParams" x-transition x-cloak wire:key="care-plans-search-filters">
                <div class="form-row-4 mb-6">
                    <div class="form-group group">
                        <div class="datepicker-wrapper">
                            <input wire:model="filterStartDateRange"
                                   wire:keydown.enter="search"
                                   type="text"
                                   name="filterStartDateRange"
                                   id="filterStartDateRange"
                                   class="datepicker-input with-leading-icon input peer w-full"
                                   placeholder=" "
                                   autocomplete="off"
                            />
                            <label for="filterStartDateRange" class="wrapped-label">
                                <?php echo e(__('care-plan.filter_start_date_range')); ?>

                            </label>
                        </div>
                    </div>

                    <div class="form-group group">
                        <div class="datepicker-wrapper">
                            <input wire:model="filterEndDateRange"
                                   wire:keydown.enter="search"
                                   type="text"
                                   name="filterEndDateRange"
                                   id="filterEndDateRange"
                                   class="datepicker-input with-leading-icon input peer w-full"
                                   placeholder=" "
                                   autocomplete="off"
                            />
                            <label for="filterEndDateRange" class="wrapped-label">
                                <?php echo e(__('care-plan.filter_end_date_range')); ?>

                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-row-4 mb-6">
                    <div class="form-group group">
                        <div class="relative">
                            <input wire:model="filterIsPartOf"
                                   wire:keydown.enter="search"
                                   type="text"
                                   name="filterIsPartOf"
                                   id="filterIsPartOf"
                                   class="input peer w-full"
                                   placeholder=" "
                                   autocomplete="off"
                            />
                            <label for="filterIsPartOf" class="label">
                                <?php echo e(__('care-plan.is_part_of_care_plan')); ?>

                            </label>
                            <button type="button" wire:click="$set('filterIsPartOf', '')"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    x-show="$wire.filterIsPartOf">
                                <?php
                // Parse arguments from the directive
                $iconArgs = ['close', 'w-4 h-4'];
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
                        </div>
                    </div>

                    <div class="form-group group">
                        <div class="relative">
                            <input wire:model="filterIncludes"
                                   wire:keydown.enter="search"
                                   type="text"
                                   name="filterIncludes"
                                   id="filterIncludes"
                                   class="input peer w-full"
                                   placeholder=" "
                                   autocomplete="off"
                            />
                            <label for="filterIncludes" class="label">
                                <?php echo e(__('care-plan.includes_care_plan')); ?>

                            </label>
                            <button type="button" wire:click="$set('filterIncludes', '')"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    x-show="$wire.filterIncludes">
                                <?php
                // Parse arguments from the directive
                $iconArgs = ['close', 'w-4 h-4'];
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
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $carePlans; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $plan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <?php
                    /** @var \App\Models\CarePlan $plan */
                    $status = strtolower($plan->status ?? '');

                    $statusClass = 'badge-dark';
                    if (in_array($status, ['active', 'new', 'completed'])) {
                        $statusClass = 'badge-green';
                    } elseif (in_array($status, ['draft', 'revoked'])) {
                        $statusClass = 'badge-red';
                    }

                    $created = $plan->created_at?->format(config('app.date_format', 'd.m.Y')) ?? '-';
                    $start = $plan->period_start?->format(config('app.date_format', 'd.m.Y')) ?? '-';
                    $end = $plan->period_end?->format(config('app.date_format', 'd.m.Y')) ?? '-';

                    $medRecordNo = $plan->encounterIdentifier?->value ?? $plan->encounter?->uuid ?? $plan->requisition ?? $plan->encounter_id ?? '-';
                ?>
                <div class="record-inner-card" wire:key="care-plan-<?php echo e($plan->id); ?>">
                    <div class="record-inner-header">
                        <div class="record-inner-column flex-1">
                            <div class="record-inner-label"><?php echo e(__('forms.name')); ?></div>
                            <div class="record-inner-value text-[17px] font-semibold text-gray-900 dark:text-gray-100">
                                <?php echo e($plan->title); ?>

                            </div>
                            <div class="text-xs text-gray-500 mt-1.5 dark:text-gray-400">
                                <?php echo e(__('care-plan.patient')); ?>: <?php echo e($plan->person?->fullName); ?>

                            </div>
                        </div>

                        <div class="record-inner-column-bordered w-full md:w-36 shrink-0">
                            <div class="record-inner-label"><?php echo e(__('forms.status.label')); ?></div>
                            <div>
                                <span class="<?php echo e($statusClass); ?>">
                                    <?php echo e($plan->status_display); ?>

                                </span>
                            </div>
                        </div>

                        <div class="record-inner-action-col">
                            <div x-data="{
                                open: false,
                                toggle() {
                                    if (this.open) { return this.close(); }
                                    this.$refs.button.focus();
                                    this.open = true;
                                },
                                close(focusAfter) {
                                    if (!this.open) return;
                                    this.open = false;
                                    focusAfter && focusAfter.focus()
                                }
                            }"
                                 @keydown.escape.prevent.stop="close($refs.button)"
                                 @focusin.window="!$refs.panel.contains($event.target) && close()"
                                 x-id="['dropdown-button']"
                                 class="relative"
                            >
                                <button @click="toggle()"
                                        x-ref="button"
                                        :aria-expanded="open"
                                        :aria-controls="$id('dropdown-button')"
                                        type="button"
                                        class="record-inner-action-btn cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50 p-2 rounded-lg"
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

                                <div x-show="open"
                                     x-cloak
                                     x-ref="panel"
                                     x-transition.origin.top.right
                                     @click.outside="close($refs.button)"
                                     :id="$id('dropdown-button')"
                                     class="absolute right-0 mt-2 w-56 rounded-md bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 shadow-lg z-50 py-1"
                                >
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($plan->id)): ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($plan->status === 'draft'): ?>
                                            <a href="<?php echo e(route('care-plans.edit', [legalEntity(), $plan->id])); ?>" class="flex items-center gap-2 w-full px-4 py-2.5 text-left text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
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

                                            </a>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                        <a href="<?php echo e(route('care-plans.show', [legalEntity(), $plan->id])); ?>" class="flex items-center gap-2 w-full px-4 py-2.5 text-left text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
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
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="record-inner-body">
                        <div class="record-inner-grid-container">
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-x-4 gap-y-3">
                                <div class="min-w-0">
                                    <div class="record-inner-label text-[10px] uppercase"><?php echo e(__('patients.created')); ?></div>
                                    <div class="record-inner-value text-[14px] font-semibold break-words">
                                        <?php echo e($created); ?>

                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="record-inner-label text-[10px] uppercase"><?php echo e(__('forms.start')); ?></div>
                                    <div class="record-inner-value text-[14px] font-semibold break-words">
                                        <?php echo e($start); ?>

                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="record-inner-label text-[10px] uppercase"><?php echo e(__('forms.end')); ?></div>
                                    <div class="record-inner-value text-[14px] font-semibold break-words">
                                        <?php echo e($end); ?>

                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="record-inner-label text-[10px] uppercase"><?php echo e(__('care-plan.doctor')); ?></div>
                                    <div class="record-inner-value text-[14px] font-semibold break-words uppercase">
                                        <?php echo e($plan->author_name); ?>

                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="record-inner-label text-[10px] uppercase"><?php echo e(__('care-plan.care_provision_conditions_label')); ?></div>
                                    <div class="record-inner-value text-[14px] font-semibold break-words">
                                        <?php echo e($plan->care_provision_conditions ?? '-'); ?>

                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="record-inner-label text-[10px] uppercase"><?php echo e(__('care-plan.medical_number')); ?></div>
                                    <div class="record-inner-value text-[14px] font-semibold break-words">
                                        <?php echo e($medRecordNo); ?>

                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="record-inner-id-col">
                            <div class="min-w-0">
                                <div class="record-inner-label text-[10px] uppercase"><?php echo e(__('patients.ehealth_id')); ?></div>
                                <div class="record-inner-id-value">
                                    <?php echo e($plan->uuid ?? '-'); ?>

                                </div>
                            </div>
                            <div class="min-w-0">
                                <div class="record-inner-label text-[10px] uppercase"><?php echo e(__('care-plan.episode_id')); ?></div>
                                <div class="record-inner-id-value">
                                    <?php echo e($plan->episode_id ?? '-'); ?>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <?php if (isset($component)) { $__componentOriginal353aa47428c848c80821cf337e0e5298 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal353aa47428c848c80821cf337e0e5298 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.nothing-found','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('nothing-found'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal353aa47428c848c80821cf337e0e5298)): ?>
<?php $attributes = $__attributesOriginal353aa47428c848c80821cf337e0e5298; ?>
<?php unset($__attributesOriginal353aa47428c848c80821cf337e0e5298); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal353aa47428c848c80821cf337e0e5298)): ?>
<?php $component = $__componentOriginal353aa47428c848c80821cf337e0e5298; ?>
<?php unset($__componentOriginal353aa47428c848c80821cf337e0e5298); ?>
<?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

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
</section>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/care-plan/care-plan-index.blade.php ENDPATH**/ ?>