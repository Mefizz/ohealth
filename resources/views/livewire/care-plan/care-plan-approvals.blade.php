<div>
    @if($isPolling)
        <div wire:poll.2s="checkApprovalJobStatus" class="hidden"></div>
    @endif

    {{-- Fieldset 1: List of Approvals --}}
    <fieldset class="fieldset bg-white dark:bg-gray-800 !rounded-xl !shadow-none !border-gray-100 dark:!border-gray-700 !max-w-full !p-6 !mb-6">
        <legend class="legend flex items-center justify-between">
            <span>{{ __('care-plan.access_management') }}</span>
            @if($isPolling)
                <span class="flex items-center gap-2 text-xs font-normal text-blue-600 dark:text-blue-400">
                    <svg class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    {{ __('care-plan.approval_processing') }}
                </span>
            @endif
        </legend>

        <div class="mt-4 index-table-wrapper">
            <table class="index-table w-full">
                <thead class="index-table-thead">
                    <tr>
                        <th class="index-table-th">{{ __('care-plan.granted_to') }}</th>
                        <th class="index-table-th">{{ __('forms.status.label') }}</th>
                        <th class="index-table-th">{{ __('forms.date') }}</th>
                        <th class="index-table-th text-right">{{ __('forms.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($approvals as $approval)
                        <tr class="index-table-tr">
                            <td class="index-table-td">
                                <div class="flex flex-col">
                                    <span class="font-medium text-gray-900 dark:text-white">
                                        {{ $approval['grantedToDetails']['name'] ?? $approval['granted_to_details']['name'] ?? '-' }}
                                    </span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $approval['grantedToDetails']['description'] ?? $approval['granted_to_details']['description'] ?? '' }}
                                    </span>
                                </div>
                            </td>
                            <td class="index-table-td">
                                <span class="badge {{ ($approval['status'] ?? '') === 'active' ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $approval['status'] ?? 'unknown' }}
                                </span>
                            </td>
                            <td class="index-table-td">
                                {{ isset($approval['createdAt']) || isset($approval['created_at']) ? \Carbon\Carbon::parse($approval['createdAt'] ?? $approval['created_at'])->format('d.m.Y H:i') : '-' }}
                            </td>
                            <td class="index-table-td-actions text-right">
                                @if(($approval['status'] ?? '') === 'active')
                                    <button type="button"
                                            wire:click="cancelApproval('{{ $approval['uuid'] }}')"
                                            wire:confirm="{{ __('care-plan.confirm_cancel_approval') }}"
                                            class="text-red-500 hover:text-red-700 p-1">
                                        @icon('close-outline', 'w-4 h-4')
                                    </button>
                                @elseif(in_array(($approval['status'] ?? ''), ['pending', 'NEW']))
                                    <button type="button"
                                            wire:click="verifyExistingApproval('{{ $approval['uuid'] }}')"
                                            class="button-primary text-xs py-1 px-3">
                                        {{ __('forms.confirm') }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="index-table-td !py-6 text-center text-gray-400">
                                {{ __('care-plan.no_approvals_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </fieldset>

    {{-- Fieldset 2: Create New Approval Form --}}
    <fieldset class="fieldset bg-white dark:bg-gray-800 !rounded-xl !shadow-none !border-gray-100 dark:!border-gray-700 !max-w-full !p-6 !mb-6">
        <legend class="legend">
            {{ __('care-plan.grant_access') }}
        </legend>

        <div class="mt-4">
            @if(empty($carePlanUuid))
                <div class="p-4 text-sm text-yellow-800 rounded-lg bg-yellow-50 dark:bg-gray-700/50 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-900/30" role="alert">
                    {{ __('care-plan.cannot_grant_unregistered') }}
                </div>
            @elseif($isPolling)
                <div class="p-4 text-sm text-blue-800 rounded-lg bg-blue-50 dark:bg-gray-700/50 dark:text-blue-300 border border-blue-200 dark:border-blue-900/30" role="alert">
                    <div class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                        {{ __('care-plan.approval_processing') }}
                    </div>
                </div>
            @else
                @if($errorMessage)
                    <div class="mb-4 p-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-700/50 dark:text-red-400 border border-red-200 dark:border-red-900/30" role="alert">
                        {{ $errorMessage }}
                    </div>
                @endif
                @if(session()->has('error'))
                    <div class="mb-4 p-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-700/50 dark:text-red-400 border border-red-200 dark:border-red-900/30" role="alert">
                        {{ session('error') }}
                    </div>
                @endif

                <form wire:submit.prevent="createApproval" class="form">
                    <div class="form-row-2">
                        <div class="form-group group">
                            @if(empty($employees))
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('care-plan.no_employees_found') }}
                                </p>
                            @else
                                <select
                                    class="input-select peer"
                                    id="employee_uuid"
                                    wire:model.live="newApproval.employee_uuid"
                                >
                                    <option value="">{{ __('care-plan.select_employee') }}</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee['uuid'] }}">
                                            {{ $employee['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <label for="employee_uuid" class="label">
                                    {{ __('care-plan.employee') }} *
                                </label>
                                @error('newApproval.employee_uuid')
                                    <p class="text-error text-xs mt-1">{{ $message }}</p>
                                @enderror
                            @endif
                        </div>

                        @if(!empty($authMethods))
                            <div class="form-group group">
                                <select class="input-select peer" wire:model="selectedAuthMethodUuid" id="selectedAuthMethodUuid">
                                    <option value="">{{ __('care-plan.choose_auth_method') }}</option>
                                    @foreach($authMethods as $method)
                                        <option value="{{ $method['id'] ?? $method['uuid'] }}">
                                            @if(($method['type'] ?? '') === 'OTP')
                                                SMS ({{ $method['phone_number'] ?? '' }})
                                            @elseif(($method['type'] ?? '') === 'OFFLINE')
                                                {{ __('care-plan.offline_paper') }}
                                            @else
                                                {{ $method['type'] ?? __('care-plan.other') }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                <label for="selectedAuthMethodUuid" class="label">
                                    {{ __('care-plan.auth_method') }} *
                                </label>
                            </div>
                        @endif
                    </div>

                    <div class="mt-4 flex justify-end">
                        <button type="submit" class="button-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove>{{ __('care-plan.grant_access_btn') }}</span>
                            <span wire:loading>{{ __('forms.loading') }}</span>
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </fieldset>

    @include('livewire.care-plan.modals.authentication')
</div>
