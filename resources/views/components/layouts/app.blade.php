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
        /* ── Sidebar nav links ──────────────────────────────────────────────── */
        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            padding: 0.5rem 0.875rem;
            border-radius: 0.625rem;
            font-size: 0.8125rem;
            font-weight: 500;
            transition: all 0.15s ease;
            color: #64748b;
            text-decoration: none;
            width: 100%;
        }
        .nav-link:hover {
            background: #f1f5f9;
            color: #1e293b;
        }
        .nav-link.active {
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 600;
        }
        .nav-link .nav-icon {
            width: 1.75rem;
            height: 1.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            flex-shrink: 0;
        }
        .nav-link.active .nav-icon {
            background: #dbeafe;
        }

        /* ── Section labels ─────────────────────────────────────────────────── */
        .nav-section {
            padding: 1rem 0.875rem 0.25rem;
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #94a3b8;
        }

        /* ── Vibrant toast alerts ───────────────────────────────────────────── */
        [class*="toast"] .alert {
            min-width: 280px;
            max-width: 380px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.18), 0 4px 12px rgba(0,0,0,0.10);
            border-radius: 0.875rem;
            padding: 0.875rem 1rem;
            border: none;
            font-size: 0.875rem;
        }
        [class*="toast"] .alert-success  { background: linear-gradient(135deg,#16a34a,#15803d); color:#fff; }
        [class*="toast"] .alert-error    { background: linear-gradient(135deg,#dc2626,#b91c1c); color:#fff; }
        [class*="toast"] .alert-warning  { background: linear-gradient(135deg,#d97706,#b45309); color:#fff; }
        [class*="toast"] .alert-info     { background: linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; }
        [class*="toast"] .alert svg      { color: rgba(255,255,255,0.85); }
        [class*="toast"] .alert .font-bold { color:#fff; }
        [class*="toast"] .alert .text-xs   { color:rgba(255,255,255,0.80); }
        [class*="toast"] progress.progress { opacity:0.35; }
        [class*="toast"] progress.progress::-webkit-progress-value { background:#fff; }
        [class*="toast"] progress.progress::-moz-progress-bar { background:#fff; }
    </style>
</head>
<body class="min-h-screen bg-slate-100 font-sans antialiased">

@php $yr = \App\Models\AcademicYear::current(); @endphp

<div class="drawer lg:drawer-open">
    <input id="sidebar-drawer" type="checkbox" class="drawer-toggle" />

    {{-- ── Main content ──────────────────────────────────────────────────── --}}
    <div class="drawer-content flex flex-col min-h-screen min-w-0">

        {{-- ── Top navbar ────────────────────────────────────────────────── --}}
        <header class="sticky top-0 z-30 h-14 bg-white border-b border-slate-200 shadow-sm flex items-center gap-3 px-4">

            {{-- Mobile hamburger --}}
            <label for="sidebar-drawer" class="btn btn-ghost btn-square btn-sm lg:hidden text-slate-500 hover:bg-slate-100">
                <svg fill="none" viewBox="0 0 24 24" class="h-5 w-5 stroke-current stroke-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/>
                </svg>
            </label>

            {{-- Page breadcrumb / title area --}}
            <div class="flex-1 flex items-center gap-3 min-w-0">
                @if($yr)
                <span class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 bg-blue-50 text-blue-700 rounded-full text-xs font-semibold border border-blue-100">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    {{ $yr->label }}
                </span>
                @endif
            </div>

            {{-- Right actions --}}
            <div class="flex items-center gap-1.5">

                {{-- Notifications --}}
                @php $notifCount = auth()->user()?->unreadNotifications->count() ?? 0; @endphp
                <div class="dropdown dropdown-end">
                    <button tabindex="0" class="btn btn-ghost btn-square btn-sm text-slate-500 hover:bg-slate-100 relative">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        @if($notifCount)
                        <span class="absolute -top-0.5 -right-0.5 w-4 h-4 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center">{{ $notifCount }}</span>
                        @endif
                    </button>
                    <div tabindex="0" class="dropdown-content z-50 mt-2 w-72 shadow-xl bg-white rounded-xl overflow-hidden border border-slate-200">
                        <div class="px-4 py-2.5 border-b border-slate-100 flex items-center justify-between">
                            <p class="font-semibold text-sm text-slate-700">Notifications</p>
                            @if($notifCount)
                            <span class="badge badge-sm badge-error">{{ $notifCount }}</span>
                            @endif
                        </div>
                        <div class="max-h-64 overflow-y-auto divide-y divide-slate-100">
                            @forelse(auth()->user()?->unreadNotifications->take(6) ?? [] as $notif)
                            <div class="px-4 py-3 text-xs hover:bg-slate-50">
                                <p class="text-slate-700 font-medium">{{ $notif->data['message'] ?? 'Notification' }}</p>
                                <p class="text-slate-400 mt-0.5">{{ $notif->created_at->diffForHumans() }}</p>
                            </div>
                            @empty
                            <div class="px-4 py-6 text-center text-xs text-slate-400">
                                <svg class="w-8 h-8 mx-auto mb-2 text-slate-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                                Aucune notification
                            </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Divider --}}
                <div class="w-px h-6 bg-slate-200"></div>

                {{-- User menu --}}
                <div class="dropdown dropdown-end">
                    <button tabindex="0" class="flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg hover:bg-slate-100 transition-colors">
                        <div class="w-7 h-7 bg-linear-to-br from-indigo-500 to-violet-600 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0">
                            {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
                        </div>
                        <div class="hidden md:block text-left leading-tight">
                            <p class="text-xs font-semibold text-slate-700">{{ Str::limit(auth()->user()?->name ?? '', 18) }}</p>
                            <p class="text-[11px] text-slate-400 capitalize">{{ auth()->user()?->getRoleNames()->first() ?? '' }}</p>
                        </div>
                        <svg class="h-3.5 w-3.5 text-slate-400 hidden md:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div tabindex="0" class="dropdown-content z-50 mt-2 w-52 shadow-xl bg-white rounded-xl border border-slate-200 overflow-hidden p-1.5">
                        <div class="px-3 py-2.5 rounded-lg bg-slate-50 mb-1.5">
                            <p class="font-semibold text-sm text-slate-800 truncate">{{ auth()->user()?->name }}</p>
                            <div class="flex flex-wrap gap-1 mt-1">
                                @foreach(auth()->user()?->getRoleNames() ?? [] as $r)
                                <span class="badge badge-xs badge-primary">{{ $r }}</span>
                                @endforeach
                            </div>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                Déconnexion
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </header>

        {{-- ── Page content ───────────────────────────────────────────────── --}}
        <main class="flex-1 p-4 lg:p-6 overflow-x-hidden min-w-0">
            {{ $slot }}
        </main>

        {{-- ── Footer ─────────────────────────────────────────────────────── --}}
        <footer class="py-2.5 px-6 bg-white border-t border-slate-200 text-center text-xs text-slate-400">
            INTEC École &copy; {{ date('Y') }} — Système de Gestion Scolaire
        </footer>
    </div>

    {{-- ── Sidebar ────────────────────────────────────────────────────────── --}}
    <div class="drawer-side z-40">
        <label for="sidebar-drawer" aria-label="close sidebar" class="drawer-overlay"></label>

        <aside class="min-h-full w-60 bg-white flex flex-col border-r border-slate-200">

            {{-- Logo / Brand --}}
            <div class="flex items-center gap-3 px-4 h-14 border-b border-slate-200 shrink-0">
                <div class="w-8 h-8 rounded-lg overflow-hidden shrink-0 bg-slate-100 p-0.5">
                    <img src="{{ asset('images/in tech.jpg') }}" alt="INTEC" class="w-full h-full object-contain">
                </div>
                <div class="min-w-0">
                    <p class="font-bold text-sm text-slate-800 leading-tight">INTEC École</p>
                    <p class="text-[11px] text-slate-400">Gestion Scolaire</p>
                </div>
            </div>

            {{-- Navigation --}}
            <nav class="flex-1 px-2.5 py-3 space-y-0.5 overflow-y-auto">

                {{-- Dashboard --}}
                <a href="{{ route('dashboard') }}" wire:navigate onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <span class="nav-icon {{ request()->routeIs('dashboard') ? 'bg-blue-100' : '' }}">📊</span>
                    Tableau de bord
                </a>

                {{-- Bulletins (teacher + admin + direction) --}}
                @role('teacher|admin|direction')
                <p class="nav-section">Bulletins</p>
                <a href="{{ route('bulletins.grade-form') }}" wire:navigate onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('bulletins.grade-form') ? 'active' : '' }}">
                    <span class="nav-icon {{ request()->routeIs('bulletins.grade-form') ? 'bg-blue-100' : '' }}">✏️</span>
                    Saisie des notes
                </a>
                @endrole

                @role('teacher')
                <a href="{{ route('bulletins.index') }}" wire:navigate onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('bulletins.index') ? 'active' : '' }}">
                    <span class="nav-icon {{ request()->routeIs('bulletins.index') ? 'bg-blue-100' : '' }}">📋</span>
                    Mes bulletins
                </a>
                @endrole

                {{-- Validation workflow --}}
                @role('pedagogie|finance|direction|admin')
                <p class="nav-section">Validation</p>
                <a href="{{ route('bulletins.index') }}" wire:navigate onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('bulletins.index') ? 'active' : '' }}">
                    <span class="nav-icon {{ request()->routeIs('bulletins.index') ? 'bg-blue-100' : '' }}">🔄</span>
                    <span class="flex-1 truncate">Workflow bulletins</span>
                    @php
                        $pendingCount = \App\Models\Bulletin::where('status', match(true) {
                            auth()->user()->hasRole('finance')   => \App\Enums\BulletinStatusEnum::PEDAGOGIE_APPROVED->value,
                            auth()->user()->hasRole('direction') => \App\Enums\BulletinStatusEnum::FINANCE_APPROVED->value,
                            default                             => \App\Enums\BulletinStatusEnum::SUBMITTED->value,
                        })->count();
                    @endphp
                    @if($pendingCount > 0)
                    <span class="badge badge-xs bg-red-500 text-white border-0 shrink-0">{{ $pendingCount }}</span>
                    @endif
                </a>
                <a href="{{ route('bulletins.suivi') }}" wire:navigate onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('bulletins.suivi') ? 'active' : '' }}">
                    <span class="nav-icon {{ request()->routeIs('bulletins.suivi') ? 'bg-blue-100' : '' }}">📈</span>
                    Suivi workflow
                </a>
                @endrole

                @role('direction|admin')
                <a href="{{ route('bulletins.annual') }}" wire:navigate onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('bulletins.annual') ? 'active' : '' }}">
                    <span class="nav-icon {{ request()->routeIs('bulletins.annual') ? 'bg-blue-100' : '' }}">📊</span>
                    Bilan annuel
                </a>
                @endrole

                @role('direction|admin')
                <a href="{{ route('bulletins.template-preview') }}" wire:navigate onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('bulletins.template-preview') ? 'active' : '' }}">
                    <span class="nav-icon {{ request()->routeIs('bulletins.template-preview') ? 'bg-blue-100' : '' }}">🖨️</span>
                    Modèles bulletin
                </a>
                @endrole

                {{-- Configuration --}}
                @role('admin|direction')
                <p class="nav-section">Configuration</p>
                <a href="{{ route('setup.classrooms') }}" wire:navigate onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('setup.classrooms') ? 'active' : '' }}">
                    <span class="nav-icon {{ request()->routeIs('setup.classrooms') ? 'bg-blue-100' : '' }}">🏛️</span>
                    Classes
                </a>
                <a href="{{ route('setup.subjects') }}" wire:navigate onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('setup.subjects') ? 'active' : '' }}">
                    <span class="nav-icon {{ request()->routeIs('setup.subjects') ? 'bg-blue-100' : '' }}">📚</span>
                    Matières
                </a>
                <a href="{{ route('setup.competences') }}" wire:navigate onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('setup.competences') ? 'active' : '' }}">
                    <span class="nav-icon {{ request()->routeIs('setup.competences') ? 'bg-blue-100' : '' }}">🎯</span>
                    Compétences
                </a>
                <a href="{{ route('setup.students') }}" wire:navigate onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('setup.students') ? 'active' : '' }}">
                    <span class="nav-icon {{ request()->routeIs('setup.students') ? 'bg-blue-100' : '' }}">👥</span>
                    Élèves
                </a>
                <a href="{{ route('setup.seuils') }}" wire:navigate onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('setup.seuils') ? 'active' : '' }}">
                    <span class="nav-icon {{ request()->routeIs('setup.seuils') ? 'bg-blue-100' : '' }}">⚙️</span>
                    Seuils d'admission
                </a>
                <a href="{{ route('setup.teachers') }}" wire:navigate onclick="closeSidebar()"
                   class="nav-link {{ request()->routeIs('setup.teachers') ? 'active' : '' }}">
                    <span class="nav-icon {{ request()->routeIs('setup.teachers') ? 'bg-blue-100' : '' }}">🧑‍🏫</span>
                    Enseignants
                </a>
                @endrole

            </nav>

            {{-- User card at bottom --}}
            <div class="px-3 py-3 border-t border-slate-200 shrink-0">
                <div class="flex items-center gap-2.5 p-2.5 rounded-xl bg-slate-50">
                    <div class="w-8 h-8 bg-linear-to-br from-indigo-500 to-violet-600 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0">
                        {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold truncate text-slate-700">{{ auth()->user()?->name }}</p>
                        <p class="text-[11px] text-slate-400 capitalize truncate">
                            {{ auth()->user()?->getRoleNames()->join(', ') }}
                        </p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                        @csrf
                        <button type="submit" title="Déconnexion"
                            class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
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
        if (toggle && window.innerWidth < 1024) toggle.checked = false;
    }
</script>

@livewireScripts
</body>
</html>
