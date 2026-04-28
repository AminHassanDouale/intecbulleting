<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Connexion — INTEC École' }}</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/in tech.jpg') }}">
    <link rel="shortcut icon" type="image/jpeg" href="{{ asset('images/in tech.jpg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        * { font-family: 'Inter', sans-serif; box-sizing: border-box; }

        body {
            min-height: 100vh;
            display: flex;
            background: #0f172a;
            position: relative;
            overflow: hidden;
        }

        /* ── Animated background ─────────────────────────────── */
        .bg-canvas {
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 40%, #0c4a6e 70%, #0f172a 100%);
            background-size: 400% 400%;
            animation: gradient-shift 18s ease infinite;
            z-index: 0;
        }

        @keyframes gradient-shift {
            0%,100% { background-position: 0% 50%; }
            50%      { background-position: 100% 50%; }
        }

        /* Dot grid */
        .dot-grid {
            position: fixed;
            inset: 0;
            opacity: 0.07;
            background-image: radial-gradient(circle, rgba(255,255,255,0.5) 1px, transparent 1px);
            background-size: 30px 30px;
            z-index: 1;
        }

        /* Glow blobs */
        .blob {
            position: fixed;
            border-radius: 9999px;
            filter: blur(80px);
            pointer-events: none;
            z-index: 1;
        }
        .blob-1 { width: 500px; height: 500px; background: rgba(96,165,250,0.12); top: -150px; left: -150px; }
        .blob-2 { width: 400px; height: 400px; background: rgba(245,158,11,0.08); bottom: -100px; right: -100px; }
        .blob-3 { width: 300px; height: 300px; background: rgba(139,92,246,0.08); top: 50%; left: 50%; transform: translate(-50%,-50%); }

        /* ── Glass utilities ─────────────────────────────────── */
        .glass {
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255,255,255,0.12);
        }

        /* ── Left panel ─────────────────────────────────────── */
        .left-panel {
            position: relative;
            z-index: 10;
            display: none;
            flex-direction: column;
            justify-content: space-between;
            padding: 3rem;
        }

        @media (min-width: 1024px) {
            .left-panel { display: flex; width: 50%; }
        }
        @media (min-width: 1280px) {
            .left-panel { width: 55%; }
        }

        /* Gradient text */
        .text-gradient {
            background: linear-gradient(90deg, #60a5fa 0%, #fbbf24 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Feature pills */
        .feat-pill {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 9999px;
            color: rgba(255,255,255,0.85);
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .feat-pill:hover {
            background: rgba(255,255,255,0.14);
            border-color: rgba(245,158,11,0.4);
            color: #fbbf24;
        }

        /* Stat card */
        .stat-card {
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s;
        }
        .stat-card:hover {
            background: rgba(255,255,255,0.10);
            border-color: rgba(245,158,11,0.3);
            transform: translateY(-2px);
        }
        .stat-num {
            font-size: 1.75rem;
            font-weight: 900;
            background: linear-gradient(135deg, #60a5fa, #fbbf24);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* ── Right panel ─────────────────────────────────────── */
        .right-panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            position: relative;
            z-index: 10;
        }

        /* Form card */
        .form-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 0 32px 80px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.1);
        }

        /* Input */
        .input-field {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 12px;
            color: #f1f5f9;
            font-size: 0.875rem;
            transition: all 0.2s;
            outline: none;
        }
        .input-field::placeholder { color: rgba(255,255,255,0.3); }
        .input-field:focus {
            background: rgba(255,255,255,0.10);
            border-color: rgba(96,165,250,0.6);
            box-shadow: 0 0 0 3px rgba(96,165,250,0.15);
        }

        /* Checkbox */
        .check-custom {
            width: 1rem;
            height: 1rem;
            border-radius: 4px;
            border: 1.5px solid rgba(255,255,255,0.3);
            background: rgba(255,255,255,0.07);
            cursor: pointer;
            accent-color: #f59e0b;
        }

        /* Submit button */
        .btn-submit {
            width: 100%;
            padding: 0.9rem 1.5rem;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #0f172a;
            font-weight: 800;
            font-size: 0.9rem;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.25s;
            box-shadow: 0 8px 24px rgba(245,158,11,0.35);
            letter-spacing: 0.025em;
        }
        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 32px rgba(245,158,11,0.45);
        }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        @keyframes pulse-glow {
            0%,100% { box-shadow: 0 8px 24px rgba(245,158,11,0.35); }
            50%      { box-shadow: 0 8px 40px rgba(245,158,11,0.55); }
        }
        .btn-submit:not(:disabled) { animation: pulse-glow 3s ease-in-out infinite; }
        .btn-submit:hover { animation: none; }

        /* Float animation for logo */
        @keyframes float {
            0%,100% { transform: translateY(0px); }
            50%      { transform: translateY(-6px); }
        }
        .float-logo { animation: float 5s ease-in-out infinite; }

        /* Error box */
        .error-box {
            background: rgba(239,68,68,0.12);
            border: 1px solid rgba(239,68,68,0.3);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            color: #fca5a5;
            font-size: 0.8rem;
        }

        /* Label */
        .field-label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.5);
            margin-bottom: 6px;
        }

        /* Icon wrapper inside input */
        .input-wrap { position: relative; }
        .input-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.3);
            pointer-events: none;
        }
    </style>
</head>
<body>

    <div class="bg-canvas"></div>
    <div class="dot-grid"></div>
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>

    {{-- ── LEFT: Branding ─────────────────────────────────────────────────────── --}}
    <div class="left-panel">

        {{-- Top logo --}}
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl overflow-hidden ring-2 ring-white/20 hover:ring-amber-400/50 transition-all shadow-lg">
                <img src="{{ asset('images/in tech.jpg') }}" alt="INTEC" class="w-full h-full object-cover">
            </div>
            <div>
                <p class="text-white font-black text-lg leading-none tracking-wide">inTEC</p>
                <p class="text-white/40 text-[9px] tracking-widest uppercase">École — Djibouti</p>
            </div>
        </div>

        {{-- Centre content --}}
        <div class="space-y-10">
            <div class="space-y-5">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold"
                     style="background:rgba(245,158,11,0.15);border:1px solid rgba(245,158,11,0.3);color:#fbbf24;">
                    🎓 Espace Administration
                </div>
                <h1 class="text-4xl xl:text-5xl font-black text-white leading-tight">
                    Gestion des<br>
                    <span class="text-gradient">Bulletins Scolaires</span>
                </h1>
                <p class="text-white/60 text-base leading-relaxed max-w-sm">
                    Suivez les notes, validez les bulletins et publiez les carnets en toute simplicité pour l'année 2026-2027.
                </p>
            </div>

            {{-- Feature pills --}}
            <div class="flex flex-wrap gap-2">
                @foreach(['✏️ Saisie des notes', '📚 Workflow pédagogique', '💰 Validation finance', '🏛️ Approbation direction', '📄 Publication PDF'] as $feat)
                <span class="feat-pill">{{ $feat }}</span>
                @endforeach
            </div>

            {{-- Stat strip --}}
            <div class="grid grid-cols-3 gap-3">
                @php
                    $stats = [
                        ['icon'=>'👥','value'=> \App\Models\Student::count(),                                                             'label'=>'Élèves'],
                        ['icon'=>'🏛️','value'=> \App\Models\Classroom::count(),                                                           'label'=>'Classes'],
                        ['icon'=>'🧑‍🏫','value'=> \App\Models\User::whereHas('roles', fn($q) => $q->where('name','teacher'))->count(), 'label'=>'Enseignants'],
                    ];
                @endphp
                @foreach($stats as $s)
                <div class="stat-card">
                    <p class="text-xl mb-1">{{ $s['icon'] }}</p>
                    <p class="stat-num">{{ $s['value'] }}</p>
                    <p class="text-white/40 text-xs mt-0.5">{{ $s['label'] }}</p>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Bottom --}}
        <div>
            <p class="text-white/25 text-xs">Année scolaire {{ \App\Models\AcademicYear::where('is_current', true)->value('label') ?? date('Y') }}</p>
        </div>
    </div>

    {{-- ── RIGHT: Form ─────────────────────────────────────────────────────────── --}}
    <div class="right-panel">
        <div class="w-full max-w-md">

            {{-- Mobile logo --}}
            <div class="lg:hidden text-center mb-8">
                <div class="w-16 h-16 rounded-2xl overflow-hidden ring-2 ring-white/20 shadow-lg mx-auto float-logo">
                    <img src="{{ asset('images/in tech.jpg') }}" alt="INTEC" class="w-full h-full object-cover">
                </div>
                <p class="text-white font-black text-xl mt-3 tracking-wide">inTEC <span class="text-gradient">École</span></p>
            </div>

            {{-- Card --}}
            <div class="form-card">
                {{ $slot }}
            </div>

            {{-- Footer link --}}
            <p class="text-center text-xs text-white/25 mt-6">
                INTEC École &copy; {{ date('Y') }}
            </p>
        </div>
    </div>

@livewireScripts
</body>
</html>
