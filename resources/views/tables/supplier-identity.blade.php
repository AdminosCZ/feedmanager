@once
    @push('styles')
        <style>
            .fi-fl-supplier-identity {
                display: flex;
                align-items: center;
                gap: 0.625rem;
                min-width: 0;
            }
            .fi-fl-supplier-avatar {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 2.25rem;
                height: 2.25rem;
                border-radius: 9999px;
                font-size: 0.8125rem;
                font-weight: 600;
                letter-spacing: 0.025em;
                flex: none;
            }
            .fi-fl-supplier-avatar--own {
                background: rgba(0, 133, 254, 0.12);
                color: rgb(0, 102, 204);
            }
            .dark .fi-fl-supplier-avatar--own {
                background: rgba(0, 133, 254, 0.2);
                color: rgb(186 220 255);
            }
            .fi-fl-supplier-avatar--external {
                background: rgba(15, 23, 42, 0.06);
                color: rgb(71 85 105);
            }
            .dark .fi-fl-supplier-avatar--external {
                background: rgba(255, 255, 255, 0.08);
                color: rgb(203 213 225);
            }

            .fi-fl-supplier-text {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                min-width: 0;
            }
            .fi-fl-supplier-name {
                font-weight: 600;
                color: rgb(15 23 42);
                font-size: 0.9375rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .dark .fi-fl-supplier-name {
                color: rgb(241 241 241);
            }

            .fi-fl-supplier-badge {
                display: inline-flex;
                align-items: center;
                font-size: 0.6875rem;
                font-weight: 600;
                line-height: 1;
                padding: 0.1875rem 0.4375rem;
                border-radius: 9999px;
                white-space: nowrap;
                flex: none;
            }
            .fi-fl-supplier-badge--success {
                color: rgb(22 101 52);
                background: rgba(34, 197, 94, 0.14);
            }
            .dark .fi-fl-supplier-badge--success {
                color: rgb(187 247 208);
                background: rgba(34, 197, 94, 0.2);
            }
            .fi-fl-supplier-badge--danger {
                color: rgb(155 28 28);
                background: rgba(235, 65, 67, 0.14);
            }
            .dark .fi-fl-supplier-badge--danger {
                color: rgb(254 202 202);
                background: rgba(235, 65, 67, 0.2);
            }
            .fi-fl-supplier-badge--gray {
                color: rgb(71 85 105);
                background: rgba(15, 23, 42, 0.06);
            }
            .dark .fi-fl-supplier-badge--gray {
                color: rgb(203 213 225);
                background: rgba(255, 255, 255, 0.08);
            }

            .fi-fl-supplier-thumbs {
                display: flex;
                align-items: center;
                gap: 0.375rem;
            }
            .fi-fl-supplier-thumb {
                width: 2.5rem;
                height: 2.5rem;
                object-fit: cover;
                border-radius: 0.5rem;
                border: 1px solid rgba(15, 23, 42, 0.08);
                background: rgba(15, 23, 42, 0.03);
            }
            .dark .fi-fl-supplier-thumb {
                border-color: rgba(255, 255, 255, 0.08);
                background: rgba(255, 255, 255, 0.04);
            }
            .fi-fl-supplier-thumbs-empty {
                color: rgb(148 163 184);
                font-size: 0.875rem;
            }
        </style>
    @endpush
@endonce

@php
    /** @var \Adminos\Modules\Feedmanager\Models\Supplier $record */
    $record = $getRecord();

    // Initials = first letter of each whitespace-separated word, max 2.
    $words = preg_split('/\s+/', trim($record->name)) ?: [];
    $initials = '';
    foreach ($words as $w) {
        $first = mb_substr($w, 0, 1);
        if ($first !== '' && ctype_alnum($first) && mb_strlen($initials) < 2) {
            $initials .= mb_strtoupper($first);
        }
    }
    if ($initials === '') {
        $initials = mb_strtoupper(mb_substr($record->name, 0, 2));
    }

    // Latest ImportLog across all of this supplier's feeds — drives the
    // status pill below the name. Status mirrors FeedConfig::STATUS_*.
    $lastLog = \Adminos\Modules\Feedmanager\Models\ImportLog::query()
        ->whereIn('feed_config_id', $record->feedConfigs()->pluck('id'))
        ->latest('finished_at')
        ->first();

    [$statusKey, $statusColor] = match ($lastLog?->status ?? null) {
        \Adminos\Modules\Feedmanager\Models\ImportLog::STATUS_SUCCESS => ['success', 'success'],
        \Adminos\Modules\Feedmanager\Models\ImportLog::STATUS_FAILED => ['failed', 'danger'],
        default => ['none', 'gray'],
    };
@endphp

<div class="fi-fl-supplier-identity">
    <span class="fi-fl-supplier-avatar fi-fl-supplier-avatar--{{ $record->is_own ? 'own' : 'external' }}">
        {{ $initials }}
    </span>
    <span class="fi-fl-supplier-text">
        <span class="fi-fl-supplier-name">{{ $record->name }}</span>
        <span class="fi-fl-supplier-badge fi-fl-supplier-badge--{{ $statusColor }}">
            {{ __('feedmanager::feedmanager.suppliers.last_sync_status.'.$statusKey) }}
        </span>
    </span>
</div>
