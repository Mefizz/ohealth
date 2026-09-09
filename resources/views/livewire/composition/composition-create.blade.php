@use('App\Livewire\Composition\CompositionCreate', 'Wizard')

{{-- Single root required by Livewire; patient layout is a <section>, safe to nest. --}}
<div>
    @if ($embedded)
        <div class="w-full">
            @include('livewire.composition.parts.newborn-create-body')
        </div>
    @else
        <x-layouts.patient
            :personId="$personId"
            :prepersonId="$prepersonId"
            :patientFullName="$patientFullName"
            :title="__('compositions.create_newborn.title')"
        >
            @include('livewire.composition.parts.newborn-create-body')
        </x-layouts.patient>
    @endif
</div>
