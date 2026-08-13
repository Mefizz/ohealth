<div>
    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('components.x-message', []);

$__key = now()->timestamp;

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-2694960998-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key);

echo $__html;

unset($__html);
unset($__key);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
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

    <?php if (isset($component)) { $__componentOriginal66cfe0cbbf6c425a3bd889176e755171 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal66cfe0cbbf6c425a3bd889176e755171 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.header-navigation','data' => ['class' => 'items-start']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('header-navigation'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'items-start']); ?>
         <?php $__env->slot('title', null, []); ?> <?php echo e(__('patients.referrals')); ?> <?php $__env->endSlot(); ?>

         <?php $__env->slot('navigation', null, []); ?> 
            <div class="-my-4 flex flex-col">
                <form wire:submit.prevent="search">
                    <div class="flex flex-col gap-2">
                        <label for="requisition" class="text-sm font-medium text-gray-900 dark:text-white">
                            Пошук за номером направлення (Requisition)
                        </label>
                        <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
                            <div class="form-group group w-full sm:w-96">
                                <input
                                    type="text"
                                    id="requisition"
                                    placeholder=" "
                                    class="input peer"
                                    wire:model.defer="requisition"
                                    x-data
                                    x-on:input="
                                        $event.target.value = $event.target.value
                                            .replace(/[^A-Za-z0-9]/g, '')
                                            .replace(/(.{4})(?! $)/g, '$1-')
                                            .toUpperCase()
                                            .slice(0, 19)
                                    "
                                    autocomplete="off"
                                />
                                <label for="requisition" class="label"> XXXX-XXXX-XXXX-XXXX </label>
                            </div>

                            <button
                                type="submit"
                                class="button-primary flex items-center justify-center gap-2 self-stretch whitespace-nowrap sm:self-auto"
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
                                <span>Знайти направлення</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
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

    <div class="shift-content mt-8 flow-root pl-3.5">
        <div class="max-w-screen-xl">
            <!-- Error Message -->
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errorMessage): ?>
                <div
                    class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-gray-800 dark:text-red-400"
                    role="alert"
                >
                    <?php echo e($errorMessage); ?>

                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <!-- Search Results -->
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasSearched && empty($errorMessage) && !empty($searchResults)): ?>
                <?php
                    $statuses = [
                        'active' => 'Активне',
                        'completed' => 'Погашене',
                        'entered_in_error' => 'Введено помилково',
                        'entered-in-error' => 'Введено помилково',
                        'draft' => 'Чернетка',
                        'revoked' => 'Відкликане',
                        'recalled' => 'Відкликане',
                        'new' => 'Нове',
                        'in_progress' => 'В роботі',
                        'in_queue' => 'В черзі',
                    ];

                    $categories = [
                        'hospitalization' => 'Госпіталізація',
                        'consultation' => 'Консультація',
                        'imaging' => 'Візуалізація (Діагностика)',
                        'laboratory_procedure' => 'Лабораторна процедура',
                        'surgical_procedure' => 'Хірургічна процедура',
                        'transfer' => 'Переведення',
                        'treatment' => 'Лікування (Процедура)',
                    ];
                ?>
                <div class="mt-2">
                    <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">Результати пошуку</h3>

                    <div class="grid gap-4">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $searchResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $referral): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div
                                class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                                wire:key="referral-<?php echo e($referral['id'] ?? $loop->index); ?>"
                            >
                                <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start">
                                    <div class="space-y-1.5">
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            ID:
                                            <span class="font-mono text-gray-700 dark:text-gray-300"><?php echo e($referral['id'] ?? 'Невідомо'); ?></span>
                                        </p>
                                        <h4 class="text-base font-semibold text-gray-900 dark:text-white">
                                            Категорія: <?php echo e($categories[$referral['category']['coding'][0]['code'] ?? ''] ?? ($referral['category']['coding'][0]['code'] ?? 'Не вказана')); ?>

                                        </h4>
                                        <div class="flex flex-wrap items-center gap-2 pt-1">
                                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                Статус:
                                            </span>
                                            <span class="rounded bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                                <?php echo e($statuses[$referral['status'] ?? ''] ?? ($referral['status'] ?? 'Невідомо')); ?>

                                            </span>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($referral['program_processing_status'])): ?>
                                                <span class="ml-2 text-sm font-medium text-gray-900 dark:text-white">
                                                    Статус за програмою:
                                                </span>
                                                <span class="rounded bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-800 dark:bg-purple-900 dark:text-purple-300">
                                                    <?php echo e($referral['program_processing_status']); ?>

                                                </span>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($referral['status'] ?? '') === 'active' || ($referral['status'] ?? '') === 'new'): ?>
                                            <button
                                                wire:click="process('<?php echo e($referral['id']); ?>', '<?php echo e($referral['subject']['identifier']['value'] ?? ''); ?>')"
                                                type="button"
                                                class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:ring-4 focus:ring-green-300 dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-800"
                                            >
                                                Взяти в роботу
                                            </button>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($referral['status'] ?? '') === 'in_progress' || ($referral['status'] ?? '') === 'in_queue' || ($referral['program_processing_status'] ?? '') === 'in_progress'): ?>
                                            <button
                                                wire:click="openCompleteModal('<?php echo e($referral['id']); ?>')"
                                                type="button"
                                                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800"
                                            >
                                                Погасити направлення
                                            </button>

                                            <button
                                                wire:click="openCancelModal('<?php echo e($referral['id']); ?>')"
                                                type="button"
                                                class="inline-flex items-center rounded-lg border border-red-600 px-4 py-2 text-center text-sm font-medium text-red-600 hover:bg-red-600 hover:text-white focus:ring-4 focus:ring-red-300 focus:outline-none dark:border-red-500 dark:text-red-500 dark:hover:bg-red-600 dark:hover:text-white dark:focus:ring-red-900"
                                            >
                                                Відмінити використання
                                            </button>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    <!-- Cancel Usage Modal -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showCancelModal): ?>
        <div
            class="bg-opacity-50 fixed inset-0 z-50 flex items-center justify-center bg-gray-900"
            aria-modal="true"
            role="dialog"
        >
            <div class="relative w-full max-w-lg rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <button
                    type="button"
                    wire:click="$set('showCancelModal', false)"
                    class="absolute top-3 right-2.5 ml-auto inline-flex h-8 w-8 items-center justify-center rounded-lg bg-transparent text-sm text-gray-400 hover:bg-gray-200 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white"
                >
                    <svg class="h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                    </svg>
                    <span class="sr-only">Закрити</span>
                </button>
                <div class="mt-2">
                    <h3 class="mb-3 text-lg font-bold text-gray-900 dark:text-white">
                        Відміна використання направлення
                    </h3>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Вкажіть причину відміни використання направлення. Це поле є обов'язковим для ЄСОЗ.
                    </p>
                    <div class="mb-5">
                        <label for="cancelLetter" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                            Пояснення (explanatory letter) <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            id="cancelLetter"
                            wire:model="cancelExplanatoryLetter"
                            rows="4"
                            class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400"
                            placeholder="Введіть причину відміни використання направлення..."
                        ></textarea>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button
                            wire:click="confirmCancelUsage"
                            type="button"
                            class="rounded-lg bg-red-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-red-700 focus:ring-4 focus:ring-red-300 focus:outline-none"
                            <?php echo e(empty(trim($cancelExplanatoryLetter ?? '')) ? 'disabled' : ''); ?>

                        >
                            Підтвердити відміну
                        </button>
                        <button
                            wire:click="$set('showCancelModal', false)"
                            type="button"
                            class="rounded-lg border border-gray-200 bg-white px-5 py-2.5 text-sm font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus:ring-4 focus:ring-gray-200 focus:outline-none"
                        >
                            Скасувати
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <!-- Complete Referral Modal -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showCompleteModal): ?>
        <div
            class="bg-opacity-50 fixed inset-0 z-50 flex items-center justify-center bg-gray-900"
            aria-modal="true"
            role="dialog"
        >
            <div class="relative w-full max-w-lg rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <button
                    type="button"
                    wire:click="$set('showCompleteModal', false)"
                    class="absolute top-3 right-2.5 ml-auto inline-flex h-8 w-8 items-center justify-center rounded-lg bg-transparent text-sm text-gray-400 hover:bg-gray-200 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white"
                >
                    <svg class="h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                    </svg>
                    <span class="sr-only">Закрити</span>
                </button>
                <div class="mt-2 text-center">
                    <h3 class="mb-5 text-lg font-bold text-gray-900 dark:text-white">Погашення направлення</h3>
                    <div class="mb-4 text-left">
                        <label for="emzType" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                            Тип ЕМЗ *
                        </label>
                        <select
                            id="emzType"
                            wire:model.live="selectedEmzType"
                            class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="encounter">Взаємодія (encounter)</option>
                            <option value="procedure">Процедура (procedure)</option>
                            <option value="diagnostic_report">Діагностичний звіт (diagnostic_report)</option>
                        </select>
                    </div>
                    <div class="mb-5 text-left">
                        <label for="emzUuid" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white">
                            Оберіть ЕМЗ, повʼязане з цим направленням *
                        </label>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(empty($availableEmzResources)): ?>
                            <p class="text-sm text-amber-700 dark:text-amber-300">
                                Немає локальних ЕМЗ обраного типу з посиланням на це направлення
                                (encounter.incoming_referral або procedure/diagnostic_report.based_on).
                            </p>
                        <?php else: ?>
                            <select
                                id="emzUuid"
                                wire:model="selectedEmzUuid"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="">Оберіть запис…</option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $availableEmzResources; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $resource): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($resource['uuid']); ?>"><?php echo e($resource['label']); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </select>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="flex justify-center gap-3">
                        <button
                            wire:click="confirmComplete"
                            type="button"
                            class="inline-flex items-center rounded-lg bg-blue-600 px-5 py-2.5 text-center text-sm font-medium text-white hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 focus:outline-none disabled:opacity-50 dark:focus:ring-blue-800"
                            <?php if(empty($selectedEmzUuid)): echo 'disabled'; endif; ?>
                        >
                            Підтвердити погашення
                        </button>
                        <button
                            wire:click="$set('showCompleteModal', false)"
                            type="button"
                            class="rounded-lg border border-gray-200 bg-white px-5 py-2.5 text-sm font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus:z-10 focus:ring-4 focus:ring-gray-200 focus:outline-none dark:border-gray-500 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 dark:hover:text-white dark:focus:ring-gray-600"
                        >
                            Скасувати
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/referral/referral-index.blade.php ENDPATH**/ ?>