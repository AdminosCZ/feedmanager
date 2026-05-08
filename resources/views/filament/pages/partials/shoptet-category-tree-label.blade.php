@php
    /** @var \Adminos\Modules\Feedmanager\Models\ShoptetCategory $node */
    /** @var int $mappingCount */
    /** @var array<int, true> $b2bExcluded */

    $linkText = trim((string) $node->link_text);
    $showLinkText = $linkText !== '' && $linkText !== $node->title;

    $b2bExcluded ??= [];
    $isExplicitlyExcluded = $node->exclude_from_b2b === true;
    $isCascadedExcluded = ! $isExplicitlyExcluded && isset($b2bExcluded[$node->id]);
    $hasAnyExclusion = $isExplicitlyExcluded || $isCascadedExcluded;
@endphp

<span
    class="fi-fl-tree-visibility {{ $node->visible ? 'fi-fl-tree-visibility--on' : 'fi-fl-tree-visibility--off' }}"
    title="{{ $node->visible
        ? __('feedmanager::feedmanager.shoptet_categories.tree.visible_in_shop')
        : __('feedmanager::feedmanager.shoptet_categories.tree.hidden_in_shop') }}"
    aria-hidden="true"
>
    @if ($node->visible)
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/>
            <path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd"/>
        </svg>
    @else
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z"/>
            <path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z"/>
        </svg>
    @endif
</span>

<span class="fi-fl-tree-title">
    {{ $node->title }}
    @if ($showLinkText)
        <span class="fi-fl-tree-link-text">({{ $linkText }})</span>
    @endif
</span>

@if ($node->is_orphaned)
    <span
        class="fi-fl-tree-badge fi-fl-tree-badge--danger"
        title="{{ __('feedmanager::feedmanager.shoptet_categories.tree.orphaned_hint') }}"
    >
        {{ __('feedmanager::feedmanager.shoptet_categories.orphan_state.orphaned') }}
    </span>
@endif

@if ($mappingCount > 0)
    <span
        class="fi-fl-tree-badge fi-fl-tree-badge--info"
        title="{{ __('feedmanager::feedmanager.shoptet_categories.tree.mapped_suppliers', ['count' => $mappingCount]) }}"
    >
        {{ $mappingCount }}
    </span>
@endif

{{-- B2B exclusion shield. Klikatelný (Livewire toggle) jen na vlastní flag;
     cascaded stav se mění z parent kategorie. --}}
<button
    type="button"
    wire:click.stop="toggleB2bExclusion({{ $node->id }})"
    onclick="event.stopPropagation()"
    class="fi-fl-tree-b2b-shield {{ $isExplicitlyExcluded ? 'fi-fl-tree-b2b-shield--explicit' : ($isCascadedExcluded ? 'fi-fl-tree-b2b-shield--cascaded' : 'fi-fl-tree-b2b-shield--idle') }}"
    title="{{ $isExplicitlyExcluded
        ? __('feedmanager::feedmanager.shoptet_categories.tree.b2b_excluded_explicit')
        : ($isCascadedExcluded
            ? __('feedmanager::feedmanager.shoptet_categories.tree.b2b_excluded_cascaded')
            : __('feedmanager::feedmanager.shoptet_categories.tree.b2b_toggle_exclude')) }}"
    aria-label="{{ __('feedmanager::feedmanager.shoptet_categories.tree.b2b_toggle_exclude') }}"
>
    {{-- Shield s X uvnitř (heroicon-s no-symbol style). Stejný symbol pro
         všechny stavy, vzhled mění CSS třídy podle exclusion stavu. --}}
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M10 1c-1.828.487-3.772.808-5.625.93C3.673 1.989 3 2.665 3 3.502v3.249c0 5.18 3.045 9.337 7 10.749 3.955-1.412 7-5.57 7-10.749V3.502c0-.836-.673-1.513-1.375-1.572A22.62 22.62 0 0 1 10 1Zm-3.03 6.53a.75.75 0 0 1 1.06 0L10 9.439l1.97-1.97a.75.75 0 1 1 1.06 1.061L11.061 10.5l1.97 1.97a.75.75 0 1 1-1.061 1.06L10 11.561l-1.97 1.97a.75.75 0 1 1-1.06-1.061l1.97-1.97-1.97-1.969a.75.75 0 0 1 0-1.061Z" clip-rule="evenodd"/>
    </svg>
</button>
