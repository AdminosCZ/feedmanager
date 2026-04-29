@php
    /** @var \Adminos\Modules\Feedmanager\Filament\Resources\ShoptetCategories\Pages\ListShoptetCategories $this */
    $roots = $this->getRoots();
    $counts = $this->getMappingCounts();
    $orphanCount = $this->getOrphanCount();
    $searchIndex = $this->getSearchIndex();
@endphp

<x-filament-panels::page>
    @push('styles')
        <style>
            .fi-fl-tree-details > summary {
                list-style: none;
                user-select: none;
            }
            .fi-fl-tree-details > summary::-webkit-details-marker {
                display: none;
            }
            .fi-fl-tree-details[open] > summary > .fi-fl-tree-chevron {
                transform: rotate(90deg);
            }
            .fi-fl-tree-chevron {
                display: inline-flex;
                transition: transform 150ms ease;
            }
            [x-cloak] {
                display: none !important;
            }
        </style>
    @endpush

    @if ($roots->isEmpty())
        <x-filament::section>
            <p class="fi-section-content fi-color-gray">
                {{ __('feedmanager::feedmanager.shoptet_categories.tree.empty') }}
            </p>
        </x-filament::section>
    @else
        <div
            x-data="shoptetCategoryTree({
                index: @js($searchIndex),
            })"
            class="fi-fl-tree space-y-3"
        >
            <x-filament::section>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex-1">
                        <x-filament::input.wrapper>
                            <x-filament::input
                                type="search"
                                x-model.debounce.150ms="search"
                                placeholder="{{ __('feedmanager::feedmanager.shoptet_categories.tree.search_placeholder') }}"
                            />
                        </x-filament::input.wrapper>
                    </div>

                    <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <span x-show="search === ''">
                            {{ __('feedmanager::feedmanager.shoptet_categories.tree.total', ['count' => count($searchIndex)]) }}
                        </span>
                        <span x-show="search !== ''" x-cloak>
                            <span x-text="visibleIds.size"></span>
                            / {{ count($searchIndex) }}
                        </span>

                        @if ($orphanCount > 0)
                            <span class="fi-badge fi-color-danger" style="padding: 0.125rem 0.5rem; border-radius: 9999px;">
                                {{ __('feedmanager::feedmanager.shoptet_categories.tree.orphan_summary', ['count' => $orphanCount]) }}
                            </span>
                        @endif
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <ul class="fi-fl-tree-roots space-y-1" role="tree">
                    @foreach ($roots as $root)
                        @include('feedmanager::filament.pages.partials.shoptet-category-tree-node', [
                            'node' => $root,
                            'counts' => $counts,
                            'depth' => 0,
                        ])
                    @endforeach
                </ul>
            </x-filament::section>
        </div>

        @push('scripts')
            <script>
                document.addEventListener('alpine:init', () => {
                    Alpine.data('shoptetCategoryTree', ({ index }) => ({
                        search: '',
                        index,

                        get visibleIds() {
                            const out = new Set();
                            if (this.search === '') {
                                for (const n of this.index) out.add(n.sid);
                                return out;
                            }
                            const needle = this.search.toLocaleLowerCase();

                            const direct = new Set();
                            for (const n of this.index) {
                                if ((n.title || '').toLocaleLowerCase().includes(needle)) {
                                    direct.add(n.sid);
                                }
                            }

                            // Include all ancestors of every match so the tree
                            // path stays connected on screen.
                            const byId = new Map(this.index.map((n) => [n.sid, n]));
                            for (const sid of direct) {
                                let cursor = byId.get(sid);
                                while (cursor) {
                                    out.add(cursor.sid);
                                    if (cursor.parent === null || cursor.parent === undefined) break;
                                    cursor = byId.get(cursor.parent);
                                }
                            }

                            // Include all descendants of every match, so
                            // searching "Knihy" reveals the whole subtree.
                            let added = true;
                            while (added) {
                                added = false;
                                for (const n of this.index) {
                                    if (out.has(n.parent) && !out.has(n.sid)) {
                                        out.add(n.sid);
                                        added = true;
                                    }
                                }
                            }

                            return out;
                        },

                        isVisible(sid) {
                            return this.visibleIds.has(sid);
                        },
                    }));
                });
            </script>
        @endpush
    @endif
</x-filament-panels::page>
