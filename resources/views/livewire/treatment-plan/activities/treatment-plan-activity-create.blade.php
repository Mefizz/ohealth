<div>
    @if(!$showModal)
        <div class="mt-4 flex gap-4">
            <button wire:click="openModal('service_request')" class="button-primary flex items-center gap-2">
                @icon('plus', 'w-4 h-4') {{ __('Нове призначення на послуги') }}
            </button>
            <button wire:click="openModal('medication_request')" class="button-primary flex items-center gap-2">
                @icon('plus', 'w-4 h-4') {{ __('Нове призначення на ліки') }}
            </button>
            <button wire:click="openModal('device_request')" class="button-primary flex items-center gap-2">
                @icon('plus', 'w-4 h-4') {{ __('Нове призначення на медичні вироби') }}
            </button>
        </div>
    @else
        <!-- Full Page Overlay / Large Panel -->
        <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8 h-full flex flex-col">
                
                <!-- Navbar/Header inside the overlay -->
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center space-x-2 text-sm text-gray-500">
                        <button wire:click="closeModal" class="hover:text-gray-900 border-b border-transparent hover:border-gray-900 pb-0.5">
                            @icon('arrow-left', 'w-4 h-4 inline') Трансліт / Пацієнт
                        </button>
                        <span>&gt;</span>
                        <span>План лікування #12345</span>
                        <span>&gt;</span>
                        <span class="text-gray-900 font-medium">Нове призначення</span>
                    </div>
                </div>

                <div class="flex items-center justify-between mb-8">
                    <h1 class="text-2xl font-bold text-gray-900">
                        @if($detail_kind === 'medication_request')
                            {{ __('Нове призначення на ліки') }}
                        @elseif($detail_kind === 'device_request')
                            {{ __('Нове призначення на медичні вироби') }}
                        @else
                            {{ __('Нове призначення на послуги') }}
                        @endif
                    </h1>
                </div>

                <form wire:submit.prevent="saveActivity" class="flex-1 space-y-6 pb-24">
                    
                    <!-- Section: Selection of Program (only for Meds/Devices) -->
                    @if(in_array($detail_kind, ['medication_request', 'device_request']))
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Вибір програми') }}</h3>
                            <div class="space-y-4">
                                <label class="block text-sm font-medium text-gray-700">{{ __('Програма') }}</label>
                                <select wire:model.live="medical_program_id" class="input-base w-full md:w-1/2">
                                    <option value="">Без програми / За власні кошти</option>
                                    <option value="prog-1">Реімбурсація ліків (Доступні ліки)</option>
                                </select>
                                
                                @if($medical_program_id == 'prog-1')
                                    <div class="mt-4 bg-gray-50 p-4 rounded-md text-sm text-gray-600 border border-gray-200">
                                        <h4 class="font-medium text-gray-900 mb-2">Програма медичних гарантій - деталі програми</h4>
                                        <ul class="list-disc pl-5 space-y-1">
                                            <li>Джерело фінансування: НСЗУ</li>
                                            <li>Тип рецептурного бланка: Ф-1</li>
                                            <li>Можливість виписувати ЕР: Так</li>
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Section: Основні дані -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2 flex items-center before:content-[''] before:w-1 before:h-5 before:bg-blue-600 before:mr-2">{{ __('Основні дані') }}</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-6">
                            @if($detail_kind === 'service_request')
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Категорія') }} <span class="text-red-500">*</span></label>
                                    <select wire:model="category" class="input-base w-full">
                                        <option value="">Оберіть категорію</option>
                                        <option value="consultation">Консультація спеціаліста</option>
                                        <option value="procedure">Процедури</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Код послуги') }} <span class="text-red-500">*</span></label>
                                    <!-- Search input mock -->
                                    <div class="relative">
                                        <input type="text" wire:model="code" class="input-base w-full pl-10" placeholder="Пошук послуги в довіднику...">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            @icon('magnifying-glass', 'h-5 w-5 text-gray-400')
                                        </div>
                                    </div>
                                    @error('code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                            @else
                                <div class="col-span-1 md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ $detail_kind === 'medication_request' ? __('Лікарський засіб (МНН)') : __('Медичний виріб') }} <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input type="text" wire:model="code" class="input-base w-full pl-10" placeholder="Пошук в довіднику...">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            @icon('magnifying-glass', 'h-5 w-5 text-gray-400')
                                        </div>
                                    </div>
                                    @error('code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                            @endif

                            <div class="flex gap-4 items-end">
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Кількість') }} <span class="text-red-500">*</span></label>
                                    <input type="number" wire:model="quantity_value" class="input-base w-full">
                                    @error('quantity_value') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Одиниці') }}</label>
                                    <select wire:model="quantity_code" class="input-base w-full">
                                        <option value="шт">шт (штуки)</option>
                                        <option value="упаковки">упаковки</option>
                                        <option value="дози">дози</option>
                                        <option value="послуга">послуги</option>
                                    </select>
                                </div>
                            </div>

                            @if($detail_kind === 'medication_request')
                                <div class="flex gap-4 items-end">
                                    <div class="flex-1">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Разова доза') }}</label>
                                        <input type="text" wire:model="dose_value" class="input-base w-full" placeholder="Напр. 1 таб">
                                    </div>
                                    <div class="flex-1">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Частота прийому') }}</label>
                                        <select wire:model="frequency" class="input-base w-full">
                                            <option value="">Оберіть...</option>
                                            <option value="BID">2 рази на день</option>
                                            <option value="TID">3 рази на день</option>
                                            <option value="QD">1 раз на день</option>
                                        </select>
                                    </div>
                                </div>
                            @endif

                            <div class="col-span-1 md:col-span-2 pt-4 border-t border-gray-100 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Період від') }} <span class="text-red-500">*</span></label>
                                    <input type="date" wire:model="period_start" class="input-base w-full text-gray-500">
                                    @error('period_start') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Період до') }}</label>
                                    <input type="date" wire:model="period_end" class="input-base w-full text-gray-500" placeholder="Безстроково">
                                    @error('period_end') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Підстави для призначення -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2 flex items-center before:content-[''] before:w-1 before:h-5 before:bg-blue-600 before:mr-2">{{ __('Підстави для призначення') }}</h3>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Діагноз / Стан (ICPC-2, МКХ-10)') }}</label>
                            <select wire:model="reason_condition_id" class="input-base w-full md:w-1/2">
                                <option value="">Оберіть діагноз пацієнта...</option>
                                <!-- Map active conditions here -->
                            </select>
                        </div>

                        <div class="mt-4">
                            <h4 class="text-sm font-medium text-gray-900 mb-2">{{ __('Обґрунтування підстав (Медичні записи)') }}</h4>
                            <div class="border rounded-md overflow-hidden">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Дата</th>
                                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Запис</th>
                                            <th class="px-4 py-2 text-right"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr>
                                            <td colspan="3" class="px-4 py-3 text-sm text-gray-500 italic text-center">
                                                Немає пов'язаних записів
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="mt-2 text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1 font-medium">
                                @icon('plus', 'w-4 h-4') Додати медичний запис
                            </button>
                        </div>
                    </div>

                    <!-- Section: Додаткова інформація -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2 flex items-center before:content-[''] before:w-1 before:h-5 before:bg-blue-600 before:mr-2">{{ __('Додаткова інформація') }}</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Джерело фінансування') }}</label>
                                <select wire:model="funding_source" class="input-base w-full">
                                    <option value="">За рахунок пацієнта</option>
                                    <option value="state">Державний бюджет</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Розширений опис / Коментар') }}</label>
                            <textarea wire:model="instruction" rows="3" class="input-base w-full bg-gray-50" placeholder="Текст..."></textarea>
                            @error('instruction') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Bottom Fixed Bar for Actions -->
                    <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-4 py-4 sm:px-6 flex justify-end gap-3 z-10 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none">
                            {{ __('Скасувати') }}
                        </button>
                        <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none flex items-center">
                            <span wire:loading.remove wire:target="saveActivity">{{ __('Додати призначення') }}</span>
                            <span wire:loading wire:target="saveActivity">Зберігається...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
