@php
    /** @var \Adminos\Modules\Feedmanager\Models\ShoptetCategory $node */
    /** @var array<int, int> $counts */
    /** @var int $depth */
    $children = $node->children;
    $hasChildren = $children->isNotEmpty();
    $mappingCount = $counts[$node->id] ?? 0;
    $editUrl = \Adminos\Modules\Feedmanager\Filament\Resources\ShoptetCategoryResource::getUrl('edit', ['record' => $node->id]);
@endphp

<li
    role="treeitem"
    x-show="isVisible({{ $node->shoptet_id }})"
    class="fi-fl-tree-item"
>
    @if ($hasChildren)
        <details class="fi-fl-tree-details" {{ $depth < 1 ? 'open' : '' }}>
            <summary class="fi-fl-tree-row">
                <span class="fi-fl-tree-chevron">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/>
                    </svg>
                </span>
                @include('feedmanager::filament.pages.partials.shoptet-category-tree-label', [
                    'node' => $node,
                    'mappingCount' => $mappingCount,
                    'editUrl' => $editUrl,
                ])
            </summary>

            <ul class="fi-fl-tree-children" role="group">
                @foreach ($children as $child)
                    @include('feedmanager::filament.pages.partials.shoptet-category-tree-node', [
                        'node' => $child,
                        'counts' => $counts,
                        'depth' => $depth + 1,
                    ])
                @endforeach
            </ul>
        </details>
    @else
        <div class="fi-fl-tree-row">
            <span class="fi-fl-tree-leaf-bullet" aria-hidden="true"></span>
            @include('feedmanager::filament.pages.partials.shoptet-category-tree-label', [
                'node' => $node,
                'mappingCount' => $mappingCount,
                'editUrl' => $editUrl,
            ])
        </div>
    @endif
</li>
