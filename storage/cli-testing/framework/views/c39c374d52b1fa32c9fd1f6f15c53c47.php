<div>
    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('components.x-message', []);

$__key = time();

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-3591308616-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key);

echo $__html;

unset($__html);
unset($__key);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isPolling): ?>
        <div wire:poll.2s="checkApprovalJobStatus" class="hidden"></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <fieldset class="fieldset !mb-6 !max-w-full !rounded-xl !border-gray-100 bg-white !p-6 !shadow-none dark:!border-gray-700 dark:bg-gray-800">
        <legend class="legend flex items-center justify-between">
            <span><?php echo e(__('care-plan.access_management')); ?></span>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isPolling): ?>
                <span class="flex items-center gap-2 text-xs font-normal text-blue-600 dark:text-blue-400">
                    <svg class="h-3.5 w-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    <?php echo e(__('care-plan.approval_processing')); ?>

                </span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </legend>

        <div class="index-table-wrapper mt-4">
            <table class="index-table w-full">
                <thead class="index-table-thead">
                    <tr>
                        <th class="index-table-th"><?php echo e(__('care-plan.granted_to')); ?></th>
                        <th class="index-table-th"><?php echo e(__('forms.status.label')); ?></th>
                        <th class="index-table-th"><?php echo e(__('forms.date')); ?></th>
                        <th class="index-table-th text-right"><?php echo e(__('forms.actions')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $approvals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $approval): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr class="index-table-tr">
                            <td class="index-table-td">
                                <div class="flex flex-col">
                                    <span class="font-medium text-gray-900 dark:text-white">
                                        <?php echo e($approval['grantedToDetails']['name'] ?? $approval['granted_to_details']['name'] ?? '-'); ?>

                                    </span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        <?php echo e($approval['grantedToDetails']['description'] ?? $approval['granted_to_details']['description'] ?? ''); ?>

                                    </span>
                                </div>
                            </td>
                            <td class="index-table-td">
                                <?php
                                    $approvalStatus = \App\Enums\Person\ApprovalStatus::resolve($approval['status'] ?? null);
                                ?>
                                <span class="badge <?php echo e(\App\Enums\Person\ApprovalStatus::colorFor($approval['status'] ?? null)); ?>">
                                    <?php echo e(\App\Enums\Person\ApprovalStatus::labelFor($approval['status'] ?? null)); ?>

                                </span>
                            </td>
                            <td class="index-table-td">
                                <?php echo e(isset($approval['createdAt']) || isset($approval['created_at']) ? \Carbon\Carbon::parse($approval['createdAt'] ?? $approval['created_at'])->format('d.m.Y H:i') : '-'); ?>

                            </td>
                            <td class="index-table-td-actions text-right">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($approvalStatus?->isGranted()): ?>
                                    <button
                                        type="button"
                                        wire:click="cancelApproval('<?php echo e($approval['uuid']); ?>')"
                                        wire:confirm="<?php echo e(__('care-plan.confirm_cancel_approval')); ?>"
                                        class="p-1 text-red-500 hover:text-red-700"
                                    >
                                        <?php
                // Parse arguments from the directive
                $iconArgs = ['close-outline', 'w-4 h-4'];
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
                                <?php elseif($approvalStatus?->isAwaitingPatient()): ?>
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            wire:click="recreateApproval('<?php echo e($approval['uuid']); ?>')"
                                            class="button-secondary px-3 py-1 text-xs text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                                            title="Перестворити запит, якщо старий завис або СМС не приходить"
                                        >
                                            <?php echo e(__('care-plan.recreate_approval') ?? 'Запросити новий'); ?>

                                        </button>
                                        <button
                                            type="button"
                                            wire:click="verifyExistingApproval('<?php echo e($approval['uuid']); ?>')"
                                            class="button-primary px-3 py-1 text-xs"
                                        >
                                            <?php echo e(__('forms.confirm')); ?>

                                        </button>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="4" class="index-table-td !py-6 text-center text-gray-400">
                                <?php echo e(__('care-plan.no_approvals_found')); ?>

                            </td>
                        </tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
    </fieldset>

    
    <fieldset class="fieldset !mb-6 !max-w-full !rounded-xl !border-gray-100 bg-white !p-6 !shadow-none dark:!border-gray-700 dark:bg-gray-800">
        <legend class="legend"><?php echo e(__('care-plan.grant_access')); ?></legend>

        <div class="mt-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(empty($carePlanUuid)): ?>
                <div
                    class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800 dark:border-yellow-900/30 dark:bg-gray-700/50 dark:text-yellow-300"
                    role="alert"
                >
                    <?php echo e(__('care-plan.cannot_grant_unregistered')); ?>

                </div>
            <?php elseif($isPolling): ?>
                <div
                    class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-900/30 dark:bg-gray-700/50 dark:text-blue-300"
                    role="alert"
                >
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 flex-shrink-0 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                        <?php echo e(__('care-plan.approval_processing')); ?>

                    </div>
                </div>
            <?php else: ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errorMessage): ?>
                    <div
                        class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900/30 dark:bg-gray-700/50 dark:text-red-400"
                        role="alert"
                    >
                        <?php echo e($errorMessage); ?>

                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <form wire:submit.prevent="createApproval" class="form">
                    <div class="form-row-2">
                        <div class="form-group group">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(empty($employees)): ?>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo e(__('care-plan.no_employees_found')); ?>

                                </p>
                            <?php else: ?>
                                <select
                                    class="input-select peer"
                                    id="employee_uuid"
                                    wire:model.live="newApproval.employee_uuid"
                                >
                                    <option value=""><?php echo e(__('care-plan.select_employee')); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $employees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $employee): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($employee['uuid']); ?>"><?php echo e($employee['label']); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </select>
                                <label for="employee_uuid" class="label"> <?php echo e(__('care-plan.employee')); ?> * </label>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newApproval.employee_uuid'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <p class="text-error mt-1 text-xs"><?php echo e($message); ?></p>
                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($authMethods)): ?>
                            <div class="form-group group">
                                <select
                                    class="input-select peer"
                                    wire:model="selectedAuthMethodUuid"
                                    id="selectedAuthMethodUuid"
                                >
                                    <option value=""><?php echo e(__('care-plan.choose_auth_method')); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $authMethods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $method): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($method['id'] ?? $method['uuid']); ?>">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($method['type'] ?? '') === 'OTP'): ?>
                                                SMS (<?php echo e($method['phone_number'] ?? ''); ?>)
                                            <?php elseif(($method['type'] ?? '') === 'OFFLINE'): ?>
                                                <?php echo e(__('care-plan.offline_paper')); ?>

                                            <?php else: ?>
                                                <?php echo e($method['type'] ?? __('care-plan.other')); ?>

                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </select>
                                <label for="selectedAuthMethodUuid" class="label">
                                    <?php echo e(__('care-plan.auth_method')); ?> *
                                </label>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <button type="submit" class="button-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove><?php echo e(__('care-plan.grant_access_btn')); ?></span>
                            <span wire:loading><?php echo e(__('forms.loading')); ?></span>
                        </button>
                    </div>
                </form>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </fieldset>

    <?php echo $__env->make('livewire.care-plan.modals.authentication', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/care-plan/care-plan-approvals.blade.php ENDPATH**/ ?>