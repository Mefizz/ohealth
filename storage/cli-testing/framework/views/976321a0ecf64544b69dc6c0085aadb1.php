
<template x-teleport="body">
    <div x-show="showMedicationDrawer"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak
         class="fixed inset-0"
         style="z-index: 39;"
         role="dialog"
         aria-modal="true"
         aria-labelledby="medications-drawer-label"
     >
        <div class="absolute inset-0 bg-gray-900/50"
             aria-hidden="true"
             @click="showMedicationDrawer = false"
        ></div>

        <div id="medications-drawer-right"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full"
             class="absolute top-0 right-0 z-10 h-screen pt-20 p-4 overflow-y-auto bg-white w-4/5 dark:bg-gray-800 shadow-2xl"
             tabindex="-1"
        >
        <h3 class="modal-header" id="medications-drawer-label">
            <?php echo e(__('care-plan.new_medication_prescription')); ?>

        </h3>

        
        <form>
            
            <fieldset class="fieldset">
                <legend class="legend">
                    <?php echo e(__('care-plan.program_selection')); ?>

                </legend>

                <div class="form-row-3">
                    <div class="form-group group">
                        <label for="medication_program" class="label">
                            <?php echo e(__('care-plan.program')); ?>*
                        </label>
                        <select id="medication_program"
                                name="medication_program"
                                class="input-select peer"
                                wire:model.live="selectedProgram"
                        >
                            <option value=""><?php echo e(__('care-plan.prescription_medication')); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ($dictionaries['medical_programs_medication'] ?? $dictionaries['medical_programs'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $id => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($id); ?>"><?php echo e($name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </select>
                    </div>
                </div>
            </fieldset>

            <div class="mt-6 flex justify-start gap-3">
                <button type="button"
                        class="button-minor"
                        aria-controls="medications-drawer-right"
                        @click="showMedicationDrawer = false"
                >
                    <?php echo e(__('forms.cancel')); ?>

                </button>

                <button type="button"
                        class="button-primary"
                        aria-controls="medication-search-drawer-right"
                        @click="showMedicationDrawer = false; showMedicationSearchDrawer = true"
                >
                    <?php echo e(__('forms.continue')); ?>

                </button>
            </div>
        </form>
        </div>
    </div>
    </div>
</template>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/care-plan/parts/modals/medications-drawer.blade.php ENDPATH**/ ?>