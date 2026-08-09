<x-layouts.patient :personId="$personId" :patientFullName="$patientFullName">
    <x-slot name="headerActions">
        <button
            wire:click.prevent="applyFilters"
            type="button"
            class="button-primary flex items-center gap-2 px-5 py-2 text-sm shadow-sm"
        >
            @icon('search-outline', 'w-4 h-4')
            Пошук
        </button>
        <button
            wire:click.prevent="resetFilters"
            type="button"
            class="button-primary-outline px-5 py-2 text-sm whitespace-nowrap"
        >
            Скинути фільтри
        </button>
    </x-slot>

    <div class="breadcrumb-form shift-content p-4">
        <div class="mt-6 w-full">
            <div class="mb-4 flex items-center gap-1 font-semibold text-gray-900 dark:text-gray-100">
                @icon('search-outline', 'w-4.5 h-4.5')
                <p>Реєстр е-рецептів пацієнта (ТВ 3.9.4.1)</p>
            </div>

            <div class="form-row-3 mb-6">
                <div class="form-group group">
                    <label class="label">Статус</label>
                    <select wire:model="filterStatus" class="input-select peer w-full">
                        <option value="">Усі</option>
                        <option value="NEW">NEW (заявка)</option>
                        <option value="draft">draft</option>
                        <option value="active">ACTIVE</option>
                        <option value="completed">COMPLETED</option>
                        <option value="rejected">REJECTED</option>
                        <option value="entered-in-error">entered-in-error</option>
                    </select>
                </div>
                <div class="form-group group">
                    <label class="label">Початок курсу з</label>
                    <input type="date" class="input peer" wire:model="filterStartedAtFrom" />
                </div>
                <div class="form-group group">
                    <label class="label">Початок курсу по</label>
                    <input type="date" class="input peer" wire:model="filterStartedAtTo" />
                </div>
            </div>

            <div class="form-row-3 mb-8">
                <div class="form-group group">
                    <label class="label">Кінець курсу з</label>
                    <input type="date" class="input peer" wire:model="filterEndedAtFrom" />
                </div>
                <div class="form-group group">
                    <label class="label">Кінець курсу по</label>
                    <input type="date" class="input peer" wire:model="filterEndedAtTo" />
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">№ / UUID</th>
                            <th class="px-4 py-3 text-left font-medium">Статус</th>
                            <th class="px-4 py-3 text-left font-medium">ЛЗ</th>
                            <th class="px-4 py-3 text-left font-medium">Кількість</th>
                            <th class="px-4 py-3 text-left font-medium">Період</th>
                            <th class="px-4 py-3 text-left font-medium">Основа</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($medicationRequests as $request)
                            <tr wire:key="mr-{{ $request['id'] ?? $request['uuid'] }}">
                                <td class="px-4 py-3 font-mono text-xs">
                                    {{ $request['request_number'] ?? $request['uuid'] ?? '—' }}
                                </td>
                                <td class="px-4 py-3">{{ $request['status'] ?? '—' }}</td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $request['medication_id'] ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $request['medication_qty'] ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    {{ isset($request['started_at']) ? \Illuminate\Support\Carbon::parse($request['started_at'])->format('d.m.Y') : '—' }} — {{ isset($request['ended_at']) ? \Illuminate\Support\Carbon::parse($request['ended_at'])->format('d.m.Y') : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    @if (!empty($request['based_on_id']))
                                        ПЛ activity #{{ $request['based_on_id'] }}
                                    @elseif (!empty($request['context_id']))
                                        Encounter #{{ $request['context_id'] }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    Рецептів за обраними фільтрами не знайдено.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.patient>
