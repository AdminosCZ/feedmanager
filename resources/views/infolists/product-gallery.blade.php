@php
    /** @var array<int, string> $urls */
    $urls = $getState() ?? [];
@endphp

@if (! empty($urls))
    <div
        x-data="{ open: false, src: '', alt: '' }"
        x-on:keydown.escape.window="open = false"
    >
        <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6">
            @foreach ($urls as $i => $url)
                <button
                    type="button"
                    @click="open = true; src = $event.currentTarget.dataset.src; alt = $event.currentTarget.dataset.alt"
                    data-src="{{ $url }}"
                    data-alt="Image {{ $i + 1 }}"
                    class="group relative block aspect-square overflow-hidden rounded-lg border border-gray-200 bg-gray-50 transition hover:opacity-90 dark:border-white/10 dark:bg-gray-900"
                >
                    <img
                        src="{{ $url }}"
                        alt="Image {{ $i + 1 }}"
                        loading="lazy"
                        class="h-full w-full cursor-zoom-in object-cover"
                    />
                </button>
            @endforeach
        </div>

        {{-- Lightbox overlay --}}
        <div
            x-show="open"
            x-on:click="open = false"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-6 backdrop-blur-sm cursor-zoom-out"
            style="display: none;"
        >
            <img
                :src="src"
                :alt="alt"
                class="max-h-[92vh] max-w-[92vw] object-contain rounded-lg shadow-2xl"
                x-on:click.stop
            />
            <button
                type="button"
                @click="open = false"
                class="absolute top-4 right-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20"
                aria-label="Close"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>
@endif
