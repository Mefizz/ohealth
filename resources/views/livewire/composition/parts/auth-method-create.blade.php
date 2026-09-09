{{-- Create OTP / OFFLINE when the patient has no usable authentication methods (TV 3.8.1.4.1 / 3.8.2.4.1). --}}
@if (empty($authMethods) && filled($this->authenticationSubjectUuid()))
    <div class="status-alert-yellow mb-4 flex-col items-start gap-3">
        <p class="text-sm font-medium">{{ __('compositions.auth.create_required') }}</p>

        <div class="flex w-full flex-col gap-3 md:flex-row md:items-end">
            <div class="form-group group min-w-0 flex-1">
                <input
                    wire:model="newOtpPhone"
                    type="text"
                    id="composition-new-otp-phone"
                    class="input peer w-full"
                    placeholder=" "
                    autocomplete="off"
                />
                <label for="composition-new-otp-phone" class="label"> {{ __('compositions.auth.otp_phone') }} </label>
                @error('newOtpPhone')
                    <p class="text-error">{{ $message }}</p>
                @enderror
            </div>
            <button type="button" wire:click="createOtpAuthMethod" class="button-primary px-5 py-2.5 text-sm">
                {{ __('compositions.auth.create_otp') }}
            </button>
        </div>

        @if ($pendingAuthMethodRequestId)
            <div class="flex w-full flex-col gap-3 md:flex-row md:items-end">
                <div class="form-group group min-w-0 flex-1">
                    <input
                        wire:model="authMethodVerificationCode"
                        type="text"
                        id="composition-otp-code"
                        class="input peer w-full"
                        placeholder=" "
                        autocomplete="off"
                    />
                    <label for="composition-otp-code" class="label"> {{ __('compositions.auth.otp_code') }} </label>
                    @error('authMethodVerificationCode')
                        <p class="text-error">{{ $message }}</p>
                    @enderror
                </div>
                <button type="button" wire:click="confirmOtpAuthMethod" class="button-primary px-5 py-2.5 text-sm">
                    {{ __('compositions.auth.confirm_otp') }}
                </button>
            </div>
        @endif

        <div class="flex flex-wrap gap-2">
            @if ($this->canCreateOfflineAuthMethod)
                <button
                    type="button"
                    wire:click="createOfflineAuthMethod"
                    class="button-primary-outline px-5 py-2.5 text-sm"
                >
                    {{ __('compositions.auth.create_offline') }}
                </button>
            @endif
            @if ($this->patientAuthManagementUrl)
                <a
                    href="{{ $this->patientAuthManagementUrl }}"
                    class="button-minor px-5 py-2.5 text-sm"
                    target="_blank"
                >
                    {{ __('compositions.auth.manage_on_card') }}
                </a>
            @endif
        </div>
    </div>
@endif
