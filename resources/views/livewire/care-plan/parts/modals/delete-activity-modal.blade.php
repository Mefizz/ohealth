<div x-data="{ show: false }"
     x-effect="show = $wire.showDeleteActivityModal">

    <template x-teleport="body">
        <div x-show="show"
             class="fixed inset-0 z-50 overflow-y-auto"
             role="dialog"
             aria-modal="true"
             style="display: none;"
             @keydown.escape.prevent.stop="$wire.closeDeleteActivityModal()">

            <div x-show="show"
                 x-transition.opacity
                 class="fixed inset-0 bg-black/30"></div>

            <div x-show="show"
                 x-transition
                 @click="$wire.closeDeleteActivityModal()"
                 class="relative flex min-h-screen items-center justify-center p-4">

                <div @click.stop
                     x-trap.noscroll.inert="show"
                     class="relative w-full max-w-md overflow-hidden rounded-lg bg-white p-6 text-center shadow-lg border border-gray-200 dark:border-gray-700 dark:bg-gray-800">

                    @if($showDeleteActivityModal)
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                            Видалити чернетку призначення?
                        </h3>

                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            Ви дійсно бажаєте видалити призначення <span class="font-bold">"{{ $activityToDeleteName }}"</span>? Цю дію неможливо буде скасувати.
                        </p>

                        <div class="mt-6 flex justify-center gap-4">
                            <button type="button"
                                    @click="$wire.closeDeleteActivityModal()"
                                    class="button-secondary">
                                Скасувати
                            </button>

                            <button type="button"
                                    wire:click="deleteActivity"
                                    wire:loading.attr="disabled"
                                    class="inline-flex justify-center rounded-lg border border-transparent bg-red-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                                Видалити
                            </button>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </template>
</div>
