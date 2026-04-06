<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name', 'INTEC École') }}</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/in tech.jpg') }}">
    <link rel="shortcut icon" type="image/jpeg" href="{{ asset('images/in tech.jpg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen flex relative overflow-hidden bg-slate-100">

    {{-- ── Left panel: branding (hidden on mobile) ──────────────────────────── --}}
    <div class="hidden lg:flex lg:w-1/2 xl:w-3/5 relative flex-col justify-between p-12 overflow-hidden
                bg-linear-to-br from-indigo-700 via-indigo-600 to-violet-700">

        {{-- Decorative blobs --}}
        <div class="absolute -top-20 -left-20 w-80 h-80 bg-white/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute top-1/3 -right-24 w-72 h-72 bg-violet-300/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-16 left-1/3 w-64 h-64 bg-indigo-300/15 rounded-full blur-3xl pointer-events-none"></div>

        {{-- Dot grid overlay --}}
        <div class="absolute inset-0 opacity-10"
             style="background-image: radial-gradient(circle, rgba(255,255,255,0.4) 1px, transparent 1px);
                    background-size: 28px 28px;"></div>

        {{-- Top logo --}}
        <div class="relative z-10">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-white/20 backdrop-blur rounded-xl flex items-center justify-center shadow-lg ring-1 ring-white/30 overflow-hidden">
                    <img src="{{ asset('images/in tech.jpg') }}" alt="INTEC Logo" class="w-full h-full object-cover">
                </div>
                <div>
                    <p class="text-white font-black text-lg leading-none">INTEC École</p>
                    <p class="text-indigo-200/70 text-xs">Système de gestion scolaire</p>
                </div>
            </div>
        </div>

        {{-- Centre copy --}}
        <div class="relative z-10 space-y-8">
            <div class="space-y-4">
                <h1 class="text-4xl xl:text-5xl font-black text-white leading-tight">
                    Gestion des<br>
                    <span class="text-transparent bg-clip-text bg-linear-to-r from-amber-300 to-yellow-200">
                        Bulletins Scolaires
                    </span>
                </h1>
                <p class="text-indigo-100/80 text-lg leading-relaxed max-w-sm">
                    Suivez les notes, validez les bulletins et publiez les carnets en toute simplicité.
                </p>
            </div>

            {{-- Feature pills --}}
            <div class="flex flex-wrap gap-2">
                @foreach(['✏️ Saisie des notes', '📚 Workflow pédagogique', '💰 Validation finance', '🏛️ Approbation direction', '📄 Publication PDF'] as $feat)
                <span class="px-3 py-1.5 bg-white/10 backdrop-blur border border-white/20 rounded-full text-white/90 text-xs font-medium">
                    {{ $feat }}
                </span>
                @endforeach
            </div>

            {{-- Stats strip --}}
            <div class="grid grid-cols-3 gap-4">
                @foreach([
                    ['label' => 'Élèves',       'value' => \App\Models\Student::count(),             'icon' => '👥'],
                    ['label' => 'Classes',       'value' => \App\Models\Classroom::count(),           'icon' => '🏛️'],
                    ['label' => 'Enseignants',   'value' => \App\Models\User::role('teacher')->count(),'icon' => '🧑‍🏫'],
                ] as $stat)
                <div class="bg-white/10 backdrop-blur border border-white/15 rounded-2xl p-4 text-center">
                    <p class="text-2xl mb-0.5">{{ $stat['icon'] }}</p>
                    <p class="text-2xl font-black text-white">{{ $stat['value'] }}</p>
                    <p class="text-indigo-200/60 text-xs mt-0.5">{{ $stat['label'] }}</p>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Bottom year --}}
        <div class="relative z-10">
            <p class="text-indigo-200/40 text-xs">Année scolaire {{ \App\Models\AcademicYear::where('is_current', true)->value('label') ?? date('Y') }}</p>
        </div>
    </div>

    {{-- ── Right panel: login form ───────────────────────────────────────────── --}}
    <div class="flex-1 flex items-center justify-center p-6 sm:p-10 bg-slate-50 relative">

        {{-- Subtle background texture --}}
        <div class="absolute inset-0 opacity-40"
             style="background-image: radial-gradient(circle at 80% 20%, #e0e7ff 0%, transparent 50%),
                                      radial-gradient(circle at 20% 80%, #ede9fe 0%, transparent 50%);"></div>

        <div class="relative z-10 w-full max-w-md">
            {{ $slot }}
        </div>
    </div>

@livewireScripts
</body>
</html>
