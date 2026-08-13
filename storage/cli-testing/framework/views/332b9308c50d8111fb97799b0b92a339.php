
<div
    x-show="showEPrescriptionDrawer"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    x-cloak
    @click="showEPrescriptionDrawer = false"
    class="fixed top-0 right-0 h-screen bg-gray-900/50 pt-20"
    style="z-index: 46; width: calc(100% - 300px)"
></div>


<div
    id="eprescription-form-drawer-right"
    x-show="showEPrescriptionDrawer"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="translate-x-full"
    x-transition:enter-end="translate-x-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="translate-x-0"
    x-transition:leave-end="translate-x-full"
    x-cloak
    class="fixed top-0 right-0 h-screen overflow-y-auto bg-gray-50 pt-20 shadow-2xl dark:bg-gray-900"
    style="z-index: 47; width: calc(100% - 300px)"
    tabindex="-1"
>
    <div class="mx-auto max-w-4xl p-8">
        <h2 class="mb-6 text-2xl font-bold text-gray-900 dark:text-white">
            Електронний рецепт - <?php echo e($carePlan->patient->full_name ?? 'Пацієнт'); ?>

        </h2>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($ePrescriptionForm)): ?>
            <form wire:submit.prevent="validateEPrescription" class="space-y-6">
                
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">План лікування</h3>

                    <div class="mb-4 flex items-center">
                        <input
                            type="checkbox"
                            checked
                            disabled
                            class="h-4 w-4 rounded border-gray-300 bg-gray-100 text-blue-600 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-blue-600"
                        />
                        <label class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Виписати рецепт за планом лікування</label>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-500">Назва плану</label>
                            <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo e($carePlan->title); ?></div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-500">Призначення</label>
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo e($ePrescriptionSelectedActivity ? ($ePrescriptionSelectedActivity['description'] ?? 'Призначення ЛЗ') : ''); ?>

                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo e(__('care-plan.eprescription_encounter')); ?></label>
                        <select
                            wire:model="ePrescriptionForm.encounter_id"
                            required
                            class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value=""><?php echo e(__('care-plan.eprescription_encounter_placeholder')); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ePrescriptionEligibleEncounters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $encounterOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($encounterOption['id']); ?>"><?php echo e($encounterOption['label']); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Лише завершені взаємодії за сьогодні, де ви є виконавцем (ТВ 3.9.1.1.2).
                        </p>
                    </div>
                </div>

                
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Програма</h3>

                    <div class="mb-4">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Програма (МНН)</label>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm font-medium dark:border-gray-600 dark:bg-gray-700">
                            <?php echo e($ePrescriptionSelectedProgram ? ($ePrescriptionSelectedProgram['name'] ?? '') : 'Загальні призначення'); ?>

                        </div>
                    </div>

                    <div class="dark:bg-gray-750 rounded-xl border border-gray-200 bg-gray-50 p-5 text-sm text-gray-600 dark:border-gray-600 dark:text-gray-400">
                        <h4 class="mb-2 font-semibold text-gray-800 dark:text-gray-200">
                            Рецептурний лікарський засіб - деталі програми
                        </h4>
                        <ul class="list-inside list-disc space-y-1">
                            <li>Джерело фінансування: НСЗУ (або інші за програмою)</li>
                            <li>Тип рецептурного бланка: Ф-1</li>
                            <li>Обов'язковість використання плану лікування: Так</li>
                            <li>Максимальна тривалість курсу: Відповідно до програми</li>
                        </ul>
                    </div>
                </div>

                
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Лікарський засіб</h3>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ePrescriptionSelectedProduct): ?>
                        <div class="flex items-center justify-between rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-gray-600 dark:bg-gray-700">
                            <div>
                                <div class="text-lg font-bold text-blue-800 dark:text-blue-300">
                                    <?php echo e($ePrescriptionSelectedProduct['name'] ?? 'Назва препарату'); ?>

                                </div>
                                <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Форма випуску, дозування згідно довідника.
                                </div>
                            </div>
                            <button type="button" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                Змінити
                            </button>
                        </div>
                    <?php else: ?>
                        <button
                            type="button"
                            class="w-full rounded-xl border-2 border-dashed border-gray-300 py-4 font-medium text-blue-600 transition-colors hover:bg-blue-50 dark:border-gray-600"
                        >
                            + Додати лікарський засіб
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                        Інструкція прийому лікарського засобу
                    </h3>

                    <div class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Разова доза (на прийом)*</label>
                            <div class="flex">
                                <input
                                    type="number"
                                    step="any"
                                    min="0.01"
                                    wire:model="ePrescriptionForm.max_dose_per_administration"
                                    class="block w-full rounded-l-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400"
                                />
                                <span class="inline-flex items-center rounded-r-md border border-l-0 border-gray-300 bg-gray-200 px-3 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-400">
                                    <?php echo e($ePrescriptionForm['medication_unit'] ?? 'од.'); ?>

                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Максимальна добова доза*</label>
                            <div class="flex">
                                <input
                                    type="number"
                                    step="any"
                                    min="0.01"
                                    wire:model="ePrescriptionForm.max_dose_per_period"
                                    class="block w-full rounded-l-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400"
                                />
                                <span class="inline-flex items-center rounded-r-md border border-l-0 border-gray-300 bg-gray-200 px-3 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-400">
                                    <?php echo e($ePrescriptionForm['medication_unit'] ?? 'од.'); ?>

                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Кількість на весь курс*</label>
                            <div class="flex">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($ePrescriptionMultiples)): ?>
                                    <select
                                        wire:model="ePrescriptionForm.medication_qty"
                                        class="block w-full rounded-l-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    >
                                        <option value="">Оберіть...</option>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ePrescriptionMultiples; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $qty): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($qty); ?>"><?php echo e($qty); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </select>
                                <?php else: ?>
                                    <input
                                        type="number"
                                        step="any"
                                        min="0.01"
                                        wire:model.live="ePrescriptionForm.medication_qty"
                                        class="block w-full rounded-l-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <span class="inline-flex items-center rounded-r-md border border-l-0 border-gray-300 bg-gray-200 px-3 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-400">
                                    <?php echo e($ePrescriptionForm['medication_unit'] ?? 'од.'); ?>

                                </span>
                            </div>
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($ePrescriptionPackages)): ?>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Первинна упаковка*</label>
                                <select
                                    wire:model="ePrescriptionForm.container_dosage"
                                    class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                >
                                    <option value="">Оберіть упаковку...</option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ePrescriptionPackages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $package): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <?php
                                            $containerDosage = $package['container_dosage'] ?? [];
                                            $value = $containerDosage['numerator_value'] ?? ($containerDosage['value'] ?? null);
                                            $unit = $containerDosage['numerator_unit'] ?? ($containerDosage['numerator']['unit'] ?? 'од.');
                                            $code = $containerDosage['code'] ?? ($containerDosage['numerator_unit'] ?? '');
                                            $translatedUnit = match($unit) {
                                                'PIECE' => 'шт.', 'ML' => 'мл', 'MG' => 'мг', 'G' => 'г', default => $unit
                                            };
                                        ?>
                                        <option value="<?php echo e($value); ?>|<?php echo e($unit); ?>|<?php echo e($code); ?>">
                                            <?php echo e($value); ?> <?php echo e($translatedUnit); ?>

                                        </option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </select>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="mb-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Сигнатура (як приймати)*</label>
                        <textarea
                            wire:model="ePrescriptionForm.signature_text"
                            rows="3"
                            class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-3 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400"
                            placeholder="Приймати по 1 таблетці..."
                        ></textarea>
                    </div>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ePrescriptionWarningMessage || $ePrescriptionShowRemainingQtyWarning || $ePrescriptionShowDailyDoseWarning): ?>
                        <div class="mt-4 space-y-3">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ePrescriptionWarningMessage): ?>
                                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:bg-gray-800 dark:text-red-400">
                                    <span class="font-bold">Увага!</span> <?php echo $ePrescriptionWarningMessage; ?>

                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ePrescriptionShowRemainingQtyWarning): ?>
                                <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800 dark:bg-gray-800 dark:text-yellow-300">
                                    <span class="font-bold">Залишок:</span>
                                    <?php echo e($ePrescriptionRemainingQtyWarningMessage !== ''
                                        ? $ePrescriptionRemainingQtyWarningMessage
                                        : ($ePrescriptionRemainingQty . ' ' . ($ePrescriptionForm['medication_unit'] ?? ''))); ?>

                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ePrescriptionShowDailyDoseWarning): ?>
                                <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800 dark:bg-gray-800 dark:text-yellow-300">
                                    Перевищено добову дозу. Впевнені?
                                    <div class="mt-2 flex gap-3">
                                        <button
                                            type="button"
                                            wire:click="confirmExceededDailyDose(true)"
                                            class="rounded bg-yellow-200 px-3 py-1 text-xs"
                                        >
                                            Так
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="confirmExceededDailyDose(false)"
                                            class="rounded border bg-white px-3 py-1 text-xs"
                                        >
                                            Ні
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Додаткові дані</h3>

                    <div class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-3">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Дата початку*</label>
                            <input
                                type="date"
                                wire:model.live="ePrescriptionForm.started_at"
                                <?php if(!$ePrescriptionSkipTreatmentPeriod): ?> disabled <?php endif; ?>
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Тривалість (дні)*</label>
                            <input
                                type="number"
                                min="1"
                                wire:model.live="ePrescriptionForm.duration"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Діє до</label>
                            <input
                                type="date"
                                value="<?php echo e($ePrescriptionForm['ended_at'] ?? ''); ?>"
                                disabled
                                class="block w-full rounded-lg border border-gray-300 bg-gray-100 p-2.5 text-sm text-gray-500 dark:border-gray-500 dark:bg-gray-600"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Метод автентифікації*</label>
                            <select
                                wire:model="ePrescriptionForm.inform_with"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="">Оберіть метод...</option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ePrescriptionAuthMethods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $method): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php
                                        $methodId = $method['uuid'] ?? $method['id'] ?? '';
                                        $typeLabel = $method['type'] === 'OTP' ? 'СМС (OTP)' : ($method['type'] === 'THIRD_PERSON' ? 'Довірена особа' : 'Документи');
                                        $valueLabel = $method['phone_number'] ?? $method['alias'] ?? '';
                                    ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($methodId !== ''): ?>
                                        <option value="<?php echo e($methodId); ?>|<?php echo e($method['type']); ?>|<?php echo e($valueLabel); ?>">
                                            <?php echo e($typeLabel); ?> <?php echo e($valueLabel ? '('.$valueLabel.')' : ''); ?>

                                        </option>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Примітка</label>
                            <input
                                type="text"
                                wire:model="ePrescriptionForm.note"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                placeholder="Опціонально"
                            />
                        </div>
                    </div>
                </div>

                
                <div class="shadow-inner-top sticky bottom-0 z-10 mt-8 flex justify-end gap-4 rounded-b-xl border-t border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                    <button
                        type="button"
                        @click="showEPrescriptionDrawer = false"
                        class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        Скасувати
                    </button>
                    <button
                        type="submit"
                        class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 focus:outline-none dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800"
                    >
                        Сформувати Заявку
                    </button>
                </div>
            </form>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH /var/www/html/.worktrees/i489_refactor/resources/views/livewire/care-plan/parts/modals/eprescription-form-drawer.blade.php ENDPATH**/ ?>