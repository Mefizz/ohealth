
<div
    x-show="showReferralDrawer"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    x-cloak
    @click="showReferralDrawer = false"
    class="fixed top-0 right-0 h-screen bg-gray-900/50 pt-20"
    style="z-index: 46; width: calc(80% - 30px)"
></div>


<div
    id="referral-form-drawer-right"
    x-show="showReferralDrawer"
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
        <?php echo e(($referralForm['kind'] ?? '') === 'device_request'
            ? __('care-plan.issue_device_eprescription_drawer_title')
            : __('care-plan.issue_referral_drawer_title')); ?>

    </h3>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($referralForm)): ?>
        <form wire:submit.prevent="validateReferral">
            
            <fieldset class="fieldset">
                <legend class="legend">Основні дані</legend>
                <div class="mb-4 grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="form-group group">
                        <label class="label">План лікування</label>
                        <input
                            type="text"
                            class="input cursor-not-allowed bg-gray-50 dark:bg-gray-700"
                            value="<?php echo e($carePlan->title); ?>"
                            disabled
                        />
                    </div>
                    <div class="form-group group">
                        <label class="label">Призначення (Activity)</label>
                        <input
                            type="text"
                            class="input cursor-not-allowed bg-gray-50 dark:bg-gray-700"
                            value="<?php echo e($referralSelectedActivity ? ($referralSelectedActivity['description'] ?? 'Призначення') : ''); ?>"
                            disabled
                        />
                    </div>
                </div>

                <div class="mb-4 grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="form-group group">
                        <label class="label">
                            <?php echo e(($referralForm['kind'] ?? '') === 'device_request' ? 'Медичний виріб (код)' : 'Послуга / Виріб (код)'); ?>

                        </label>
                        <input
                            type="text"
                            class="input cursor-not-allowed bg-gray-50 font-medium text-gray-900 dark:bg-gray-700 dark:text-white"
                            value="<?php echo e($referralForm['code']); ?>"
                            disabled
                        />
                    </div>
                    <div class="form-group group">
                        <label class="label">Тип документа</label>
                        <input
                            type="text"
                            class="input cursor-not-allowed bg-gray-50 dark:bg-gray-700"
                            value="<?php echo e(($referralForm['kind'] ?? '') === 'service_request'
                                ? __('care-plan.document_type_service_referral')
                                : __('care-plan.document_type_device_eprescription')); ?>"
                            disabled
                        />
                    </div>
                </div>
            </fieldset>

            
            <fieldset class="fieldset">
                <legend class="legend">Термін дії та Кількість</legend>

                <div class="mb-4 grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div class="form-group group">
                        <label class="label">Дата початку дії*</label>
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
                                class="input peer ps-10"
                                placeholder="dd.mm.yyyy"
                                wire:model.live="referralForm.started_at"
                            />
                        </div>
                    </div>

                    <div class="form-group group">
                        <label class="label">Дата закінчення дії*</label>
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
                                class="input peer ps-10"
                                placeholder="dd.mm.yyyy"
                                wire:model.live="referralForm.ended_at"
                            />
                        </div>
                    </div>

                    <div class="form-group group">
                        <label class="label">Кількість*</label>
                        <div class="flex gap-2">
                            <input
                                type="number"
                                step="any"
                                min="0.01"
                                class="input peer w-full"
                                wire:model.live="referralForm.quantity"
                            />
                            <span class="inline-flex items-center rounded-r-md border border-l-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-700">
                                од.
                            </span>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($referralForm['kind'] ?? '') === 'device_request' && ($referralDevicePackageQty ?? 0) > 0): ?>
                            <p class="mt-1 text-xs text-gray-500">
                                <?php echo e(__('care-plan.device_quantity_packaging', ['count' => $referralDevicePackageQty])); ?>

                            </p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($referralForm['kind'] === 'service_request'): ?>
                    <div class="mb-4 grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div class="form-group group">
                            <label class="label">Категорія направлення*</label>
                            <input
                                type="text"
                                class="input cursor-not-allowed bg-gray-50 dark:bg-gray-700"
                                value="<?php echo e($referralForm['category_label'] ?? $referralForm['category']); ?>"
                                disabled
                            />
                        </div>

                        <div class="form-group group">
                            <label class="label"><?php echo e(__('care-plan.priority')); ?>*</label>
                            <select class="input-select peer w-full" wire:model="referralForm.priority">
                                <option value="routine"><?php echo e(__('care-plan.priority_options.routine')); ?></option>
                                <option value="urgent"><?php echo e(__('care-plan.priority_options.urgent')); ?></option>
                                <option value="asap"><?php echo e(__('care-plan.priority_options.asap')); ?></option>
                                <option value="stat"><?php echo e(__('care-plan.priority_options.stat')); ?></option>
                            </select>
                        </div>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($referralSelectedActivity['program'] ?? null)): ?>
                        <div class="form-group group mb-4">
                            <label class="label">Медична програма</label>
                            <input
                                type="text"
                                class="input cursor-not-allowed bg-gray-50 dark:bg-gray-700"
                                value="<?php echo e($dictionaries['medical_programs'][$referralSelectedActivity['program']] ?? $referralSelectedActivity['program']); ?>"
                                disabled
                            />
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php else: ?>
                    <div class="mb-4 grid grid-cols-1 gap-6">
                        <div class="form-group group">
                            <label class="label"><?php echo e(__('care-plan.priority')); ?>*</label>
                            <select class="input-select peer w-full" wire:model="referralForm.priority">
                                <option value="routine"><?php echo e(__('care-plan.priority_options.routine')); ?></option>
                                <option value="urgent"><?php echo e(__('care-plan.priority_options.urgent')); ?></option>
                                <option value="asap"><?php echo e(__('care-plan.priority_options.asap')); ?></option>
                                <option value="stat"><?php echo e(__('care-plan.priority_options.stat')); ?></option>
                            </select>
                        </div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($referralWarningMessage): ?>
                    <div
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
                        <div class="mt-2"><?php echo $referralWarningMessage; ?></div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($referralShowRemainingQtyWarning): ?>
                    <div
                        class="mb-4 rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800 dark:border-yellow-900 dark:bg-gray-800 dark:text-yellow-300"
                        role="alert"
                    >
                        <div class="flex items-center gap-2">
                            <?php
                // Parse arguments from the directive
                $iconArgs = ['alert-circle', 'w-5 h-5 text-yellow-500'];
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
                            <span class="font-bold">Увага! залишкова кількість</span>
                        </div>
                        <div class="mt-2">
                            Для пацієнта в плані лікування залишалось послуг/виробів в кількості <?php echo e($referralRemainingQty); ?> од.
                        </div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </fieldset>

            
            <fieldset class="fieldset">
                <legend class="legend">Додаткова інформація</legend>

                <div class="form-group group mb-4">
                    <label class="label">Нотатка / Обґрунтування лікаря</label>
                    <textarea
                        class="block w-full rounded-2xl border border-gray-200 bg-white p-4 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 dark:focus:border-blue-500 dark:focus:ring-blue-500"
                        rows="3"
                        placeholder="Вкажіть додаткову інформацію до направлення"
                        wire:model="referralForm.note"
                    ></textarea>
                </div>

                <div class="form-group group mb-4">
                    <label class="label"><?php echo e(__('care-plan.referral_patient_instruction')); ?></label>
                    <textarea
                        class="block w-full rounded-2xl border border-gray-200 bg-white p-4 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 dark:focus:border-blue-500 dark:focus:ring-blue-500"
                        rows="2"
                        placeholder="<?php echo e(__('care-plan.referral_patient_instruction_placeholder')); ?>"
                        wire:model="referralForm.patient_instruction"
                    ></textarea>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($referralAuthMethods)): ?>
                    <div class="form-group group mb-4">
                        <label class="label"><?php echo e(__('care-plan.referral_inform_with')); ?></label>
                        <select class="input-select peer w-full" wire:model="referralForm.inform_with">
                            <option value=""><?php echo e(__('forms.select')); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $referralAuthMethods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $method): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($method['raw']); ?>"><?php echo e($method['label']); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </select>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($referralForm['reason_reference'])): ?>
                    <div class="form-group group mb-4">
                        <label class="label"><?php echo e(__('care-plan.referral_reason_reference')); ?></label>
                        <div class="space-y-1 rounded-xl border border-gray-100 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-700/50">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $referralForm['reason_reference']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $info): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                    $reasonTypeKey = 'care-plan.reason_reference_types.' . strtolower((string) ($info['type'] ?? ''));
                                    $reasonTypeLabel = \Illuminate\Support\Facades\Lang::has($reasonTypeKey)
                                        ? __($reasonTypeKey)
                                        : ($info['type'] ?? '');
                                ?>
                                <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                    <span class="badge badge-minor"><?php echo e($reasonTypeLabel); ?></span>
                                    <span class="font-mono text-gray-800 dark:text-gray-200"><?php echo e($info['uuid']); ?></span>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($referralForm['supporting_info'])): ?>
                    <div class="form-group group mb-4">
                        <label class="label"><?php echo e(__('care-plan.referral_supporting_info')); ?></label>
                        <div class="space-y-1 rounded-xl border border-gray-100 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-700/50">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $referralForm['supporting_info']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $info): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                    $supportTypeKey = 'care-plan.reason_reference_types.' . strtolower((string) ($info['type'] ?? ''));
                                    $supportTypeLabel = \Illuminate\Support\Facades\Lang::has($supportTypeKey)
                                        ? __($supportTypeKey)
                                        : ($info['type'] ?? '');
                                ?>
                                <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                    <span class="badge badge-minor"><?php echo e($supportTypeLabel); ?></span>
                                    <span class="font-mono text-gray-800 dark:text-gray-200"><?php echo e($info['uuid']); ?></span>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </fieldset>

            <div class="mt-6 flex justify-start gap-3">
                <button type="button" class="button-minor" @click="showReferralDrawer = false">
                    <?php echo e(__('forms.cancel')); ?>

                </button>
                <button
                    type="submit"
                    class="button-primary"
                    <?php if($referralWarningMessage): ?> disabled class="button-primary cursor-not-allowed opacity-50" <?php endif; ?>
                >
                    <?php echo e(($referralForm['kind'] ?? '') === 'device_request'
                        ? __('care-plan.submit_device_eprescription')
                        : __('care-plan.submit_referral')); ?>

                </button>
            </div>
        </form>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/care-plan/parts/modals/referral-form-drawer.blade.php ENDPATH**/ ?>