@if ($showEncounterCompositionDrawer)
    <div
        wire:click="closeEncounterCompositionDrawer"
        class="fixed top-0 right-0 z-[46] h-screen bg-gray-900/50 pt-20"
        style="width: calc(100% - 300px)"
    ></div>

    <div
        class="fixed top-0 right-0 z-[47] h-screen overflow-y-auto bg-gray-50 p-8 pt-20 shadow-2xl dark:bg-gray-900"
        style="width: calc(100% - 300px)"
        tabindex="-1"
    >
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    @if ($encounterCompositionKind === 'newborn')
                        {{ __('compositions.create_newborn.title') }}
                    @else
                        {{ __('compositions.create_temp_disability.title') }}
                    @endif
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    {{ __('encounters.plural') }} · {{ $encounterUuid }}
                </p>
            </div>
            <button type="button" class="button-minor px-4 py-2 text-sm" wire:click="closeEncounterCompositionDrawer">
                {{ __('forms.close') }}
            </button>
        </div>

        <div class="mx-auto max-w-4xl" wire:key="encounter-composition-{{ $encounterCompositionDrawerKey }}">
            @if ($encounterCompositionKind === 'newborn' && $this->encounterCompositionPreperson)
                <livewire:composition.composition-create
                    :legal-entity="legalEntity()"
                    :preperson="$this->encounterCompositionPreperson"
                    :embedded="true"
                    :encounter="$encounterUuid"
                    :key="'enc-comp-nb-'.$encounterCompositionDrawerKey"
                />
            @elseif ($this->encounterCompositionPerson)
                <livewire:composition.composition-temp-disability-create
                    :legal-entity="legalEntity()"
                    :person="$this->encounterCompositionPerson"
                    :embedded="true"
                    :encounter="$encounterUuid"
                    :key="'enc-comp-td-p-'.$encounterCompositionDrawerKey"
                />
            @elseif ($this->encounterCompositionPreperson)
                <livewire:composition.composition-temp-disability-create
                    :legal-entity="legalEntity()"
                    :preperson="$this->encounterCompositionPreperson"
                    :embedded="true"
                    :encounter="$encounterUuid"
                    :key="'enc-comp-td-pp-'.$encounterCompositionDrawerKey"
                />
            @endif
        </div>
    </div>
@endif
