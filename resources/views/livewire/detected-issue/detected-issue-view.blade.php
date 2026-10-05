@php
    use App\Models\MedicalEvents\Sql\DetectedIssue;

    $title = $deviceName ?? __('detected-issues.label');
    $codeCode = $detectedIssue->code?->coding->first()?->code;
    $reportOriginCode = $detectedIssue->reportOrigin?->coding->first()?->code;
    $statusReasonCode = $detectedIssue->statusReason?->coding->first()?->code;
@endphp

<div>
    <section class="section-form p-6">
        <x-header-navigation class="breadcrumb-form" title="{{ $title }}">
            <x-slot name="title">{{ $title }}</x-slot>

            <x-slot name="actions">
                @can('view', DetectedIssue::class)
                    <button
                        wire:click.prevent="sync"
                        type="button"
                        class="button-sync flex items-center gap-2 px-4 py-2 text-sm shadow-sm"
                    >
                        @icon('refresh', 'w-4 h-4')
                        <span>{{ __('forms.synchronise_with_eHealth') }}</span>
                    </button>
                @endcan
            </x-slot>
        </x-header-navigation>

        <div class="form shift-content">
            <fieldset class="fieldset">
                <legend class="legend">{{ __('forms.main_information') }}</legend>

                <div class="form-row-2">
                    <div class="form-group group">
                        <input
                            type="text"
                            name="deviceName"
                            id="deviceName"
                            class="input peer"
                            value="{{ $deviceName ?? '-' }}"
                            disabled
                        />
                        <label for="deviceName" class="label">{{ __('detected-issues.device') }}</label>
                    </div>
                    <div class="form-group group">
                        <input
                            type="text"
                            name="deviceId"
                            id="deviceId"
                            class="input peer"
                            value="{{ $detectedIssue->subject?->value ?? '-' }}"
                            disabled
                        />
                        <label for="deviceId" class="label">{{ __('detected-issues.device_id') }}</label>
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group group">
                        <input
                            type="text"
                            name="status"
                            id="status"
                            class="input peer"
                            value="{{ $dictionaries['detected_issue_statuses'][$detectedIssue->status->value] }}"
                            disabled
                        />
                        <label for="status" class="label">{{ __('detected-issues.status') }}</label>
                    </div>
                    <div class="form-group group">
                        <input
                            type="text"
                            name="code"
                            id="code"
                            class="input peer"
                            value="{{ data_get($dictionaries, 'detected_issue_codes.' . $codeCode) ?? '-' }}"
                            disabled
                        />
                        <label for="code" class="label">{{ __('detected-issues.type') }}</label>
                    </div>
                </div>

                <div class="form-row-3">
                    <div class="form-group group">
                        <div class="datepicker-wrapper">
                            <input
                                type="text"
                                name="identifiedDate"
                                id="identifiedDate"
                                class="datepicker-input with-leading-icon input peer"
                                value="{{ $detectedIssue->identifiedDate ?: '-' }}"
                                placeholder=" "
                                disabled
                            />
                            <label for="identifiedDate" class="wrapped-label">
                                {{ __('detected-issues.identified_at') }}
                            </label>
                        </div>
                    </div>
                    <div class="form-group group w-1/2!">
                        <div class="relative flex items-center">
                            @icon('mingcute-time-fill', 'svg-input left-2.5')
                            <input
                                type="text"
                                name="identifiedTime"
                                id="identifiedTime"
                                class="input peer pl-10!"
                                value="{{ $detectedIssue->identifiedTime ?: '-' }}"
                                placeholder=" "
                                disabled
                            />
                            <label for="identifiedTime" class="sr-only">{{ __('forms.time') }}</label>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group group">
                        <label for="detail" class="label-modal mb-1">{{ __('detected-issues.detail') }}</label>
                        <textarea
                            name="detail"
                            id="detail"
                            class="textarea"
                            disabled
                            rows="3"
                        >{{ $detectedIssue->detail }}</textarea>
                    </div>
                </div>

                @if ($statusReasonCode)
                    <div class="form-row-2">
                        <div class="form-group group">
                            <input
                                type="text"
                                name="statusReason"
                                id="statusReason"
                                class="input peer"
                                value="{{ $dictionaries['detected_issue_status_reasons'][$statusReasonCode] }}"
                                disabled
                            />
                            <label for="statusReason" class="label">{{ __('detected-issues.status_reason') }}</label>
                        </div>
                    </div>
                @endif

                <div class="form-row">
                    <div class="form-group group">
                        <label for="explanatoryLetter" class="label-modal mb-1">
                            {{ __('detected-issues.explanatory_letter') }}
                        </label>
                        <textarea
                            name="explanatoryLetter"
                            id="explanatoryLetter"
                            class="textarea"
                            disabled
                            rows="3"
                        >{{ $detectedIssue->explanatoryLetter }}</textarea>
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group group">
                        <input
                            type="text"
                            name="encounterId"
                            id="encounterId"
                            class="input peer"
                            value="{{ $detectedIssue->encounter?->value ?? '-' }}"
                            disabled
                        />
                        <label for="encounterId" class="label">{{ __('patients.encounter_id') }}</label>
                    </div>
                    <div class="form-group group">
                        <input
                            type="text"
                            name="detectedIssueId"
                            id="detectedIssueId"
                            class="input peer"
                            value="{{ $detectedIssue->uuid }}"
                            disabled
                        />
                        <label for="detectedIssueId" class="label">{{ __('detected-issues.id') }}</label>
                    </div>
                </div>
            </fieldset>

            <fieldset class="fieldset mt-8">
                <legend class="legend">{{ __('forms.additional_information') }}</legend>

                <div class="form-row-2">
                    <div class="form-group group">
                        <input
                            type="text"
                            name="implicatedDevice"
                            id="implicatedDevice"
                            class="input peer"
                            value="{{ $implicatedDeviceName ?? $detectedIssue->implicated?->value ?? '-' }}"
                            disabled
                        />
                        <label
                            for="implicatedDevice"
                            class="label"
                        >{{ __('detected-issues.implicated_device') }}</label>
                    </div>
                    <div class="form-group group">
                        <input
                            type="text"
                            name="basedOn"
                            id="basedOn"
                            class="input peer"
                            value="{{ $detectedIssue->basedOn?->value ?? '-' }}"
                            disabled
                        />
                        <label for="basedOn" class="label">{{ __('detected-issues.based_on') }}</label>
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group group">
                        <input
                            type="text"
                            name="recorder"
                            id="recorder"
                            class="input peer"
                            value="{{ $detectedIssue->recorder?->displayValue ?? $detectedIssue->recorder?->value ?? '-' }}"
                            disabled
                        />
                        <label for="recorder" class="label">{{ __('detected-issues.recorder') }}</label>
                    </div>
                    <div class="form-group group">
                        <input
                            type="text"
                            name="author"
                            id="author"
                            class="input peer"
                            value="{{ $detectedIssue->author?->displayValue ?? $detectedIssue->author?->value ?? '-' }}"
                            disabled
                        />
                        <label for="author" class="label">{{ __('detected-issues.author') }}</label>
                    </div>
                </div>

                <div
                    class="form-row-2 mb-4"
                    x-data="{ isOtherSource: {{ $detectedIssue->primarySource ? 'false' : 'true' }} }"
                >
                    <div class="form-group group">
                        <div class="flex items-center gap-4 pt-2">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                {{ __('devices.source_data') }}
                            </span>
                            <label for="isOtherSource" class="flex cursor-pointer items-center gap-2">
                                <input
                                    type="radio"
                                    name="isOtherSource"
                                    id="isOtherSource"
                                    :checked="isOtherSource"
                                    disabled
                                    class="default-radio"
                                />
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('devices.other_source') }}</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group group" x-show="isOtherSource">
                        <div class="relative flex-1">
                            <input
                                type="text"
                                name="reportOrigin"
                                id="reportOrigin"
                                class="input peer w-full"
                                value="{{ data_get($dictionaries, 'eHealth/report_origins.' . $reportOriginCode) ?? '-' }}"
                                disabled
                            />
                            <label for="reportOrigin" class="label">{{ __('devices.source_reference') }}</label>
                        </div>
                    </div>
                </div>

                <div class="form-row-3">
                    <div class="form-group group">
                        <div class="datepicker-wrapper">
                            <input
                                type="text"
                                name="ehealthInsertedDate"
                                id="ehealthInsertedDate"
                                class="datepicker-input with-leading-icon input peer"
                                value="{{ $detectedIssue->ehealthInsertedDate ?: '-' }}"
                                placeholder=" "
                                disabled
                            />
                            <label for="ehealthInsertedDate" class="wrapped-label">
                                {{ __('devices.created_at_system') }}
                            </label>
                        </div>
                    </div>
                    <div class="form-group group w-1/2!">
                        <div class="relative flex items-center">
                            @icon('mingcute-time-fill', 'svg-input left-2.5')
                            <input
                                type="text"
                                name="ehealthInsertedTime"
                                id="ehealthInsertedTime"
                                class="input peer pl-10!"
                                value="{{ $detectedIssue->ehealthInsertedTime ?: '-' }}"
                                placeholder=" "
                                disabled
                            />
                            <label for="ehealthInsertedTime" class="sr-only">{{ __('forms.time') }}</label>
                        </div>
                    </div>
                </div>

                <div class="form-row-3">
                    <div class="form-group group">
                        <div class="datepicker-wrapper">
                            <input
                                type="text"
                                name="ehealthUpdatedDate"
                                id="ehealthUpdatedDate"
                                class="datepicker-input with-leading-icon input peer"
                                value="{{ $detectedIssue->ehealthUpdatedDate ?: '-' }}"
                                placeholder=" "
                                disabled
                            />
                            <label for="ehealthUpdatedDate" class="wrapped-label">
                                {{ __('devices.updated_at_system') }}
                            </label>
                        </div>
                    </div>
                    <div class="form-group group w-1/2!">
                        <div class="relative flex items-center">
                            @icon('mingcute-time-fill', 'svg-input left-2.5')
                            <input
                                type="text"
                                name="ehealthUpdatedTime"
                                id="ehealthUpdatedTime"
                                class="input peer pl-10!"
                                value="{{ $detectedIssue->ehealthUpdatedTime ?: '-' }}"
                                placeholder=" "
                                disabled
                            />
                            <label for="ehealthUpdatedTime" class="sr-only">{{ __('forms.time') }}</label>
                        </div>
                    </div>
                </div>
            </fieldset>

            <div class="mt-8">
                <a
                    href="{{ $personId ? route('persons.device-issues', [legalEntity(), 'person' => $personId]) : route('prepersons.device-issues', [legalEntity(), 'preperson' => $prepersonId]) }}"
                    class="button-minor px-6 py-2"
                >{{ __('forms.back') }}</a>
            </div>
        </div>
    </section>

    <livewire:components.x-message :key="now()->timestamp" />
</div>
