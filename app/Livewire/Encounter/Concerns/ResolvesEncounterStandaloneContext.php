<?php

declare(strict_types=1);

namespace App\Livewire\Encounter\Concerns;

use App\Models\MedicalEvents\Sql\Encounter;

trait ResolvesEncounterStandaloneContext
{
    protected function resolveEncounterModelForStandalone(): ?Encounter
    {
        if (!isset($this->encounterId)) {
            $this->dispatch('flashMessage', [
                'type' => 'error',
                'message' => 'Взаємодію не знайдено.',
            ]);

            return null;
        }

        $encounter = Encounter::query()->with('episode')->find($this->encounterId);
        if ($encounter === null) {
            $this->dispatch('flashMessage', [
                'type' => 'error',
                'message' => 'Взаємодію не знайдено.',
            ]);

            return null;
        }

        return $encounter;
    }
}
