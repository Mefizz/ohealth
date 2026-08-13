<section class="section-form">
    <?php if (isset($component)) { $__componentOriginal66cfe0cbbf6c425a3bd889176e755171 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal66cfe0cbbf6c425a3bd889176e755171 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.header-navigation','data' => ['class' => 'breadcrumb-form']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('header-navigation'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'breadcrumb-form']); ?>
         <?php $__env->slot('title', null, []); ?> 
            <?php echo e(__('care-plan.prescriptions')); ?> — <?php echo e(__('care-plan.care_plan')); ?> №<?php echo e($carePlan->requisition ?? $carePlan->id); ?>

         <?php $__env->endSlot(); ?>
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

    <?php
        $resolvedKind = $activity->resolvedKind();
        $activityStatus = is_array($activity->status) ? ($activity->status['coding'][0]['code'] ?? ($activity->status['text'] ?? '')) : $activity->status;
    ?>

    <div
        x-data="{
        showEPrescriptionDrawer: <?php if ((object) ('showEPrescriptionDrawer') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showEPrescriptionDrawer'->value()); ?>')<?php echo e('showEPrescriptionDrawer'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showEPrescriptionDrawer'); ?>')<?php endif; ?>.live,
        showReferralDrawer: <?php if ((object) ('showReferralDrawer') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showReferralDrawer'->value()); ?>')<?php echo e('showReferralDrawer'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showReferralDrawer'); ?>')<?php endif; ?>.live,
    }"
        @close-drawers.window="
            showEPrescriptionDrawer = false;
            showReferralDrawer = false;
        "
        class="form shift-content space-y-6 px-4"
    >
        <div class="flex flex-wrap items-center gap-3">
            <a href="<?php echo e(route('care-plans.show', [legalEntity(), $carePlan->id])); ?>" class="button-minor" wire:navigate>
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

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array(strtoupper($activityStatus), ['NEW', 'DRAFT'])): ?>
                <a
                    href="<?php echo e(route('care-plans.show', [legalEntity(), $carePlan->id, 'edit_activity' => $activity->id])); ?>"
                    class="button-minor"
                    wire:navigate
                >
                    <?php echo e(__('forms.edit')); ?>

                </a>
                <button
                    type="button"
                    class="button-primary-outline"
                    wire:click="openSignatureModal('sign_activity', <?php echo e($activity->id); ?>)"
                >
                    <?php echo e(__('care-plan.sign_activity')); ?>

                </button>
            <?php elseif(in_array(strtoupper($activityStatus), ['ACTIVE', 'SCHEDULED', 'IN-PROGRESS', 'IN_PROGRESS', 'ON-HOLD', 'PROCESSED'])): ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolvedKind === 'medication_request'): ?>
                    <button
                        type="button"
                        class="button-primary"
                        wire:click="initEPrescriptionForm(<?php echo e($activity->id); ?>)"
                    >
                        <?php echo e(__('care-plan.issue_eprescription')); ?>

                    </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolvedKind === 'device_request'): ?>
                    <button type="button" class="button-primary" wire:click="initReferralForm(<?php echo e($activity->id); ?>)">
                        <?php echo e(__('care-plan.issue_device_eprescription')); ?>

                    </button>
                <?php elseif($resolvedKind === 'service_request'): ?>
                    <button type="button" class="button-primary" wire:click="initReferralForm(<?php echo e($activity->id); ?>)">
                        <?php echo e(__('care-plan.create_referral')); ?>

                    </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <button
                    type="button"
                    class="button-minor"
                    wire:click="openSignatureModal('complete_activity', <?php echo e($activity->id); ?>)"
                >
                    <?php echo e(__('forms.complete')); ?>

                </button>
                <button
                    type="button"
                    class="button-minor border-red-200 text-red-500"
                    wire:click="openSignatureModal('cancel_activity', <?php echo e($activity->id); ?>)"
                >
                    <?php echo e(__('forms.cancel')); ?>

                </button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <?php echo $__env->make('livewire.care-plan.parts.activity.detail-card', [
                            'dictionaries' => $dictionaries,
                            'activityProductLabel' => $activityProductLabel,
                        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resolvedKind === 'medication_request'): ?>
            <?php echo $__env->make('livewire.care-plan.parts.activity.prescriptions-list', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php elseif(in_array($resolvedKind, ['service_request', 'device_request'], true)): ?>
            <?php echo $__env->make('livewire.care-plan.parts.activity.referrals-list', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($actionType === 'cancel_activity'): ?>
            <?php echo $__env->make('livewire.care-plan.parts.modals.cancel-activity-modal', ['method' => 'sign'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php elseif($actionType === 'complete_activity'): ?>
            <?php echo $__env->make('livewire.care-plan.parts.modals.complete-activity-modal', ['method' => 'sign'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php else: ?>
            <?php echo $__env->make('components.signature-modal', ['method' => 'sign'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isPolling): ?>
            <div wire:poll.2s="checkApprovalJobStatus" class="hidden"></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showAuthModal): ?>
            <?php echo $__env->make('livewire.care-plan.modals.authentication', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showMethodSelectionModal): ?>
            <?php echo $__env->make('livewire.care-plan.modals.method-selection', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php echo $__env->make('livewire.care-plan.parts.modals.eprescription-form-drawer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('livewire.care-plan.parts.modals.referral-form-drawer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>

    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('components.x-message', []);

$__key = time();

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-856283694-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key);

echo $__html;

unset($__html);
unset($__key);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
</section>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/care-plan/activity/show/care-plan-activity-show.blade.php ENDPATH**/ ?>