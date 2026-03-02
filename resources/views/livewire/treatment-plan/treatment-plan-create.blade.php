@use('App\Livewire\TreatmentPlan\TreatmentPlanCreate')

<section class="section-form">
    <x-header-navigation x-.data="{ showFilter: false }" class="breadcrumb-form">
        <x-slot name="title">
            {{ __('treatment-plan.new_treatment_plan') }}
        </x-slot>
    </x-header-navigation>

    <div class="form shift-content" wire:key="{{ time() }}" x-data="{ showSignatureModal: false }">

        @include('livewire.treatment-plan.parts.doctors')
        @include('livewire.treatment-plan.parts.patient_data')
        @include('livewire.treatment-plan.parts.treatment_plan_data')
        @include('livewire.treatment-plan.parts.condition_diagnosis')
        @include('livewire.treatment-plan.parts.supporting_information')
        @include('livewire.treatment-plan.parts.additional_info', ['context' => 'create'])

        <div class="mt-6 flex flex-row items-center gap-4 pt-6">
            <div class="flex items-center space-x-3">
                <a href=" " class="button-primary-outline-red">
                    {{ __('Видалити') }}
                </a>

                @if(get_class($this) === TreatmentPlanCreate::class)
                    <button type="submit"
                            class="button-primary-outline flex items-center gap-2 px-4 py-2"
                            wire:click="createLocally"
                    >
                        @icon('archive', 'w-4 h-4')
                        {{ __('forms.save') }}
                    </button>
                @endif

                <button type="button" @click="showSignatureModal = true" class="button-primary flex items-center gap-2">
                    @icon('key', 'w-5 h-5')
                    {{ __('forms.complete_the_interaction_and_sign') }}
                    @icon('arrow-right', 'w-5 h-5')
                </button>
            </div>
        </div>

        <template x-if="showSignatureModal">
            @include('livewire.treatment-plan.modals.signature')
        </template>
    </div>

    <x-messages/>
    <x-forms.loading/>
</section>
