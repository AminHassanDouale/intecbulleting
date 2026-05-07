<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'INTEC École') }} — @yield('title', 'Gestion des bulletins')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    {{-- Lock light mode: prevent OS dark-mode preference from overriding DaisyUI --}}
    <style>
        :root { color-scheme: light only; }
    </style>
</head>
<body class="min-h-screen bg-base-200 font-sans">

{{-- Barre de navigation --}}
<div class="navbar bg-primary text-primary-content shadow-lg px-4">
    <div class="flex-1">
        <a href="{{ route('dashboard') }}" class="btn btn-ghost text-xl font-bold">
            🏫 INTEC École
        </a>
    </div>
    <div class="flex-none gap-2">
        {{-- Notifications --}}
        <div class="dropdown dropdown-end">
            <button tabindex="0" class="btn btn-ghost btn-circle">
                <div class="indicator">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    @if(auth()->user()?->unreadNotifications->count())
                        <span class="badge badge-xs badge-warning indicator-item">
                            {{ auth()->user()->unreadNotifications->count() }}
                        </span>
                    @endif
                </div>
            </button>
        </div>

        {{-- Menu utilisateur --}}
        <div class="dropdown dropdown-end">
            <button tabindex="0" class="btn btn-ghost btn-circle avatar placeholder">
                <div class="bg-neutral-focus text-neutral-content rounded-full w-10">
                    <span class="text-sm">{{ substr(auth()->user()?->name ?? 'U', 0, 1) }}</span>
                </div>
            </button>
            <ul tabindex="0" class="menu menu-sm dropdown-content mt-3 z-[1] p-2 shadow bg-base-100 rounded-box w-52 text-base-content">
                <li class="menu-title"><span>{{ auth()->user()?->name }}</span></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">Déconnexion</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</div>

<div class="flex">
    {{-- Sidebar --}}
    <aside class="w-64 min-h-screen bg-base-100 shadow-md">
        <ul class="menu p-4 gap-1">
            <li>
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    📊 Tableau de bord
                </a>
            </li>

            @role('teacher|admin')
            <li class="menu-title"><span>Bulletins</span></li>
            <li>
                <a href="{{ route('bulletins.grade-form') }}"
                   class="{{ request()->routeIs('bulletins.grade-form') ? 'active' : '' }}">
                    ✏️ Saisie des notes
                </a>
            </li>
            <li>
                <a href="{{ route('bulletins.index') }}"
                   class="{{ request()->routeIs('bulletins.index') ? 'active' : '' }}">
                    📋 Mes bulletins
                </a>
            </li>
            @endrole

            @role('pedagogie|finance|direction|admin')
            <li class="menu-title"><span>Validation</span></li>
            <li>
                <a href="{{ route('bulletins.index') }}"
                   class="{{ request()->routeIs('bulletins.index') ? 'active' : '' }}">
                    🔄 Workflow bulletins
                </a>
            </li>
            @endrole

            @role('admin|direction')
            <li class="menu-title"><span>Configuration</span></li>
            <li>
                <a href="{{ route('setup.classrooms') }}"
                   class="{{ request()->routeIs('setup.classrooms') ? 'active' : '' }}">
                    🏛️ Classes
                </a>
            </li>
            <li>
                <a href="{{ route('setup.subjects') }}"
                   class="{{ request()->routeIs('setup.subjects') ? 'active' : '' }}">
                    📚 Matières
                </a>
            </li>
            <li>
                <a href="{{ route('setup.competences') }}"
                   class="{{ request()->routeIs('setup.competences') ? 'active' : '' }}">
                    🎯 Compétences
                </a>
            </li>
            <li>
                <a href="{{ route('setup.students') }}"
                   class="{{ request()->routeIs('setup.students') ? 'active' : '' }}">
                    👥 Élèves
                </a>
            </li>
            @endrole
        </ul>
    </aside>

    {{-- Contenu principal --}}
    <main class="flex-1 p-6">
        {{ $slot }}
    </main>
</div>

@livewireScripts
</body>
</html>
