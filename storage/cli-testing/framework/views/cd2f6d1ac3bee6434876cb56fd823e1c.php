<?php
    $linkedReferrals = collect($activeReferrals)->where('based_on_id', $activity->id);
?>

<div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="mb-4 flex items-center justify-between">
        <h3 class="text-sm font-bold tracking-wider text-gray-500 uppercase dark:text-gray-400">
            <?php echo e(($activity->resolvedKind() ?? '') === 'device_request'
                ? 'Виписані електронні рецепти на МВ'
                : 'Виписані направлення'); ?>

        </h3>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($linkedReferrals->isNotEmpty()): ?>
            <span class="text-xs text-gray-400 dark:text-gray-500"><?php echo e($linkedReferrals->count()); ?> шт.</span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($linkedReferrals->isEmpty()): ?>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            <?php echo e(($activity->resolvedKind() ?? '') === 'device_request'
                ? 'Ще немає виписаних електронних рецептів на медичні вироби для цього призначення. Після успішного створення в ЕСОЗ тут з’явиться номер, статус і доступні дії.'
                : 'Ще немає виписаних направлень для цього призначення. Після успішного створення в ЕСОЗ тут з’явиться номер, статус і доступні дії.'); ?>

        </p>
    <?php else: ?>
        <div class="space-y-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $linkedReferrals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $referral): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $referralKind = $referral['kind'] ?? (isset($referral['service_id']) ? 'service_request' : 'device_request');
                    $status = \App\Enums\Person\ServiceRequestStatus::resolve($referral['status'] ?? null);
                    $statusLabel = $referral['status_label'] ?? ($referral['status'] ?? '—');
                    $statusBadgeClass = \App\Enums\Person\ServiceRequestStatus::colorFor($referral['status'] ?? null);
                ?>
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4 text-sm dark:border-gray-600 dark:bg-gray-900/60">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 space-y-2">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="font-bold text-gray-900 dark:text-gray-100">
                                    № <?php echo e($referral['request_number'] ?? $referral['requisition'] ?? $referral['uuid']); ?>

                                </span>
                                <span class="badge <?php echo e($statusBadgeClass); ?>"> <?php echo e($statusLabel); ?> </span>
                            </div>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 text-gray-600 dark:text-gray-300">
                                <span>Код: <?php echo e($referral['product_code'] ?? '—'); ?></span>
                                <span>Кількість: <?php echo e($referral['quantity'] ?? '—'); ?></span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($referral['category_label']) || !empty($referral['category'])): ?>
                                    <span>Категорія: <?php echo e($referral['category_label'] ?? $referral['category']); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($referral['priority_label']) || !empty($referral['priority'])): ?>
                                    <span>Пріоритет: <?php echo e($referral['priority_label'] ?? $referral['priority']); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($referral['started_at']) && !empty($referral['ended_at'])): ?>
                                <div class="text-xs text-gray-400 dark:text-gray-500">
                                    Діє з <?php echo e(\Carbon\Carbon::parse($referral['started_at'])->format('d.m.Y')); ?> по <?php echo e(\Carbon\Carbon::parse($referral['ended_at'])->format('d.m.Y')); ?>

                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($referral['employee_name'])): ?>
                                <div class="text-xs text-gray-400 dark:text-gray-500">
                                    Виписав: <?php echo e($referral['employee_name']); ?>

                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($referral['note'])): ?>
                                <div class="text-xs text-gray-500 italic dark:text-gray-400">
                                    <?php echo e($referral['note']); ?>

                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-3">
                            <button
                                type="button"
                                class="flex items-center gap-1 text-gray-500 transition-colors hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                title="Оновити з ЕСОЗ"
                                wire:click="syncReferralFromEHealth('<?php echo e($referral['uuid']); ?>', '<?php echo e($referralKind); ?>')"
                            >
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
                                <span class="text-xs">Оновити</span>
                            </button>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($status, [\App\Enums\Person\ServiceRequestStatus::DRAFT, \App\Enums\Person\ServiceRequestStatus::NEW], true)): ?>
                                <?php
                                    $signAction = $referralKind === 'service_request' ? 'sign_servicerequest' : 'sign_devicerequest';
                                ?>
                                <button
                                    type="button"
                                    class="flex items-center gap-1 text-green-500 transition-colors hover:text-green-600 dark:text-green-400 dark:hover:text-green-300"
                                    title="Підписати КЕП"
                                    wire:click="openSignatureModal('<?php echo e($signAction); ?>', null, '<?php echo e($referral['uuid']); ?>')"
                                >
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

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($status === \App\Enums\Person\ServiceRequestStatus::ACTIVE): ?>
                                <button
                                    type="button"
                                    class="flex items-center gap-1 text-blue-500 transition-colors hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-300"
                                    title="Друк пам'ятки"
                                    @click="
                                            $wire.loadReferralPrintoutForm('<?php echo e($referral['uuid']); ?>').then((html) => {
                                                if (! html) {
                                                    return;
                                                }
                                                const printWindow = window.open('', '_blank');
                                                printWindow.document.open();
                                                printWindow.document.write('<!DOCTYPE html><html><head><meta charset=&quot;utf-8&quot;><title>Пам\'ятка</title></head><body>' + html + '</body></html>');
                                                printWindow.document.close();
                                                printWindow.focus();
                                                setTimeout(() => {
                                                    printWindow.print();
                                                }, 250);
                                            });
                                        "
                                >
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
                                <button
                                    type="button"
                                    class="flex items-center gap-1 text-yellow-600 transition-colors hover:text-yellow-500 dark:text-yellow-400 dark:hover:text-yellow-300"
                                    title="Повторно надіслати SMS"
                                    wire:click="resendReferralSms('<?php echo e($referral['uuid']); ?>', '<?php echo e($referralKind); ?>')"
                                >
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
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($referralKind === 'service_request'): ?>
                                    <button
                                        type="button"
                                        class="flex items-center gap-1 text-amber-600 transition-colors hover:text-amber-500 dark:text-amber-400 dark:hover:text-amber-300"
                                        title="Відкликати (за непотрібністю)"
                                        wire:click="recallReferral('<?php echo e($referral['uuid']); ?>', '<?php echo e($referralKind); ?>')"
                                    >
                                        <?php
                // Parse arguments from the directive
                $iconArgs = ['trash', 'w-4 h-4'];
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
                                        <span class="text-xs">Відкликати</span>
                                    </button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <button
                                    type="button"
                                    class="flex items-center gap-1 text-red-500 transition-colors hover:text-red-400 dark:text-red-400 dark:hover:text-red-300"
                                    title="Позначити внесеним помилково"
                                    wire:click="cancelReferral('<?php echo e($referral['uuid']); ?>', '<?php echo e($referralKind); ?>')"
                                >
                                    <?php
                // Parse arguments from the directive
                $iconArgs = ['trash', 'w-4 h-4'];
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
                                    <span class="text-xs">Внесено помилково</span>
                                </button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/care-plan/parts/activity/referrals-list.blade.php ENDPATH**/ ?>