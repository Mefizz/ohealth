<?php

declare(strict_types=1);

namespace App\Livewire\Encounter\Concerns;

use App\Models\MedicalEvents\Sql\Composition;
use App\Models\Person\Person;
use App\Models\Preperson;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

/**
 * Opens the medical-conclusion wizard as a drawer on the encounter page (same shell as
 * e-prescription / referral), so the doctor never leaves the encounter for МВТН / МВН.
 */
trait ManagesEncounterComposition
{
    public bool $showEncounterCompositionDrawer = false;

    /**
     * Which wizard to nest: `temp_disability` or `newborn`.
     */
    public string $encounterCompositionKind = 'temp_disability';

    /**
     * Remount key so each open gets a fresh nested wizard.
     */
    public int $encounterCompositionDrawerKey = 0;

    #[Computed]
    public function canOpenEncounterCompositionDrawer(): bool
    {
        return $this->resolveEncounterCompositionKind() !== null;
    }

    /**
     * Plain method for Blade — avoids Computed hydration edge cases on first paint.
     */
    public function mayOpenEncounterCompositionDrawer(): bool
    {
        return $this->resolveEncounterCompositionKind() !== null;
    }

    public function openEncounterCompositionDrawer(): void
    {
        $kind = $this->resolveEncounterCompositionKind();

        if ($kind === null) {
            Session::flash('error', __('compositions.errors.create_temp_disability_not_allowed'));

            return;
        }

        $this->encounterCompositionKind = $kind;
        $this->encounterCompositionDrawerKey++;
        $this->showEncounterCompositionDrawer = true;
    }

    #[On('composition-wizard-closed')]
    public function closeEncounterCompositionDrawer(): void
    {
        $this->showEncounterCompositionDrawer = false;
    }

    #[Computed]
    public function encounterCompositionPerson(): ?Person
    {
        if ($this->prepersonId !== null || $this->personId === null) {
            return null;
        }

        return Person::query()->find($this->personId);
    }

    #[Computed]
    public function encounterCompositionPreperson(): ?Preperson
    {
        if ($this->prepersonId === null) {
            return null;
        }

        return Preperson::query()->find($this->prepersonId);
    }

    private function resolveEncounterCompositionKind(): ?string
    {
        try {
            if ($this->prepersonId !== null && Gate::allows('createNewborn', Composition::class)) {
                return 'newborn';
            }

            if (Gate::allows('createTempDisability', Composition::class)) {
                return 'temp_disability';
            }
        } catch (\Throwable) {
            // Never let authorization probing take down the encounter page.
            return null;
        }

        return null;
    }
}
