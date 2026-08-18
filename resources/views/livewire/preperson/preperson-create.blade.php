@use('App\Models\Preperson')

<div x-data="{ reason: $wire.entangle('form.reasonContext.reason') }">
    <livewire:components.x-message :key="time()" />

    <div>
        @include('livewire.preperson.parts.preperson-reason')
        @include('livewire.preperson.parts.preperson-personal-data')
        @include('livewire.preperson.parts.emergency-contact')
    </div>

    @can('create', Preperson::class)
        <div class="flex flex-wrap gap-4 items-center">
            <button
                type="submit"
                wire:click.prevent="createLocally"
                class="button-primary-outline flex items-center gap-2"
            >
                @icon('archive', 'w-4 h-4')
                {{ __('forms.save') }}
            </button>
            <button type="button" wire:click.prevent="create" class="button-primary">
                {{ __('forms.create') }}
            </button>
        </div>
    @endcan

    <div x-data="{ showAlternativeIdentificationModal: $wire.entangle('showAlternativeIdentificationModal') }">
        @include('livewire.preperson.modals.warning')
    </div>
</div>
