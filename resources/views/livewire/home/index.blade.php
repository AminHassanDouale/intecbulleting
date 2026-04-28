<?php
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\Student;
use App\Models\Classroom;
use App\Models\User;

new #[Layout('components.layouts.public')] class extends Component {
    public function with(): array
    {
        return [
            'totalStudents' => Student::count(),
            'totalClasses'  => Classroom::count(),
            'totalTeachers' => User::role('teacher')->count(),
        ];
    }
}; ?>

<div>

{{-- ════════════════════════════════════════════
     NAVBAR
════════════════════════════════════════════ --}}
<nav id="main-nav" class="fixed top-0 left-0 right-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">

            {{-- Logo --}}
            <a href="{{ route('home') }}" class="flex items-center gap-3 group">
                <div class="w-9 h-9 rounded-xl overflow-hidden ring-2 ring-white/20 group-hover:ring-amber-400/60 transition-all shadow-lg">
                    <img src="{{ asset('images/in tech.jpg') }}" alt="INTEC" class="w-full h-full object-cover">
                </div>
                <div>
                    <p class="text-white font-black text-base leading-none tracking-wide">inTEC</p>
                    <p class="text-white/40 text-[9px] tracking-widest uppercase">École — Djibouti</p>
                </div>
            </a>

            {{-- Desktop links --}}
            <div class="hidden md:flex items-center gap-7">
                <a href="#about"          class="nav-link">À propos</a>
                <a href="#programmes"     class="nav-link">Programmes</a>
                <a href="#galerie"        class="nav-link">Galerie</a>
                <a href="#preinscription" class="nav-link">Pré-inscription</a>
                <a href="#contact"        class="nav-link">Contact</a>
            </div>

            {{-- CTA --}}
            <div class="flex items-center gap-2.5">
                <a href="{{ route('preinscription') }}"
                   class="hidden sm:inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-bold text-amber-400 border border-amber-400/40 hover:border-amber-400 hover:bg-amber-400/10 transition-all duration-200">
                    📝 S'inscrire
                </a>
                <a href="{{ route('login') }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold glass text-white hover:bg-white/15 transition-all duration-200">
                    🔐 Connexion
                </a>
                <button id="mobile-btn" class="md:hidden text-white/80 hover:text-white p-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Mobile menu --}}
    <div id="mobile-menu" class="hidden md:hidden glass-dark border-t border-white/10">
        <div class="px-5 py-4 space-y-3">
            <a href="#about"          class="block text-white/80 hover:text-white py-2 text-sm">À propos</a>
            <a href="#programmes"     class="block text-white/80 hover:text-white py-2 text-sm">Programmes</a>
            <a href="#galerie"        class="block text-white/80 hover:text-white py-2 text-sm">Galerie</a>
            <a href="#preinscription" class="block text-white/80 hover:text-white py-2 text-sm">Pré-inscription</a>
            <a href="#contact"        class="block text-white/80 hover:text-white py-2 text-sm">Contact</a>
            <a href="{{ route('preinscription') }}" class="block text-amber-400 font-bold py-2 text-sm">📝 Pré-inscription 2026-2027</a>
        </div>
    </div>
</nav>

{{-- ════════════════════════════════════════════
     HERO
════════════════════════════════════════════ --}}
<section class="hero-gradient relative min-h-screen flex items-center overflow-hidden">
    <div class="hero-pattern absolute inset-0"></div>

    {{-- Orbs --}}
    <div class="absolute top-1/4 right-1/4 w-96 h-96 rounded-full opacity-15 animate-float"
         style="background:radial-gradient(circle,#1e3a8a,transparent 70%);"></div>
    <div class="absolute bottom-1/4 left-1/5 w-72 h-72 rounded-full opacity-10 animate-float-b"
         style="background:radial-gradient(circle,#f59e0b,transparent 70%);"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[700px] rounded-full opacity-5 animate-spin-slow border border-white/10"></div>

    {{-- Floating shapes --}}
    <div class="absolute top-32 right-24 animate-float delay-200">
        <div class="w-14 h-14 rounded-2xl rotate-12 glass flex items-center justify-center text-2xl shadow-xl">📚</div>
    </div>
    <div class="absolute top-56 right-10 animate-float-b delay-400">
        <div class="w-12 h-12 rounded-2xl -rotate-6 glass flex items-center justify-center text-xl">✏️</div>
    </div>
    <div class="absolute bottom-40 right-36 animate-float delay-600">
        <div class="w-10 h-10 rounded-xl rotate-3 glass flex items-center justify-center text-lg">🎓</div>
    </div>
    <div class="absolute top-44 left-10 animate-float-b delay-300 hidden lg:flex">
        <div class="w-11 h-11 rounded-xl -rotate-12 glass flex items-center justify-center text-xl">🔬</div>
    </div>

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-16 w-full">
        <div class="grid lg:grid-cols-2 gap-16 items-center">

            {{-- Left --}}
            <div class="space-y-8">
                <div class="animate-fade-up">
                    <div class="section-badge">
                        <span class="w-1.5 h-1.5 bg-amber-400 rounded-full animate-pulse"></span>
                        Pré-inscription 2026-2027 ouverte
                    </div>
                </div>

                <div class="animate-fade-up delay-100">
                    <h1 class="text-5xl xl:text-6xl font-black text-white leading-[1.05]">
                        Excellence<br>
                        Académique<br>
                        <span class="text-gradient">à Djibouti</span>
                    </h1>
                </div>

                <p class="animate-fade-up delay-200 text-white/60 text-lg leading-relaxed max-w-lg">
                    INTEC École offre un enseignement bilingue d'excellence, de la Petite Section jusqu'au Lycée, alliant pédagogie moderne, langues et technologies.
                </p>

                <div class="animate-fade-up delay-300 flex flex-wrap gap-4">
                    <a href="{{ route('preinscription') }}" class="btn-primary text-base shadow-2xl">
                        📝 S'inscrire Maintenant
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    </a>
                    <a href="#about" class="btn-outline text-base">Découvrir l'école</a>
                </div>

                <div class="animate-fade-up delay-400 flex flex-wrap gap-3">
                    @foreach(['🌍 Bilingue Français/Anglais','🏅 Qualité certifiée','💻 Pédagogie moderne'] as $b)
                    <span class="text-white/50 text-sm">{{ $b }}</span>
                    @endforeach
                </div>
            </div>

            {{-- Right: glass card --}}
            <div class="animate-slide-right delay-300 hidden lg:block">
                <div class="relative">
                    <div class="glass rounded-3xl p-8 shadow-2xl">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-14 h-14 rounded-2xl overflow-hidden ring-2 ring-amber-400/40 shadow-xl">
                                <img src="{{ asset('images/in tech.jpg') }}" alt="INTEC" class="w-full h-full object-cover">
                            </div>
                            <div>
                                <h3 class="text-white font-black text-xl">inTEC École</h3>
                                <p class="text-white/50 text-sm">Djibouti — Fondée 2015</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3 mb-6">
                            @foreach([
                                ['value'=>$totalStudents,'suffix'=>'+','label'=>'Élèves','color'=>'text-white'],
                                ['value'=>$totalClasses,'suffix'=>'','label'=>'Classes','color'=>'text-amber-400'],
                                ['value'=>$totalTeachers,'suffix'=>'','label'=>'Enseignants','color'=>'text-sky-400'],
                            ] as $s)
                            <div class="glass-dark rounded-2xl p-3 text-center">
                                <p class="text-2xl font-black {{ $s['color'] }}" data-target="{{ $s['value'] }}" data-suffix="{{ $s['suffix'] }}">0{{ $s['suffix'] }}</p>
                                <p class="text-white/50 text-xs mt-0.5">{{ $s['label'] }}</p>
                            </div>
                            @endforeach
                        </div>

                        <div>
                            <p class="text-white/40 text-xs font-semibold uppercase tracking-wider mb-2.5">Niveaux proposés</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach([
                                    'PS'=>'amber','MS'=>'amber','GS'=>'amber',
                                    'CP'=>'green','CE1'=>'green','CE2'=>'green','CM1'=>'green','CM2'=>'green',
                                    '6ème'=>'sky','5ème'=>'sky','4ème'=>'sky','3ème'=>'sky',
                                    '2nde'=>'violet','1ère'=>'violet','Tle'=>'violet',
                                ] as $n => $c)
                                @php $cls = match($c) {
                                    'amber'  => 'bg-amber-400/15 text-amber-300 border-amber-400/25',
                                    'green'  => 'bg-emerald-400/15 text-emerald-300 border-emerald-400/25',
                                    'sky'    => 'bg-sky-400/15 text-sky-300 border-sky-400/25',
                                    'violet' => 'bg-violet-400/15 text-violet-300 border-violet-400/25',
                                    default  => '',
                                }; @endphp
                                <span class="niveau-chip border {{ $cls }}">{{ $n }}</span>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-5 pt-4 border-t border-white/10 flex items-center justify-between">
                            <span class="text-white/30 text-xs">Année scolaire active</span>
                            <span class="text-amber-400 font-bold text-sm">
                                {{ \App\Models\AcademicYear::where('is_current',true)->value('label') ?? '2025-2026' }}
                            </span>
                        </div>
                    </div>

                    <div class="absolute -top-5 -right-5 animate-float bg-amber-400 text-slate-900 rounded-2xl px-4 py-2.5 shadow-2xl text-center">
                        <p class="text-2xl font-black">15</p>
                        <p class="text-xs font-semibold opacity-80">niveaux</p>
                    </div>
                    <div class="absolute -bottom-4 -left-4 animate-float-b glass rounded-2xl px-4 py-2.5 shadow-xl">
                        <p class="text-xs font-bold text-white">🎯 Pré-inscription</p>
                        <p class="text-xs text-white/50">2026-2027 ouverte</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Scroll indicator --}}
        <div class="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 animate-fade-in delay-700">
            <span class="text-white/30 text-[10px] tracking-widest uppercase">Découvrir</span>
            <div class="w-5 h-9 border border-white/20 rounded-full flex justify-center pt-1.5">
                <div class="w-1 h-2 bg-white/40 rounded-full animate-bounce"></div>
            </div>
        </div>
    </div>
</section>

{{-- ════════════════════════════════════════════
     STATS
════════════════════════════════════════════ --}}
<section style="background:linear-gradient(135deg,#0f172a,#1e293b);">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-8">
            @foreach([
                ['value'=>$totalStudents,'suffix'=>'+','label'=>'Élèves inscrits','icon'=>'👥'],
                ['value'=>$totalClasses,'suffix'=>'','label'=>'Classes actives','icon'=>'🏛️'],
                ['value'=>$totalTeachers,'suffix'=>'','label'=>'Enseignants qualifiés','icon'=>'🧑‍🏫'],
                ['value'=>15,'suffix'=>'','label'=>'Niveaux scolaires','icon'=>'🎓'],
            ] as $stat)
            <div class="reveal text-center">
                <p class="text-3xl mb-2">{{ $stat['icon'] }}</p>
                <p class="text-5xl font-black stat-number" data-target="{{ $stat['value'] }}" data-suffix="{{ $stat['suffix'] }}">0{{ $stat['suffix'] }}</p>
                <p class="text-white/50 text-sm mt-1.5">{{ $stat['label'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ════════════════════════════════════════════
     ABOUT
════════════════════════════════════════════ --}}
<section id="about" class="py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-16 items-center">

            <div class="reveal-left relative">
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-4">
                        <div class="rounded-3xl overflow-hidden h-52 shadow-xl">
                            <div class="w-full h-full flex items-center justify-center text-6xl"
                                 style="background:linear-gradient(135deg,#0f172a,#1e3a8a);">🏫</div>
                        </div>
                        <div class="rounded-3xl overflow-hidden h-36 shadow-xl">
                            <div class="w-full h-full flex items-center justify-center text-5xl"
                                 style="background:linear-gradient(135deg,#f59e0b,#d97706);">📚</div>
                        </div>
                    </div>
                    <div class="space-y-4 mt-8">
                        <div class="rounded-3xl overflow-hidden h-36 shadow-xl">
                            <div class="w-full h-full flex items-center justify-center text-5xl"
                                 style="background:linear-gradient(135deg,#0c4a6e,#0369a1);">✏️</div>
                        </div>
                        <div class="rounded-3xl overflow-hidden h-52 shadow-xl">
                            <img src="{{ asset('images/in tech.jpg') }}" alt="INTEC École" class="w-full h-full object-cover">
                        </div>
                    </div>
                </div>
                <div class="absolute -bottom-4 left-1/2 -translate-x-1/2 bg-white rounded-2xl shadow-2xl px-6 py-3 flex items-center gap-3 border border-slate-100">
                    <span class="text-3xl">🏅</span>
                    <div>
                        <p class="font-black text-slate-800 text-sm">École de référence</p>
                        <p class="text-slate-400 text-xs">Djibouti depuis 2015</p>
                    </div>
                </div>
            </div>

            <div class="reveal-right space-y-6">
                <div>
                    <div class="section-badge">À propos de nous</div>
                    <h2 class="text-4xl font-black text-slate-900 leading-tight mt-3">
                        Une école internationale<br>
                        <span class="text-gradient">ancrée à Djibouti</span>
                    </h2>
                    <div class="w-16 h-1 mt-4 rounded-full" style="background:linear-gradient(90deg,#f59e0b,#d97706);"></div>
                </div>
                <p class="text-slate-600 text-lg leading-relaxed">
                    Fondée avec la vision de former les citoyens de demain, INTEC École offre un enseignement bilingue de qualité supérieure, alliant les meilleures pratiques pédagogiques internationales aux valeurs culturelles djiboutiennes.
                </p>
                <div class="grid grid-cols-2 gap-4">
                    @foreach([
                        ['icon'=>'🌍','title'=>'Bilingue','desc'=>'Français & Anglais dès la Petite Section'],
                        ['icon'=>'💻','title'=>'Technologie','desc'=>'Initiation au numérique et aux TICE'],
                        ['icon'=>'🎨','title'=>'Épanouissement','desc'=>'Arts, sport et développement personnel'],
                        ['icon'=>'👨‍👩‍👧','title'=>'Partenariat','desc'=>'Communication régulière avec les parents'],
                    ] as $item)
                    <div class="flex gap-3 p-4 rounded-2xl bg-slate-50 hover:bg-amber-50 transition-colors duration-200 group">
                        <span class="text-2xl">{{ $item['icon'] }}</span>
                        <div>
                            <p class="font-bold text-slate-800 text-sm group-hover:text-amber-700 transition-colors">{{ $item['title'] }}</p>
                            <p class="text-slate-500 text-xs leading-snug">{{ $item['desc'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
                <a href="{{ route('preinscription') }}" class="btn-primary inline-flex shadow-xl">
                    📝 Pré-inscrire mon enfant
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>
</section>

{{-- ════════════════════════════════════════════
     PROGRAMMES
════════════════════════════════════════════ --}}
<section id="programmes" class="py-24" style="background:#f8fafc;">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 reveal">
            <div class="section-badge mx-auto">Nos programmes</div>
            <h2 class="text-4xl font-black text-slate-900 mt-3">
                Nos Niveaux <span class="text-gradient">Scolaires</span>
            </h2>
            <p class="text-slate-500 mt-4 max-w-xl mx-auto text-lg">De la Petite Section jusqu'au Baccalauréat, une continuité pédagogique unique à Djibouti.</p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach([
                [
                    'cycle'  => 'Préscolaire',
                    'ages'   => '3 — 6 ans',
                    'icon'   => '🌱',
                    'levels' => ['PS','MS','GS'],
                    'desc'   => 'Éveil moteur, développement artistique, initiation aux langues dans un cadre bienveillant.',
                    'grad'   => 'linear-gradient(135deg,#f59e0b,#d97706)',
                    'chip'   => 'bg-amber-100 text-amber-700',
                    'link'   => '#f59e0b',
                ],
                [
                    'cycle'  => 'Primaire',
                    'ages'   => '6 — 12 ans',
                    'icon'   => '📚',
                    'levels' => ['CP','CE1','CE2','CM1','CM2'],
                    'desc'   => 'Français, Arabe, Anglais, Mathématiques, Sciences dans un enseignement structuré et progressif.',
                    'grad'   => 'linear-gradient(135deg,#10b981,#059669)',
                    'chip'   => 'bg-emerald-100 text-emerald-700',
                    'link'   => '#10b981',
                ],
                [
                    'cycle'  => 'Collège',
                    'ages'   => '12 — 15 ans',
                    'icon'   => '🏫',
                    'levels' => ['6ème','5ème','4ème','3ème'],
                    'desc'   => 'Sciences avancées, langues approfondies, initiation à la pensée critique et aux projets.',
                    'grad'   => 'linear-gradient(135deg,#3b82f6,#1d4ed8)',
                    'chip'   => 'bg-blue-100 text-blue-700',
                    'link'   => '#3b82f6',
                ],
                [
                    'cycle'  => 'Lycée',
                    'ages'   => '15 — 18 ans',
                    'icon'   => '🎓',
                    'levels' => ['2nde','1ère','Tle'],
                    'desc'   => 'Préparation au Baccalauréat, orientation et accompagnement vers les études supérieures.',
                    'grad'   => 'linear-gradient(135deg,#8b5cf6,#6d28d9)',
                    'chip'   => 'bg-violet-100 text-violet-700',
                    'link'   => '#8b5cf6',
                ],
            ] as $i => $prog)
            <div class="card-3d bg-white rounded-3xl overflow-hidden shadow-lg reveal" style="animation-delay:{{ $i * 0.1 }}s;">
                <div class="p-6 text-white" style="background:{{ $prog['grad'] }};">
                    <div class="text-4xl mb-3">{{ $prog['icon'] }}</div>
                    <h3 class="text-xl font-black">{{ $prog['cycle'] }}</h3>
                    <p class="text-white/70 text-sm mt-0.5">{{ $prog['ages'] }}</p>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-slate-600 text-sm leading-relaxed">{{ $prog['desc'] }}</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($prog['levels'] as $lvl)
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $prog['chip'] }}">{{ $lvl }}</span>
                        @endforeach
                    </div>
                    <a href="{{ route('preinscription') }}"
                       class="inline-flex items-center gap-1.5 text-xs font-bold transition-all hover:gap-2.5"
                       style="color:{{ $prog['link'] }};">
                        S'inscrire
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ════════════════════════════════════════════
     GALERIE
════════════════════════════════════════════ --}}
<section id="galerie" class="py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 reveal">
            <div class="section-badge mx-auto">Galerie</div>
            <h2 class="text-4xl font-black text-slate-900 mt-3">La vie à <span class="text-gradient">INTEC École</span></h2>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            @php
            $gallery = [
                ['emoji'=>'🏫','label'=>'Notre école','grad'=>'from-slate-700 to-slate-900','span'=>'col-span-2 row-span-2'],
                ['emoji'=>'📚','label'=>'Bibliothèque','grad'=>'from-amber-600 to-orange-800','span'=>''],
                ['emoji'=>'✏️','label'=>'En classe','grad'=>'from-blue-600 to-blue-900','span'=>''],
                ['emoji'=>'🎨','label'=>'Arts plastiques','grad'=>'from-amber-500 to-orange-600','span'=>''],
                ['emoji'=>'💻','label'=>'Informatique','grad'=>'from-teal-600 to-cyan-800','span'=>''],
                ['emoji'=>'⚽','label'=>'Sport','grad'=>'from-emerald-600 to-green-800','span'=>''],
                ['emoji'=>'🎵','label'=>'Musique','grad'=>'from-violet-600 to-purple-800','span'=>''],
                ['emoji'=>'🔬','label'=>'Sciences','grad'=>'from-blue-500 to-indigo-800','span'=>''],
            ];
            @endphp
            @foreach($gallery as $i => $item)
            <div class="gallery-item {{ $item['span'] }} reveal" style="animation-delay:{{ $i * 0.07 }}s;">
                <div class="gallery-inner w-full min-h-36 bg-gradient-to-br {{ $item['grad'] }} flex items-center justify-center" style="height:100%;">
                    <div class="text-center py-8">
                        <div class="text-5xl mb-2">{{ $item['emoji'] }}</div>
                        <p class="text-white/60 text-xs font-semibold">{{ $item['label'] }}</p>
                    </div>
                </div>
                <div class="gallery-overlay">
                    <p class="text-white font-bold text-sm">{{ $item['label'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
        <p class="text-center text-slate-400 text-sm mt-8 reveal">📸 Photos de l'école à venir prochainement</p>
    </div>
</section>

{{-- ════════════════════════════════════════════
     VIDEO
════════════════════════════════════════════ --}}
<section class="py-24" style="background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 50%,#0f172a 100%);">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12 reveal">
            <div class="section-badge mx-auto">Découvrez l'école</div>
            <h2 class="text-4xl font-black text-white mt-3">Notre école <span class="text-gradient">en vidéo</span></h2>
        </div>
        <div class="reveal">
            <div class="relative rounded-3xl overflow-hidden shadow-2xl border border-white/10">
                <div class="aspect-video flex flex-col items-center justify-center cursor-pointer group"
                     id="video-placeholder"
                     style="background:linear-gradient(135deg,rgba(30,58,138,.6),rgba(15,23,42,.7));">
                    <div class="relative">
                        <div class="absolute inset-0 rounded-full bg-amber-400/30" style="animation:ping-ring 1.5s ease-in-out infinite;"></div>
                        <div class="relative w-20 h-20 rounded-full bg-amber-400 flex items-center justify-center shadow-2xl group-hover:scale-110 transition-transform duration-200 animate-pulse-glow">
                            <svg class="w-8 h-8 text-slate-900 ml-1" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-6 text-white font-bold text-xl">Présentation de INTEC École</p>
                    <p class="text-white/40 text-sm mt-1">Cliquez pour lancer la vidéo</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ════════════════════════════════════════════
     PRÉ-INSCRIPTION CTA
════════════════════════════════════════════ --}}
<section id="preinscription" class="py-24" style="background:#f8fafc;">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="relative rounded-3xl overflow-hidden shadow-2xl"
             style="background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 55%,#0c4a6e 100%);">
            <div class="hero-pattern absolute inset-0 opacity-30"></div>
            <div class="absolute top-0 right-0 w-96 h-96 rounded-full opacity-15 animate-float"
                 style="background:radial-gradient(circle,#f59e0b,transparent 70%);"></div>

            <div class="relative z-10 grid lg:grid-cols-2 gap-12 items-center p-10 lg:p-16">
                <div class="space-y-6">
                    <div class="section-badge">Inscriptions ouvertes — 2026-2027</div>
                    <h2 class="text-4xl lg:text-5xl font-black text-white leading-tight">
                        Rejoignez la<br>
                        <span class="text-gradient">famille INTEC</span>
                    </h2>
                    <p class="text-white/60 text-lg leading-relaxed">
                        Offrez à votre enfant un environnement d'apprentissage exceptionnel. Les pré-inscriptions pour 2026-2027 sont ouvertes dès maintenant.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        @foreach(['✅ Réponse sous 48h','📞 Entretien personnalisé','📋 Dossier simplifié'] as $item)
                        <span class="text-white/70 text-sm glass px-3 py-1.5 rounded-full">{{ $item }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="bg-white rounded-3xl p-8 shadow-2xl">
                    <h3 class="text-2xl font-black text-slate-900 mb-2">Pré-inscription rapide</h3>
                    <p class="text-slate-500 text-sm mb-6">Remplissez le formulaire, nous vous contacterons dans les 48h.</p>
                    <a href="{{ route('preinscription') }}"
                       class="btn-primary flex items-center justify-center gap-3 w-full py-4 rounded-2xl text-lg shadow-xl mb-4">
                        📝 Commencer la pré-inscription
                    </a>
                    <p class="text-center text-slate-400 text-xs">
                        Année <strong>2026-2027</strong> · Préscolaire, Primaire, Collège & Lycée
                    </p>
                    <div class="mt-5 pt-4 border-t border-slate-100 flex items-center justify-between">
                        <a href="tel:+25377087979" class="text-slate-600 hover:text-amber-600 font-semibold text-sm transition-colors">📞 77 08 79 79</a>
                        <a href="tel:+25377057878" class="text-slate-600 hover:text-amber-600 font-semibold text-sm transition-colors">📞 77 05 78 78</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ════════════════════════════════════════════
     TÉMOIGNAGES
════════════════════════════════════════════ --}}
<section class="py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 reveal">
            <div class="section-badge mx-auto">Témoignages</div>
            <h2 class="text-4xl font-black text-slate-900 mt-3">Ce que disent <span class="text-gradient">les parents</span></h2>
        </div>
        <div class="grid md:grid-cols-3 gap-6">
            @foreach([
                ['name'=>'Mme Fatouma A.','role'=>'Mère d\'élève — GS','text'=>'INTEC a transformé mon enfant. Il parle couramment français et anglais, et adore venir à l\'école. Le suivi par les enseignants est remarquable.','avatar'=>'👩'],
                ['name'=>'M. Ibrahim H.','role'=>'Père d\'élève — CE2','text'=>'Le système de bulletins en ligne est très pratique. Je peux suivre les notes et l\'évolution de ma fille à tout moment. Une école vraiment moderne.','avatar'=>'👨'],
                ['name'=>'Mme Amina D.','role'=>'Mère d\'élève — CP','text'=>'L\'équipe pédagogique est exceptionnelle. Mon fils est épanoui, curieux et motivé. Je recommande INTEC à toutes les familles de Djibouti.','avatar'=>'👩‍💼'],
            ] as $i => $t)
            <div class="quote-card reveal" style="animation-delay:{{ $i * 0.1 }}s;">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-11 h-11 rounded-full flex items-center justify-center text-xl"
                         style="background:linear-gradient(135deg,#1e3a8a,#0c4a6e);">{{ $t['avatar'] }}</div>
                    <div>
                        <p class="font-bold text-slate-800 text-sm">{{ $t['name'] }}</p>
                        <p class="text-slate-400 text-xs">{{ $t['role'] }}</p>
                    </div>
                    <div class="ml-auto text-amber-400 text-2xl font-black leading-none">"</div>
                </div>
                <p class="text-slate-600 text-sm leading-relaxed italic">{{ $t['text'] }}</p>
                <div class="flex gap-0.5 mt-4">
                    @for($s=0;$s<5;$s++)<span class="text-amber-400 text-sm">★</span>@endfor
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ════════════════════════════════════════════
     CONTACT
════════════════════════════════════════════ --}}
<section id="contact" class="py-24" style="background:#f8fafc;">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 reveal">
            <div class="section-badge mx-auto">Contact</div>
            <h2 class="text-4xl font-black text-slate-900 mt-3">Nous <span class="text-gradient">trouver</span></h2>
        </div>
        <div class="grid md:grid-cols-3 gap-6">
            @foreach([
                ['icon'=>'📍','title'=>'Adresse','lines'=>['Djibouti-Ville','République de Djibouti'],'bg'=>'linear-gradient(135deg,#0f172a,#1e3a8a)'],
                ['icon'=>'📞','title'=>'Téléphone','lines'=>['77 08 79 79','77 05 78 78'],'bg'=>'linear-gradient(135deg,#1e3a8a,#0c4a6e)'],
                ['icon'=>'✉️','title'=>'Email & Horaires','lines'=>['intec.ecole.djibouti@gmail.com','Lun–Ven 7h30–17h00'],'bg'=>'linear-gradient(135deg,#0c4a6e,#0f172a)'],
            ] as $c)
            <div class="reveal text-center p-8 rounded-3xl bg-white border border-slate-100 hover:border-amber-200 transition-all duration-200 shadow-sm hover:shadow-xl group">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-4 group-hover:scale-110 transition-transform"
                     style="background:{{ $c['bg'] }};">{{ $c['icon'] }}</div>
                <h3 class="font-bold text-slate-800 mb-2">{{ $c['title'] }}</h3>
                @foreach($c['lines'] as $line)
                <p class="text-slate-500 text-sm">{{ $line }}</p>
                @endforeach
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ════════════════════════════════════════════
     FOOTER
════════════════════════════════════════════ --}}
<footer style="background:#0f172a;">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
        <div class="grid md:grid-cols-4 gap-10 mb-10">
            <div class="md:col-span-2">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl overflow-hidden ring-2 ring-amber-400/30">
                        <img src="{{ asset('images/in tech.jpg') }}" alt="INTEC" class="w-full h-full object-cover">
                    </div>
                    <div>
                        <p class="text-white font-black text-lg leading-none">inTEC École</p>
                        <p class="text-white/30 text-xs">École internationale — Djibouti</p>
                    </div>
                </div>
                <p class="text-white/40 text-sm leading-relaxed max-w-xs">
                    École internationale pour les langues et les technologies. Du Préscolaire au Lycée, une éducation de qualité à Djibouti.
                </p>
            </div>
            <div>
                <h4 class="text-white font-bold text-sm mb-4 uppercase tracking-wider">Navigation</h4>
                <ul class="space-y-2.5">
                    @foreach([['À propos','#about'],['Programmes','#programmes'],['Galerie','#galerie'],['Contact','#contact']] as [$l,$h])
                    <li><a href="{{ $h }}" class="text-white/40 hover:text-amber-400 text-sm transition-colors">{{ $l }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h4 class="text-white font-bold text-sm mb-4 uppercase tracking-wider">Espace parents</h4>
                <ul class="space-y-2.5">
                    <li><a href="{{ route('preinscription') }}" class="text-amber-400 hover:text-amber-300 text-sm font-semibold transition-colors">📝 Pré-inscription 2026-2027</a></li>
                    <li><a href="{{ route('login') }}" class="text-white/40 hover:text-white text-sm transition-colors">🔐 Espace bulletins</a></li>
                    <li><span class="text-white/25 text-xs">Lun–Ven 7h30–17h00</span></li>
                    <li><span class="text-white/25 text-xs">Sam 8h00–12h00</span></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-white/8 pt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-white/25 text-xs">© {{ date('Y') }} INTEC École — Tous droits réservés</p>
            <p class="text-white/25 text-xs">Pré-inscription ouverte · <span class="text-amber-400/70 font-semibold">2026-2027</span></p>
        </div>
    </div>
</footer>

<script>
document.getElementById('mobile-btn')?.addEventListener('click', () => {
    document.getElementById('mobile-menu')?.classList.toggle('hidden');
});
</script>

</div>
