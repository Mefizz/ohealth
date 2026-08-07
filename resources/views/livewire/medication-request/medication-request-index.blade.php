<div>
    <livewire:components.x-message :key="now()->timestamp" />
    <x-forms.loading />

    <x-header-navigation class="items-start">
        <x-slot name="title">
            Е-Рецепти
        </x-slot>

        <div class="mt-3 ml-0 flex flex-col sm:flex-row sm:flex-wrap gap-2 self-start">
            <button
                type="button"
                data-modal-target="create-mr-modal"
                data-modal-toggle="create-mr-modal"
                class="button-primary flex items-center gap-2"
            >
                @icon('plus', 'w-4 h-4')
                <span>Створити рецепт</span>
            </button>
        </div>
    </x-header-navigation>

    <div class="flow-root mt-8 shift-content pl-3.5">
        <div class="max-w-screen-xl">
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 p-6 dark:bg-gray-800">
                <div class="w-full mb-4">
                    <p class="text-sm font-normal text-gray-500 dark:text-gray-400">
                        Тут буде відображатися список виписаних рецептів для пацієнтів.
                    </p>
                </div>

                <!-- Placeholder for table/list -->
                <div class="p-4 text-sm text-gray-700 bg-gray-100 rounded-lg dark:bg-gray-700 dark:text-gray-300">
                    Список порожній.
                </div>
            </div>
        </div>
    </div>

    <!-- Create Modal (Includes MedicationRequestForm) -->
    <div
        id="create-mr-modal"
        tabindex="-1"
        aria-hidden="true"
        class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-modal md:h-full"
    >
        <div class="relative p-4 w-full max-w-4xl h-full md:h-auto">
            <div class="relative bg-white rounded-lg shadow dark:bg-gray-800">
                <div class="flex justify-between items-start p-4 rounded-t border-b dark:border-gray-600">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                        Виписати новий е-Рецепт
                    </h3>
                    <button
                        type="button"
                        class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center dark:hover:bg-gray-600 dark:hover:text-white"
                        data-modal-hide="create-mr-modal"
                    >
                        @icon('close')
                    </button>
                </div>
                <div class="p-6 space-y-6">
                    @livewire('medication-request.medication-request-form', ['legalEntity' => $legalEntity])
                </div>
            </div>
        </div>
    </div>
</div>
