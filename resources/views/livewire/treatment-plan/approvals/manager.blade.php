<div>
    <div class="card p-6 shadow-sm mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900">{{ __('Доступи до Плану лікування') }}</h3>
            
            @if(count($approvals) === 0)
                <button wire:click="requestAccess" 
                        wire:loading.attr="disabled"
                        class="button-primary flex items-center gap-2">
                    <span wire:loading.remove wire:target="requestAccess">@icon('plus', 'w-4 h-4')</span>
                    <span wire:loading wire:target="requestAccess">...</span>
                    {{ __('Запитати доступ (Write)') }}
                </button>
            @endif
        </div>

        @if(count($approvals) > 0)
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        @icon('check-circle', 'h-5 w-5 text-green-400')
                    </div>
                    <div class="ml-3 flex-1">
                        <h3 class="text-sm font-medium text-green-800">
                            {{ __('Доступ відкрито') }}
                        </h3>
                        <div class="mt-2 text-sm text-green-700">
                            <ul class="list-disc pl-5 space-y-1">
                                @foreach($approvals as $approval)
                                    <li>ID: {{ $approval['id'] ?? 'unknown' }} (Level: {{ $approval['access_level'] ?? 'read' }}) 
                                        <button wire:click="cancelApproval('{{ $approval['id'] }}')" 
                                                class="ml-2 text-red-600 hover:text-red-800 underline text-xs">
                                            {{ __('Скасувати') }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="text-sm text-gray-500 italic">
                {{ __('Ви ще не маєте доступу до цього плану лікування або він не підтверджений пацієнтом.') }}
            </div>
        @endif
    </div>

    <!-- SMS Modal Overlay -->
    @if($showSmsModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="$set('showSmsModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                @icon('device-mobile', 'h-6 w-6 text-blue-600')
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    {{ __('Підтвердження через SMS') }}
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 mb-4">
                                        {{ __('Введіть код з SMS, яке було надіслано на номер пацієнта.') }}
                                    </p>
                                    <input type="text" wire:model="smsCode" class="input-base w-full" placeholder="1234">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" wire:click="confirmSms" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            {{ __('Підтвердити') }}
                        </button>
                        <button type="button" wire:click="$set('showSmsModal', false)" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            {{ __('Скасувати') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
