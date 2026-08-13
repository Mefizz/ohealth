<?php
    $dictionaries = $dictionaries ?? [];
    $resolvedKind = $activity->resolvedKind();
    $kindTranslationKey = 'care-plan.activity_kind.' . $resolvedKind;
    $translatedKind = \Illuminate\Support\Facades\Lang::has($kindTranslationKey) ? __($kindTranslationKey) : $resolvedKind;

    $activityStatus = is_array($activity->status) ? ($activity->status['coding'][0]['code'] ?? ($activity->status['text'] ?? '')) : $activity->status;
    $statusKey = 'care-plan.status.' . strtolower((string) $activityStatus);
    $activityStatusDisplay = \Illuminate\Support\Facades\Lang::has($statusKey)
        ? __($statusKey)
        : (is_array($activity->status) ? ($activity->status['text'] ?? ($activity->status['coding'][0]['display'] ?? $activityStatus)) : $activityStatus);

    $quantityValue = is_array($activity->quantity) ? ($activity->quantity['value'] ?? null) : $activity->quantity;
    $quantityUnitCode = $activity->quantityCode ?: (is_array($activity->quantity) ? ($activity->quantity['code'] ?? null) : null);
    $quantityUnitLabel = $quantityUnitCode
        ? ($dictionaries['device_unit'][$quantityUnitCode]
            ?? $dictionaries['MEDICATION_UNIT'][$quantityUnitCode]
            ?? $quantityUnitCode)
        : '';

    $remainingValue = $activity->remainingQuantity;
    $remainingUnitCode = $activity->remainingQuantityCode;
    $remainingUnitLabel = $remainingUnitCode
        ? ($dictionaries['device_unit'][$remainingUnitCode]
            ?? $dictionaries['MEDICATION_UNIT'][$remainingUnitCode]
            ?? $remainingUnitCode)
        : $quantityUnitLabel;

    $dailyAmountValue = $activity->dailyAmount;
    $dailyAmountUnit = $activity->dailyAmountCode
        ? ($dictionaries['MEDICATION_UNIT'][$activity->dailyAmountCode] ?? $activity->dailyAmountCode)
        : '';

    $programLabel = $activity->program
        ? ($dictionaries['medical_programs'][$activity->program]
            ?? $dictionaries['medical_programs_device'][$activity->program]
            ?? $dictionaries['medical_programs_medication'][$activity->program]
            ?? $activity->program)
        : null;

    $productLabel = $activityProductLabel ?? null;
    if ($productLabel === null && !empty($activity->productReference)) {
        $productLabel = $activity->productReference;
    }
    if ($productLabel === null && !empty($activity->productCodeableConcept)) {
        $productLabel = $dictionaries['device_definition_classification_type'][$activity->productCodeableConcept]
            ?? $activity->productCodeableConcept;
    }

    $authorName = $activity->author?->party?->fullName
        ?? $activity->author?->fullName
        ?? null;

    $reasonCodeLabel = $activity->reasonCode
        ? ($dictionaries['eHealth/ICD10_AM/condition_codes'][$activity->reasonCode]
            ?? $dictionaries['eHealth/ICPC2/condition_codes'][$activity->reasonCode]
            ?? $activity->reasonCode)
        : null;

    $reasonReferences = collect($activity->reasonReference ?? [])
        ->map(fn ($ref) => is_string($ref) ? $ref : ($ref['uuid'] ?? json_encode($ref)))
        ->filter()
        ->values()
        ->all();

    $goals = collect($activity->goal ?? [])
        ->map(fn ($goal) => is_string($goal) ? $goal : ($goal['code'] ?? ($goal['text'] ?? json_encode($goal))))
        ->filter()
        ->values()
        ->all();

    $statusReason = is_array($activity->statusReason)
        ? ($activity->statusReason['text'] ?? ($activity->statusReason['coding'][0]['code'] ?? null))
        : $activity->statusReason;

    $outcomeCodeable = $activity->outcomeCodeableConcept;
    $outcomeReference = $activity->outcomeReference;
?>


<div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white"><?php echo e($translatedKind ?: '-'); ?></h2>
            <p class="mt-1 text-sm text-gray-500">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activity->uuid): ?>
                    ID:
                    <span class="font-mono"><?php echo e($activity->uuid); ?></span>
                <?php else: ?>
                    ID:
                    <span class="font-mono"><?php echo e($activity->id); ?> (Чернетка)</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </p>
        </div>
        <span class="badge <?php echo e(in_array(strtoupper((string) $activityStatus), ['NEW', 'DRAFT']) ? 'badge-yellow' : 'badge-green'); ?>">
            <?php echo e($activityStatusDisplay); ?>

        </span>
    </div>

    <div class="grid grid-cols-1 gap-6 text-sm md:grid-cols-2 lg:grid-cols-3">
        <div>
            <div class="mb-1 text-xs font-semibold tracking-wider text-gray-400 uppercase">
                <?php echo e(__('care-plan.author')); ?>

            </div>
            <div class="text-gray-900 dark:text-white"><?php echo e($authorName ?: '—'); ?></div>
        </div>

        <div>
            <div class="mb-1 text-xs font-semibold tracking-wider text-gray-400 uppercase">
                <?php echo e(__('care-plan.status_label')); ?>

            </div>
            <div class="text-gray-900 dark:text-white"><?php echo e($activityStatusDisplay ?: '—'); ?></div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($productLabel): ?>
            <div class="md:col-span-2 lg:col-span-3">
                <div class="mb-1 text-xs font-semibold tracking-wider text-gray-400 uppercase">
                    <?php echo e(__('care-plan.assignment')); ?>

                </div>
                <div class="text-gray-900 dark:text-white"><?php echo e($productLabel); ?></div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($activity->productReference) && $productLabel !== $activity->productReference): ?>
                    <div class="mt-1 font-mono text-xs text-gray-400"><?php echo e($activity->productReference); ?></div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div>
            <div class="mb-1 text-xs font-semibold tracking-wider text-gray-400 uppercase">
                <?php echo e(__('care-plan.grounds_for_prescription')); ?>

            </div>
            <div class="text-gray-900 dark:text-white"><?php echo e($reasonCodeLabel ?: '—'); ?></div>
        </div>

        <div class="md:col-span-2">
            <div class="mb-1 text-xs font-semibold tracking-wider text-gray-400 uppercase">
                <?php echo e(__('care-plan.justification_of_grounds')); ?>

            </div>
            <div class="text-gray-900 dark:text-white">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reasonReferences !== []): ?>
                    <ul class="list-inside list-disc space-y-0.5">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $reasonReferences; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ref): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li class="font-mono text-xs"><?php echo e($ref); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </ul>
                <?php else: ?>
                    —
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <div class="md:col-span-2 lg:col-span-3">
            <div class="mb-1 text-xs font-semibold tracking-wider text-gray-400 uppercase">
                <?php echo e(__('care-plan.expected_result')); ?>

            </div>
            <div class="text-gray-900 dark:text-white">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($goals !== []): ?>
                    <?php echo e(implode(', ', $goals)); ?>

                <?php else: ?>
                    —
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($programLabel): ?>
            <div>
                <div class="mb-1 text-xs font-semibold tracking-wider text-gray-400 uppercase">
                    <?php echo e(__('care-plan.program')); ?>

                </div>
                <div class="text-gray-900 dark:text-white"><?php echo e($programLabel); ?></div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div>
            <div class="mb-1 text-xs font-semibold tracking-wider text-gray-400 uppercase">
                <?php echo e(__('care-plan.quantity')); ?>

            </div>
            <div class="text-gray-900 dark:text-white">
                <?php echo e($quantityValue ?? '—'); ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($quantityUnitLabel): ?>
                    <?php echo e($quantityUnitLabel); ?>

                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <div>
            <div class="mb-1 text-xs font-semibold tracking-wider text-gray-400 uppercase">
                <?php echo e(__('care-plan.remaining_quantity')); ?>

            </div>
            <div class="text-gray-900 dark:text-white">
                <?php echo e($remainingValue ?? '—'); ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($remainingValue !== null && $remainingUnitLabel): ?>
                    <?php echo e($remainingUnitLabel); ?>

                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(str_contains($resolvedKind, 'medication')): ?>
            <div>
                <div class="mb-1 text-xs font-semibold tracking-wider text-gray-400 uppercase">
                    <?php echo e(__('care-plan.daily_amount')); ?>

                </div>
                <div class="text-gray-900 dark:text-white">
                    <?php echo e($dailyAmountValue ?? '—'); ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dailyAmountValue !== null && $dailyAmountUnit): ?>
                        <?php echo e($dailyAmountUnit); ?>

                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div>
            <div class="mb-1 text-xs font-semibold tracking-wider text-gray-400 uppercase">
                <?php echo e(__('forms.start_date')); ?>

            </div>
            <div class="text-gray-900 dark:text-white">
                <?php echo e($activity->scheduledPeriodStart?->format('d.m.Y') ?: '—'); ?>

            </div>
        </div>
        <div>
            <div class="mb-1 text-xs font-semibold tracking-wider text-gray-400 uppercase">
                <?php echo e(__('forms.end_date')); ?>

            </div>
            <div class="text-gray-900 dark:text-white">
                <?php echo e($activity->scheduledPeriodEnd?->format('d.m.Y') ?: '—'); ?>

            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($statusReason): ?>
            <div class="md:col-span-2 lg:col-span-3">
                <div class="mb-1 text-xs font-semibold tracking-wider text-gray-400 uppercase">
                    <?php echo e(__('care-plan.status_reason')); ?>

                </div>
                <div class="text-gray-900 dark:text-white"><?php echo e($statusReason); ?></div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($outcomeCodeable || $outcomeReference): ?>
            <div>
                <div class="mb-1 text-xs font-semibold tracking-wider text-gray-400 uppercase">
                    <?php echo e(__('care-plan.outcome_dictionary')); ?>

                </div>
                <div class="text-gray-900 dark:text-white"><?php echo e($outcomeCodeable ?: '—'); ?></div>
            </div>
            <div class="md:col-span-2">
                <div class="mb-1 text-xs font-semibold tracking-wider text-gray-400 uppercase">
                    <?php echo e(__('care-plan.activity_outcomes')); ?>

                </div>
                <div class="font-mono text-xs text-gray-900 dark:text-white"><?php echo e($outcomeReference ?: '—'); ?></div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activity->description): ?>
        <div class="mt-6">
            <div class="mb-2 text-xs font-semibold tracking-wider text-gray-400 uppercase">
                <?php echo e(__('care-plan.description')); ?>

            </div>
            <div class="text-sm whitespace-pre-line text-gray-700 dark:text-gray-300"><?php echo e($activity->description); ?></div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/care-plan/parts/activity/detail-card.blade.php ENDPATH**/ ?>