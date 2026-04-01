<section class="section-form">
    <x-header-navigation class="breadcrumb-form">
        <x-slot name="title">
            {{ __('patients.interaction') }} - {{ $patientFullName }}
        </x-slot>
    </x-header-navigation>

    <form class="form">
        @include('livewire.encounter.parts.aside-navigation')
        @include('livewire.encounter.parts.main-data')
        @include('livewire.encounter.parts.reasons')
        @include('livewire.encounter.parts.conditions')
        @include('livewire.encounter.parts.actions')
        @include('livewire.encounter.parts.additional-data')
        @include('livewire.encounter.parts.immunizations')
        @include('livewire.encounter.parts.diagnostic-reports')
        @include('livewire.encounter.parts.observations')
        @include('livewire.encounter.parts.procedures')
        @include('livewire.encounter.parts.clinical-impressions')

        <div class="flex gap-8">
            <button wire:click.prevent="" type="submit" class="button-minor">
                {{ __('forms.delete') }}
            </button>

            <button wire:click.prevent="save" type="submit" class="button-primary">
                {{ __('forms.save') }}
            </button>

            <button type="submit" @click="$wire.showSignatureModal = true" class="button-primary">
                {{ __('forms.save_and_send') }}
            </button>
        </div>

        <x-signature-modal method="sign" />
    </form>

    <livewire:components.x-message :key="time()" />
    <x-forms.loading />
</section>
