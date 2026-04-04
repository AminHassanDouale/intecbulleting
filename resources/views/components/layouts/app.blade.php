<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'INTEC École') }}</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/in tech.jpg') }}">
    <link rel="shortcut icon" type="image/jpeg" href="{{ asset('images/in tech.jpg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            padding: 0.45rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: background 0.15s, color 0.15s;
            color: inherit;
            text-decoration: none;
            width: 100%;
        }
        .nav-link:hover { background: oklch(var(--b2)); }
        .nav-link.active { font-weight: 600; }

        /* ── Vibrant toast alerts ───────────────────────────────────────────── */
        [class*="toast"] .alert {
            min-width: 280px;
            max-width: 380px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.20), 0 4px 12px rgba(0,0,0,0.12);
            border-radius: 0.75rem;
            padding: 0.875rem 1rem;
            border: none;
            font-size: 0.875rem;
        }
        [class*="toast"] .alert-success {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: #fff;
        }
        [class*="toast"] .alert-success svg { color: rgba(255,255,255,0.85); }
        [class*="toast"] .alert-error {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: #fff;
        }
        [class*="toast"] .alert-error svg { color: rgba(255,255,255,0.85); }
        [class*="toast"] .alert-warning {
            background: linear-gradient(135deg, #d97706, #b45309);
            color: #fff;
        }
        [class*="toast"] .alert-warning svg { color: rgba(255,255,255,0.85); }
        [class*="toast"] .alert-info {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
        }
        [class*="toast"] .alert-info svg { color: rgba(255,255,255,0.85); }
        [class*="toast"] .alert .font-bold { color: #fff; }
        [class*="toast"] .alert .text-xs { color: rgba(255,255,255,0.80); }
        [class*="toast"] progress.progress { opacity: 0.35; }
        [class*="toast"] progress.progress::-webkit-progress-value { background: #fff; }
        [class*="toast"] progress.progress::-moz-progress-bar { background: #fff; }
    </style>
</head>
<body class="min-h-screen bg-base-200 font-sans antialiased">

@php $yr = \App\Models\AcademicYear::current(); @endphp

<div class="drawer lg:drawer-open">
    <input id="sidebar-drawer" type="checkbox" class="drawer-toggle" />

    {{-- ── Main content area ───────────────────────────────────────────── --}}
    <div class="drawer-content flex flex-col min-h-screen min-w-0">

        {{-- Sticky navbar --}}
        <nav class="sticky top-0 z-30 navbar bg-linear-to-r from-blue-700 via-indigo-700 to-violet-700 text-white shadow-lg px-3 min-h-14 h-14">

            {{-- Mobile hamburger --}}
            <div class="flex-none lg:hidden">
                <label for="sidebar-drawer" class="btn btn-ghost btn-square btn-sm text-white hover:bg-white/15">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="h-5 w-5 stroke-current">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
                    </svg>
                </label>
            </div>

            {{-- Brand --}}
            <div class="flex-1 flex items-center gap-2 min-w-0">
                <a href="{{ route('dashboard') }}" wire:navigate
                   class="flex items-center gap-2 px-2 py-1 rounded-lg hover:bg-white/10 transition-colors shrink-0">
                    <div class="w-7 h-7 bg-white/20 rounded-lg flex items-center justify-center backdrop-blur overflow-hidden p-0.5">
                        <img src="{{ asset('images/in tech.jpg') }}" alt="INTEC" class="w-full h-full object-contain">
                    </div>
                    <span class="hidden sm:block text-sm font-bold">INTEC École</span>
                </a>
                @if($yr)
                <span class="hidden md:flex items-center gap-1 px-2 py-0.5 bg-white/15 rounded-full text-xs font-medium">
                    📅 {{ $yr->label }}
                </span>
                @endif
            </div>

            {{-- Right: notifications + user --}}
            <div class="flex-none flex items-center gap-1">

                {{-- Notifications dropdown --}}
                @php $notifCount = auth()->user()?->unreadNotifications->count() ?? 0; @endphp
                <div class="dropdown dropdown-end">
                    <button tabindex="0" class="btn btn-ghost btn-circle btn-sm text-white hover:bg-white/15 relative">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        @if($notifCount)
                        <span class="absolute -top-0.5 -right-0.5 w-4 h-4 bg-amber-400 text-black text-xs font-bold rounded-full flex items-center justify-center leading-none">{{ $notifCount }}</span>
                        @endif
                    </button>
                    <div tabindex="0" class="dropdown-content z-50 mt-2 w-72 shadow-2xl bg-base-100 text-base-content rounded-2xl overflow-hidden border border-base-200">
                        <div class="px-4 py-3 bg-linear-to-r from-indigo-50 to-violet-50 border-b border-base-200">
                            <p class="font-bold text-sm text-indigo-700">🔔 Notifications</p>
                        </div>
                        <div class="max-h-60 overflow-y-auto divide-y divide-base-200">
                            @forelse(auth()->user()?->unreadNotifications->take(6) ?? [] as $notif)
                            <div class="px-4 py-2.5 text-xs hover:bg-base-50">
                                <p class="text-base-content/80">{{ $notif->data['message'] ?? 'Notification' }}</p>
                                <p class="text-base-content/40 mt-0.5">{{ $notif->created_at->diffForHumans() }}</p>
                            </div>
                            @empty
                            <div class="px-4 py-5 text-center text-xs text-base-content/40">Aucune notification</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- User menu --}}
                <div class="dropdown dropdown-end">
                    <button tabindex="0" class="flex items-center gap-2 px-2 py-1 rounded-lg hover:bg-white/15 transition-colors">
                        <div class="w-7 h-7 bg-linear-to-br from-amber-400 to-orange-500 rounded-full flex items-center justify-center text-white text-xs font-bold shadow shrink-0">
                            {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
                        </div>
                        <div class="hidden md:block text-left leading-none">
                            <p class="text-xs font-semibold">{{ Str::limit(auth()->user()?->name ?? '', 16) }}</p>
                            <p class="text-xs opacity-50 capitalize">{{ auth()->user()?->getRoleNames()->first() ?? '' }}</p>
                        </div>
                        <svg class="h-3 w-3 opacity-50 hidden md:block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <ul tabindex="0" class="menu menu-sm dropdown-content z-50 mt-2 shadow-2xl bg-base-100 text-base-content rounded-2xl w-52 p-2 border border-base-200">
                        <li class="pointer-events-none mb-1">
                            <div class="px-3 py-2 bg-linear-to-r from-indigo-50 to-violet-50 rounded-lg">
                                <p class="font-bold text-sm text-indigo-900">{{ auth()->user()?->name }}</p>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @foreach(auth()->user()?->getRoleNames() ?? [] as $r)
                                    <span class="badge badge-primary badge-xs">{{ $r }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="text-error gap-2 w-full text-left px-3 py-2 rounded-lg hover:bg-error/10 transition-colors text-sm">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    Déconnexion
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        {{-- Page content --}}
        <main class="flex-1 p-4 lg:p-6 overflow-x-hidden min-w-0">
            {{ $slot }}
        </main>

        {{-- Footer --}}
        <footer class="py-3 px-6 bg-base-100 border-t border-base-200 text-center text-xs text-base-content/30">
            INTEC École &copy; {{ date('Y') }} &mdash; Système de Gestion Scolaire
        </footer>
    </div>

    {{-- ── Sidebar ───────────────────────────────────────────────────────── --}}
    <div class="drawer-side z-40">
        <label for="sidebar-drawer" aria-label="close sidebar" class="drawer-overlay"></label>

        <aside class="min-h-full w-64 bg-base-100 shadow-xl flex flex-col border-r border-base-200">

            {{-- Sidebar header --}}
            <div class="px-4 py-4 bg-linear-to-br from-blue-700 via-indigo-700 to-violet-700 text-white shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur shrink-0 overflow-hidden p-1">
                        <img src="{{ asset('images/in tech.jpg') }}" alt="INTEC" class="w-full h-full object-contain">
                    </div>
                    <div class="min-w-0">
                        <p class="font-bold text-sm leading-tight">INTEC École</p>
                        <p class="text-xs opacity-60">Gestion Scolaire</p>
                    </div>
                </div>
                @if($yr)
                <div class="mt-3 px-2.5 py-1.5 bg-white/15 rounded-lg text-xs flex items-center gap-1.5">
                    <span>📅</span>
                    <span class="font-medium truncate">{{ $yr->label }}</span>
                </div>
                @endif
            </div>

            {{-- Navigation links --}}
            <nav class="flex-1 px-2 py-3 space-y-0.5 overflow-y-auto">

                {{-- Dashboard --}}
                <a href="{{ route('dashboard') }}" wire:navigate
                   onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('dashboard') ? 'active bg-indigo-50 text-indigo-700' : 'text-base-content/70' }}">
                    <span class="text-base w-5 text-center shrink-0">📊</span>
                    <span>Tableau de bord</span>
                </a>

                {{-- Teacher / Admin section --}}
                @role('teacher|admin')
                <p class="px-3 pt-4 pb-1 text-xs font-bold uppercase tracking-widest text-base-content/30">✏️ Bulletins</p>
                <a href="{{ route('bulletins.grade-form') }}" wire:navigate
                   onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('bulletins.grade-form') ? 'active bg-amber-50 text-amber-700' : 'text-base-content/70' }}">
                    <span class="text-base w-5 text-center shrink-0">✏️</span>
                    <span>Saisie des notes</span>
                </a>
                @endrole

                @role('teacher')
                <a href="{{ route('bulletins.index') }}" wire:navigate
                   onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('bulletins.index') ? 'active bg-amber-50 text-amber-700' : 'text-base-content/70' }}">
                    <span class="text-base w-5 text-center shrink-0">📋</span>
                    <span>Mes bulletins</span>
                </a>
                @endrole

                {{-- Workflow validation section --}}
                @role('pedagogie|finance|direction|admin')
                <p class="px-3 pt-4 pb-1 text-xs font-bold uppercase tracking-widest text-base-content/30">🔄 Validation</p>
                <a href="{{ route('bulletins.index') }}" wire:navigate
                   onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('bulletins.index') ? 'active bg-blue-50 text-blue-700' : 'text-base-content/70' }}">
                    <span class="text-base w-5 text-center shrink-0">🔄</span>
                    <span class="flex-1 min-w-0">Workflow bulletins</span>
                    @php
                        $pendingCount = \App\Models\Bulletin::where('status', match(true) {
                            auth()->user()->hasRole('finance')   => \App\Enums\BulletinStatusEnum::PEDAGOGIE_APPROVED->value,
                            auth()->user()->hasRole('direction') => \App\Enums\BulletinStatusEnum::FINANCE_APPROVED->value,
                            default                             => \App\Enums\BulletinStatusEnum::SUBMITTED->value,
                        })->count();
                    @endphp
                    @if($pendingCount > 0)
                    <span class="badge badge-sm bg-red-500 text-white border-0 shrink-0">{{ $pendingCount }}</span>
                    @endif
                </a>
                <a href="{{ route('bulletins.suivi') }}" wire:navigate
                   onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('bulletins.suivi') ? 'active bg-teal-50 text-teal-700' : 'text-base-content/70' }}">
                    <span class="text-base w-5 text-center shrink-0">📊</span>
                    <span>Suivi workflow</span>
                </a>
                @endrole

                @role('direction|admin')
                <a href="{{ route('bulletins.annual') }}" wire:navigate
                   onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('bulletins.annual') ? 'active bg-teal-50 text-teal-700' : 'text-base-content/70' }}">
                    <span class="text-base w-5 text-center shrink-0">📈</span>
                    <span>Bilan annuel</span>
                </a>
                @endrole

                {{-- Configuration section --}}
                @role('admin|direction')
                <p class="px-3 pt-4 pb-1 text-xs font-bold uppercase tracking-widest text-base-content/30">⚙️ Configuration</p>
                <a href="{{ route('setup.classrooms') }}" wire:navigate
                   onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('setup.classrooms') ? 'active bg-emerald-50 text-emerald-700' : 'text-base-content/70' }}">
                    <span class="text-base w-5 text-center shrink-0">🏛️</span>
                    <span>Classes</span>
                </a>
                <a href="{{ route('setup.subjects') }}" wire:navigate
                   onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('setup.subjects') ? 'active bg-emerald-50 text-emerald-700' : 'text-base-content/70' }}">
                    <span class="text-base w-5 text-center shrink-0">📚</span>
                    <span>Matières</span>
                </a>
                <a href="{{ route('setup.competences') }}" wire:navigate
                   onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('setup.competences') ? 'active bg-emerald-50 text-emerald-700' : 'text-base-content/70' }}">
                    <span class="text-base w-5 text-center shrink-0">🎯</span>
                    <span>Compétences</span>
                </a>
                <a href="{{ route('setup.students') }}" wire:navigate
                   onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('setup.students') ? 'active bg-emerald-50 text-emerald-700' : 'text-base-content/70' }}">
                    <span class="text-base w-5 text-center shrink-0">👥</span>
                    <span>Élèves</span>
                </a>
                @endrole

            </nav>

            {{-- Sidebar user card + logout --}}
            <div class="px-2 py-3 border-t border-base-200 space-y-2 shrink-0">
                <div class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl bg-base-200">
                    <div class="w-7 h-7 bg-linear-to-br from-amber-400 to-orange-500 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0">
                        {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold truncate text-base-content">{{ auth()->user()?->name }}</p>
                        <p class="text-xs text-base-content/40 capitalize truncate">
                            {{ auth()->user()?->getRoleNames()->join(', ') }}
                        </p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                        @csrf
                        <button type="submit"
                            class="btn btn-xs btn-square btn-error btn-outline hover:btn-error"
                            title="Déconnexion">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>

        </aside>
    </div>
</div>

<x-toast />

<script>
    function closeSidebar() {
        const toggle = document.getElementById('sidebar-drawer');
        if (toggle && window.innerWidth < 1024) {
            toggle.checked = false;
        }
    }
</script>

@livewireScripts
</body>
</html>
