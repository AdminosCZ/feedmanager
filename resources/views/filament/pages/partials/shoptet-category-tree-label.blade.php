@php
    /** @var \Adminos\Modules\Feedmanager\Models\ShoptetCategory $node */
    /** @var int $mappingCount */
    /** @var string $editUrl */
@endphp

<span class="fi-fl-tree-title flex-1 truncate text-sm text-gray-900 dark:text-gray-50">
    {{ $node->title }}
</span>

@if (! $node->visible)
    <span class="fi-badge fi-color-gray text-xs" title="{{ __('feedmanager::feedmanager.shoptet_categories.tree.hidden_in_shop') }}">
        <x-filament::icon icon="heroicon-m-eye-slash" class="h-3.5 w-3.5" />
    </span>
@endif

@if ($node->is_orphaned)
    <span
        class="fi-badge fi-color-danger text-xs"
        title="{{ __('feedmanager::feedmanager.shoptet_categories.tree.orphaned_hint') }}"
        style="padding: 0.0625rem 0.375rem; border-radius: 9999px;"
    >
        {{ __('feedmanager::feedmanager.shoptet_categories.orphan_state.orphaned') }}
    </span>
@endif

@if ($mappingCount > 0)
    <span
        class="fi-badge fi-color-primary text-xs"
        title="{{ __('feedmanager::feedmanager.shoptet_categories.tree.mapped_suppliers', ['count' => $mappingCount]) }}"
        style="padding: 0.0625rem 0.375rem; border-radius: 9999px;"
    >
        {{ $mappingCount }}
    </span>
@endif

<a
    href="{{ $editUrl }}"
    class="fi-link fi-color-gray opacity-0 group-hover/node:opacity-100 hover:opacity-100 text-xs"
    title="{{ __('feedmanager::feedmanager.shoptet_categories.tree.edit') }}"
>
    <x-filament::icon icon="heroicon-m-pencil-square" class="h-4 w-4" />
</a>
