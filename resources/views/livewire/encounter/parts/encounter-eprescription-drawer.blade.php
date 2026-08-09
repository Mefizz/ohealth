@if ($showEncounterEPrescriptionDrawer)
    <div
        wire:click="closeEncounterEPrescriptionDrawer"
        class="fixed top-0 right-0 z-[46] h-screen bg-gray-900/50 pt-20"
        style="width: calc(100% - 300px)"
    ></div>

    <div
        class="fixed top-0 right-0 z-[47] h-screen overflow-y-auto bg-gray-50 p-8 pt-20 shadow-2xl dark:bg-gray-900"
        style="width: calc(100% - 300px)"
        tabindex="-1"
    >
        <h2 class="mb-6 text-2xl font-bold text-gray-900 dark:text-white">Електронний рецепт без плану лікування</h2>
        <p class="mb-6 text-sm text-gray-600 dark:text-gray-300">
            Взаємодія №{{ $encounterUuid ?? $encounterId }} · ТВ 3.9.3.3
        </p>

        <form wire:submit.prevent="validateEncounterEPrescription" class="mx-auto max-w-3xl space-y-6">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="mb-4 text-lg font-semibold">Лікарський засіб</h3>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">UUID ЛЗ *</label>
                        <input
                            type="text"
                            class="input peer w-full"
                            wire:model="encounterEPrescriptionForm.medication_id"
                        />
                        @error('encounterEPrescriptionForm.medication_id')
                            <p class="text-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Програма (опційно)</label>
                        <input
                            type="text"
                            class="input peer w-full"
                            wire:model="encounterEPrescriptionForm.program_id"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Кількість *</label>
                        <input
                            type="number"
                            min="0.01"
                            step="any"
                            class="input peer w-full"
                            wire:model="encounterEPrescriptionForm.medication_qty"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Одиниця</label>
                        <input
                            type="text"
                            class="input peer w-full"
                            wire:model="encounterEPrescriptionForm.medication_unit"
                        />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="mb-4 text-lg font-semibold">Дозування</h3>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium">Сигнатура *</label>
                        <input
                            type="text"
                            class="input peer w-full"
                            wire:model="encounterEPrescriptionForm.signature_text"
                        />
                        @error('encounterEPrescriptionForm.signature_text')
                            <p class="text-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Макс. доза за прийом *</label>
                        <input
                            type="number"
                            min="0.01"
                            step="any"
                            class="input peer w-full"
                            wire:model="encounterEPrescriptionForm.max_dose_per_administration"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Макс. доза за період *</label>
                        <input
                            type="number"
                            min="0.01"
                            step="any"
                            class="input peer w-full"
                            wire:model="encounterEPrescriptionForm.max_dose_per_period"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Початок курсу</label>
                        <input
                            type="date"
                            class="input peer w-full"
                            wire:model="encounterEPrescriptionForm.started_at"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Кінець курсу</label>
                        <input type="date" class="input peer w-full" wire:model="encounterEPrescriptionForm.ended_at" />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="mb-4 text-lg font-semibold">Автентифікація</h3>
                <div>
                    <label class="mb-1 block text-sm font-medium">Метод автентифікації пацієнта *</label>
                    <select class="input-select peer w-full" wire:model="encounterEPrescriptionForm.inform_with">
                        <option value="">Оберіть</option>
                        @foreach ($encounterEPrescriptionAuthMethods as $method)
                            <option value="{{ $method['uuid'] }}">{{ $method['label'] }}</option>
                        @endforeach
                    </select>
                    @error('encounterEPrescriptionForm.inform_with')
                        <p class="text-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            @if ($encounterEPrescriptionWarningMessage !== '')
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                    {{ $encounterEPrescriptionWarningMessage }}
                </div>
            @endif

            <div class="flex justify-end gap-3">
                <button type="button" class="button-minor" wire:click="closeEncounterEPrescriptionDrawer">
                    Скасувати
                </button>
                <button type="submit" class="button-primary">Створити та підписати</button>
            </div>
        </form>
    </div>
@endif
