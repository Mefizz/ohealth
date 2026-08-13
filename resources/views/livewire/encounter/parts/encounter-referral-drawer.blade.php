@if ($showEncounterReferralDrawer)
    <div
        wire:click="closeEncounterReferralDrawer"
        class="fixed top-0 right-0 z-[46] h-screen bg-gray-900/50 pt-20"
        style="width: calc(80% - 30px)"
    ></div>

    <div
        class="fixed top-0 right-0 z-[47] h-screen overflow-y-auto bg-white p-4 pt-20 shadow-2xl dark:bg-gray-800"
        style="width: calc(80% - 60px)"
        tabindex="-1"
    >
        <h3 class="modal-header">Виписати електронне направлення (без плану лікування)</h3>

        <form wire:submit.prevent="validateEncounterReferral" class="space-y-6">
            <fieldset class="fieldset">
                <legend class="legend">Послуга</legend>
                <div class="mb-4 grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="form-group group">
                        <label class="label required">UUID послуги (service)</label>
                        <input
                            type="text"
                            class="input peer"
                            wire:model="encounterReferralForm.service_id"
                            placeholder="uuid"
                        />
                        @error('encounterReferralForm.service_id')
                            <p class="text-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="form-group group">
                        <label class="label required">Категорія</label>
                        <select class="input-select peer w-full" wire:model="encounterReferralForm.category">
                            <option value="procedure">procedure</option>
                            <option value="diagnostic_procedure">diagnostic_procedure</option>
                            <option value="counselling">counselling</option>
                            <option value="transfer_of_care">transfer_of_care</option>
                        </select>
                    </div>
                </div>
            </fieldset>

            <fieldset class="fieldset">
                <legend class="legend">Термін дії та кількість</legend>
                <div class="mb-4 grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div class="form-group group">
                        <label class="label required">Дата початку</label>
                        <input
                            type="text"
                            class="input peer"
                            placeholder="dd.mm.yyyy"
                            wire:model="encounterReferralForm.started_at"
                        />
                    </div>
                    <div class="form-group group">
                        <label class="label required">Дата закінчення</label>
                        <input
                            type="text"
                            class="input peer"
                            placeholder="dd.mm.yyyy"
                            wire:model="encounterReferralForm.ended_at"
                        />
                    </div>
                    <div class="form-group group">
                        <label class="label required">Кількість</label>
                        <input
                            type="number"
                            min="0.01"
                            step="any"
                            class="input peer"
                            wire:model="encounterReferralForm.quantity"
                        />
                    </div>
                </div>
                <div class="mb-4 grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="form-group group">
                        <label class="label required">Пріоритет</label>
                        <select class="input-select peer w-full" wire:model="encounterReferralForm.priority">
                            <option value="routine">Планове (Routine)</option>
                            <option value="urgent">Ургентне (Urgent)</option>
                            <option value="asap">Якнайшвидше (ASAP)</option>
                            <option value="stat">Негайно (STAT)</option>
                        </select>
                    </div>
                    <div class="form-group group">
                        <label class="label">Програма (опційно)</label>
                        <input
                            type="text"
                            class="input peer"
                            wire:model="encounterReferralForm.program_id"
                            placeholder="uuid програми"
                        />
                    </div>
                </div>
            </fieldset>

            <fieldset class="fieldset">
                <legend class="legend">Додатково</legend>
                <div class="mb-4 grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="form-group group">
                        <label class="label">Метод автентифікації</label>
                        <select class="input-select peer w-full" wire:model="encounterReferralForm.inform_with">
                            <option value="">Не обрано</option>
                            @foreach ($encounterReferralAuthMethods as $method)
                                <option value="{{ $method['uuid'] }}">{{ $method['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group group">
                        <label class="label">Інструкція пацієнту</label>
                        <input type="text" class="input peer" wire:model="encounterReferralForm.patient_instruction" />
                    </div>
                </div>
                <div class="form-group group">
                    <label class="label">Примітки</label>
                    <textarea class="input peer min-h-20" wire:model="encounterReferralForm.note"></textarea>
                </div>
            </fieldset>

            @if ($encounterReferralWarningMessage !== '')
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                    {{ $encounterReferralWarningMessage }}
                </div>
            @endif

            <div class="flex justify-end gap-3">
                <button type="button" class="button-minor" wire:click="closeEncounterReferralDrawer">Скасувати</button>
                <button type="submit" class="button-primary">Створити та підписати</button>
            </div>
        </form>
    </div>
@endif
