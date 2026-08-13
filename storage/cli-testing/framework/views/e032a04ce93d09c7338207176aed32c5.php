<?php if (isset($component)) { $__componentOriginal6d9e3514a1cea5bcf1d55f6370d4b5a0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6d9e3514a1cea5bcf1d55f6370d4b5a0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.patient','data' => ['personId' => $personId,'uuid' => $uuid,'patientFullName' => $patientFullName,'hideNavigation' => $allowsPatientChange,'breadcrumbs' => [
        ['label' => __('general.home') ?? 'Головна', 'url' => route('dashboard', [legalEntity()])],
        ['label' => $patientFullName ?? __('care-plan.patient') ?? 'Пацієнт']
    ]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.patient'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['personId' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($personId),'uuid' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($uuid),'patientFullName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($patientFullName),'hideNavigation' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($allowsPatientChange),'breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([
        ['label' => __('general.home') ?? 'Головна', 'url' => route('dashboard', [legalEntity()])],
        ['label' => $patientFullName ?? __('care-plan.patient') ?? 'Пацієнт']
    ])]); ?>
     <?php $__env->slot('headerActions', null, []); ?>  <?php $__env->endSlot(); ?>

    <div class="shift-content mt-6 pl-4">
        <div class="w-full max-w-screen-xl">
            <?php echo $__env->make('livewire.care-plan.parts.doctors', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->make('livewire.care-plan.parts.patient_data', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->make('livewire.care-plan.parts.care_plan_data', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->make('livewire.care-plan.parts.condition_diagnosis', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->make('livewire.care-plan.parts.supporting_information', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->make('livewire.care-plan.parts.additional_info', ['context' => 'create'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <div class="mt-8 flex items-center gap-4 pt-4">
                <button
                    type="button"
                    wire:click.prevent="<?php echo e((isset($carePlan) && $carePlan->exists) ? 'delete' : 'cancel'); ?>"
                    class="button-primary-outline-red px-6 py-2.5"
                >
                    <?php echo e(__('forms.delete') ?? 'Видалити'); ?>

                </button>

                <button
                    type="button"
                    class="button-primary-outline flex items-center gap-2 px-6 py-2.5"
                    wire:click="save"
                >
                    <?php
                // Parse arguments from the directive
                $iconArgs = ['archive', 'w-4 h-4'];
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
                    <span><?php echo e(__('forms.save') ?? 'Зберегти'); ?></span>
                </button>

                <button type="button" wire:click="startSigningProcess" class="button-primary px-8 py-2.5">
                    <?php echo e(__('care-plan.create_care_plan') ?? 'Створити план лікування'); ?>

                </button>
            </div>
        </div>
    </div>

    <?php echo $__env->make('livewire.care-plan.modals.authentication', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('livewire.care-plan.modals.method-selection', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isPolling): ?>
        <div wire:poll.2s="checkApprovalJobStatus" class="fixed right-6 bottom-6 z-50">
            <div class="flex items-center gap-3 rounded-xl border border-blue-200 bg-white px-4 py-3 text-sm text-blue-700 shadow-lg dark:border-blue-700 dark:bg-gray-800 dark:text-blue-300">
                <svg class="h-4 w-4 flex-shrink-0 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                </svg>
                <?php echo e(__('care-plan.approval_processing')); ?>

            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if (isset($component)) { $__componentOriginal1d854201cf475ddfa1f2037c0d75e745 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1d854201cf475ddfa1f2037c0d75e745 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.signature-modal','data' => ['method' => 'sign']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('signature-modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['method' => 'sign']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1d854201cf475ddfa1f2037c0d75e745)): ?>
<?php $attributes = $__attributesOriginal1d854201cf475ddfa1f2037c0d75e745; ?>
<?php unset($__attributesOriginal1d854201cf475ddfa1f2037c0d75e745); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1d854201cf475ddfa1f2037c0d75e745)): ?>
<?php $component = $__componentOriginal1d854201cf475ddfa1f2037c0d75e745; ?>
<?php unset($__componentOriginal1d854201cf475ddfa1f2037c0d75e745); ?>
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
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/care-plan/care-plan-create.blade.php ENDPATH**/ ?>