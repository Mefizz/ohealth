@use('App\Livewire\Composition\CompositionTempDisabilityCreate', 'Wizard')
@use('App\Enums\Person\CompositionCategory')

{{-- Single root required by Livewire; patient layout is a <section>, safe to nest. --}}
<div>
    @if ($embedded)
        <div class="w-full">
            @include('livewire.composition.parts.temp-disability-create-body')
        </div>
    @else
        <x-layouts.patient
            :personId="$personId"
            :prepersonId="$prepersonId"
            :patientFullName="$patientFullName"
            :title="__('compositions.create_temp_disability.title')"
        >
            @include('livewire.composition.parts.temp-disability-create-body')
        </x-layouts.patient>
    @endif
</div>
