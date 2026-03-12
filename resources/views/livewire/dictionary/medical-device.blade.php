<div x-data="{
        selectedProgramId: '',
        get selectedProgram() {
            return this.programs.find(program => program.id === this.selectedProgramId) || null;
        },
        translateRoles(roles) {
            return roles?.map(role => this.roleLabels[role] || role).join(', ') || '-';
        },
        translateSpecialities(specialities) {
            return specialities?.map(speciality => this.dictionaries.SPECIALITY_TYPE[speciality] || speciality).join(', ') || '-';
        }
    }"
>
    <x-header-navigation x-data="{ showFilter: false }" class="breadcrumb-form">
        <x-slot name="title">
            {{ __('dictionaries.medical_device.title') }}
        </x-slot>

        <x-slot name="navigation">
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-1 font-semibold text-gray-900 dark:text-white">
                    @icon('search-outline', 'w-4.5 h-4.5')
                    <p>{{ __('dictionaries.search_title') }}</p>
                </div>

                <div class="form-row-3">
                    <div class="form-group group w-full">
                        <select id="program"
                                name="program"
                                class="peer input-select"
                                x-model="selectedProgramId"
                        >
                            <option value="" selected>{{ __('forms.select') }}</option>
                            <template x-for="program in programs" :key="program.id">
                                <option :value="program.id" x-text="program.name"></option>
                            </template>
                        </select>

                        <label for="program" class="label peer-focus:text-blue-600 peer-valid:text-blue-600">
                            {{ __('dictionaries.program_label') }}
                        </label>
                    </div>
                </div>
            </div>
        </x-slot>
    </x-header-navigation>

    <section class="shift-content pl-3.5 mt-6 max-w-[1280px]">
        <fieldset class="fieldset p-6 sm:p-8">
            <legend class="legend">
                {{ __('dictionaries.medical_device.medical_guarantees') }}
            </legend>

            <div class="space-y-2 text-gray-900 dark:text-gray-100">
                <p>
                    <span class="font-semibold">{{ __('dictionaries.medical_device.funding_source') }}:</span>
                    <span
                        x-text="selectedProgram ? (dictionaries.FUNDING_SOURCE[selectedProgram.funding_source] ?? '') : ''"
                    ></span>
                </p>
                <p>
                    <span class="font-semibold">{{ __('dictionaries.medical_device.employee_types_to_create_request') }}:</span>
                    <span
                        x-text="selectedProgram ? translateRoles(selectedProgram.medical_program_settings.employee_types_to_create_request) : ''"
                    ></span>
                </p>
                <p>
                    <span class="font-semibold">{{ __('dictionaries.medical_device.speciality_types_allowed') }}:</span>
                    <span
                        x-text="selectedProgram ? translateSpecialities(selectedProgram.medical_program_settings.speciality_types_allowed) : ''"
                    ></span>
                </p>
                <p>
                    <span class="font-semibold">{{ __('dictionaries.medical_device.skip_treatment_period') }}:</span>
                    <span
                        x-text="selectedProgram
                            ? (selectedProgram.medical_program_settings.skip_treatment_period ? '{{ __('forms.yes') }}' : '{{ __('forms.no') }}')
                            : ''"
                    ></span>
                </p>
                <p>
                    <span class="font-semibold">{{ __('dictionaries.medical_device.request_max_period_day') }}:</span>
                    <span
                        x-text="selectedProgram ? (selectedProgram.medical_program_settings.request_max_period_day ?? '') : ''"
                    ></span>
                </p>
                <p>
                    <span class="font-semibold">{{ __('dictionaries.medical_device.skip_request_employee_declaration_verify') }}:</span>
                    <span
                        x-text="selectedProgram
                            ? (selectedProgram.medical_program_settings.skip_request_employee_declaration_verify ? '{{ __('forms.yes') }}' : '{{ __('forms.no') }}')
                            : ''"
                    ></span>
                </p>
                <p>
                    <span class="font-semibold">{{ __('dictionaries.medical_device.skip_request_legal_entity_declaration_verify') }}:</span>
                    <span
                        x-text="selectedProgram
                            ? (selectedProgram.medical_program_settings.skip_request_legal_entity_declaration_verify ? '{{ __('forms.yes') }}' : '{{ __('forms.no') }}')
                            : ''"
                    ></span>
                </p>
            </div>
        </fieldset>
    </section>

    <div class="mt-8 pl-3.5 pb-8 lg:pl-8 2xl:pl-5">
        {{--{{ $device->links() }}--}}
</div>
<x-forms.loading />
<livewire:components.x-message :key="time()" />
</div>
