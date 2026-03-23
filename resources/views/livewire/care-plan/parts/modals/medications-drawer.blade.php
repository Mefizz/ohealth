{{-- Medications Drawer --}}
<template x-teleport="body">
    <div>
        <div x-show="showMedicationDrawer" class="fixed inset-0 z-30 bg-gray-900 bg-opacity-50" x-transition.opacity style="display: none;" @click="showMedicationDrawer = false"></div>
        <div x-show="showMedicationDrawer"
             x-transition:enter="transition-transform ease-out duration-300"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition-transform ease-in duration-300"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full"
             class="fixed top-0 right-0 h-screen pt-20 p-4 overflow-y-auto bg-white w-4/5 dark:bg-gray-800"
         style="z-index: 40;"
         tabindex="-1"
         aria-labelledby="medications-drawer-label"
    >
        <h3 class="modal-header" id="medications-drawer-label">
            {{ __('care-plan.new_medication_prescription') }}
        </h3>

        {{-- Content --}}
        <form>
            {{-- Program Selection Section --}}
            <fieldset class="fieldset">
                <legend class="legend">
                    {{ __('care-plan.program_selection') }}
                </legend>

                <div class="form-row-3">
                    <div class="form-group group">
                        <label for="medication_program" class="label">
                            {{ __('care-plan.program') }}*
                        </label>
                        <select id="medication_program"
                                name="medication_program"
                                class="input-select peer"
                        >
                            <option selected value="">{{ __('care-plan.prescription_medication') }}</option>
                        </select>
                    </div>
                </div>
            </fieldset>

            <div class="mt-6 flex justify-start gap-3">
                <button type="button"
                        class="button-minor"
                        aria-controls=""
                        @click="showMedicationDrawer = false"
                >
                    {{ __('forms.cancel') }}
                </button>

                <button type="button"
                        class="button-primary"
                        aria-controls="medication-search-drawer-right"
                        @click="showMedicationSearchDrawer = true"
                >
                    {{ __('forms.continue') }}
                </button>
            </div>
        </form>
    </div>
    </div>
</template>
