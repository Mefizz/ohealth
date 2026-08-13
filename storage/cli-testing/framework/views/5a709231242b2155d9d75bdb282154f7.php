<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'method',
    'agreementText' => null,
    'onlyActions' => null,
    'exceptActions' => null,
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'method',
    'agreementText' => null,
    'onlyActions' => null,
    'exceptActions' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $inputId = 'keyContainerUpload-'.Str::slug((string) $method, '-');
    $knedpId = 'knedp-'.Str::slug((string) $method, '-');
    $passwordId = 'password-'.Str::slug((string) $method, '-');
    $noFileChosen = __('forms.no_file_chosen');
?>

<template x-teleport="body">
    <div
        x-data="{
            showSignatureModal: $wire.entangle('showSignatureModal'),
            onlyActions: <?php echo e(Js::from($onlyActions)); ?>,
            exceptActions: <?php echo e(Js::from($exceptActions)); ?>,
            isKeyUploading: false,
            fileName: <?php echo e(Js::from($noFileChosen)); ?>,
            noFileLabel: <?php echo e(Js::from($noFileChosen)); ?>,
            isVisible() {
                if (! this.showSignatureModal) {
                    return false;
                }

                const actionType = $wire.actionType ?? null;

                if (Array.isArray(this.onlyActions) && this.onlyActions.length > 0) {
                    return this.onlyActions.includes(actionType);
                }

                if (Array.isArray(this.exceptActions) && this.exceptActions.length > 0) {
                    return ! this.exceptActions.includes(actionType);
                }

                return true;
            },
            displayFileName() {
                const stored = $wire.form?.keyContainerFileName;
                if (stored) {
                    return stored;
                }
                if (this.fileName && this.fileName !== this.noFileLabel && ! String(this.fileName).startsWith('livewire-file:')) {
                    return this.fileName;
                }
                return this.noFileLabel;
            },
            setFileNameFromInput(event) {
                const file = event.target.files?.[0];
                if (file) {
                    this.fileName = file.name;
                    $wire.set('form.keyContainerFileName', file.name);
                } else {
                    this.fileName = this.noFileLabel;
                    $wire.set('form.keyContainerFileName', '');
                }
            },
            syncFileNameFromWire() {
                const stored = $wire.form?.keyContainerFileName;
                if (stored) {
                    this.fileName = stored;
                    return;
                }
                const upload = $wire.form?.keyContainerUpload;
                if (! upload) {
                    this.fileName = this.noFileLabel;
                    return;
                }
                // Keep the previously shown client filename while Livewire holds a temp upload token.
            },
         }"
        x-effect="
            if (! showSignatureModal) {
                if ($refs.keyContainerUpload) $refs.keyContainerUpload.value = '';
                this.isKeyUploading = false;
            } else {
                this.syncFileNameFromWire();
            }
        "
        x-show="isVisible()"
        x-cloak
        role="dialog"
        aria-modal="true"
        class="modal"
        @keydown.escape.prevent.stop="showSignatureModal = false"
    >
        <div x-transition.opacity class="fixed inset-0 bg-black/30" @click="showSignatureModal = false"></div>
        <div class="modal-wrapper">
            <div
                class="modal-content mx-auto w-full max-w-4xl"
                @click.stop
                x-transition
                x-trap.noscroll.inert="isVisible()"
            >
                
                <h3 class="modal-header">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($this->actionType) && $this->actionType === 'sign_eprescription'): ?>
                        Підписання заявки електронного рецепта (КЕП)
                    <?php elseif(isset($this->actionType) && $this->actionType === 'sign_referral'): ?>
                        Підписання заявки електронного направлення (КЕП)
                    <?php else: ?>
                        <?php echo $__env->yieldContent('title', __('forms.sign_with_KEP')); ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </h3>

                
                <div class="p-6">
                    <form onsubmit="return false;">
                        <div class="flex flex-col gap-6">
                            <?php if (! empty(trim($__env->yieldContent('custom-fields')))): ?>
                                <?php echo $__env->yieldContent('custom-fields'); ?>
                            <?php elseif(isset($customFields)): ?>
                                <?php echo e($customFields); ?>

                            <?php elseif(method_exists($this, 'getStatusReasonsProperty') && isset($this->actionType) && in_array($this->actionType, ['cancel_prescription', 'cancel_referral'])): ?>
                                <div>
                                    <label for="statusReason" class="default-label"
                                        ><?php echo e(__('care-plan.status_reason')); ?> *</label>
                                    <select
                                        class="input-modal"
                                        wire:model="statusReason"
                                        name="statusReason"
                                        id="statusReason"
                                    >
                                        <option value="" selected><?php echo e(__('forms.select')); ?></option>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $this->statusReasons; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $code => $description): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($code); ?>" wire:key="reason-<?php echo e($code); ?>">
                                                <?php echo e($description); ?>

                                            </option>
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
                            <?php elseif(isset($this->actionType) && $this->actionType === 'recall_referral'): ?>
                                <div>
                                    <label for="referralExplanatoryLetter" class="default-label"
                                        ><?php echo e(__('care-plan.referral_recall_letter')); ?> *</label>
                                    <textarea
                                        id="referralExplanatoryLetter"
                                        class="input-modal"
                                        rows="4"
                                        wire:model="referralExplanatoryLetter"
                                        placeholder="<?php echo e(__('care-plan.referral_recall_letter_placeholder')); ?>"
                                    ></textarea>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['referralExplanatoryLetter'];
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
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($agreementText)): ?>
                                <div class="show-alert-warning">
                                    <p class="text-sm font-medium"><?php echo e($agreementText); ?></p>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            
                            <div>
                                <label for="<?php echo e($knedpId); ?>" class="default-label"><?php echo e(__('forms.knedp')); ?> *</label>
                                <select class="input-modal" wire:model="form.knedp" name="knedp" id="<?php echo e($knedpId); ?>">
                                    <option value="" selected><?php echo e(__('forms.select')); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = signatureService()->getCertificateAuthorities(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $certificateType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option
                                            value="<?php echo e($certificateType['id']); ?>"
                                            wire:key="<?php echo e($certificateType['id']); ?>"
                                        >
                                            <?php echo e($certificateType['name']); ?>

                                        </option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </select>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.knedp'];
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

                            
                            <div>
                                <label for="<?php echo e($inputId); ?>" class="default-label">
                                    <?php echo e(__('forms.key_container_upload')); ?> *
                                </label>
                                <div class="file-input-wrapper">
                                    <label for="<?php echo e($inputId); ?>" class="file-input-button">
                                        <?php echo e(__('forms.choose_file')); ?>

                                    </label>
                                    <span class="file-input-text" x-text="displayFileName()"></span>
                                    <input
                                        type="file"
                                        wire:model="form.keyContainerUpload"
                                        class="hidden"
                                        id="<?php echo e($inputId); ?>"
                                        name="keyContainerUpload"
                                        x-ref="keyContainerUpload"
                                        accept=".dat,.pfx,.pk8,.zs2,.jks,.p7s"
                                        @change="setFileNameFromInput($event)"
                                        x-on:livewire-upload-start="isKeyUploading = true"
                                        x-on:livewire-upload-finish="
                                            isKeyUploading = false;
                                            if ($wire.form?.keyContainerFileName) {
                                                fileName = $wire.form.keyContainerFileName;
                                            }
                                        "
                                        x-on:livewire-upload-error="isKeyUploading = false"
                                        x-on:livewire-upload-cancel="isKeyUploading = false"
                                    />
                                </div>
                                <div x-show="isKeyUploading" class="mt-2 text-sm text-gray-500">
                                    <?php echo e(__('general.loading')); ?>...
                                </div>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.keyContainerUpload'];
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

                            
                            <div>
                                <label for="<?php echo e($passwordId); ?>" class="default-label"
                                    ><?php echo e(__('forms.password')); ?> *</label>
                                <input
                                    type="password"
                                    wire:model="form.password"
                                    class="default-input"
                                    id="<?php echo e($passwordId); ?>"
                                    name="password"
                                    autocomplete="current-password"
                                />

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.password'];
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
                        </div>
                    </form>

                    <div class="mt-6 flex flex-row items-center gap-4 border-t border-gray-200 pt-6">
                        <button type="button" @click="showSignatureModal = false" class="button-minor">
                            <?php echo e(__('forms.cancel')); ?>

                        </button>
                        <button
                            wire:click="<?php echo e($method); ?>"
                            type="button"
                            class="button-primary"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-not-allowed"
                            wire:target="<?php echo e($method); ?>"
                        >
                            <span wire:loading.remove wire:target="<?php echo e($method); ?>"><?php echo e(__('forms.sign')); ?></span>
                            <span wire:loading wire:target="<?php echo e($method); ?>"><?php echo e(__('forms.signature')); ?>...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/components/signature-modal.blade.php ENDPATH**/ ?>