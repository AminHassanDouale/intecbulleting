<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name', 'INTEC École') }}</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/in tech.jpg') }}">
    <link rel="shortcut icon" type="image/jpeg" href="{{ asset('images/in tech.jpg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen flex relative overflow-hidden bg-blue-950">

    {{-- ── Left panel: branding (hidden on mobile) ──────────────────────────── --}}
    <div class="hidden lg:flex lg:w-1/2 xl:w-3/5 relative flex-col justify-between p-12 overflow-hidden
                bg-linear-to-br from-blue-800 via-blue-700 to-blue-900">

        {{-- Decorative circles --}}
        <div class="absolute -top-24 -left-24 w-96 h-96 bg-yellow-400/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute top-1/3 -right-32 w-80 h-80 bg-yellow-300/15 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-20 left-1/4 w-72 h-72 bg-blue-300/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute top-2/3 left-1/3 w-48 h-48 bg-yellow-200/10 rounded-full blur-2xl pointer-events-none"></div>

        {{-- Grid pattern overlay --}}
        <div class="absolute inset-0 opacity-5"
             style="background-image: linear-gradient(rgba(255,255,255,.15) 1px, transparent 1px),
                                      linear-gradient(90deg, rgba(255,255,255,.15) 1px, transparent 1px);
                    background-size: 40px 40px;"></div>

        {{-- Top logo --}}
        <div class="relative z-10">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-yellow-400/20 backdrop-blur rounded-xl flex items-center justify-center shadow-lg ring-1 ring-yellow-400/30 overflow-hidden">
                    <img src="{{ asset('images/in tech.jpg') }}" alt="INTEC Logo" class="w-full h-full object-cover">
                </div>
                <div>
                    <p class="text-white font-black text-lg leading-none"><span class="text-yellow-400">IN</span>TEC École</p>
                    <p class="text-yellow-300/60 text-xs">Système de gestion</p>
                </div>
            </div>
        </div>

        {{-- Centre copy --}}
        <div class="relative z-10 space-y-8">
            <div class="space-y-4">
                <h1 class="text-4xl xl:text-5xl font-black text-white leading-tight">
                    Gestion des<br>
                    <span class="text-transparent bg-clip-text bg-linear-to-r from-yellow-300 to-amber-400">
                        Bulletins Scolaires
                    </span>
                </h1>
                <p class="text-blue-100/70 text-lg leading-relaxed max-w-sm">
                    Suivez les notes, validez les bulletins et publiez les carnets en toute simplicité.
                </p>
            </div>

            {{-- Feature pills --}}
            <div class="flex flex-wrap gap-2">
                @foreach(['✏️ Saisie des notes', '📚 Workflow pédagogique', '💰 Validation finance', '🏛️ Approbation direction', '📄 Publication PDF'] as $feat)
                <span class="px-3 py-1.5 bg-yellow-400/10 backdrop-blur border border-yellow-400/20 rounded-full text-yellow-100/80 text-xs font-medium">
                    {{ $feat }}
                </span>
                @endforeach
            </div>

            {{-- Stats strip --}}
            <div class="grid grid-cols-3 gap-4">
                @foreach([['label' => 'Élèves', 'value' => \App\Models\Student::count()],
                           ['label' => 'Classes', 'value' => \App\Models\Classroom::count()],
                           ['label' => 'Enseignants', 'value' => \App\Models\User::role('teacher')->count()]] as $stat)
                <div class="bg-white/5 backdrop-blur border border-yellow-400/15 rounded-2xl p-4 text-center">
                    <p class="text-2xl font-black text-yellow-300">{{ $stat['value'] }}</p>
                    <p class="text-blue-200/50 text-xs mt-0.5">{{ $stat['label'] }}</p>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Bottom year --}}
        <div class="relative z-10">
            <p class="text-blue-200/30 text-xs">Année scolaire {{ \App\Models\AcademicYear::where('is_current', true)->value('label') ?? date('Y') }}</p>
        </div>
    </div>

    {{-- ── Right panel: login form ───────────────────────────────────────────── --}}
    <div class="flex-1 flex items-center justify-center p-6 sm:p-10 bg-blue-950 relative">

        {{-- Subtle radial glow --}}
        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
            <div class="w-[600px] h-[600px] bg-yellow-400/5 rounded-full blur-3xl"></div>
            <div class="absolute w-[300px] h-[300px] bg-blue-500/15 rounded-full blur-2xl"></div>
        </div>

        <div class="relative z-10 w-full max-w-md">
            {{ $slot }}
        </div>
    </div>

@livewireScripts
</body>
</html>
