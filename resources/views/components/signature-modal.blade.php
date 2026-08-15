@props([
    'method',
    'agreementText' => null,
    'onlyActions' => null,
    'exceptActions' => null,
])

@php
    $inputId = 'keyContainerUpload-'.Str::slug((string) $method, '-');
    $knedpId = 'knedp-'.Str::slug((string) $method, '-');
    $passwordId = 'password-'.Str::slug((string) $method, '-');
    $noFileChosen = __('forms.no_file_chosen');
@endphp

<template x-teleport="body">
    <div
        x-data="{
            showSignatureModal: $wire.entangle('showSignatureModal'),
            onlyActions: {{ Js::from($onlyActions) }},
            exceptActions: {{ Js::from($exceptActions) }},
            isKeyUploading: false,
            fileName: {{ Js::from($noFileChosen) }},
            noFileLabel: {{ Js::from($noFileChosen) }},
            isVisible() {
                if (! this.showSignatureModal) {
                    return false;
                }

                const actionType = $wire.actionType ?? null;

                if (Array.isArray(this.onlyActions) && this.onlyActions.length > 0) {
                    return this.onlyActions.includes(actionType);
                }

                if (Array.isArray(this.exceptActions) && this.exceptActions.length > 0) {
                    return ! this.exceptActions.includes(actionType);
                }

                return true;
            },
            displayFileName() {
                const stored = $wire.form?.keyContainerFileName;
                if (stored) {
                    return stored;
                }
                if (this.fileName && this.fileName !== this.noFileLabel && ! String(this.fileName).startsWith('livewire-file:')) {
                    return this.fileName;
                }
                return this.noFileLabel;
            },
            setFileNameFromInput(event) {
                const file = event.target.files?.[0];
                if (file) {
                    this.fileName = file.name;
                    $wire.set('form.keyContainerFileName', file.name);
                } else {
                    this.fileName = this.noFileLabel;
                    $wire.set('form.keyContainerFileName', '');
                }
            },
            syncFileNameFromWire() {
                const stored = $wire.form?.keyContainerFileName;
                if (stored) {
                    this.fileName = stored;
                    return;
                }
                const upload = $wire.form?.keyContainerUpload;
                if (! upload) {
                    this.fileName = this.noFileLabel;
                    return;
                }
                // Keep the previously shown client filename while Livewire holds a temp upload token.
            },
         }"
        x-effect="
            if (! showSignatureModal) {
                if ($refs.keyContainerUpload) $refs.keyContainerUpload.value = '';
                this.isKeyUploading = false;
            } else {
                this.syncFileNameFromWire();
            }
        "
        x-show="isVisible()"
        x-cloak
        role="dialog"
        aria-modal="true"
        class="modal"
        @keydown.escape.prevent.stop="showSignatureModal = false"
    >
        <div x-transition.opacity class="fixed inset-0 bg-black/30" @click="showSignatureModal = false"></div>
        <div class="modal-wrapper">
            <div
                class="modal-content mx-auto w-full max-w-4xl"
                @click.stop
                x-transition
                x-trap.noscroll.inert="isVisible()"
            >
                {{-- Title --}}
                <h3 class="modal-header">
                    @if (isset($this->actionType) && $this->actionType === 'sign_eprescription')
                        Підписання заявки електронного рецепта (КЕП)
                    @elseif (isset($this->actionType) && $this->actionType === 'sign_referral')
                        Підписання заявки електронного направлення (КЕП)
                    @elseif (isset($this->actionType) && $this->actionType === 'reject_prescription')
                        Відхилити електронний рецепт
                    @else
                        @yield('title', __('forms.sign_with_KEP'))
                    @endif
                </h3>

                {{-- Content --}}
                <div class="p-6">
                    <form onsubmit="return false;">
                        <div class="flex flex-col gap-6">
                            @hasSection('custom-fields')
                                @yield('custom-fields')
                            @elseif (isset($customFields))
                                {{ $customFields }}
                            @elseif (method_exists($this, 'getStatusReasonsProperty') && isset($this->actionType) && in_array($this->actionType, ['cancel_prescription', 'cancel_referral', 'reject_prescription']))
                                <div>
                                    <label for="statusReason" class="default-label"
                                        >{{ $this->actionType === 'reject_prescription' ? 'Причина відхилення' : __('care-plan.status_reason') }} *</label>
                                    <select
                                        class="input-modal"
                                        wire:model="statusReason"
                                        name="statusReason"
                                        id="statusReason"
                                    >
                                        <option value="" selected>{{ __('forms.select') }}</option>
                                        @foreach ($this->statusReasons as $code => $description)
                                            <option value="{{ $code }}" wire:key="reason-{{ $code }}">
                                                {{ $description }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('statusReason')
                                        <p class="text-error">{{ $message }}</p>
                                    @enderror
                                </div>
                            @elseif (isset($this->actionType) && $this->actionType === 'recall_referral')
                                <div>
                                    <label for="referralExplanatoryLetter" class="default-label"
                                        >{{ __('care-plan.referral_recall_letter') }} *</label>
                                    <textarea
                                        id="referralExplanatoryLetter"
                                        class="input-modal"
                                        rows="4"
                                        wire:model="referralExplanatoryLetter"
                                        placeholder="{{ __('care-plan.referral_recall_letter_placeholder') }}"
                                    ></textarea>
                                    @error('referralExplanatoryLetter')
                                        <p class="text-error">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif

                            @if (!empty($agreementText))
                                <div class="show-alert-warning">
                                    <p class="text-sm font-medium">{{ $agreementText }}</p>
                                </div>
                            @endif

                            {{-- Not every action eHealth exposes needs a digital signature; the
                                 caller passes requiresSignature => false for those. --}}
                            @if ($requiresSignature ?? true)
                                {{-- KEP Provider --}}
                                <div>
                                    <label for="{{ $knedpId }}" class="default-label">{{ __('forms.knedp') }} *</label>
                                    <select
                                        class="input-modal"
                                        wire:model="form.knedp"
                                        name="knedp"
                                        id="{{ $knedpId }}"
                                    >
                                        <option value="" selected>{{ __('forms.select') }}</option>
                                        @foreach (signatureService()->getCertificateAuthorities() as $certificateType)
                                            <option
                                                value="{{ $certificateType['id'] }}"
                                                wire:key="{{ $certificateType['id'] }}"
                                            >
                                                {{ $certificateType['name'] }}
                                            </option>
                                        @endforeach
                                    </select>

                                    @error('form.knedp')
                                        <p class="text-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Key File --}}
                                <div>
                                    <label for="{{ $inputId }}" class="default-label">
                                        {{ __('forms.key_container_upload') }} *
                                    </label>
                                    <div class="file-input-wrapper">
                                        <label for="{{ $inputId }}" class="file-input-button">
                                            {{ __('forms.choose_file') }}
                                        </label>
                                        <span class="file-input-text" x-text="displayFileName()"></span>
                                        <input
                                            type="file"
                                            wire:model="form.keyContainerUpload"
                                            class="hidden"
                                            id="{{ $inputId }}"
                                            name="keyContainerUpload"
                                            x-ref="keyContainerUpload"
                                            accept=".dat,.pfx,.pk8,.zs2,.jks,.p7s"
                                            @change="setFileNameFromInput($event)"
                                            x-on:livewire-upload-start="isKeyUploading = true"
                                            x-on:livewire-upload-finish="
                                                isKeyUploading = false;
                                                if ($wire.form?.keyContainerFileName) {
                                                    fileName = $wire.form.keyContainerFileName;
                                                }
                                            "
                                            x-on:livewire-upload-error="isKeyUploading = false"
                                            x-on:livewire-upload-cancel="isKeyUploading = false"
                                        />
                                    </div>
                                    <div x-show="isKeyUploading" class="mt-2 text-sm text-gray-500">
                                        {{ __('general.loading') }}...
                                    </div>

                                    @error('form.keyContainerUpload')
                                        <p class="text-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Password --}}
                                <div>
                                    <label for="{{ $passwordId }}" class="default-label"
                                        >{{ __('forms.password') }} *</label>
                                    <input
                                        type="password"
                                        wire:model="form.password"
                                        class="default-input"
                                        id="{{ $passwordId }}"
                                        name="password"
                                        autocomplete="current-password"
                                    />

                                    @error('form.password')
                                        <p class="text-error">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif
                        </div>
                    </form>

                    <div class="mt-6 flex flex-row items-center gap-4 border-t border-gray-200 pt-6">
                        <button type="button" @click="showSignatureModal = false" class="button-minor">
                            {{ __('forms.cancel') }}
                        </button>
                        <button
                            wire:click="{{ $method }}"
                            type="button"
                            class="button-primary"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-not-allowed"
                            wire:target="{{ $method }}"
                        >
                            <span wire:loading.remove wire:target="{{ $method }}">
                                {{ ($requiresSignature ?? true) ? __('forms.sign') : __('forms.confirm') }}
                            </span>
                            <span wire:loading wire:target="{{ $method }}">
                                {{ ($requiresSignature ?? true) ? __('forms.signature') : __('general.loading') }}...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
