<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Livewire\Components\FlashMessage;

trait InteractsWithFlashMessages
{
    /** Notify the mounted toast on AJAX updates; redirects use session flash instead. */
    protected function flashOutcome(string $type, string $message): void
    {
        $this->dispatch('flashMessage', ['message' => $message, 'type' => $type])->to(FlashMessage::class);
    }
}
