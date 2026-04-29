@php
    /** @var \Adminos\Modules\Feedmanager\Models\Supplier $record */
    $record = $getRecord();

    $thumbs = $record->products()
        ->whereNotNull('image_url')
        ->where('image_url', '!=', '')
        ->orderByDesc('imported_at')
        ->limit(3)
        ->pluck('image_url');
@endphp

@if ($thumbs->isEmpty())
    <span class="fi-fl-supplier-thumbs-empty">—</span>
@else
    <div class="fi-fl-supplier-thumbs">
        @foreach ($thumbs as $url)
            <img src="{{ $url }}" alt="" class="fi-fl-supplier-thumb" loading="lazy" />
        @endforeach
    </div>
@endif
