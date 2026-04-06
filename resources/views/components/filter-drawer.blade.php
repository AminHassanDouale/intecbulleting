{{--
    Custom filter drawer component.
    Props:
      model    – Livewire property name to entangle (string, e.g. "showFilters")
      title    – Panel heading
      subtitle – Panel sub-heading (optional)
    Slots:
      default  – Body content (filters, etc.)
      actions  – Footer action buttons
--}}
@props(['model' => 'showFilters', 'title' => 'Filtres', 'subtitle' => null])

<div
    x-data="{ open: $wire.entangle('{{ $model }}') }"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-[60]"
    @keydown.escape.window="open = false"
>
    {{-- Backdrop --}}
    <div
        class="absolute inset-0 bg-black/50"
        x-transition:enter="transition-opacity duration-300 ease-out"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity duration-200 ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="open = false"
    ></div>

    {{-- Slide-in panel --}}
    <div
        class="absolute top-0 right-0 h-full w-80 max-w-[90vw] bg-white shadow-2xl flex flex-col"
        x-transition:enter="transition-transform duration-300 ease-out"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition-transform duration-200 ease-in"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
    >
        {{-- Header --}}
        <div class="flex items-start justify-between px-5 py-4 border-b border-slate-200 shrink-0">
            <div>
                <h3 class="text-sm font-semibold text-slate-800">{{ $title }}</h3>
                @if($subtitle)
                <p class="text-xs text-slate-500 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            <button
                @click="open = false"
                class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
            {{ $slot }}
        </div>

        {{-- Footer actions --}}
        @if (isset($actions))
        <div class="px-5 py-4 border-t border-slate-200 flex gap-2 justify-end shrink-0">
            {{ $actions }}
        </div>
        @endif
    </div>
</div>
