<x-layouts.patient :id="$id" :patientFullName="$patientFullName">
    <x-slot name="headerActions">
        @can('create', \App\Models\MedicalEvents\Sql\CarePlan::class)
            <a href="{{ route('care-plan.create', [legalEntity(), 'patientId' => $id]) }}"
               class="flex items-center gap-2 button-primary px-5 py-2 text-sm shadow-sm"
            >
                @icon('plus', 'w-4 h-4')
                {{ __('care-plan.new_care_plan') }}
            </a>
        @endcan

        <button type="button"
                class="button-primary-outline whitespace-nowrap px-5 py-2 text-sm"
        >
            {{ __('patients.data_access') }}
        </button>

        <button wire:click.prevent="$refresh"
                type="button"
                class="button-sync flex items-center gap-2 whitespace-nowrap px-5 py-2 text-sm shadow-sm"
        >
            @icon('refresh', 'w-4 h-4')
            {{ __('patients.sync_ehealth_data') }}
        </button>
    </x-slot>

    <div class="breadcrumb-form p-4 shift-content">
        <div class="w-full mt-6" x-data="{ showAdditionalParams: $wire.entangle('showAdditionalParams') }">
            <div class="mb-4 flex items-center gap-1 font-semibold text-gray-900 dark:text-gray-100">
                @icon('search-outline', 'w-4.5 h-4.5')
                <p>Пошук плану лікування</p>
            </div>

            <div class="form-row-3 mb-6">
                <div class="form-group group relative">
                    <input wire:model="filterName"
                           type="text"
                           name="filterName"
                           id="filterName"
                           class="input peer w-full"
                           placeholder=" "
                           autocomplete="off"
                    />
                    <label for="filterName" class="label">Назва</label>
                    <button type="button" wire:click="$set('filterName', '')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            x-show="$wire.filterName">
                        @icon('close', 'w-4 h-4')
                    </button>
                </div>

                <div class="form-group group relative">
                    <input wire:model="filterEncounterId"
                           type="text"
                           name="filterEncounterId"
                           id="filterEncounterId"
                           class="input peer w-full"
                           placeholder=" "
                           autocomplete="off"
                    />
                    <label for="filterEncounterId" class="label">ID взаємодії</label>
                    <button type="button" wire:click="$set('filterEncounterId', '')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            x-show="$wire.filterEncounterId">
                        @icon('close', 'w-4 h-4')
                    </button>
                </div>

                <div class="form-group group">
                    <select wire:model="filterStatus"
                            name="filterStatus"
                            id="filterStatus"
                            class="input-select peer w-full"
                    >
                        <option value="">{{ __('forms.select') }} ...</option>
                        <option value="active">Активний</option>
                        <option value="completed">Завершений</option>
                        <option value="cancelled">Скасований</option>
                    </select>
                    <label for="filterStatus" class="label">Статус</label>
                </div>
            </div>

            <div class="mb-9 flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="search" class="flex items-center gap-2 button-primary px-5 py-2.5 text-sm shadow-sm">
                        @icon('search', 'w-4 h-4')
                        <span>{{ __('patients.search_button') }}</span>
                    </button>
                    <button type="button" wire:click="resetFilters" class="button-primary-outline-red px-5 py-2.5 text-sm">
                        {{ __('patients.reset_filters') }}
                    </button>
                    <button type="button"
                            class="flex items-center gap-2 button-minor px-5 py-2.5 text-sm whitespace-nowrap"
                            @click.prevent="showAdditionalParams = !showAdditionalParams"
                    >
                        @icon('adjustments', 'w-4 h-4 text-gray-500')
                        <span>Додаткові параметри пошуку</span>
                    </button>
                </div>

                <div class="relative" x-data="{ openGroupActions: false }" @click.outside="openGroupActions = false">
                    <button type="button" @click="openGroupActions = !openGroupActions" class="button-primary-outline px-5 py-2.5 text-sm">
                        {{ __('patients.group_actions') }}
                    </button>

                    <div x-show="openGroupActions" x-transition x-cloak class="absolute right-0 top-full mt-2 z-10 w-[240px] bg-white rounded-lg shadow-lg border border-gray-200 dark:bg-gray-700 dark:border-gray-600 overflow-hidden">
                        <div class="py-1">
                            <button type="button" @click="openGroupActions = false" class="dropdown-button !flex items-center gap-2.5 w-full px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors text-left">
                                <span class="text-gray-500">@icon('close', 'w-4 h-4')</span>
                                {{ __('patients.revoke_access') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="showAdditionalParams" x-transition x-cloak wire:key="care-plans-search-filters">
                <div class="form-row-3 mb-6">
                    <div class="form-group group">
                        <div class="datepicker-wrapper">
                            <input wire:model="filterStartDateRange" type="text" name="filterStartDateRange" id="filterStartDateRange" class="datepicker-input with-leading-icon input peer w-full" placeholder=" " autocomplete="off" />
                            <label for="filterStartDateRange" class="wrapped-label">Дата початку від - до</label>
                        </div>
                    </div>
                    <div class="form-group group">
                        <div class="datepicker-wrapper">
                            <input wire:model="filterEndDateRange" type="text" name="filterEndDateRange" id="filterEndDateRange" class="datepicker-input with-leading-icon input peer w-full" placeholder=" " autocomplete="off" />
                            <label for="filterEndDateRange" class="wrapped-label">Дата завершення від - до</label>
                        </div>
                    </div>
                </div>

                <div class="form-row-3 mb-9">
                    <div class="form-group group relative">
                        <input wire:model="filterIsPartOf" type="text" name="filterIsPartOf" id="filterIsPartOf" class="input peer w-full" placeholder=" " autocomplete="off" />
                        <label for="filterIsPartOf" class="label">Є частиною плана лікування</label>
                        <button type="button" wire:click="$set('filterIsPartOf', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" x-show="$wire.filterIsPartOf">
                            @icon('close', 'w-4 h-4')
                        </button>
                    </div>

                    <div class="form-group group relative">
                        <input wire:model="filterIncludes" type="text" name="filterIncludes" id="filterIncludes" class="input peer w-full" placeholder=" " autocomplete="off" />
                        <label for="filterIncludes" class="label">Включає в себе план лікування</label>
                        <button type="button" wire:click="$set('filterIncludes', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" x-show="$wire.filterIncludes">
                            @icon('close', 'w-4 h-4')
                        </button>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                @foreach($carePlans as $plan)
                <div class="record-inner-card" wire:key="care-plan-{{ $plan->id }}">
                    <div class="record-inner-header">
                        <div class="record-inner-checkbox-col">
                            <input type="checkbox" class="default-checkbox w-5 h-5">
                        </div>

                        <div class="record-inner-column flex-1">
                            <div class="record-inner-label">Назва</div>
                            <div class="record-inner-value text-[16px] font-semibold dark:text-gray-100">
                                {{ $plan->title }}
                            </div>
                        </div>

                        <div class="record-inner-column-bordered w-full md:w-36 shrink-0 h-full flex flex-col justify-center gap-1">
                            <div class="record-inner-label">Статус</div>
                            <div>
                                <span class="badge-green">
                                    {{ $plan->status_display }}
                                </span>
                            </div>
                        </div>

                        <div class="record-inner-action-col">
                            <div class="flex justify-center relative">
                                <div x-data="{
                                         open: false,
                                         toggle() {
                                             if (this.open) { return this.close(); }
                                             this.$refs.button.focus();
                                             this.open = true;
                                         },
                                         close(focusAfter) {
                                             if (!this.open) return;
                                             this.open = false;
                                             focusAfter && focusAfter.focus()
                                         }
                                     }"
                                     @keydown.escape.prevent.stop="close($refs.button)"
                                     @focusin.window="!$refs.panel.contains($event.target) && close()"
                                     x-id="['dropdown-button']"
                                     class="relative"
                                >
                                    <button @click="toggle()"
                                            x-ref="button"
                                            :aria-expanded="open"
                                            :aria-controls="$id('dropdown-button')"
                                            type="button"
                                            class="record-inner-action-btn"
                                    >
                                        @icon('edit-user-outline', 'w-6 h-6 text-gray-700 dark:text-gray-300')
                                    </button>

                                    <div x-show="open"
                                         x-cloak
                                         x-ref="panel"
                                         x-transition.origin.top.right
                                         @click.outside="close($refs.button)"
                                         :id="$id('dropdown-button')"
                                         class="absolute right-0 mt-2 w-56 rounded-md bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 shadow-lg z-50 py-1"
                                    >
                                        <button @click="close($refs.button)"
                                                class="flex items-center gap-2 w-full px-4 py-2.5 text-left text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                                        >
                                            @icon('eye', 'w-5 h-5 text-gray-500')
                                            {{ __('patients.view_details') }}
                                        </button>

                                        <button @click="close($refs.button)"
                                                class="flex items-center gap-2 w-full px-4 py-2.5 text-left text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                                        >
                                            @icon('alert-circle', 'w-5 h-5 text-gray-500')
                                            {{ __('patients.status.entered_in_error') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="record-inner-body">
                        <div class="record-inner-grid-container">
                            <div class="grid grid-cols-2 xl:grid-cols-6 gap-y-4 gap-x-4 w-full [&>div]:min-w-0 [&_.record-inner-value]:break-words">
                                <div>
                                    <div class="record-inner-label">Створено</div>
                                    <div class="record-inner-value">{{ $plan->created_at->format('d.m.Y') }}</div>
                                </div>

                                <div>
                                    <div class="record-inner-label">Початок</div>
                                    <div class="record-inner-value">{{ $plan->period_start->format('d.m.Y') }}</div>
                                </div>

                                <div>
                                    <div class="record-inner-label">Кінець</div>
                                    <div class="record-inner-value">{{ $plan->period_end ? $plan->period_end->format('d.m.Y') : '' }}</div>
                                </div>

                                <div>
                                    <div class="record-inner-label">Лікар</div>
                                    <div class="record-inner-value">{{ $plan->author->party->full_name ?? '' }}</div>
                                </div>

                                <div>
                                    <div class="record-inner-label">Умови надання медичної допомоги</div>
                                    <div class="record-inner-value">{{ $plan->care_provision_conditions }}</div>
                                </div>

                                <div>
                                    <div class="record-inner-label">Медичний стан/діагноз</div>
                                    <div class="record-inner-value">{{ $plan->medical_condition }}</div>
                                </div>

                                <div>
                                    <div class="record-inner-label">Розширений опис</div>
                                    <div class="record-inner-value">{{ $plan->extended_description }}</div>
                                </div>

                                <div>
                                    <div class="record-inner-label">Допоміжна інформація</div>
                                    <div class="record-inner-value">{{ $plan->additional_info }}</div>
                                </div>

                                <div>
                                    <div class="record-inner-label">Нотатки</div>
                                    <div class="record-inner-value">{{ $plan->notes }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="record-inner-id-col">
                            <div class="min-w-0">
                                <div class="record-inner-label">ID ECO3</div>
                                <div class="record-inner-id-value">{{ $plan->ehealth_id }}</div>
                            </div>
                            <div class="min-w-0">
                                <div class="record-inner-label">ID Епізоду</div>
                                <div class="record-inner-id-value">{{ $plan->episode_id }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <x-forms.loading />
</x-layouts.patient>
