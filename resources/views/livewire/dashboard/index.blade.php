<?php

use App\Models\AcademicYear;
use App\Models\Bulletin;
use App\Models\Classroom;
use App\Models\Niveau;
use App\Models\Student;
use App\Enums\BulletinStatusEnum;
use App\Enums\PeriodEnum;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {

    public ?string $filterPeriod = null;
    public bool    $showFilters  = false;

    public function mount(): void
    {
        $this->filterPeriod = PeriodEnum::current()->value;
    }

    public function with(): array
    {
        $user = auth()->user();
        $yr   = AcademicYear::current();

        // ── Students & classes ─────────────────────────────────────────────
        $totalEleves  = Student::count();
        $garcons      = Student::where('gender', 'M')->count();
        $filles       = Student::where('gender', 'F')->count();
        $totalClasses = Classroom::count();

        // ── Status counts (period-filtered) ───────────────────────────────
        $bulletinBase = Bulletin::query();
        if ($this->filterPeriod) {
            $bulletinBase->where('period', $this->filterPeriod);
        }

        $statusCounts = (clone $bulletinBase)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $brouillons   = $statusCounts[BulletinStatusEnum::DRAFT->value]      ?? 0;
        $soumis       = $statusCounts[BulletinStatusEnum::SUBMITTED->value]   ?? 0;
        $enAttente    = $soumis
                      + ($statusCounts[BulletinStatusEnum::PEDAGOGIE_REVIEW->value] ?? 0)
                      + ($statusCounts[BulletinStatusEnum::FINANCE_REVIEW->value]   ?? 0)
                      + ($statusCounts[BulletinStatusEnum::DIRECTION_REVIEW->value] ?? 0);
        $publies      = $statusCounts[BulletinStatusEnum::PUBLISHED->value]   ?? 0;
        $rejetes      = $statusCounts[BulletinStatusEnum::REJECTED->value]    ?? 0;
        $totalBulletins = array_sum($statusCounts);

        // ── Moyenne calculations (published only) ──────────────────────────
        $pubBase = Bulletin::where('status', BulletinStatusEnum::PUBLISHED)
            ->when($this->filterPeriod, fn($q) => $q->where('period', $this->filterPeriod));

        $globalMoyenne  = round((clone $pubBase)->whereNotNull('moyenne')->avg('moyenne') ?? 0, 2);
        $totalPublished = (clone $pubBase)->count();

        $successCount = (clone $pubBase)->whereNotNull('moyenne')->where('moyenne', '>=', 5)->count();
        $pubWithMoy   = (clone $pubBase)->whereNotNull('moyenne')->count();
        $tauxReussite = $pubWithMoy > 0 ? round($successCount / $pubWithMoy * 100) : 0;

        // Per-class average ranking
        $classRanking = Bulletin::where('status', BulletinStatusEnum::PUBLISHED)
            ->whereNotNull('moyenne')
            ->when($this->filterPeriod, fn($q) => $q->where('period', $this->filterPeriod))
            ->with('classroom')
            ->selectRaw('classroom_id, ROUND(AVG(CAST(moyenne AS FLOAT)), 2) as avg_moy, COUNT(*) as nb')
            ->groupBy('classroom_id')
            ->orderByDesc('avg_moy')
            ->get();

        $bestClass     = $classRanking->first();
        $bestClassName = $bestClass?->classroom?->code ?? '—';
        $bestClassMoy  = $bestClass?->avg_moy ?? 0;

        // Moyenne trend
        $moyTrend = Bulletin::where('status', BulletinStatusEnum::PUBLISHED)
            ->whereNotNull('moyenne')
            ->whereIn('period', ['T1','T2','T3'])
            ->selectRaw('period, ROUND(AVG(CAST(moyenne AS FLOAT)), 2) as avg_moy')
            ->groupBy('period')
            ->pluck('avg_moy', 'period')
            ->toArray();
        $trendValues = [
            round((float)($moyTrend['T1'] ?? 0), 2),
            round((float)($moyTrend['T2'] ?? 0), 2),
            round((float)($moyTrend['T3'] ?? 0), 2),
        ];

        // ── Bar chart: bulletins by period ────────────────────────────────
        $byPeriod = Bulletin::selectRaw('period, count(*) as total')
            ->groupBy('period')->pluck('total', 'period')->toArray();
        $barLabels = ['T1', 'T2', 'T3'];
        $barValues = [
            $byPeriod[PeriodEnum::TRIMESTRE_1->value] ?? 0,
            $byPeriod[PeriodEnum::TRIMESTRE_2->value] ?? 0,
            $byPeriod[PeriodEnum::TRIMESTRE_3->value] ?? 0,
        ];

        // ── Donut chart: status breakdown ─────────────────────────────────
        $donutLabels = ['Brouillon', 'Soumis', 'Péd. ✓', 'Fin. ✓', 'Dir. ✓', 'Publié', 'Rejeté'];
        $donutValues = [
            $statusCounts[BulletinStatusEnum::DRAFT->value]              ?? 0,
            $statusCounts[BulletinStatusEnum::SUBMITTED->value]          ?? 0,
            $statusCounts[BulletinStatusEnum::PEDAGOGIE_APPROVED->value] ?? 0,
            $statusCounts[BulletinStatusEnum::FINANCE_APPROVED->value]   ?? 0,
            $statusCounts[BulletinStatusEnum::APPROVED->value]           ?? 0,
            $statusCounts[BulletinStatusEnum::PUBLISHED->value]          ?? 0,
            $statusCounts[BulletinStatusEnum::REJECTED->value]           ?? 0,
        ];
        $donutColors = ['#94a3b8','#3b82f6','#8b5cf6','#f59e0b','#10b981','#6366f1','#ef4444'];

        $classLabels = $classRanking->map(fn($r) => $r->classroom?->code ?? '?')->values()->toArray();
        $classValues = $classRanking->pluck('avg_moy')->values()->toArray();
        $classColors = array_map(fn($v) => $v >= 5 ? 'rgba(16,185,129,0.85)' : 'rgba(239,68,68,0.85)', $classValues);

        // ── Pending for role ───────────────────────────────────────────────
        $pendingStatuses = match(true) {
            $user->hasRole('pedagogie') => [BulletinStatusEnum::SUBMITTED->value, BulletinStatusEnum::PEDAGOGIE_REVIEW->value],
            $user->hasRole('finance')   => [BulletinStatusEnum::PEDAGOGIE_APPROVED->value, BulletinStatusEnum::FINANCE_REVIEW->value],
            $user->hasRole('direction') => [BulletinStatusEnum::FINANCE_APPROVED->value, BulletinStatusEnum::DIRECTION_REVIEW->value],
            default                     => [BulletinStatusEnum::SUBMITTED->value, BulletinStatusEnum::PEDAGOGIE_REVIEW->value,
                                            BulletinStatusEnum::FINANCE_REVIEW->value, BulletinStatusEnum::DIRECTION_REVIEW->value],
        };
        $myUrgentCount = Bulletin::whereIn('status', $pendingStatuses)
            ->when($this->filterPeriod, fn($q) => $q->where('period', $this->filterPeriod))
            ->count();
        $pending = Bulletin::whereIn('status', $pendingStatuses)
            ->when($this->filterPeriod, fn($q) => $q->where('period', $this->filterPeriod))
            ->with(['student','classroom'])->latest()->take(6)->get();

        $recentBulletins = Bulletin::with(['student','classroom'])
            ->latest('updated_at')->take(8)->get();

        // ── Teacher-specific classrooms ────────────────────────────────────
        $teacherBulletins   = null;
        $teacherClassrooms  = collect();
        if ($user->hasRole('teacher')) {
            $teacherBulletins = Bulletin::whereHas('classroom', fn($q) => $q->where('teacher_id', $user->id))
                ->when($this->filterPeriod, fn($q) => $q->where('period', $this->filterPeriod))
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total','status')
                ->toArray();

            $teacherClassrooms = Classroom::where('teacher_id', $user->id)
                ->with('niveau')
                ->withCount('students')
                ->get()
                ->map(function($c) {
                    $period = $this->filterPeriod;
                    $total      = $c->students_count;
                    $submitted  = Bulletin::where('classroom_id', $c->id)
                        ->when($period, fn($q) => $q->where('period', $period))
                        ->whereNotIn('status', [BulletinStatusEnum::DRAFT->value])
                        ->count();
                    $published  = Bulletin::where('classroom_id', $c->id)
                        ->when($period, fn($q) => $q->where('period', $period))
                        ->where('status', BulletinStatusEnum::PUBLISHED->value)
                        ->count();
                    $drafts     = Bulletin::where('classroom_id', $c->id)
                        ->when($period, fn($q) => $q->where('period', $period))
                        ->where('status', BulletinStatusEnum::DRAFT->value)
                        ->count();
                    $avgMoy     = Bulletin::where('classroom_id', $c->id)
                        ->when($period, fn($q) => $q->where('period', $period))
                        ->where('status', BulletinStatusEnum::PUBLISHED->value)
                        ->whereNotNull('moyenne')
                        ->avg('moyenne');
                    return [
                        'classroom' => $c,
                        'total'     => $total,
                        'submitted' => $submitted,
                        'published' => $published,
                        'drafts'    => $drafts,
                        'progress'  => $total > 0 ? round($submitted / $total * 100) : 0,
                        'avg'       => $avgMoy ? round((float)$avgMoy, 2) : null,
                    ];
                });
        }

        // ── Per-niveau stats (admin/direction) ─────────────────────────────
        $niveauStats = collect();
        if ($user->hasRole('admin') || $user->hasRole('direction')) {
            $niveauStats = Niveau::orderBy('order')->get()->map(function($n) {
                $period  = $this->filterPeriod;
                $classIds = Classroom::where('niveau_id', $n->id)->pluck('id');
                $bulletinQ = Bulletin::whereIn('classroom_id', $classIds)
                    ->when($period, fn($q) => $q->where('period', $period));
                $pubQ = (clone $bulletinQ)->where('status', BulletinStatusEnum::PUBLISHED->value);
                $total   = (clone $bulletinQ)->count();
                $pub     = $pubQ->count();
                $avgMoy  = (clone $pubQ)->whereNotNull('moyenne')->avg('moyenne');
                $pass    = (clone $pubQ)->whereNotNull('moyenne')->where('moyenne','>=',5)->count();
                $withMoy = (clone $pubQ)->whereNotNull('moyenne')->count();
                return [
                    'niveau'   => $n,
                    'total'    => $total,
                    'published'=> $pub,
                    'avg'      => $avgMoy ? round((float)$avgMoy, 2) : null,
                    'taux'     => $withMoy > 0 ? round($pass / $withMoy * 100) : null,
                    'classes'  => $classIds->count(),
                ];
            })->filter(fn($s) => $s['classes'] > 0)->values();
        }

        return compact(
            'totalEleves','garcons','filles','totalClasses',
            'totalBulletins','brouillons','soumis','enAttente','publies','rejetes',
            'globalMoyenne','tauxReussite','totalPublished','bestClassName','bestClassMoy',
            'trendValues',
            'donutLabels','donutValues','donutColors',
            'barLabels','barValues',
            'classLabels','classValues','classColors','classRanking',
            'pending','recentBulletins','statusCounts',
            'teacherBulletins','teacherClassrooms',
            'niveauStats','myUrgentCount'
        );
    }
}; ?>

<div class="space-y-5">

    {{-- ── Hero portal banner ─────────────────────────────────────────────── --}}
    @php
        $role       = auth()->user()->getRoleNames()->first();
        $heroGrad   = match($role) {
            'teacher'   => 'from-[#16363a] via-teal-800 to-emerald-900',
            'pedagogie' => 'from-violet-800 via-purple-800 to-indigo-900',
            'finance'   => 'from-amber-700 via-orange-700 to-amber-800',
            'direction' => 'from-[#16363a] via-slate-800 to-slate-900',
            default     => 'from-[#16363a] via-teal-900 to-slate-900',
        };
        $roleLabel  = match($role) {
            'admin'     => 'Portail Administrateur',
            'direction' => 'Portail Direction',
            'pedagogie' => 'Portail Pédagogique',
            'finance'   => 'Portail Financier',
            'teacher'   => 'Portail Enseignant',
            default     => 'Tableau de bord',
        };
        $roleIcon   = match($role) {
            'admin'     => '⚙️',
            'direction' => '🏛️',
            'pedagogie' => '📚',
            'finance'   => '💼',
            'teacher'   => '🧑‍🏫',
            default     => '📊',
        };
    @endphp
    <div class="relative overflow-hidden rounded-2xl bg-linear-to-br {{ $heroGrad }} text-white px-6 py-6 shadow-xl">
        {{-- Decorative blobs --}}
        <div class="absolute -right-8 -top-8 w-48 h-48 bg-white/5 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute right-24 bottom-0 w-36 h-36 bg-[#c8913a]/15 rounded-full blur-2xl pointer-events-none"></div>
        <div class="absolute left-1/3 -top-4 w-40 h-40 bg-white/5 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 w-full h-0.5 bg-gradient-to-r from-[#c8913a]/60 via-transparent to-[#c8913a]/30"></div>

        <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-xl bg-white/10 border border-white/20 flex items-center justify-center text-2xl backdrop-blur shrink-0">
                    {{ $roleIcon }}
                </div>
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wide bg-[#c8913a]/30 text-[#f5c87a] border border-[#c8913a]/40">
                            {{ $roleLabel }}
                        </span>
                        @if($filterPeriod)
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-white/15 text-white/80">
                            {{ \App\Enums\PeriodEnum::from($filterPeriod)->label() }}
                        </span>
                        @endif
                    </div>
                    <h1 class="text-2xl font-black tracking-tight">
                        Bonjour, {{ explode(' ', auth()->user()->name)[0] }} 👋
                    </h1>
                    <p class="text-white/60 text-sm mt-0.5">{{ now()->translatedFormat('l d F Y') }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3 self-start sm:self-auto">
                @unless(auth()->user()->hasRole('teacher'))
                @if($myUrgentCount > 0)
                <a href="{{ route('bulletins.index') }}" wire:navigate
                   class="flex items-center gap-2 px-4 py-2 bg-red-500/90 hover:bg-red-500 rounded-xl text-sm font-bold transition-all shadow-lg">
                    <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
                    {{ $myUrgentCount }} en attente
                </a>
                @else
                <div class="flex items-center gap-2 px-4 py-2 bg-emerald-500/80 rounded-xl text-sm font-semibold">
                    ✓ File d'attente vide
                </div>
                @endif
                @endunless

                <button @click="$wire.showFilters = true"
                   class="btn btn-sm bg-white/10 hover:bg-white/20 text-white border-white/20 gap-2 font-semibold">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18M7 8h10M11 12h2"/>
                    </svg>
                    Filtres
                </button>
            </div>
        </div>

        {{-- Inline mini-stats row --}}
        <div class="relative z-10 flex flex-wrap items-center gap-x-6 gap-y-1 mt-4 pt-4 border-t border-white/10 text-sm">
            <div class="flex items-center gap-1.5 text-white/70">
                <span class="text-white font-bold text-base">{{ $totalEleves }}</span> élèves
            </div>
            <div class="flex items-center gap-1.5 text-white/70">
                <span class="text-white font-bold text-base">{{ $totalClasses }}</span> classes
            </div>
            <div class="flex items-center gap-1.5 text-white/70">
                <span class="text-white font-bold text-base">{{ $publies }}</span> bulletins publiés
            </div>
            @if($globalMoyenne > 0)
            <div class="flex items-center gap-1.5 text-white/70">
                Moy. globale : <span class="text-[#f5c87a] font-bold text-base">{{ number_format($globalMoyenne, 2) }}/10</span>
            </div>
            @endif
            @if($tauxReussite > 0)
            <div class="flex items-center gap-1.5 text-white/70">
                Réussite : <span class="{{ $tauxReussite >= 50 ? 'text-emerald-300' : 'text-red-300' }} font-bold text-base">{{ $tauxReussite }}%</span>
            </div>
            @endif
        </div>
    </div>

    {{-- ── Teacher portal: my classrooms ────────────────────────────────── --}}
    @role('teacher')
    @if($teacherClassrooms->isNotEmpty())
    <div>
        <h2 class="text-xs font-bold uppercase tracking-widest text-base-content/40 mb-3 px-1">Mes classes</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($teacherClassrooms as $tc)
            @php
                $cls   = $tc['classroom'];
                $prog  = $tc['progress'];
                $pColor = $prog >= 80 ? 'bg-emerald-500' : ($prog >= 40 ? 'bg-amber-500' : 'bg-red-400');
            @endphp
            <div class="card bg-base-100 shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden">
                <div class="h-1.5 w-full {{ $pColor }}"></div>
                <div class="card-body p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-black text-lg leading-tight">{{ $cls->code }}</h3>
                            <p class="text-xs text-base-content/50">{{ $cls->niveau?->label ?? '—' }}</p>
                        </div>
                        <div class="text-right shrink-0">
                            @if($tc['avg'])
                            <div class="text-xl font-black {{ $tc['avg'] >= 5 ? 'text-emerald-600' : 'text-red-500' }}">
                                {{ number_format($tc['avg'], 1) }}<span class="text-xs text-base-content/40 font-normal">/10</span>
                            </div>
                            <div class="text-[10px] text-base-content/40">moy. publiée</div>
                            @else
                            <div class="text-xs text-base-content/30 italic">pas de moyenne</div>
                            @endif
                        </div>
                    </div>
                    <div class="mt-3 space-y-1.5">
                        <div class="flex justify-between text-xs text-base-content/50 mb-0.5">
                            <span>Progression soumission</span>
                            <span class="font-bold {{ $prog >= 80 ? 'text-emerald-600' : ($prog >= 40 ? 'text-amber-600' : 'text-red-500') }}">{{ $prog }}%</span>
                        </div>
                        <div class="w-full h-2 bg-base-200 rounded-full overflow-hidden">
                            <div class="{{ $pColor }} h-full rounded-full transition-all duration-700" style="width:{{ $prog }}%"></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 mt-3 pt-2 border-t border-base-200 text-xs text-base-content/50">
                        <span><b class="text-base-content">{{ $tc['total'] }}</b> élèves</span>
                        <span><b class="text-blue-600">{{ $tc['submitted'] }}</b> soumis</span>
                        <span><b class="text-indigo-600">{{ $tc['published'] }}</b> publiés</span>
                        @if($tc['drafts'] > 0)
                        <span><b class="text-amber-600">{{ $tc['drafts'] }}</b> brouillons</span>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Teacher quick actions --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <a href="{{ route('bulletins.grade-form') }}" wire:navigate
           class="card bg-linear-to-br from-[#16363a] to-teal-700 text-white shadow hover:shadow-lg hover:-translate-y-0.5 transition-all cursor-pointer">
            <div class="card-body p-5 flex-row items-center gap-4">
                <span class="text-3xl">✏️</span>
                <div>
                    <h3 class="font-bold">Saisir les notes</h3>
                    <p class="text-xs text-white/60 mt-0.5">Formulaire de saisie</p>
                </div>
            </div>
        </a>
        <a href="{{ route('bulletins.index') }}" wire:navigate
           class="card bg-linear-to-br from-blue-600 to-indigo-700 text-white shadow hover:shadow-lg hover:-translate-y-0.5 transition-all cursor-pointer">
            <div class="card-body p-5 flex-row items-center gap-4">
                <span class="text-3xl">📋</span>
                <div>
                    <h3 class="font-bold">Mes bulletins</h3>
                    <p class="text-xs text-white/60 mt-0.5">Suivi &amp; statuts</p>
                </div>
            </div>
        </a>
        <a href="{{ route('setup.programme') }}" wire:navigate
           class="card bg-linear-to-br from-violet-600 to-purple-700 text-white shadow hover:shadow-lg hover:-translate-y-0.5 transition-all cursor-pointer">
            <div class="card-body p-5 flex-row items-center gap-4">
                <span class="text-3xl">📖</span>
                <div>
                    <h3 class="font-bold">Mon programme</h3>
                    <p class="text-xs text-white/60 mt-0.5">Matières &amp; compétences</p>
                </div>
            </div>
        </a>
    </div>
    @endrole

    {{-- ── Per-niveau overview (admin/direction) ─────────────────────────── --}}
    @role('admin|direction')
    @if($niveauStats->isNotEmpty())
    <div>
        <h2 class="text-xs font-bold uppercase tracking-widest text-base-content/40 mb-3 px-1">Aperçu par niveau</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-{{ min($niveauStats->count(), 5) }} gap-3">
            @foreach($niveauStats as $ns)
            @php
                $avgColor  = $ns['avg'] ? ($ns['avg'] >= 7 ? 'text-emerald-600' : ($ns['avg'] >= 5 ? 'text-amber-600' : 'text-red-500')) : 'text-base-content/30';
                $tauxColor = $ns['taux'] !== null ? ($ns['taux'] >= 70 ? 'bg-emerald-100 text-emerald-700' : ($ns['taux'] >= 50 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-600')) : 'bg-base-200 text-base-content/30';
            @endphp
            <div class="card bg-base-100 shadow-sm hover:shadow-md transition-all p-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <span class="badge badge-outline badge-info badge-sm font-semibold">{{ $ns['niveau']->code }}</span>
                        <p class="text-xs text-base-content/50 mt-1 truncate">{{ $ns['niveau']->label }}</p>
                    </div>
                    <span class="text-2xl font-black {{ $avgColor }}">
                        {{ $ns['avg'] ? number_format($ns['avg'], 1) : '—' }}
                    </span>
                </div>
                <div class="mt-3 flex flex-wrap gap-1.5 text-xs">
                    <span class="px-2 py-0.5 rounded-full bg-base-200 text-base-content/60">
                        {{ $ns['classes'] }} classe(s)
                    </span>
                    <span class="px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600 font-semibold">
                        {{ $ns['published'] }} publiés
                    </span>
                    @if($ns['taux'] !== null)
                    <span class="px-2 py-0.5 rounded-full {{ $tauxColor }} font-semibold">
                        {{ $ns['taux'] }}% réussite
                    </span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
    @endrole

    {{-- ── KPI cards ─────────────────────────────────────────────────────── --}}
    @unless(auth()->user()->hasRole('teacher'))
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
        @php
        $kpis = [
            ['icon'=>'👥', 'color'=>'bg-blue-600',    'light'=>'bg-blue-50',    'value'=>$totalEleves,  'label'=>'Élèves',      'sub'=>$garcons.'G / '.$filles.'F', 'href'=>route('setup.students')],
            ['icon'=>'🏛️', 'color'=>'bg-violet-600',  'light'=>'bg-violet-50',  'value'=>$totalClasses, 'label'=>'Classes',     'sub'=>null, 'href'=>route('setup.classrooms')],
            ['icon'=>'📝', 'color'=>'bg-slate-500',   'light'=>'bg-slate-50',   'value'=>$brouillons,   'label'=>'Brouillons',  'sub'=>null, 'href'=>route('bulletins.index')],
            ['icon'=>'⏳', 'color'=>'bg-amber-500',   'light'=>'bg-amber-50',   'value'=>$enAttente,    'label'=>'En attente',  'sub'=>null, 'href'=>route('bulletins.index'),
             'urgent'=>$enAttente > 0],
            ['icon'=>'✅', 'color'=>'bg-emerald-600', 'light'=>'bg-emerald-50', 'value'=>$publies,      'label'=>'Publiés',     'sub'=>null, 'href'=>route('bulletins.index')],
            ['icon'=>'↩️', 'color'=>'bg-red-600',     'light'=>'bg-red-50',     'value'=>$rejetes,      'label'=>'Rejetés',     'sub'=>null, 'href'=>route('bulletins.index'),
             'alert'=>$rejetes > 0],
        ];
        @endphp
        @foreach($kpis as $kpi)
        <a href="{{ $kpi['href'] }}" wire:navigate
           class="card bg-base-100 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 {{ ($kpi['urgent'] ?? false) ? 'ring-2 ring-amber-400 ring-offset-1' : '' }} {{ ($kpi['alert'] ?? false) ? 'ring-2 ring-red-400 ring-offset-1' : '' }}">
            <div class="card-body p-4">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl {{ $kpi['light'] }} flex items-center justify-center text-lg shrink-0">
                        {{ $kpi['icon'] }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="font-black text-2xl leading-tight text-base-content tabular-nums">{{ $kpi['value'] }}</div>
                        <div class="text-xs text-base-content/50 font-medium">{{ $kpi['label'] }}</div>
                        @if($kpi['sub'])
                        <div class="text-[11px] text-base-content/30 mt-0.5">{{ $kpi['sub'] }}</div>
                        @endif
                    </div>
                </div>
                @if(($kpi['urgent'] ?? false) && $kpi['value'] > 0)
                <div class="mt-2 text-[10px] text-amber-600 font-semibold flex items-center gap-1">
                    <span class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-pulse"></span> Action requise
                </div>
                @endif
            </div>
        </a>
        @endforeach
    </div>
    @endunless

    {{-- ── Moyenne highlight cards ─────────────────────────────────────── --}}
    @unless(auth()->user()->hasRole('teacher'))
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">

        <div class="card overflow-hidden shadow-sm hover:shadow-md transition-all duration-200">
            <div class="bg-linear-to-br from-[#16363a] to-teal-700 p-5 text-white">
                <p class="text-[11px] font-bold uppercase tracking-widest opacity-60">Moyenne globale</p>
                <div class="flex items-end gap-2 mt-2">
                    <span class="text-4xl font-black tabular-nums">{{ $globalMoyenne > 0 ? number_format($globalMoyenne, 2) : '—' }}</span>
                    @if($globalMoyenne > 0)<span class="text-lg opacity-50 mb-1">/10</span>@endif
                </div>
                <div class="mt-2 flex items-center gap-1.5 text-xs opacity-60">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#c8913a]"></span>
                    {{ $totalPublished }} bulletins publiés
                </div>
            </div>
        </div>

        <div class="card overflow-hidden shadow-sm hover:shadow-md transition-all duration-200">
            <div class="bg-linear-to-br from-emerald-600 to-teal-600 p-5 text-white">
                <p class="text-[11px] font-bold uppercase tracking-widest opacity-60">Taux de réussite</p>
                <div class="flex items-end gap-2 mt-2">
                    <span class="text-4xl font-black tabular-nums">{{ $tauxReussite }}</span>
                    <span class="text-lg opacity-50 mb-1">%</span>
                </div>
                <div class="mt-2.5 w-full bg-white/20 rounded-full h-1.5">
                    <div class="bg-white h-1.5 rounded-full transition-all" style="width:{{ $tauxReussite }}%"></div>
                </div>
            </div>
        </div>

        <div class="card overflow-hidden shadow-sm hover:shadow-md transition-all duration-200">
            <div class="bg-linear-to-br from-[#c8913a] to-amber-600 p-5 text-white">
                <p class="text-[11px] font-bold uppercase tracking-widest opacity-70">Meilleure classe</p>
                <div class="flex items-end gap-2 mt-2">
                    <span class="text-4xl font-black truncate">{{ $bestClassName }}</span>
                </div>
                <div class="mt-2 flex items-center gap-1.5 text-xs opacity-80">
                    🏆 Moy. {{ $bestClassMoy > 0 ? number_format($bestClassMoy, 2).'/10' : '—' }}
                </div>
            </div>
        </div>

        <div class="card overflow-hidden shadow-sm hover:shadow-md transition-all duration-200">
            <div class="bg-linear-to-br from-sky-600 to-blue-700 p-5 text-white">
                <p class="text-[11px] font-bold uppercase tracking-widest opacity-60">Tendance T1 → T3</p>
                <div class="flex items-center gap-2 mt-2 justify-between">
                    @foreach(['T1','T2','T3'] as $ti => $tp)
                    <div class="text-center">
                        <div class="text-xl font-black tabular-nums">{{ $trendValues[$ti] > 0 ? number_format($trendValues[$ti], 1) : '—' }}</div>
                        <div class="text-[10px] opacity-60">{{ $tp }}</div>
                    </div>
                    @if($ti < 2)<div class="opacity-30 text-base">→</div>@endif
                    @endforeach
                </div>
                <div class="h-1 w-full relative mt-3">
                    <canvas id="chart-sparkline" class="absolute inset-0 w-full h-full"></canvas>
                </div>
            </div>
        </div>
    </div>
    @endunless

    {{-- ── Charts row ─────────────────────────────────────────────────────── --}}
    @unless(auth()->user()->hasRole('teacher'))
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <div class="card bg-base-100 shadow-sm lg:col-span-2">
            <div class="card-body p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide">Bulletins par trimestre</h3>
                    <span class="badge badge-ghost badge-sm font-mono">Total : {{ $totalBulletins }}</span>
                </div>
                <div class="h-48">
                    <canvas id="chart-bar"></canvas>
                </div>
                <div class="grid grid-cols-3 gap-2 mt-3 pt-3 border-t border-base-200">
                    @foreach($barLabels as $i => $label)
                    <div class="text-center p-2 rounded-lg bg-base-200/50">
                        <p class="text-xl font-black text-base-content tabular-nums">{{ $barValues[$i] }}</p>
                        <p class="text-xs text-base-content/40">Trimestre {{ $i + 1 }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body p-5">
                <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide mb-3">Répartition statuts</h3>
                <div class="flex justify-center">
                    <div class="relative w-36 h-36">
                        <canvas id="chart-donut"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <span class="text-2xl font-black text-base-content tabular-nums">{{ $totalBulletins }}</span>
                            <span class="text-xs text-base-content/40">bulletins</span>
                        </div>
                    </div>
                </div>
                <div class="space-y-1.5 mt-3">
                    @foreach($donutLabels as $i => $label)
                    @if($donutValues[$i] > 0)
                    <div class="flex items-center gap-2 text-xs">
                        <span class="w-2 h-2 rounded-full shrink-0" style="background:{{ $donutColors[$i] }}"></span>
                        <span class="flex-1 text-base-content/60 truncate">{{ $label }}</span>
                        <span class="font-bold text-base-content tabular-nums">{{ $donutValues[$i] }}</span>
                        @php $pct = $totalBulletins > 0 ? round($donutValues[$i]/$totalBulletins*100) : 0; @endphp
                        <span class="text-base-content/30 w-8 text-right tabular-nums">{{ $pct }}%</span>
                    </div>
                    @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endunless

    {{-- ── Moyennes par classe ─────────────────────────────────────────── --}}
    @unless(auth()->user()->hasRole('teacher'))
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide">Moyennes par classe</h3>
                    @if($globalMoyenne > 0)
                    <span class="badge badge-sm font-mono">Éc. : {{ number_format($globalMoyenne, 2) }}</span>
                    @endif
                </div>
                @if(count($classLabels) > 0)
                <div class="h-52">
                    <canvas id="chart-class"></canvas>
                </div>
                <div class="flex items-center gap-4 mt-2 pt-2 border-t border-base-200 text-xs text-base-content/40">
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-emerald-500 inline-block"></span>≥ 5/10</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-red-400 inline-block"></span>&lt; 5/10</span>
                </div>
                @else
                <div class="h-52 flex flex-col items-center justify-center text-base-content/25 gap-2">
                    <p class="text-5xl">📊</p>
                    <p class="text-sm font-semibold">Aucune moyenne disponible</p>
                    <p class="text-xs text-center">Publiez des bulletins pour voir les statistiques</p>
                </div>
                @endif
            </div>
        </div>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body p-5">
                <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide mb-3">Classement des classes</h3>
                @if($classRanking->isEmpty())
                <div class="flex flex-col items-center py-10 text-base-content/25 gap-2">
                    <p class="text-4xl">🏅</p>
                    <p class="text-sm">Aucune donnée publiée</p>
                </div>
                @else
                <div class="space-y-2">
                    @foreach($classRanking as $rank => $cls)
                    @php
                        $moy      = (float)$cls->avg_moy;
                        $pct      = $moy / 10 * 100;
                        $barClr   = $moy >= 7 ? 'bg-emerald-500' : ($moy >= 5 ? 'bg-amber-400' : 'bg-red-400');
                        $txtClr   = $moy >= 7 ? 'text-emerald-600' : ($moy >= 5 ? 'text-amber-600' : 'text-red-600');
                        $medal    = match($rank) { 0 => '🥇', 1 => '🥈', 2 => '🥉', default => '#'.($rank+1) };
                    @endphp
                    <div class="flex items-center gap-3">
                        <span class="text-sm w-7 text-center shrink-0">{{ $medal }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-0.5">
                                <span class="text-xs font-semibold text-base-content truncate">{{ $cls->classroom?->code ?? '?' }}</span>
                                <span class="text-xs font-black tabular-nums ml-2 shrink-0 {{ $txtClr }}">{{ number_format($moy, 2) }}/10</span>
                            </div>
                            <div class="w-full h-2 bg-base-200 rounded-full overflow-hidden">
                                <div class="{{ $barClr }} h-full rounded-full transition-all duration-700" style="width:{{ min($pct, 100) }}%"></div>
                            </div>
                            <div class="text-xs text-base-content/30 mt-0.5">{{ $cls->nb }} élève(s)</div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
    @endunless

    {{-- ── Workflow progress ─────────────────────────────────────────────── --}}
    @unless(auth()->user()->hasRole('teacher'))
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide">Workflow bulletins</h3>
                <a href="{{ route('bulletins.suivi') }}" wire:navigate class="text-xs text-primary hover:underline font-medium">Voir suivi détaillé →</a>
            </div>
            @php
                $wfSteps = [
                    ['label'=>'Brouillon',   'count'=>$brouillons, 'color'=>'bg-slate-400',   'bg'=>'bg-slate-100 text-slate-600',   'icon'=>'📝'],
                    ['label'=>'Soumis',      'count'=>$soumis,     'color'=>'bg-blue-500',    'bg'=>'bg-blue-100 text-blue-600',     'icon'=>'📤'],
                    ['label'=>'Péd. validé', 'count'=>$statusCounts[\App\Enums\BulletinStatusEnum::PEDAGOGIE_APPROVED->value]??0, 'color'=>'bg-violet-500', 'bg'=>'bg-violet-100 text-violet-600', 'icon'=>'📚'],
                    ['label'=>'Fin. validé', 'count'=>$statusCounts[\App\Enums\BulletinStatusEnum::FINANCE_APPROVED->value]??0,   'color'=>'bg-amber-500',  'bg'=>'bg-amber-100 text-amber-600',   'icon'=>'💼'],
                    ['label'=>'Approuvé',    'count'=>$statusCounts[\App\Enums\BulletinStatusEnum::APPROVED->value]??0,           'color'=>'bg-emerald-500','bg'=>'bg-emerald-100 text-emerald-600','icon'=>'✅'],
                    ['label'=>'Publié',      'count'=>$publies,    'color'=>'bg-[#16363a]',   'bg'=>'bg-teal-100 text-teal-700',    'icon'=>'🎓'],
                    ['label'=>'Rejeté',      'count'=>$rejetes,    'color'=>'bg-red-500',     'bg'=>'bg-red-100 text-red-600',      'icon'=>'↩️'],
                ];
                $wfTotal = max(array_sum(array_column($wfSteps,'count')), 1);
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2">
                @foreach($wfSteps as $i => $step)
                @php $pct = round($step['count'] / $wfTotal * 100); @endphp
                <div class="flex flex-col gap-1.5 p-3 rounded-xl bg-base-200/40 {{ $step['count'] > 0 && in_array($i,[1,2,3,4]) ? 'ring-1 ring-base-300' : '' }}">
                    <div class="flex items-center justify-between">
                        <span class="text-base leading-none">{{ $step['icon'] }}</span>
                        <span class="text-xs font-black tabular-nums">{{ $step['count'] }}</span>
                    </div>
                    <div class="w-full h-1.5 bg-base-200 rounded-full overflow-hidden mt-0.5">
                        <div class="{{ $step['color'] }} h-full rounded-full" style="width:{{ $pct }}%"></div>
                    </div>
                    <span class="text-[10px] font-semibold text-base-content/50 leading-tight">{{ $step['label'] }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endunless

    {{-- ── Pending + Recent activity ─────────────────────────────────────── --}}
    @unless(auth()->user()->hasRole('teacher'))
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide">
                        En attente de votre action
                        @if($myUrgentCount > 0)
                        <span class="badge badge-error badge-xs ml-1">{{ $myUrgentCount }}</span>
                        @endif
                    </h3>
                    <a href="{{ route('bulletins.index') }}" wire:navigate class="text-xs text-primary hover:underline font-medium">Voir tout →</a>
                </div>
                @if($pending->isEmpty())
                <div class="flex flex-col items-center py-10 text-base-content/25 gap-2">
                    <p class="text-5xl">🎉</p>
                    <p class="text-sm font-semibold">Tout est à jour !</p>
                    <p class="text-xs">Aucun bulletin en attente.</p>
                </div>
                @else
                <div class="divide-y divide-base-200">
                    @foreach($pending as $b)
                    <div class="flex items-center gap-3 py-2.5">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0
                            {{ $b->student->gender === 'M' ? 'bg-blue-100 text-blue-700' : 'bg-pink-100 text-pink-700' }}">
                            {{ strtoupper(substr($b->student->full_name, 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold truncate">{{ $b->student->full_name }}</p>
                            <p class="text-xs text-base-content/40">{{ $b->classroom->code ?? '—' }} &bull; {{ \App\Enums\PeriodEnum::from($b->period)->label() }}</p>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <span class="badge {{ $b->status->color() }} badge-xs">{{ $b->status->label() }}</span>
                            <a href="{{ route('bulletins.workflow', $b->id) }}" wire:navigate class="btn btn-xs btn-primary">⚖️</a>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide">Activité récente</h3>
                    <a href="{{ route('bulletins.index') }}" wire:navigate class="text-xs text-primary hover:underline font-medium">Tous →</a>
                </div>
                <div class="divide-y divide-base-200">
                    @forelse($recentBulletins as $b)
                    @php
                        $actMap = [
                            'draft'              => ['bg-slate-100 text-slate-500',    '📝'],
                            'submitted'          => ['bg-blue-100 text-blue-600',      '📤'],
                            'pedagogie_approved' => ['bg-violet-100 text-violet-600',  '📚'],
                            'finance_approved'   => ['bg-amber-100 text-amber-600',    '💼'],
                            'approved'           => ['bg-emerald-100 text-emerald-600','✅'],
                            'published'          => ['bg-teal-100 text-teal-700',      '🎓'],
                            'rejected'           => ['bg-red-100 text-red-600',        '↩️'],
                        ];
                        $act = $actMap[$b->status->value] ?? ['bg-base-200 text-base-content','📋'];
                    @endphp
                    <div class="flex items-center gap-3 py-2.5">
                        <div class="w-8 h-8 rounded-full {{ $act[0] }} flex items-center justify-center text-sm shrink-0">{{ $act[1] }}</div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium truncate">{{ $b->student->full_name }}</p>
                            <p class="text-xs text-base-content/40">
                                {{ $b->classroom->code ?? '—' }} &bull; {{ \App\Enums\PeriodEnum::from($b->period)->label() }} &bull; {{ $b->updated_at->format('d/m H:i') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            @if($b->moyenne !== null)
                            <span class="text-xs font-bold tabular-nums px-1.5 py-0.5 rounded {{ $b->moyenne >= 5 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600' }}">
                                {{ number_format($b->moyenne, 2) }}
                            </span>
                            @endif
                            <span class="badge {{ $b->status->color() }} badge-xs">{{ $b->status->label() }}</span>
                        </div>
                    </div>
                    @empty
                    <div class="py-8 text-center text-base-content/25 text-sm">Aucune activité récente.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    @endunless

    {{-- Filter drawer --}}
    <x-filter-drawer model="showFilters" title="Filtres" subtitle="Filtrer les données du tableau de bord">
        <x-choices label="Trimestre" wire:model.live="filterPeriod" :options="App\Enums\PeriodEnum::options()" single clearable icon="o-clock" placeholder="Tous les trimestres" />
        <x-slot:actions>
            <x-button label="Réinitialiser" wire:click="$set('filterPeriod', null)" icon="o-arrow-path" />
            <x-button label="Fermer" @click="$wire.showFilters = false" class="btn-primary" icon="o-check" />
        </x-slot:actions>
    </x-filter-drawer>

</div>

<script>
function destroyCharts() {
    ['chart-bar','chart-donut','chart-class','chart-sparkline'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el && el._chartInstance) { el._chartInstance.destroy(); el._chartInstance = null; }
    });
}

function initCharts() {
    var barEl = document.getElementById('chart-bar');
    if (barEl && !barEl._chartInstance) {
        barEl._chartInstance = new Chart(barEl, {
            type: 'bar',
            data: {
                labels: @json($barLabels),
                datasets: [{ label: 'Bulletins', data: @json($barValues),
                    backgroundColor: ['rgba(22,54,58,0.85)','rgba(200,145,58,0.85)','rgba(16,185,129,0.85)'],
                    borderRadius: 10, borderSkipped: false }]
            },
            options: { responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 11 } }, grid: { color: '#f1f5f9' } },
                    x: { grid: { display: false }, ticks: { font: { size: 12, weight: 'bold' } } }
                }
            }
        });
    }

    var donutEl = document.getElementById('chart-donut');
    if (donutEl && !donutEl._chartInstance) {
        donutEl._chartInstance = new Chart(donutEl, {
            type: 'doughnut',
            data: { labels: @json($donutLabels),
                datasets: [{ data: @json($donutValues), backgroundColor: @json($donutColors),
                    borderWidth: 3, borderColor: '#ffffff', hoverOffset: 6 }]
            },
            options: { cutout: '70%', plugins: { legend: { display: false } },
                animation: { animateRotate: true, duration: 600 } }
        });
    }

    var classEl = document.getElementById('chart-class');
    if (classEl && !classEl._chartInstance) {
        classEl._chartInstance = new Chart(classEl, {
            type: 'bar',
            data: { labels: @json($classLabels),
                datasets: [{ label: 'Moyenne /10', data: @json($classValues),
                    backgroundColor: @json($classColors), borderRadius: 8, borderSkipped: false }]
            },
            options: { responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, max: 10, ticks: { stepSize: 2, font: { size: 11 } }, grid: { color: '#f1f5f9' } },
                    x: { grid: { display: false }, ticks: { font: { size: 11, weight: '600' } } }
                }
            }
        });
    }

    var sparkEl = document.getElementById('chart-sparkline');
    if (sparkEl && !sparkEl._chartInstance) {
        sparkEl._chartInstance = new Chart(sparkEl, {
            type: 'line',
            data: { labels: ['T1','T2','T3'],
                datasets: [{ data: @json($trendValues),
                    borderColor: 'rgba(255,255,255,0.8)', backgroundColor: 'rgba(255,255,255,0.15)',
                    borderWidth: 2, pointRadius: 3, pointBackgroundColor: '#fff', fill: true, tension: 0.4 }]
            },
            options: { responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                scales: { x: { display: false }, y: { display: false, min: 0, max: 10 } }, animation: false
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', initCharts);
document.addEventListener('livewire:navigated', function() { destroyCharts(); initCharts(); });
document.addEventListener('livewire:updated', function() { destroyCharts(); setTimeout(initCharts, 30); });
</script>
