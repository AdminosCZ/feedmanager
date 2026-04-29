@php
    /** @var \Adminos\Modules\Feedmanager\Filament\Resources\ShoptetCategories\Pages\ListShoptetCategories $this */
    $roots = $this->getRoots();
    $counts = $this->getMappingCounts();
    $orphanCount = $this->getOrphanCount();
    $searchIndex = $this->getSearchIndex();
@endphp

<x-filament-panels::page>
    @push('styles')
        {{-- Self-contained tree styles. The feedmanager package can't rely on
             skeleton's Tailwind being aware of these views — utilities like
             ml-5 / border-l / opacity-0 wouldn't ship in the compiled bundle.
             Everything is scoped under fi-fl-tree-*. --}}
        <style>
            [x-cloak] { display: none !important; }

            .fi-fl-tree-roots,
            .fi-fl-tree-children {
                list-style: none;
                margin: 0;
                padding: 0;
            }
            .fi-fl-tree-children {
                margin-left: 1rem;
                padding-left: 0.75rem;
                border-left: 1px solid rgba(15, 23, 42, 0.08);
                margin-top: 0.125rem;
            }
            .dark .fi-fl-tree-children {
                border-left-color: rgba(255, 255, 255, 0.1);
            }

            .fi-fl-tree-item + .fi-fl-tree-item {
                margin-top: 0.125rem;
            }

            .fi-fl-tree-row {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.25rem 0.5rem;
                border-radius: 0.5rem;
                cursor: pointer;
                line-height: 1.3;
            }
            .fi-fl-tree-row:hover {
                background-color: rgba(15, 23, 42, 0.04);
            }
            .dark .fi-fl-tree-row:hover {
                background-color: rgba(255, 255, 255, 0.06);
            }

            .fi-fl-tree-details > summary {
                list-style: none;
                user-select: none;
            }
            .fi-fl-tree-details > summary::-webkit-details-marker {
                display: none;
            }

            .fi-fl-tree-chevron {
                display: inline-flex;
                width: 1rem;
                height: 1rem;
                flex: none;
                color: rgb(148 163 184);
                transition: transform 150ms ease;
            }
            .fi-fl-tree-chevron svg {
                width: 1rem;
                height: 1rem;
            }
            .fi-fl-tree-details[open] > summary .fi-fl-tree-chevron {
                transform: rotate(90deg);
            }

            .fi-fl-tree-leaf-bullet {
                display: inline-flex;
                width: 1rem;
                height: 1rem;
                flex: none;
                align-items: center;
                justify-content: center;
                color: rgb(203 213 225);
            }
            .fi-fl-tree-leaf-bullet::before {
                content: "·";
                font-weight: 700;
            }

            .fi-fl-tree-title {
                flex: 1;
                min-width: 0;
                font-size: 0.875rem;
                color: rgb(15 23 42);
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .dark .fi-fl-tree-title {
                color: rgb(241 241 241);
            }

            .fi-fl-tree-badge {
                display: inline-flex;
                align-items: center;
                gap: 0.25rem;
                font-size: 0.6875rem;
                font-weight: 600;
                line-height: 1;
                padding: 0.1875rem 0.4375rem;
                border-radius: 9999px;
                white-space: nowrap;
            }
            .fi-fl-tree-badge--info {
                color: rgb(15 67 138);
                background: rgba(0, 133, 254, 0.12);
            }
            .dark .fi-fl-tree-badge--info {
                color: rgb(186 220 255);
                background: rgba(0, 133, 254, 0.2);
            }
            .fi-fl-tree-badge--danger {
                color: rgb(155 28 28);
                background: rgba(235, 65, 67, 0.14);
            }
            .dark .fi-fl-tree-badge--danger {
                color: rgb(254 202 202);
                background: rgba(235, 65, 67, 0.2);
            }
            .fi-fl-tree-badge--gray {
                color: rgb(71 85 105);
                background: rgba(15, 23, 42, 0.06);
            }
            .dark .fi-fl-tree-badge--gray {
                color: rgb(203 213 225);
                background: rgba(255, 255, 255, 0.08);
            }

            .fi-fl-tree-toolbar {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
            }
            @media (min-width: 640px) {
                .fi-fl-tree-toolbar {
                    flex-direction: row;
                    align-items: center;
                    justify-content: space-between;
                }
            }

            .fi-fl-tree-toolbar-search {
                flex: 1;
            }
            .fi-fl-tree-toolbar-meta {
                display: flex;
                align-items: center;
                gap: 0.625rem;
                color: rgb(100 116 139);
                font-size: 0.8125rem;
            }
            .dark .fi-fl-tree-toolbar-meta {
                color: rgb(148 163 184);
            }

            .fi-fl-tree-search-input {
                width: 100%;
                background: var(--glass-light-bg, rgba(255, 255, 255, 0.6));
                border: 1px solid rgba(15, 23, 42, 0.08);
                border-radius: 0.625rem;
                padding: 0.5rem 0.75rem;
                font-size: 0.875rem;
                color: rgb(15 23 42);
                outline: none;
                transition: border-color 200ms ease, box-shadow 200ms ease;
            }
            .fi-fl-tree-search-input:focus {
                border-color: var(--primary-500, #0085FE);
                box-shadow: 0 0 0 3px rgba(0, 133, 254, 0.18);
            }
            .dark .fi-fl-tree-search-input {
                background: var(--glass-dark-bg, rgba(1, 1, 1, 0.55));
                border-color: rgba(255, 255, 255, 0.08);
                color: rgb(241 241 241);
            }
        </style>
    @endpush

    @if ($roots->isEmpty())
        <x-filament::section>
            <p>{{ __('feedmanager::feedmanager.shoptet_categories.tree.empty') }}</p>
        </x-filament::section>
    @else
        <div
            x-data="shoptetCategoryTree({ index: @js($searchIndex) })"
            class="fi-fl-tree"
        >
            <x-filament::section>
                <div class="fi-fl-tree-toolbar">
                    <div class="fi-fl-tree-toolbar-search">
                        <input
                            type="search"
                            class="fi-fl-tree-search-input"
                            x-model.debounce.150ms="search"
                            placeholder="{{ __('feedmanager::feedmanager.shoptet_categories.tree.search_placeholder') }}"
                        />
                    </div>

                    <div class="fi-fl-tree-toolbar-meta">
                        <span x-show="search === ''">
                            {{ __('feedmanager::feedmanager.shoptet_categories.tree.total', ['count' => count($searchIndex)]) }}
                        </span>
                        <span x-show="search !== ''" x-cloak>
                            <span x-text="visibleIds.size"></span> / {{ count($searchIndex) }}
                        </span>

                        @if ($orphanCount > 0)
                            <span class="fi-fl-tree-badge fi-fl-tree-badge--danger">
                                {{ __('feedmanager::feedmanager.shoptet_categories.tree.orphan_summary', ['count' => $orphanCount]) }}
                            </span>
                        @endif
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <ul class="fi-fl-tree-roots" role="tree">
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

                            const byId = new Map(this.index.map((n) => [n.sid, n]));
                            for (const sid of direct) {
                                let cursor = byId.get(sid);
                                while (cursor) {
                                    out.add(cursor.sid);
                                    if (cursor.parent === null || cursor.parent === undefined) break;
                                    cursor = byId.get(cursor.parent);
                                }
                            }

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
