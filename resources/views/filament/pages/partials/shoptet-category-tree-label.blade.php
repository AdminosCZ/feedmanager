@php
    /** @var \Adminos\Modules\Feedmanager\Models\ShoptetCategory $node */
    /** @var int $mappingCount */
    /** @var string $editUrl */
@endphp

<span class="fi-fl-tree-title">
    {{ $node->title }}
</span>

@if (! $node->visible)
    <span
        class="fi-fl-tree-badge fi-fl-tree-badge--gray"
        title="{{ __('feedmanager::feedmanager.shoptet_categories.tree.hidden_in_shop') }}"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="width:0.875rem;height:0.875rem;">
            <path d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z"/>
            <path d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z"/>
        </svg>
    </span>
@endif

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

<a
    href="{{ $editUrl }}"
    class="fi-fl-tree-edit"
    title="{{ __('feedmanager::feedmanager.shoptet_categories.tree.edit') }}"
    onclick="event.stopPropagation()"
>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="width:0.875rem;height:0.875rem;">
        <path d="M2.695 14.762l-1.262 3.155a.5.5 0 0 0 .65.65l3.155-1.262a4 4 0 0 0 1.343-.886L17.5 5.501a2.121 2.121 0 0 0-3-3L3.58 13.419a4 4 0 0 0-.885 1.343Z"/>
    </svg>
</a>
