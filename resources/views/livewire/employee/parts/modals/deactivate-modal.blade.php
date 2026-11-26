@use('App\Enums\Status')

<div x-data="{
        show: @entangle('showDeactivateModal')
    }"
     @keydown.escape.window="show = false"
     wire:ignore.self
     class="relative z-[9999]"
>
    <template x-teleport="body">
        <div class="relative z-[9999]">
            @if($showDeactivateModal)
                <div x-data
                     x-show="show"
                     class="fixed inset-0 z-[9999] overflow-y-auto"
                     role="dialog"
                     aria-modal="true"
                >
                    <div x-show="show"
                         x-transition.opacity
                         class="fixed inset-0 bg-black/50"></div>

                    <div x-show="show"
                         x-transition
                         @click="show = false"
                         class="relative flex min-h-screen items-center justify-center p-4"
                    >
                        <div @click.stop
                             x-trap.noscroll.inert="show"
                             class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white p-6 text-center shadow-xl border border-gray-200 dark:border-gray-700 dark:bg-gray-800"
                        >
                            @if($employeeToDeactivateName)
                                <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                                    {{ __('employees.modals.deactivate.title_with_name', ['name' => $employeeToDeactivateName]) }}
                                </h2>

                                <div role="alert" class="mt-4 p-4 mb-4 text-sm text-yellow-800 rounded-lg bg-yellow-50 dark:bg-gray-800 dark:text-yellow-300">
                                    @if($isDoctorToDeactivate ?? false)
                                        {{ __('employees.dismissal_warning_doctor') }}
                                    @else
                                        {{ __('employees.dismissal_warning') }}
                                    @endif
                                </div>

                                <div class="mt-4 text-left">
                                    <label for="deactivation-status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        {{ __('forms.status.label') }}
                                    </label>
                                    <select id="deactivation-status"
                                            wire:model.live="deactivationStatus"
                                            @error('deactivationStatus') aria-invalid="true" aria-describedby="deactivation-status-error" @enderror
                                            class="input w-full px-3 py-2 text-sm"
                                    >
                                        <option value="{{ Status::STOPPED->value }}">
                                            {{ Status::STOPPED->employmentLabel() }}
                                        </option>
                                        <option value="{{ Status::ENTERED_IN_ERROR->value }}">
                                            {{ Status::ENTERED_IN_ERROR->employmentLabel() }}
                                        </option>
                                    </select>
                                    @error('deactivationStatus')
                                        <p id="deactivation-status-error" class="text-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                @if($deactivationStatus === Status::STOPPED->value)
                                    <div class="mt-4 text-left">
                                        <label for="deactivation-end-date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            {{ __('forms.end_date') }}
                                        </label>
                                        <input type="date"
                                               id="deactivation-end-date"
                                               wire:model="deactivationEndDate"
                                               max="{{ now('Europe/Kyiv')->toDateString() }}"
                                               @error('deactivationEndDate') aria-invalid="true" aria-describedby="deactivation-end-date-error" @enderror
                                               required
                                               class="input w-full px-3 py-2 text-sm"
                                        />
                                        @error('deactivationEndDate')
                                            <p id="deactivation-end-date-error" class="text-error">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endif

                                <div class="mt-6 flex justify-center gap-4">
                                    <button type="button" wire:click="closeModal" class="button-minor">
                                        {{ __('forms.cancel') }}
                                    </button>

                                    <button type="button"
                                            wire:click="deactivate"
                                            wire:loading.attr="disabled"
                                            class="inline-flex justify-center rounded-lg border border-transparent bg-red-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                    >
                                        <span wire:loading.remove wire:target="deactivate">
                                            {{ __('forms.deactivate') }}
                                        </span>
                                        <span wire:loading wire:target="deactivate">
                                            ...
                                        </span>
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </template>
</div>
