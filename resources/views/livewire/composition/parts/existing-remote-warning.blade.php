{{-- Warn when eHealth already has non-error conclusions for this subject (TV 3.8.1.3 / 3.8.2.3). --}}
@if (!empty($existingActiveRemote))
    <div class="status-alert-yellow mb-6 flex-col items-start">
        <p class="mb-2 text-sm font-medium">{{ __('compositions.existing_remote_warning') }}</p>
        <ul class="list-inside list-disc text-sm">
            @foreach ($existingActiveRemote as $row)
                <li wire:key="existing-{{ $row['uuid'] }}">
                    {{ $row['title'] ?: $row['uuid'] }}
                    @if ($row['status'])
                        ({{ $row['status'] }})
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
