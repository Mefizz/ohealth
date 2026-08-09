{{-- E-Prescription Form Drawer Overlay --}}
<div x-show="showEPrescriptionDrawer"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     x-cloak
     @click="showEPrescriptionDrawer = false"
     class="fixed top-0 right-0 h-screen pt-20 bg-gray-900/50"
     style="z-index: 46; width: calc(100% - 300px);"
></div>

{{-- E-Prescription Form Drawer --}}
<div id="eprescription-form-drawer-right"
     x-show="showEPrescriptionDrawer"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="translate-x-full"
     x-transition:enter-end="translate-x-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="translate-x-0"
     x-transition:leave-end="translate-x-full"
     x-cloak
     class="fixed top-0 right-0 h-screen pt-20 bg-gray-50 dark:bg-gray-900 shadow-2xl overflow-y-auto"
     style="z-index: 47; width: calc(100% - 300px);"
     tabindex="-1"
>
    <div class="max-w-4xl mx-auto p-8">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
            Електронний рецепт - {{ $carePlan->patient->full_name ?? 'Пацієнт' }}
        </h2>

        @if(!empty($ePrescriptionForm))
        <form wire:submit.prevent="validateEPrescription" class="space-y-6">
            
            {{-- Card 1: План лікування --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">План лікування</h3>
                
                <div class="flex items-center mb-4">
                    <input type="checkbox" checked disabled class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                    <label class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Виписати рецепт за планом лікування</label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-500">Назва плану</label>
                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $carePlan->title }}</div>
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-500">Призначення</label>
                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $ePrescriptionSelectedActivity ? ($ePrescriptionSelectedActivity['description'] ?? 'Призначення ЛЗ') : '' }}</div>
                    </div>
                </div>
            </div>

            {{-- Card 2: Програма --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Програма</h3>
                
                <div class="mb-4">
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Програма (МНН)</label>
                    <div class="p-3 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg text-sm font-medium">
                        {{ $ePrescriptionSelectedProgram ? ($ePrescriptionSelectedProgram['name'] ?? '') : 'Загальні призначення' }}
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-750 border border-gray-200 dark:border-gray-600 rounded-xl p-5 text-sm text-gray-600 dark:text-gray-400">
                    <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">Рецептурний лікарський засіб - деталі програми</h4>
                    <ul class="space-y-1 list-disc list-inside">
                        <li>Джерело фінансування: НСЗУ (або інші за програмою)</li>
                        <li>Тип рецептурного бланка: Ф-1</li>
                        <li>Обов'язковість використання плану лікування: Так</li>
                        <li>Максимальна тривалість курсу: Відповідно до програми</li>
                    </ul>
                </div>
            </div>

            {{-- Card 3: Лікарський засіб --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Лікарський засіб</h3>
                
                @if($ePrescriptionSelectedProduct)
                    <div class="border border-blue-200 bg-blue-50 dark:bg-gray-700 dark:border-gray-600 rounded-xl p-4 flex justify-between items-center">
                        <div>
                            <div class="font-bold text-blue-800 dark:text-blue-300 text-lg">
                                {{ $ePrescriptionSelectedProduct['name'] ?? 'Назва препарату' }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Форма випуску, дозування згідно довідника.
                            </div>
                        </div>
                        <button type="button" class="text-blue-600 hover:text-blue-800 font-medium text-sm">Змінити</button>
                    </div>
                @else
                    <button type="button" class="w-full py-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl text-blue-600 font-medium hover:bg-blue-50 transition-colors">
                        + Додати лікарський засіб
                    </button>
                @endif
            </div>

            {{-- Card 4: Інструкція прийому --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Інструкція прийому лікарського засобу</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Разова доза (на прийом)*</label>
                        <div class="flex">
                            <input type="number" step="any" min="0.01" wire:model="ePrescriptionForm.max_dose_per_administration" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-l-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            <span class="inline-flex items-center px-3 text-sm text-gray-900 bg-gray-200 border border-l-0 border-gray-300 rounded-r-md dark:bg-gray-600 dark:text-gray-400 dark:border-gray-600">
                                {{ $ePrescriptionForm['medication_unit'] ?? 'од.' }}
                            </span>
                        </div>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Максимальна добова доза*</label>
                        <div class="flex">
                            <input type="number" step="any" min="0.01" wire:model="ePrescriptionForm.max_dose_per_period" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-l-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                            <span class="inline-flex items-center px-3 text-sm text-gray-900 bg-gray-200 border border-l-0 border-gray-300 rounded-r-md dark:bg-gray-600 dark:text-gray-400 dark:border-gray-600">
                                {{ $ePrescriptionForm['medication_unit'] ?? 'од.' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Кількість на весь курс*</label>
                        <div class="flex">
                            @if(!empty($ePrescriptionMultiples))
                                <select wire:model="ePrescriptionForm.medication_qty" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-l-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <option value="">Оберіть...</option>
                                    @foreach($ePrescriptionMultiples as $qty)
                                        <option value="{{ $qty }}">{{ $qty }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="number" step="any" min="0.01" wire:model.live="ePrescriptionForm.medication_qty" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-l-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            @endif
                            <span class="inline-flex items-center px-3 text-sm text-gray-900 bg-gray-200 border border-l-0 border-gray-300 rounded-r-md dark:bg-gray-600 dark:text-gray-400 dark:border-gray-600">
                                {{ $ePrescriptionForm['medication_unit'] ?? 'од.' }}
                            </span>
                        </div>
                    </div>
                    
                    @if(!empty($ePrescriptionPackages))
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Первинна упаковка*</label>
                        <select wire:model="ePrescriptionForm.container_dosage" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="">Оберіть упаковку...</option>
                            @foreach($ePrescriptionPackages as $package)
                                @php
                                    $containerDosage = $package['container_dosage'] ?? [];
                                    $value = $containerDosage['numerator_value'] ?? ($containerDosage['value'] ?? null);
                                    $unit = $containerDosage['numerator_unit'] ?? ($containerDosage['numerator']['unit'] ?? 'од.');
                                    $code = $containerDosage['code'] ?? ($containerDosage['numerator_unit'] ?? '');
                                    $translatedUnit = match($unit) { 'PIECE' => 'шт.', 'ML' => 'мл', 'MG' => 'мг', 'G' => 'г', default => $unit };
                                @endphp
                                <option value="{{ $value }}|{{ $unit }}|{{ $code }}">{{ $value }} {{ $translatedUnit }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                </div>

                <div class="mb-2">
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Сигнатура (як приймати)*</label>
                    <textarea wire:model="ePrescriptionForm.signature_text" rows="3" class="block p-3 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white" placeholder="Приймати по 1 таблетці..."></textarea>
                </div>

                {{-- Warnings --}}
                @if($ePrescriptionWarningMessage || $ePrescriptionShowRemainingQtyWarning || $ePrescriptionShowDailyDoseWarning)
                    <div class="mt-4 space-y-3">
                        @if($ePrescriptionWarningMessage)
                            <div class="p-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400 border border-red-200">
                                <span class="font-bold">Увага!</span> {!! $ePrescriptionWarningMessage !!}
                            </div>
                        @endif
                        @if($ePrescriptionShowRemainingQtyWarning)
                            <div class="p-4 text-sm text-yellow-800 rounded-lg bg-yellow-50 dark:bg-gray-800 dark:text-yellow-300 border border-yellow-200">
                                <span class="font-bold">Залишок:</span> {{ $ePrescriptionRemainingQty }} {{ $ePrescriptionForm['medication_unit'] ?? '' }}.
                            </div>
                        @endif
                        @if($ePrescriptionShowDailyDoseWarning)
                            <div class="p-4 text-sm text-yellow-800 rounded-lg bg-yellow-50 dark:bg-gray-800 dark:text-yellow-300 border border-yellow-200">
                                Перевищено добову дозу. Впевнені?
                                <div class="mt-2 flex gap-3">
                                    <button type="button" wire:click="confirmExceededDailyDose(true)" class="text-xs px-3 py-1 bg-yellow-200 rounded">Так</button>
                                    <button type="button" wire:click="confirmExceededDailyDose(false)" class="text-xs px-3 py-1 bg-white rounded border">Ні</button>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Card 5: Додаткові дані --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Додаткові дані</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Дата початку*</label>
                        <input type="date" wire:model.live="ePrescriptionForm.started_at" @if(!$ePrescriptionSkipTreatmentPeriod) disabled @endif class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Тривалість (дні)*</label>
                        <input type="number" min="1" wire:model.live="ePrescriptionForm.duration" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Діє до</label>
                        <input type="date" value="{{ $ePrescriptionForm['ended_at'] ?? '' }}" disabled class="bg-gray-100 border border-gray-300 text-gray-500 text-sm rounded-lg block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Метод автентифікації*</label>
                        <select wire:model="ePrescriptionForm.inform_with" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="">Оберіть метод...</option>
                            @foreach($ePrescriptionAuthMethods as $method)
                                @php
                                    $methodId = $method['uuid'] ?? $method['id'] ?? '';
                                    $typeLabel = $method['type'] === 'OTP' ? 'СМС (OTP)' : ($method['type'] === 'THIRD_PERSON' ? 'Довірена особа' : 'Документи');
                                    $valueLabel = $method['phone_number'] ?? $method['alias'] ?? '';
                                @endphp
                                @if($methodId !== '')
                                <option value="{{ $methodId }}|{{ $method['type'] }}|{{ $valueLabel }}">{{ $typeLabel }} {{ $valueLabel ? '('.$valueLabel.')' : '' }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Примітка</label>
                        <input type="text" wire:model="ePrescriptionForm.note" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="Опціонально">
                    </div>
                </div>
            </div>

            {{-- Footer Actions --}}
            <div class="sticky bottom-0 bg-gray-50 dark:bg-gray-900 p-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-4 mt-8 z-10 rounded-b-xl shadow-inner-top">
                <button type="button" @click="showEPrescriptionDrawer = false" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                    Скасувати
                </button>
                <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:outline-none focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                    Сформувати Заявку
                </button>
            </div>
            
        </form>
        @endif
    </div>
</div>
