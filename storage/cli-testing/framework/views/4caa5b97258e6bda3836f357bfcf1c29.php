<div>
    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('components.x-message', []);

$__key = time();

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-746332804-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key);

echo $__html;

unset($__html);
unset($__key);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>

    <form wire:submit.prevent="createDraft">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Пацієнт</label>
                <input type="text" wire:model.defer="patientId" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" placeholder="UUID пацієнта">
            </div>

            <div>
                <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Медична програма</label>
                <select wire:model.defer="medicalProgram" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    <option value="">Оберіть програму...</option>
                    <option value="test-strips">Тест-смужки для глюкометрів</option>
                </select>
            </div>
            
            <div>
                <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Тип медичного виробу (Код SNOMED)</label>
                <input type="text" wire:model.defer="deviceType" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" placeholder="Наприклад: 395995000">
            </div>

            <div>
                <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Кількість одиниць</label>
                <input type="number" wire:model.defer="quantity" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" placeholder="50">
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($statusMessage): ?>
            <div class="mt-4 p-4 text-sm text-blue-800 rounded-lg bg-blue-50 dark:bg-gray-800 dark:text-blue-400" role="alert">
                <?php echo e($statusMessage); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="flex items-center justify-end space-x-3 mt-6">
            <button type="button" wire:click="preQualify" class="text-gray-900 bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-gray-800 dark:text-white dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:border-gray-600 dark:focus:ring-gray-700">
                Попередня перевірка (PreQualify)
            </button>
            <button type="submit" class="text-white bg-primary-700 hover:bg-primary-800 focus:ring-4 focus:outline-none focus:ring-primary-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
                Створити чернетку (Draft)
            </button>
            <button type="button" wire:click="openSignatureModal" <?php if(!$isDraftCreated): ?> disabled <?php endif; ?> class="text-white bg-green-600 hover:bg-green-700 focus:ring-4 focus:outline-none focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-800 disabled:opacity-50 disabled:cursor-not-allowed">
                Підписати КЕП (Sign)
            </button>
        </div>
    </form>

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
</div>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/device-request/device-request-form.blade.php ENDPATH**/ ?>