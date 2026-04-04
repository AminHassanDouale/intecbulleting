<?php

use App\Models\AcademicYear;
use App\Models\Bulletin;
use App\Models\Classroom;
use App\Models\Student;
use App\Enums\BulletinStatusEnum;
use App\Enums\PeriodEnum;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {

    public string $filterPeriod = '';

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

        $globalMoyenne    = round((clone $pubBase)->whereNotNull('moyenne')->avg('moyenne') ?? 0, 2);
        $totalPublished   = (clone $pubBase)->count();

        // Success rate: moyenne >= 5 (out of 10)
        $successCount = (clone $pubBase)->whereNotNull('moyenne')->where('moyenne', '>=', 5)->count();
        $pubWithMoy   = (clone $pubBase)->whereNotNull('moyenne')->count();
        $tauxReussite = $pubWithMoy > 0 ? round($successCount / $pubWithMoy * 100) : 0;

        // Per-class average ranking table (published, current filter)
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

        // Moyenne trend per period (T1, T2, T3) — all published, no period filter here
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

        // Class bar chart data
        $classLabels = $classRanking->map(fn($r) => $r->classroom?->code ?? '?')->values()->toArray();
        $classValues = $classRanking->pluck('avg_moy')->values()->toArray();
        $classColors = array_map(
            fn($v) => $v >= 5 ? 'rgba(16,185,129,0.85)' : 'rgba(239,68,68,0.85)',
            $classValues
        );

        // ── Pending for role ───────────────────────────────────────────────
        $pendingStatuses = match(true) {
            $user->hasRole('pedagogie') => [BulletinStatusEnum::SUBMITTED->value, BulletinStatusEnum::PEDAGOGIE_REVIEW->value],
            $user->hasRole('finance')   => [BulletinStatusEnum::PEDAGOGIE_APPROVED->value, BulletinStatusEnum::FINANCE_REVIEW->value],
            $user->hasRole('direction') => [BulletinStatusEnum::FINANCE_APPROVED->value, BulletinStatusEnum::DIRECTION_REVIEW->value],
            default                     => [BulletinStatusEnum::SUBMITTED->value, BulletinStatusEnum::PEDAGOGIE_REVIEW->value,
                                            BulletinStatusEnum::FINANCE_REVIEW->value, BulletinStatusEnum::DIRECTION_REVIEW->value],
        };
        $pending = Bulletin::whereIn('status', $pendingStatuses)
            ->when($this->filterPeriod, fn($q) => $q->where('period', $this->filterPeriod))
            ->with(['student','classroom'])->latest()->take(6)->get();

        $recentBulletins = Bulletin::with(['student','classroom'])
            ->latest('updated_at')->take(8)->get();

        // ── Teacher-specific ───────────────────────────────────────────────
        $teacherBulletins = null;
        if ($user->hasRole('teacher')) {
            $teacherBulletins = Bulletin::whereHas('classroom', function($q) use ($user) {
                    $q->where('teacher_id', $user->id);
                })
                ->when($this->filterPeriod, fn($q) => $q->where('period', $this->filterPeriod))
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total','status')
                ->toArray();
        }

        return compact(
            'totalEleves','garcons','filles','totalClasses',
            'totalBulletins','brouillons','enAttente','publies','rejetes',
            'globalMoyenne','tauxReussite','totalPublished','bestClassName','bestClassMoy',
            'trendValues',
            'donutLabels','donutValues','donutColors',
            'barLabels','barValues',
            'classLabels','classValues','classColors','classRanking',
            'pending','recentBulletins','statusCounts','teacherBulletins'
        );
    }
}; ?>

<div class="space-y-5">

    {{-- ── Welcome banner ──────────────────────────────────────────────── --}}
    <div class="relative overflow-hidden rounded-2xl bg-linear-to-br from-blue-700 via-indigo-700 to-violet-700 text-white px-6 py-6 shadow-xl">
        <div class="absolute -right-10 -top-10 w-48 h-48 bg-white/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute right-20 bottom-0   w-32 h-32 bg-violet-400/20 rounded-full blur-2xl pointer-events-none"></div>
        <div class="absolute left-1/3 -top-5    w-40 h-40 bg-blue-300/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-white/50 text-xs mb-1">{{ now()->translatedFormat('l d/m/Y') }}</p>
                <h1 class="text-2xl font-black tracking-tight">
                    Bonjour, {{ explode(' ', auth()->user()->name)[0] }} 👋
                </h1>
                <p class="text-white/70 text-sm mt-1">
                    @switch(auth()->user()->getRoleNames()->first())
                        @case('admin')     Accès administrateur complet @break
                        @case('direction') Vue direction — supervision générale @break
                        @case('pedagogie') Validation pédagogique des bulletins @break
                        @case('finance')   Validation financière des bulletins @break
                        @case('teacher')   Saisie et suivi de vos classes @break
                        @default Tableau de bord @endswitch
                </p>
            </div>
            <div class="self-start sm:self-auto">
                <x-select
                    wire:model.live="filterPeriod"
                    :options="App\Enums\PeriodEnum::options()"
                    placeholder="Tous les trimestres"
                    class="select-sm bg-white/15 border-white/30 text-white [&>option]:text-base-content"
                />
            </div>
        </div>
    </div>

    {{-- ── KPI cards ─────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
        @foreach([
            ['icon'=>'o-users',            'color'=>'from-blue-500 to-blue-600',       'bg'=>'bg-blue-50',    'value'=>$totalEleves,  'label'=>'Élèves',     'sub'=>$garcons.'G / '.$filles.'F'],
            ['icon'=>'o-building-library', 'color'=>'from-violet-500 to-violet-600',   'bg'=>'bg-violet-50',  'value'=>$totalClasses, 'label'=>'Classes',    'sub'=>null],
            ['icon'=>'o-document-text',    'color'=>'from-slate-400 to-slate-500',     'bg'=>'bg-slate-50',   'value'=>$brouillons,   'label'=>'Brouillons', 'sub'=>null],
            ['icon'=>'o-clock',            'color'=>'from-amber-400 to-amber-500',     'bg'=>'bg-amber-50',   'value'=>$enAttente,    'label'=>'En attente', 'sub'=>null],
            ['icon'=>'o-check-circle',     'color'=>'from-emerald-500 to-emerald-600', 'bg'=>'bg-emerald-50', 'value'=>$publies,      'label'=>'Publiés',    'sub'=>null],
            ['icon'=>'o-arrow-uturn-left', 'color'=>'from-red-500 to-red-600',         'bg'=>'bg-red-50',     'value'=>$rejetes,      'label'=>'Rejetés',    'sub'=>null],
        ] as $kpi)
        <div class="card bg-base-100 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200">
            <div class="card-body p-4">
                <div class="flex items-start gap-3">
                    <div class="p-2.5 rounded-xl {{ $kpi['bg'] }} shrink-0">
                        <x-icon :name="$kpi['icon']" class="w-5 h-5 bg-linear-to-br {{ $kpi['color'] }} bg-clip-text" style="color:transparent;background-clip:text;-webkit-background-clip:text;" />
                    </div>
                    <div class="min-w-0">
                        <div class="font-black text-2xl leading-tight text-base-content tabular-nums">{{ $kpi['value'] }}</div>
                        <div class="text-xs text-base-content/50 font-medium">{{ $kpi['label'] }}</div>
                        @if($kpi['sub'])
                        <div class="text-xs text-base-content/30 mt-0.5">{{ $kpi['sub'] }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ── Moyenne highlight cards ─────────────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">

        {{-- Moyenne globale --}}
        <div class="card overflow-hidden shadow-sm hover:shadow-md transition-all duration-200">
            <div class="bg-linear-to-br from-indigo-500 to-violet-600 p-5 text-white">
                <p class="text-xs font-semibold uppercase tracking-wide opacity-70">Moyenne globale</p>
                <div class="flex items-end gap-2 mt-2">
                    <span class="text-4xl font-black tabular-nums">{{ $globalMoyenne > 0 ? number_format($globalMoyenne, 2) : '—' }}</span>
                    @if($globalMoyenne > 0)<span class="text-lg opacity-60 mb-1">/10</span>@endif
                </div>
                <div class="mt-2 flex items-center gap-1.5 text-xs opacity-70">
                    <span class="w-1.5 h-1.5 rounded-full bg-white/60"></span>
                    {{ $totalPublished }} bulletins publiés
                </div>
            </div>
        </div>

        {{-- Taux de réussite --}}
        <div class="card overflow-hidden shadow-sm hover:shadow-md transition-all duration-200">
            <div class="bg-linear-to-br from-emerald-500 to-teal-600 p-5 text-white">
                <p class="text-xs font-semibold uppercase tracking-wide opacity-70">Taux de réussite</p>
                <div class="flex items-end gap-2 mt-2">
                    <span class="text-4xl font-black tabular-nums">{{ $tauxReussite }}</span>
                    <span class="text-lg opacity-60 mb-1">%</span>
                </div>
                <div class="mt-2 w-full bg-white/20 rounded-full h-1.5">
                    <div class="bg-white h-1.5 rounded-full transition-all" style="width:{{ $tauxReussite }}%"></div>
                </div>
            </div>
        </div>

        {{-- Meilleure classe --}}
        <div class="card overflow-hidden shadow-sm hover:shadow-md transition-all duration-200">
            <div class="bg-linear-to-br from-amber-400 to-orange-500 p-5 text-white">
                <p class="text-xs font-semibold uppercase tracking-wide opacity-70">Meilleure classe</p>
                <div class="flex items-end gap-2 mt-2">
                    <span class="text-4xl font-black truncate">{{ $bestClassName }}</span>
                </div>
                <div class="mt-2 flex items-center gap-1.5 text-xs opacity-80">
                    🏆 Moy. {{ $bestClassMoy > 0 ? number_format($bestClassMoy, 2).'/10' : '—' }}
                </div>
            </div>
        </div>

        {{-- Tendance --}}
        <div class="card overflow-hidden shadow-sm hover:shadow-md transition-all duration-200">
            <div class="bg-linear-to-br from-sky-500 to-blue-600 p-5 text-white">
                <p class="text-xs font-semibold uppercase tracking-wide opacity-70">Tendance (T1→T3)</p>
                <div class="flex items-center gap-2 mt-2 justify-between">
                    @foreach(['T1','T2','T3'] as $ti => $tp)
                    <div class="text-center">
                        <div class="text-xl font-black tabular-nums">{{ $trendValues[$ti] > 0 ? number_format($trendValues[$ti], 1) : '—' }}</div>
                        <div class="text-xs opacity-60">{{ $tp }}</div>
                    </div>
                    @if($ti < 2)<div class="opacity-30 text-lg">→</div>@endif
                    @endforeach
                </div>
                <div class="h-1 w-full relative mt-3">
                    <canvas id="chart-sparkline" class="absolute inset-0 w-full h-full"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Charts row ──────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Bar: bulletins by period --}}
        <div class="card bg-base-100 shadow-sm lg:col-span-2">
            <div class="card-body p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide">📅 Bulletins par trimestre</h3>
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

        {{-- Donut: status breakdown --}}
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body p-5">
                <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide mb-3">📊 Répartition statuts</h3>
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

    {{-- ── Moyennes par classe ─────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- Bar chart classes --}}
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide">🏆 Moyennes par classe</h3>
                    @if($globalMoyenne > 0)
                    <span class="badge badge-sm font-mono">Moy. école : {{ number_format($globalMoyenne, 2) }}</span>
                    @endif
                </div>
                @if(count($classLabels) > 0)
                <div class="h-52">
                    <canvas id="chart-class"></canvas>
                </div>
                <div class="flex items-center gap-4 mt-2 pt-2 border-t border-base-200 text-xs text-base-content/40">
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-emerald-500 inline-block"></span> ≥ 5/10 (réussite)</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-red-400 inline-block"></span> &lt; 5/10</span>
                </div>
                @else
                <div class="h-52 flex flex-col items-center justify-center text-base-content/25 gap-2">
                    <span class="text-5xl">📊</span>
                    <p class="text-sm font-semibold">Aucune moyenne disponible</p>
                    <p class="text-xs text-center">Publiez des bulletins pour voir les statistiques</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Class ranking table --}}
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body p-5">
                <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide mb-3">📋 Classement des classes</h3>
                @if($classRanking->isEmpty())
                <div class="flex flex-col items-center py-10 text-base-content/25 gap-2">
                    <span class="text-4xl">🏅</span>
                    <p class="text-sm">Aucune donnée publiée</p>
                </div>
                @else
                <div class="space-y-2">
                    @foreach($classRanking as $rank => $cls)
                    @php
                        $moy = (float)$cls->avg_moy;
                        $pct = $moy / 10 * 100;
                        $barColor = $moy >= 7 ? 'bg-emerald-500' : ($moy >= 5 ? 'bg-amber-400' : 'bg-red-400');
                        $medal = match($rank) { 0 => '🥇', 1 => '🥈', 2 => '🥉', default => '#'.($rank+1) };
                    @endphp
                    <div class="flex items-center gap-3">
                        <span class="text-sm w-7 text-center shrink-0">{{ $medal }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-0.5">
                                <span class="text-xs font-semibold text-base-content truncate">{{ $cls->classroom?->code ?? '?' }}</span>
                                <span class="text-xs font-black tabular-nums ml-2 shrink-0
                                    {{ $moy >= 7 ? 'text-emerald-600' : ($moy >= 5 ? 'text-amber-600' : 'text-red-600') }}">
                                    {{ number_format($moy, 2) }}/10
                                </span>
                            </div>
                            <div class="w-full h-2 bg-base-200 rounded-full overflow-hidden">
                                <div class="{{ $barColor }} h-full rounded-full transition-all duration-700"
                                     style="width:{{ min($pct, 100) }}%"></div>
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

    {{-- ── Workflow progress ────────────────────────────────────────────── --}}
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body p-5">
            <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide mb-4">🔄 Progression du workflow bulletins</h3>
            @php
                $wfSteps = [
                    ['label' => 'Brouillon',   'count' => $brouillons, 'color' => 'bg-slate-400',    'icon' => '📝', 'bg' => 'bg-slate-100 text-slate-600'],
                    ['label' => 'Soumis',      'count' => $statusCounts[\App\Enums\BulletinStatusEnum::SUBMITTED->value] ?? 0,           'color' => 'bg-blue-500',    'icon' => '📤', 'bg' => 'bg-blue-100 text-blue-600'],
                    ['label' => 'Péd. validé', 'count' => $statusCounts[\App\Enums\BulletinStatusEnum::PEDAGOGIE_APPROVED->value] ?? 0,   'color' => 'bg-violet-500',  'icon' => '📚', 'bg' => 'bg-violet-100 text-violet-600'],
                    ['label' => 'Fin. validé', 'count' => $statusCounts[\App\Enums\BulletinStatusEnum::FINANCE_APPROVED->value] ?? 0,     'color' => 'bg-amber-500',   'icon' => '💰', 'bg' => 'bg-amber-100 text-amber-600'],
                    ['label' => 'Approuvé',    'count' => $statusCounts[\App\Enums\BulletinStatusEnum::APPROVED->value] ?? 0,             'color' => 'bg-emerald-500', 'icon' => '✅', 'bg' => 'bg-emerald-100 text-emerald-600'],
                    ['label' => 'Publié',      'count' => $publies,    'color' => 'bg-indigo-500',   'icon' => '🎓', 'bg' => 'bg-indigo-100 text-indigo-600'],
                    ['label' => 'Rejeté',      'count' => $rejetes,    'color' => 'bg-red-500',      'icon' => '❌', 'bg' => 'bg-red-100 text-red-600'],
                ];
                $wfTotal = max(array_sum(array_column($wfSteps, 'count')), 1);
            @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach($wfSteps as $step)
                @php $pct = round($step['count'] / $wfTotal * 100); @endphp
                <div class="flex items-center gap-3 p-3 rounded-xl bg-base-200/40">
                    <div class="w-8 h-8 rounded-lg {{ $step['bg'] }} flex items-center justify-center text-sm shrink-0">
                        {{ $step['icon'] }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-semibold text-base-content/70 truncate">{{ $step['label'] }}</span>
                            <span class="text-xs font-black tabular-nums ml-1 shrink-0">{{ $step['count'] }}</span>
                        </div>
                        <div class="w-full h-1.5 bg-base-200 rounded-full overflow-hidden">
                            <div class="{{ $step['color'] }} h-full rounded-full" style="width:{{ $pct }}%"></div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ── Pending + Recent activity ────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        @unless(auth()->user()->hasRole('teacher'))
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide">⏳ En attente de votre action</h3>
                    <a href="{{ route('bulletins.index') }}" wire:navigate class="text-xs text-primary hover:underline font-medium">Voir tout →</a>
                </div>
                @if($pending->isEmpty())
                <div class="flex flex-col items-center py-10 text-base-content/25 gap-2">
                    <span class="text-5xl">🎉</span>
                    <p class="text-sm font-semibold">Tout est à jour !</p>
                    <p class="text-xs">Aucun bulletin en attente.</p>
                </div>
                @else
                <div class="divide-y divide-base-200">
                    @foreach($pending as $b)
                    <div class="flex items-center gap-3 py-2.5">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0
                            {{ $b->student->gender === 'M' ? 'bg-blue-100 text-blue-700' : 'bg-pink-100 text-pink-700' }}">
                            {{ strtoupper(substr($b->student->first_name, 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold truncate">{{ $b->student->full_name }}</p>
                            <p class="text-xs text-base-content/40">
                                {{ $b->classroom->code ?? '—' }} &bull; {{ \App\Enums\PeriodEnum::from($b->period)->label() }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="badge {{ $b->status->color() }} badge-xs">{{ $b->status->label() }}</span>
                            <a href="{{ route('bulletins.workflow', $b->id) }}" wire:navigate class="btn btn-xs btn-primary">⚖️</a>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
        @endunless

        <div class="card bg-base-100 shadow-sm {{ auth()->user()->hasRole('teacher') ? 'lg:col-span-2' : '' }}">
            <div class="card-body p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide">🕐 Activité récente</h3>
                    <a href="{{ route('bulletins.index') }}" wire:navigate class="text-xs text-primary hover:underline font-medium">Tous →</a>
                </div>
                <div class="divide-y divide-base-200">
                    @forelse($recentBulletins as $b)
                    @php
                        $actIcons = [
                            'draft'              => ['bg-slate-100 text-slate-500',    '📝'],
                            'submitted'          => ['bg-blue-100 text-blue-600',      '📤'],
                            'pedagogie_approved' => ['bg-violet-100 text-violet-600',  '📚'],
                            'finance_approved'   => ['bg-amber-100 text-amber-600',    '💰'],
                            'approved'           => ['bg-emerald-100 text-emerald-600','✅'],
                            'published'          => ['bg-indigo-100 text-indigo-600',  '🎓'],
                            'rejected'           => ['bg-red-100 text-red-600',        '❌'],
                        ];
                        $act = $actIcons[$b->status->value] ?? ['bg-base-200 text-base-content', '📋'];
                    @endphp
                    <div class="flex items-center gap-3 py-2.5">
                        <div class="w-8 h-8 rounded-full {{ $act[0] }} flex items-center justify-center text-sm shrink-0">{{ $act[1] }}</div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium truncate">{{ $b->student->full_name }}</p>
                            <p class="text-xs text-base-content/40">
                                {{ $b->classroom->code ?? '—' }} &bull; {{ \App\Enums\PeriodEnum::from($b->period)->label() }} &bull; {{ $b->updated_at->format('d/m H:i') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            @if($b->moyenne !== null)
                            <span class="text-xs font-bold tabular-nums px-1.5 py-0.5 rounded
                                {{ $b->moyenne >= 5 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600' }}">
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

    {{-- ── Teacher quick actions ────────────────────────────────────────── --}}
    @role('teacher')
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <a href="{{ route('bulletins.grade-form') }}" wire:navigate
           class="card bg-linear-to-br from-amber-400 to-orange-500 text-white shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all duration-200 cursor-pointer">
            <div class="card-body p-6">
                <span class="text-4xl">✏️</span>
                <h3 class="font-bold text-lg mt-3">Saisir les notes</h3>
                <p class="text-sm text-white/70">Accéder au formulaire de saisie des compétences</p>
            </div>
        </a>
        <a href="{{ route('bulletins.index') }}" wire:navigate
           class="card bg-linear-to-br from-blue-500 to-indigo-600 text-white shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all duration-200 cursor-pointer">
            <div class="card-body p-6">
                <span class="text-4xl">📋</span>
                <h3 class="font-bold text-lg mt-3">Mes bulletins</h3>
                <p class="text-sm text-white/70">Suivre l'état de vos bulletins soumis</p>
            </div>
        </a>
    </div>
    @endrole

</div>

{{-- Chart.js — destroy + reinit on every Livewire update so filter changes work --}}
<script>
function destroyCharts() {
    ['chart-bar','chart-donut','chart-class','chart-sparkline'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el && el._chartInstance) {
            el._chartInstance.destroy();
            el._chartInstance = null;
        }
    });
}

function initCharts() {
    // Bar: bulletins by period
    var barEl = document.getElementById('chart-bar');
    if (barEl && !barEl._chartInstance) {
        barEl._chartInstance = new Chart(barEl, {
            type: 'bar',
            data: {
                labels: @json($barLabels),
                datasets: [{
                    label: 'Bulletins',
                    data: @json($barValues),
                    backgroundColor: [
                        'rgba(59,130,246,0.85)',
                        'rgba(139,92,246,0.85)',
                        'rgba(16,185,129,0.85)'
                    ],
                    borderRadius: 10,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 11 } }, grid: { color: '#f1f5f9' } },
                    x: { grid: { display: false }, ticks: { font: { size: 12, weight: 'bold' } } }
                }
            }
        });
    }

    // Donut: status breakdown
    var donutEl = document.getElementById('chart-donut');
    if (donutEl && !donutEl._chartInstance) {
        donutEl._chartInstance = new Chart(donutEl, {
            type: 'doughnut',
            data: {
                labels: @json($donutLabels),
                datasets: [{
                    data: @json($donutValues),
                    backgroundColor: @json($donutColors),
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverOffset: 6
                }]
            },
            options: {
                cutout: '70%',
                plugins: { legend: { display: false } },
                animation: { animateRotate: true, duration: 600 }
            }
        });
    }

    // Bar: class averages
    var classEl = document.getElementById('chart-class');
    if (classEl && !classEl._chartInstance) {
        classEl._chartInstance = new Chart(classEl, {
            type: 'bar',
            data: {
                labels: @json($classLabels),
                datasets: [{
                    label: 'Moyenne /10',
                    data: @json($classValues),
                    backgroundColor: @json($classColors),
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true, max: 10,
                        ticks: { stepSize: 2, font: { size: 11 } },
                        grid: { color: '#f1f5f9' }
                    },
                    x: { grid: { display: false }, ticks: { font: { size: 11, weight: '600' } } }
                }
            }
        });
    }

    // Sparkline: moyenne trend T1→T2→T3
    var sparkEl = document.getElementById('chart-sparkline');
    if (sparkEl && !sparkEl._chartInstance) {
        sparkEl._chartInstance = new Chart(sparkEl, {
            type: 'line',
            data: {
                labels: ['T1', 'T2', 'T3'],
                datasets: [{
                    data: @json($trendValues),
                    borderColor: 'rgba(255,255,255,0.8)',
                    backgroundColor: 'rgba(255,255,255,0.15)',
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: '#fff',
                    fill: true,
                    tension: 0.4,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                scales: { x: { display: false }, y: { display: false, min: 0, max: 10 } },
                animation: false
            }
        });
    }
}

// Initial load
document.addEventListener('DOMContentLoaded', initCharts);
document.addEventListener('livewire:navigated', function() {
    destroyCharts();
    initCharts();
});
// Re-render on Livewire updates (e.g. filterPeriod change)
document.addEventListener('livewire:updated', function() {
    destroyCharts();
    setTimeout(initCharts, 30);
});
</script>
