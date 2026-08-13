<fieldset
    class="fieldset !mb-6 !max-w-full !rounded-xl !border-gray-100 bg-white !p-6 !shadow-none dark:!border-gray-700 dark:bg-gray-800"
    x-data="{ coAuthors: $wire.entangle('form.coAuthors') }"
    x-init="
        if (! Array.isArray(coAuthors)) {
            coAuthors = [];
        }
    "
>
    <legend class="legend"><?php echo e(__('care-plan.doctors') ?? 'Лікарі'); ?></legend>

    <div class="form">
        <div class="form-row-2">
            <div class="form-group group">
                <input
                    type="text"
                    wire:model="form.author"
                    name="author"
                    id="author"
                    class="input peer"
                    placeholder=" "
                    required
                />
                <label for="author" class="label"> <?php echo e(__('care-plan.author') ?? 'Автор'); ?> </label>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.author'];
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

        <div class="mt-4 space-y-4">
            <template x-for="(coAuthor, index) in coAuthors" :key="index">
                <div class="form-row-2 flex items-center gap-4">
                    <div class="form-group group relative flex-1">
                        <select x-model="coAuthors[index]" class="input-select peer" :id="'coAuthor_' + index">
                            <option value=""><?php echo e(__('care-plan.find_doctor') ?? 'Оберіть лікаря'); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $doctors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $doctor): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($doctor['uuid']); ?>"><?php echo e($doctor['name']); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </select>
                        <label :for="'coAuthor_' + index" class="label">
                            <?php echo e(__('care-plan.co-author') ?? 'Співавтор'); ?>

                        </label>

                        <button
                            type="button"
                            @click="coAuthors.splice(index, 1)"
                            class="absolute top-3 -right-8 text-red-500 hover:text-red-700"
                        >
                            <?php
                // Parse arguments from the directive
                $iconArgs = ['delete', 'w-5 h-5'];
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
            </template>
        </div>

        <div class="mt-4">
            <button
                type="button"
                @click="coAuthors.push('')"
                class="flex items-center font-medium text-blue-600 transition-colors hover:text-blue-800 dark:text-blue-400"
            >
                <span class="mr-2 text-xl">+</span>
                <span><?php echo e(__('care-plan.add_coauthor') ?? 'Додати співавтора'); ?></span>
            </button>
        </div>
    </div>
</fieldset>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/care-plan/parts/doctors.blade.php ENDPATH**/ ?>