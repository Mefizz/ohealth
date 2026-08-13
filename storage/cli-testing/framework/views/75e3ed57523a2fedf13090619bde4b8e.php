<?php
    $linkedPrescriptions = collect($activePrescriptions)->filter(function ($item) use ($activity) {
        return (int) ($item['based_on_id'] ?? $item['basedOnId'] ?? 0) === (int) $activity->id;
    });
?>

<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Виписані Е-Рецепти</h3>
        <div class="flex items-center gap-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($linkedPrescriptions->isNotEmpty()): ?>
                <span class="text-xs text-gray-400 dark:text-gray-500"><?php echo e($linkedPrescriptions->count()); ?> шт.</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <button type="button" wire:click="syncEPrescriptions" wire:loading.attr="disabled" class="text-xs font-semibold text-teal-600 hover:text-teal-700 dark:text-teal-400 dark:hover:text-teal-300 transition flex items-center gap-1" title="Оновити статуси з ЕСОЗ">
                <?php
                // Parse arguments from the directive
                $iconArgs = ['refresh', 'w-3.5 h-3.5'];
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
                <span>Синхронізувати з ЕСОЗ</span>
            </button>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($linkedPrescriptions->isEmpty()): ?>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Ще немає виписаних електронних рецептів для цього призначення. Після успішного створення в ЕСОЗ тут з’явиться номер, статус і доступні дії.
        </p>
    <?php else: ?>
        <div class="space-y-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $linkedPrescriptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $prescription): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $rawStatus = (string) ($prescription['status'] ?? '');
                    $status = \App\Enums\Person\MedicationRequestStatus::resolve($rawStatus);
                    $uuid = $prescription['uuid'] ?? '';
                    $requestNumber = $prescription['request_number'] ?? $prescription['requestNumber'] ?? $uuid;
                    $medicationQty = $prescription['medication_qty'] ?? $prescription['medicationQty'] ?? '—';
                    $startedAt = $prescription['started_at'] ?? $prescription['startedAt'] ?? null;
                    $endedAt = $prescription['ended_at'] ?? $prescription['endedAt'] ?? null;
                ?>
                <div class="flex items-center justify-between text-sm bg-gray-50 dark:bg-gray-700/40 p-3 rounded-lg border border-gray-100 dark:border-gray-700">
                    <div class="flex flex-wrap items-center gap-4">
                        <span class="font-bold text-gray-900 dark:text-white">№ <?php echo e($requestNumber); ?></span>
                        <span class="text-gray-500">Кількість: <?php echo e($medicationQty); ?></span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($startedAt) && !empty($endedAt)): ?>
                            <span class="text-gray-400 text-xs">Діє з <?php echo e(\Carbon\Carbon::parse($startedAt)->format('d.m.Y')); ?> по <?php echo e(\Carbon\Carbon::parse($endedAt)->format('d.m.Y')); ?></span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <span class="badge <?php echo e(\App\Enums\Person\MedicationRequestStatus::colorFor($rawStatus)); ?>">
                            <?php echo e(\App\Enums\Person\MedicationRequestStatus::labelFor($rawStatus)); ?>

                        </span>
                    </div>
                    <div class="flex items-center gap-3">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($status?->isUnsigned()): ?>
                            <button type="button" class="text-green-500 hover:text-green-700 transition-colors flex items-center gap-1" title="Підписати КЕП" wire:click="openSignatureModal('sign_eprescription', null, '<?php echo e($uuid); ?>')">
                                <?php
                // Parse arguments from the directive
                $iconArgs = ['key', 'w-4 h-4'];
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
                                <span class="text-xs">Підписати</span>
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($status === \App\Enums\Person\MedicationRequestStatus::ACTIVE): ?>
                            <button type="button" class="text-blue-500 hover:text-blue-700 transition-colors flex items-center gap-1" title="Друк пам'ятки"
                                    @click="
                                        $wire.loadPrintoutForm('<?php echo e($uuid); ?>').then((content) => {
                                            let printWindow = window.open('', '_blank');
                                            if (printWindow) {
                                                printWindow.document.open();
                                                printWindow.document.write(content || $wire.printableContent || '<h3>Дані для друку відсутні</h3>');
                                                printWindow.document.close();
                                                setTimeout(() => { printWindow.focus(); printWindow.print(); }, 250);
                                            }
                                        });
                                    ">
                                <?php
                // Parse arguments from the directive
                $iconArgs = ['printer', 'w-4 h-4'];
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
                                <span class="text-xs">Пам'ятка</span>
                            </button>
                            <button type="button" class="text-yellow-600 hover:text-yellow-800 transition-colors flex items-center gap-1" title="Повторно надіслати SMS" wire:click="resendPrescriptionSms('<?php echo e($uuid); ?>')">
                                <?php
                // Parse arguments from the directive
                $iconArgs = ['refresh', 'w-4 h-4'];
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
                                <span class="text-xs">SMS</span>
                            </button>
                            <button type="button" class="text-indigo-600 hover:text-indigo-800 transition-colors flex items-center gap-1" title="Історія погашення в аптеках" wire:click="checkDispenseHistory('<?php echo e($uuid); ?>')">
                                <?php
                // Parse arguments from the directive
                $iconArgs = ['file-text', 'w-4 h-4'];
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
                                <span class="text-xs">Погашення</span>
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($status?->isUnsigned() || $status === \App\Enums\Person\MedicationRequestStatus::ACTIVE): ?>
                            <button type="button" class="text-orange-500 hover:text-orange-700 transition-colors flex items-center gap-1" title="Відхилити рецепт" wire:click="rejectPrescription('<?php echo e($uuid); ?>')">
                                <?php
                // Parse arguments from the directive
                $iconArgs = ['x-circle', 'w-4 h-4'];
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
                                <span class="text-xs">Відхилити</span>
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/care-plan/parts/activity/prescriptions-list.blade.php ENDPATH**/ ?>