<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ isset($title) ? $title . ' — INTEC École' : config('app.name', 'INTEC École') }}</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/in tech.jpg') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=Figtree:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

</head>
<body class="min-h-screen font-sans antialiased bg-base-200">

@php
    $yr         = \App\Models\AcademicYear::current();
    $notifCount = auth()->user()?->unreadNotifications->count() ?? 0;
    $pending    = 0;
    if (auth()->user()?->hasAnyRole(['admin', 'direction', 'pedagogie', 'finance'])) {
        $pending = \App\Models\Bulletin::where('status', '=', match(true) {
            auth()->user()->hasRole('finance')   => \App\Enums\BulletinStatusEnum::PEDAGOGIE_APPROVED->value,
            auth()->user()->hasRole('direction') => \App\Enums\BulletinStatusEnum::FINANCE_APPROVED->value,
            default                              => \App\Enums\BulletinStatusEnum::SUBMITTED->value,
        })->count();
    }
@endphp

{{-- ── Mobile top bar ────────────────────────────────────────────────────── --}}
<x-nav sticky class="lg:hidden bg-base-100 border-b border-base-300">
    <x-slot:brand>
        <div class="flex items-center gap-2.5 px-1">
            <div class="w-8 h-8 rounded-lg overflow-hidden ring-2 ring-primary/20 bg-primary/5 shrink-0 p-0.5">
                <img src="{{ asset('images/in tech.jpg') }}" alt="INTEC" class="w-full h-full object-contain rounded-md" />
            </div>
            <div>
                <p class="font-black text-sm text-primary leading-none">INTEC</p>
                <p class="text-[9px] text-secondary font-bold tracking-widest uppercase leading-none mt-0.5">École</p>
            </div>
        </div>
    </x-slot:brand>
    <x-slot:actions>
        <label for="main-drawer" class="btn btn-ghost btn-square btn-sm">
            <x-icon name="o-bars-3" class="w-5 h-5" />
        </label>
    </x-slot:actions>
</x-nav>

{{-- ── Main layout ────────────────────────────────────────────────────────── --}}
<x-main>

    {{-- ── Sidebar ──────────────────────────────────────────────────────── --}}
    <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 border-r border-base-300">

        {{-- Brand header --}}
        <a href="{{ route('dashboard') }}" wire:navigate
           class="flex items-center gap-3 px-5 py-4 border-b border-base-300 hover:bg-base-200/60 transition-colors">
            <div class="w-10 h-10 rounded-xl overflow-hidden ring-2 ring-primary/20 bg-primary/5 shrink-0 p-0.5">
                <img src="{{ asset('images/in tech.jpg') }}" alt="INTEC" class="w-full h-full object-contain rounded-lg" />
            </div>
            <div class="min-w-0">
                <p class="font-black text-sm text-primary leading-tight tracking-tight">INTEC École</p>
                <p class="text-[9px] text-secondary font-bold tracking-widest uppercase mt-0.5">Gestion Scolaire</p>
            </div>
        </a>

        {{-- Navigation menu --}}
        <x-menu activate-by-route>

            {{-- ── Tableau de bord ── --}}
            <x-menu-item title="Tableau de bord" icon="o-chart-pie" link="{{ route('dashboard') }}" />

            {{-- ── Bulletins (enseignants + admin + direction) ── --}}
            @role('teacher|admin|direction')
            <x-menu-sub title="Bulletins" icon="o-document-text">
                <x-menu-item title="Saisie des notes"  icon="o-pencil-square"         link="{{ route('bulletins.grade-form') }}" />
                @role('teacher')
                <x-menu-item title="Mes bulletins"     icon="o-clipboard-document-list" link="{{ route('bulletins.index') }}" />
                <x-menu-item title="Mon programme"     icon="o-book-open"              link="{{ route('setup.programme') }}" />
                @endrole
                @role('admin|direction')
                <x-menu-item title="Bilan annuel"      icon="o-chart-bar"              link="{{ route('bulletins.annual') }}" />
                <x-menu-item title="Modèles bulletin"  icon="o-printer"                link="{{ route('bulletins.template-preview') }}" />
                @endrole
            </x-menu-sub>
            @endrole

            {{-- ── Validation (pédagogie + finance + direction + admin) ── --}}
            @role('pedagogie|finance|direction|admin')
            <x-menu-sub title="Validation" icon="o-check-badge">
                <x-menu-item
                    title="Workflow bulletins"
                    icon="o-arrows-right-left"
                    link="{{ route('bulletins.index') }}"
                    :badge="$pending ?: null"
                    badge-classes="badge-error badge-xs" />
                <x-menu-item title="Suivi workflow" icon="o-magnifying-glass-circle" link="{{ route('bulletins.suivi') }}" />
            </x-menu-sub>
            @endrole

            {{-- ── Rapports ── --}}
            @role('admin|direction|pedagogie')
            <x-menu-item title="Rapports" icon="o-presentation-chart-line" link="{{ route('rapports.index') }}" />
            @endrole

            {{-- ── Configuration ── --}}
            @role('admin|direction')
            <x-menu-sub title="Configuration" icon="o-cog-6-tooth">
                <x-menu-item title="Années scolaires"    icon="o-calendar-days"          link="{{ route('setup.annees') }}" />
                <x-menu-item title="Niveaux"             icon="o-academic-cap"            link="{{ route('setup.niveaux') }}" />
                <x-menu-item title="Classes"             icon="o-building-library"        link="{{ route('setup.classrooms') }}" />
                <x-menu-item title="Matières"            icon="o-tag"                     link="{{ route('setup.subjects') }}" />
                <x-menu-item title="Compétences"         icon="o-star"                    link="{{ route('setup.competences') }}" />
                <x-menu-item title="Programme"           icon="o-book-open"               link="{{ route('setup.programme') }}" />
                <x-menu-item title="Élèves"              icon="o-users"                   link="{{ route('setup.students') }}" />
                <x-menu-item title="Seuils"              icon="o-adjustments-horizontal"  link="{{ route('setup.seuils') }}" />
                <x-menu-item title="Enseignants"         icon="o-user-group"              link="{{ route('setup.teachers') }}" />
                <x-menu-item title="Données"             icon="o-table-cells"             link="{{ route('setup.donnees') }}" />
            </x-menu-sub>
            @endrole

        </x-menu>

        {{-- User card + logout --}}
        <x-menu-separator />
        <div class="px-3 pb-4">
            <div class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl bg-base-200 border border-base-300/60">
                <div class="w-8 h-8 rounded-full bg-primary text-primary-content flex items-center justify-center text-xs font-bold shrink-0 ring-2 ring-primary/20">
                    {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold truncate text-base-content">{{ Str::limit(auth()->user()?->name ?? '', 20) }}</p>
                    <p class="text-[10px] text-base-content/50 capitalize truncate">{{ auth()->user()?->getRoleNames()->join(', ') }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                    @csrf
                    <button type="submit" title="Déconnexion"
                        class="btn btn-ghost btn-xs btn-circle text-error hover:bg-error/10">
                        <x-icon name="o-arrow-right-on-rectangle" class="w-4 h-4" />
                    </button>
                </form>
            </div>
        </div>

    </x-slot:sidebar>

    {{-- ── Main content area ───────────────────────────────────────────────── --}}
    <x-slot:content>

        {{-- Sticky top header --}}
        <header class="sticky top-0 z-20 h-14 flex items-center gap-3 px-4 lg:px-6 bg-base-100 border-b border-base-300 shadow-sm shrink-0">

            {{-- Academic year badge --}}
            @if($yr)
            <span class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold
                         bg-primary/10 text-primary border border-primary/20">
                <x-icon name="o-calendar" class="w-3.5 h-3.5" />
                {{ $yr->label }}
            </span>
            @endif

            <div class="flex-1"></div>

            {{-- Notifications --}}
            <div class="dropdown dropdown-end">
                <button tabindex="0" class="btn btn-ghost btn-circle btn-sm relative">
                    <x-icon name="o-bell" class="w-5 h-5" />
                    @if($notifCount)
                    <span class="absolute -top-0.5 -right-0.5 min-w-[1rem] h-4 px-0.5 bg-error text-white text-[9px] font-bold rounded-full flex items-center justify-center">
                        {{ $notifCount }}
                    </span>
                    @endif
                </button>
                <div tabindex="0" class="dropdown-content z-50 mt-2 w-72 rounded-2xl bg-base-100 border border-base-300 shadow-xl overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-2.5 border-b border-base-300 bg-base-200/60">
                        <p class="font-semibold text-sm">Notifications</p>
                        @if($notifCount)
                        <span class="badge badge-error badge-sm">{{ $notifCount }}</span>
                        @endif
                    </div>
                    <div class="max-h-64 overflow-y-auto divide-y divide-base-200">
                        @forelse(auth()->user()?->unreadNotifications->take(6) ?? [] as $notif)
                        <div class="px-4 py-3 text-xs hover:bg-base-200/70 transition-colors">
                            <p class="font-medium text-base-content">{{ $notif->data['message'] ?? 'Notification' }}</p>
                            <p class="text-base-content/50 mt-0.5">{{ $notif->created_at->diffForHumans() }}</p>
                        </div>
                        @empty
                        <div class="px-4 py-8 text-center text-xs text-base-content/40">
                            <x-icon name="o-bell-slash" class="w-8 h-8 mx-auto mb-2 opacity-30" />
                            <p>Aucune notification</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Divider --}}
            <div class="w-px h-6 bg-base-300"></div>

            {{-- User dropdown --}}
            <div class="dropdown dropdown-end">
                <button tabindex="0"
                    class="flex items-center gap-2 px-2.5 py-1.5 rounded-xl hover:bg-base-200 transition-colors cursor-pointer">
                    <div class="w-7 h-7 rounded-full bg-primary text-primary-content flex items-center justify-center text-xs font-bold shrink-0">
                        {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
                    </div>
                    <div class="hidden md:block text-left leading-tight">
                        <p class="text-xs font-semibold text-base-content max-w-[120px] truncate">{{ auth()->user()?->name }}</p>
                        <p class="text-[10px] text-base-content/50 capitalize">{{ auth()->user()?->getRoleNames()->first() }}</p>
                    </div>
                    <x-icon name="o-chevron-down" class="w-3.5 h-3.5 text-base-content/40 hidden md:block" />
                </button>
                <div tabindex="0"
                    class="dropdown-content z-50 mt-2 w-52 rounded-2xl bg-base-100 border border-base-300 shadow-xl overflow-hidden p-1.5">
                    <div class="px-3 py-2.5 rounded-xl bg-base-200/70 mb-1.5 border border-base-300/40">
                        <p class="font-semibold text-sm text-base-content truncate">{{ auth()->user()?->name }}</p>
                        <div class="flex flex-wrap gap-1 mt-1.5">
                            @foreach(auth()->user()?->getRoleNames() ?? [] as $r)
                            <span class="badge badge-xs badge-primary">{{ $r }}</span>
                            @endforeach
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="w-full flex items-center gap-2 px-3 py-2 text-sm text-error hover:bg-error/10 rounded-xl transition-colors">
                            <x-icon name="o-arrow-right-on-rectangle" class="w-4 h-4" />
                            Déconnexion
                        </button>
                    </form>
                </div>
            </div>

        </header>

        {{-- Page content --}}
        <main class="p-4 lg:p-6 min-w-0">
            {{ $slot }}
        </main>

        {{-- Footer --}}
        <footer class="py-3 px-6 border-t border-base-300 text-center text-xs text-base-content/30">
            INTEC École &copy; {{ date('Y') }} — Système de Gestion Scolaire
        </footer>

    </x-slot:content>
</x-main>

<x-toast />


@livewireScripts
</body>
</html>
