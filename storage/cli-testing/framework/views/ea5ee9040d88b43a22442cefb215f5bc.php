<?php $__env->startSection('title', __('care-plan.cancel_activity') ?? 'Скасувати призначення'); ?>

<?php $__env->startSection('custom-fields'); ?>
    <div class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-600 dark:bg-amber-950/40 dark:text-amber-100">
        <?php echo e(__('care-plan.cancel_activity_irreversible_warning')); ?>

    </div>

    <div>
        <label for="statusReason" class="default-label"><?php echo e(__('care-plan.status_reason')); ?> *</label>
        <select class="input-modal" wire:model="statusReason" name="statusReason" id="statusReason">
            <option value="" selected><?php echo e(__('forms.select')); ?></option>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $this->statusReasons; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $code => $description): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($code); ?>" wire:key="reason-<?php echo e($code); ?>"><?php echo e($description); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </select>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['statusReason'];
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
<?php $__env->stopSection(); ?>

<?php echo $__env->make('components.signature-modal', ['method' => 'sign'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/care-plan/parts/modals/cancel-activity-modal.blade.php ENDPATH**/ ?>