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
            <summary class="fi-fl-tree-summary group/node flex items-center gap-2 rounded-md px-2 py-1 hover:bg-gray-50 dark:hover:bg-white/5 cursor-pointer">
                <span class="fi-fl-tree-chevron text-gray-400 transition-transform">
                    <x-filament::icon
                        icon="heroicon-m-chevron-right"
                        class="h-4 w-4"
                    />
                </span>
                @include('feedmanager::filament.pages.partials.shoptet-category-tree-label', [
                    'node' => $node,
                    'mappingCount' => $mappingCount,
                    'editUrl' => $editUrl,
                ])
            </summary>

            <ul class="fi-fl-tree-children ml-5 mt-0.5 space-y-0.5 border-l border-gray-200 pl-2 dark:border-white/10" role="group">
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
        <div class="fi-fl-tree-leaf flex items-center gap-2 rounded-md px-2 py-1 hover:bg-gray-50 dark:hover:bg-white/5">
            <span class="fi-fl-tree-leaf-bullet text-gray-300 dark:text-white/20">·</span>
            @include('feedmanager::filament.pages.partials.shoptet-category-tree-label', [
                'node' => $node,
                'mappingCount' => $mappingCount,
                'editUrl' => $editUrl,
            ])
        </div>
    @endif
</li>

