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

    public ?string $filterPeriod  = '';
    public ?int    $filterNiveau  = null;
    public string  $activeTab     = 'overview';

    public function mount(): void
    {
        $this->filterPeriod = '';
    }

    public function with(): array
    {
        $periods = ['T1', 'T2', 'T3'];

        // ── Global stats ───────────────────────────────────────────────────
        $totalEleves   = Student::count();
        $totalClasses  = Classroom::count();
        $garcons       = Student::where('gender', 'M')->count();
        $filles        = Student::where('gender', 'F')->count();

        $pubQ = Bulletin::where('status', BulletinStatusEnum::PUBLISHED)
            ->when($this->filterPeriod, fn($q) => $q->where('period', $this->filterPeriod))
            ->when($this->filterNiveau, fn($q) =>
                $q->whereHas('classroom', fn($q2) => $q2->where('niveau_id', $this->filterNiveau))
            );

        $totalPublished  = (clone $pubQ)->count();
        $globalMoyenne   = round((clone $pubQ)->whereNotNull('moyenne')->avg('moyenne') ?? 0, 2);
        $pubWithMoy      = (clone $pubQ)->whereNotNull('moyenne')->count();
        $successCount    = (clone $pubQ)->whereNotNull('moyenne')->where('moyenne','>=',5)->count();
        $tauxReussite    = $pubWithMoy > 0 ? round($successCount / $pubWithMoy * 100) : 0;

        // ── Per-niveau detailed stats ──────────────────────────────────────
        $niveauxAll = Niveau::orderBy('order')->get();
        $niveauRows = $niveauxAll
            ->when($this->filterNiveau, fn($c) => $c->where('id', $this->filterNiveau))
            ->map(function($n) {
                $classIds = Classroom::where('niveau_id', $n->id)->pluck('id');
                if ($classIds->isEmpty()) return null;

                $base = Bulletin::whereIn('classroom_id', $classIds)
                    ->when($this->filterPeriod, fn($q) => $q->where('period', $this->filterPeriod));
                $pub  = (clone $base)->where('status', BulletinStatusEnum::PUBLISHED);

                $total   = (clone $base)->count();
                $pubCnt  = $pub->count();
                $draft   = (clone $base)->where('status', BulletinStatusEnum::DRAFT)->count();
                $pending = (clone $base)->whereIn('status', [
                    BulletinStatusEnum::SUBMITTED->value,
                    BulletinStatusEnum::PEDAGOGIE_REVIEW->value,
                    BulletinStatusEnum::FINANCE_REVIEW->value,
                    BulletinStatusEnum::DIRECTION_REVIEW->value,
                ])->count();
                $avg     = round((float)((clone $pub)->whereNotNull('moyenne')->avg('moyenne') ?? 0), 2);
                $withMoy = (clone $pub)->whereNotNull('moyenne')->count();
                $pass    = (clone $pub)->whereNotNull('moyenne')->where('moyenne','>=',5)->count();
                $fail    = $withMoy - $pass;
                $taux    = $withMoy > 0 ? round($pass / $withMoy * 100) : null;

                // Per-period breakdown for this niveau
                $byPeriod = collect(['T1','T2','T3'])->mapWithKeys(function($p) use ($classIds) {
                    $pq  = Bulletin::whereIn('classroom_id', $classIds)
                        ->where('period', $p)
                        ->where('status', BulletinStatusEnum::PUBLISHED);
                    $avg = round((float)((clone $pq)->whereNotNull('moyenne')->avg('moyenne') ?? 0), 2);
                    $cnt = (clone $pq)->count();
                    return [$p => ['avg' => $avg, 'count' => $cnt]];
                })->toArray();

                // Classes within niveau
                $classes = Classroom::where('niveau_id', $n->id)
                    ->with(['teacher'])
                    ->withCount('students')
                    ->get()
                    ->map(function($c) {
                        $bq  = Bulletin::where('classroom_id', $c->id)
                            ->when($this->filterPeriod, fn($q) => $q->where('period', $this->filterPeriod))
                            ->where('status', BulletinStatusEnum::PUBLISHED);
                        $avg    = round((float)((clone $bq)->whereNotNull('moyenne')->avg('moyenne') ?? 0), 2);
                        $cnt    = $bq->count();
                        $with   = (clone $bq)->whereNotNull('moyenne')->count();
                        $pass   = (clone $bq)->whereNotNull('moyenne')->where('moyenne','>=',5)->count();
                        return [
                            'code'     => $c->code,
                            'teacher'  => $c->teacher?->name ?? '—',
                            'students' => $c->students_count,
                            'published'=> $cnt,
                            'avg'      => $avg,
                            'taux'     => $with > 0 ? round($pass / $with * 100) : null,
                        ];
                    })
                    ->sortByDesc('avg')
                    ->values();

                return compact('n','total','pubCnt','draft','pending','avg','taux','pass','fail','withMoy','byPeriod','classes');
            })
            ->filter()
            ->values();

        // ── Period comparison (all niveaux) ───────────────────────────────
        $periodComparison = collect(['T1','T2','T3'])->map(function($p) {
            $base = Bulletin::where('period', $p)->where('status', BulletinStatusEnum::PUBLISHED);
            $cnt  = (clone $base)->count();
            $avg  = round((float)((clone $base)->whereNotNull('moyenne')->avg('moyenne') ?? 0), 2);
            $with = (clone $base)->whereNotNull('moyenne')->count();
            $pass = (clone $base)->whereNotNull('moyenne')->where('moyenne','>=',5)->count();
            return [
                'period' => $p,
                'label'  => PeriodEnum::from($p)->label(),
                'count'  => $cnt,
                'avg'    => $avg,
                'taux'   => $with > 0 ? round($pass / $with * 100) : null,
                'pass'   => $pass,
                'fail'   => $with - $pass,
            ];
        });

        // ── Distribution des moyennes (published, current filters) ─────────
        $distribution = [
            ['range' => '0 – 2.9',  'min' => 0,  'max' => 2.9,  'color' => 'bg-red-600',     'label' => 'Très faible'],
            ['range' => '3 – 4.9',  'min' => 3,  'max' => 4.9,  'color' => 'bg-red-400',     'label' => 'Faible'],
            ['range' => '5 – 5.9',  'min' => 5,  'max' => 5.9,  'color' => 'bg-amber-400',   'label' => 'Passable'],
            ['range' => '6 – 6.9',  'min' => 6,  'max' => 6.9,  'color' => 'bg-yellow-400',  'label' => 'Assez bien'],
            ['range' => '7 – 7.9',  'min' => 7,  'max' => 7.9,  'color' => 'bg-lime-400',    'label' => 'Bien'],
            ['range' => '8 – 10',   'min' => 8,  'max' => 10,   'color' => 'bg-emerald-500', 'label' => 'Très bien'],
        ];
        $distTotal = 0;
        foreach ($distribution as &$d) {
            $d['count'] = (clone $pubQ)->whereNotNull('moyenne')
                ->where('moyenne', '>=', $d['min'])
                ->where('moyenne', '<=', $d['max'])
                ->count();
            $distTotal += $d['count'];
        }
        unset($d);
        foreach ($distribution as &$d) {
            $d['pct'] = $distTotal > 0 ? round($d['count'] / $distTotal * 100) : 0;
        }
        unset($d);

        // Chart data: periode trend per niveau (all niveaux)
        $trendChartLabels  = ['T1','T2','T3'];
        $trendChartDatasets = $niveauxAll->take(6)->map(function($n, $i) {
            $colors = ['#16363a','#c8913a','#3b82f6','#8b5cf6','#10b981','#ef4444'];
            $vals = collect(['T1','T2','T3'])->map(function($p) use ($n) {
                $cids = Classroom::where('niveau_id', $n->id)->pluck('id');
                return round((float)(Bulletin::whereIn('classroom_id', $cids)
                    ->where('period', $p)
                    ->where('status', BulletinStatusEnum::PUBLISHED)
                    ->whereNotNull('moyenne')
                    ->avg('moyenne') ?? 0), 2);
            })->values()->toArray();
            return ['label' => $n->label, 'data' => $vals, 'borderColor' => $colors[$i % 6],
                    'backgroundColor' => 'transparent', 'borderWidth' => 2,
                    'pointRadius' => 4, 'tension' => 0.3];
        })->values()->toArray();

        $niveauxOptions = array_merge(
            [['id' => '', 'name' => 'Tous les niveaux']],
            $niveauxAll->map(fn($n) => ['id' => $n->id, 'name' => $n->label])->toArray()
        );
        $periodOptions = array_merge(
            [['id' => '', 'name' => 'Tous trimestres']],
            PeriodEnum::options()
        );

        return compact(
            'totalEleves','totalClasses','garcons','filles',
            'totalPublished','globalMoyenne','tauxReussite','pubWithMoy',
            'niveauRows','periodComparison','distribution','distTotal',
            'trendChartLabels','trendChartDatasets',
            'niveauxOptions','periodOptions'
        );
    }
}; ?>

<div class="space-y-5">

    {{-- Page header --}}
    <div class="relative overflow-hidden rounded-2xl bg-linear-to-br from-[#16363a] via-teal-900 to-slate-900 text-white px-6 py-6 shadow-xl">
        <div class="absolute -right-8 -top-8 w-48 h-48 bg-white/5 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute right-24 bottom-0 w-36 h-36 bg-[#c8913a]/15 rounded-full blur-2xl pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 w-full h-0.5 bg-gradient-to-r from-[#c8913a]/60 via-transparent to-transparent"></div>
        <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-xl bg-white/10 border border-white/20 flex items-center justify-center text-2xl backdrop-blur">📊</div>
                <div>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wide bg-[#c8913a]/30 text-[#f5c87a] border border-[#c8913a]/40">
                        Rapports &amp; Analytiques
                    </span>
                    <h1 class="text-2xl font-black tracking-tight mt-1">Centre de rapports</h1>
                    <p class="text-white/60 text-sm mt-0.5">Statistiques détaillées par niveau, classe et période</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 self-start sm:self-auto">
                <x-choices wire:model.live="filterNiveau" :options="$niveauxOptions" single clearable
                    placeholder="Niveau…" class="min-w-36 bg-white/10 border-white/20 text-white" />
                <x-choices wire:model.live="filterPeriod" :options="$periodOptions" single clearable
                    placeholder="Trimestre…" class="min-w-36 bg-white/10 border-white/20 text-white" />
            </div>
        </div>
    </div>

    {{-- ── Global summary KPIs ─────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        @foreach([
            ['icon'=>'🎓', 'bg'=>'bg-[#16363a]', 'text'=>'text-white', 'value'=>$totalPublished, 'label'=>'Bulletins publiés', 'sub'=>null],
            ['icon'=>'📈', 'bg'=>'bg-[#c8913a]', 'text'=>'text-white', 'value'=>($globalMoyenne > 0 ? number_format($globalMoyenne,2).'/10' : '—'), 'label'=>'Moyenne globale', 'sub'=>$pubWithMoy.' avec moyenne'],
            ['icon'=>'✅', 'bg'=>'bg-emerald-600','text'=>'text-white', 'value'=>$tauxReussite.'%', 'label'=>'Taux de réussite', 'sub'=>'Seuil ≥ 5/10'],
            ['icon'=>'👥', 'bg'=>'bg-blue-600',   'text'=>'text-white', 'value'=>$totalEleves, 'label'=>'Élèves inscrits', 'sub'=>$garcons.'G / '.$filles.'F'],
        ] as $kpi)
        <div class="card overflow-hidden shadow-sm">
            <div class="{{ $kpi['bg'] }} p-5 {{ $kpi['text'] }}">
                <div class="text-2xl mb-2">{{ $kpi['icon'] }}</div>
                <div class="text-3xl font-black tabular-nums leading-tight">{{ $kpi['value'] }}</div>
                <div class="text-xs opacity-70 mt-1 font-semibold">{{ $kpi['label'] }}</div>
                @if($kpi['sub'])
                <div class="text-[11px] opacity-50 mt-0.5">{{ $kpi['sub'] }}</div>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- ── Tabs ─────────────────────────────────────────────────────────── --}}
    <div class="flex gap-1 p-1 bg-base-200 rounded-xl w-fit">
        @foreach([
            ['id'=>'overview',     'label'=>'Vue d\'ensemble', 'icon'=>'📋'],
            ['id'=>'niveaux',      'label'=>'Par niveau',      'icon'=>'🎓'],
            ['id'=>'periodes',     'label'=>'Par période',     'icon'=>'📅'],
            ['id'=>'distribution', 'label'=>'Distribution',    'icon'=>'📊'],
        ] as $tab)
        <button wire:click="$set('activeTab','{{ $tab['id'] }}')"
            class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold transition-all
                {{ $activeTab === $tab['id'] ? 'bg-base-100 text-[#16363a] shadow-sm' : 'text-base-content/50 hover:text-base-content' }}">
            {{ $tab['icon'] }} {{ $tab['label'] }}
        </button>
        @endforeach
    </div>

    {{-- ── Tab: Overview ──────────────────────────────────────────────── --}}
    @if($activeTab === 'overview')
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- Trend chart: moyenne par niveau par trimestre --}}
        <div class="card bg-base-100 shadow-sm lg:col-span-2">
            <div class="card-body p-5">
                <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide mb-4">Évolution des moyennes T1 → T3 par niveau</h3>
                @if(count($trendChartDatasets) > 0)
                <div class="h-64">
                    <canvas id="chart-trend"></canvas>
                </div>
                @else
                <div class="h-64 flex items-center justify-center text-base-content/25 text-sm">
                    <p>Aucune donnée publiée disponible</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Period comparison summary --}}
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body p-5">
                <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide mb-4">Récapitulatif par trimestre</h3>
                <div class="space-y-3">
                    @foreach($periodComparison as $pc)
                    @php
                        $pColor = $pc['avg'] >= 7 ? 'text-emerald-600' : ($pc['avg'] >= 5 ? 'text-amber-600' : 'text-red-500');
                        $bColor = $pc['avg'] >= 7 ? 'bg-emerald-500' : ($pc['avg'] >= 5 ? 'bg-amber-400' : 'bg-red-400');
                    @endphp
                    <div class="p-3 rounded-xl bg-base-200/50">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="badge badge-outline badge-sm font-bold">{{ $pc['period'] }}</span>
                                <span class="text-sm font-semibold text-base-content/70">{{ $pc['label'] }}</span>
                            </div>
                            <span class="text-lg font-black {{ $pColor }} tabular-nums">
                                {{ $pc['avg'] > 0 ? number_format($pc['avg'], 2).'/10' : '—' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-4 text-xs text-base-content/50">
                            <span><b class="text-indigo-600">{{ $pc['count'] }}</b> publiés</span>
                            @if($pc['taux'] !== null)
                            <span><b class="{{ $pc['taux'] >= 50 ? 'text-emerald-600' : 'text-red-500' }}">{{ $pc['taux'] }}%</b> réussite</span>
                            <span><b class="text-emerald-600">{{ $pc['pass'] }}</b> réussis</span>
                            <span><b class="text-red-500">{{ $pc['fail'] }}</b> en échec</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Top classes --}}
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body p-5">
                <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide mb-4">Top classes par moyenne</h3>
                @php
                    $allClasses = $niveauRows->flatMap(fn($nr) => $nr['classes']->map(fn($c) => array_merge($c, ['niveau' => $nr['n']->label])))
                        ->sortByDesc('avg')->take(10)->values();
                @endphp
                @if($allClasses->isEmpty())
                <div class="py-10 text-center text-base-content/25 text-sm">Aucune donnée</div>
                @else
                <div class="space-y-2">
                    @foreach($allClasses as $rank => $cls)
                    @php
                        $moy   = (float)($cls['avg'] ?? 0);
                        $pct   = $moy / 10 * 100;
                        $bc    = $moy >= 7 ? 'bg-emerald-500' : ($moy >= 5 ? 'bg-amber-400' : 'bg-red-400');
                        $tc    = $moy >= 7 ? 'text-emerald-600' : ($moy >= 5 ? 'text-amber-600' : 'text-red-500');
                        $medal = match($rank) { 0=>'🥇', 1=>'🥈', 2=>'🥉', default=>'#'.($rank+1) };
                    @endphp
                    <div class="flex items-center gap-3">
                        <span class="text-sm w-7 text-center shrink-0">{{ $medal }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-0.5">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="text-xs font-bold text-base-content">{{ $cls['code'] }}</span>
                                    <span class="text-[10px] text-base-content/40 truncate">{{ $cls['niveau'] }}</span>
                                </div>
                                <span class="text-xs font-black {{ $tc }} tabular-nums ml-2 shrink-0">{{ $moy > 0 ? number_format($moy,2).'/10' : '—' }}</span>
                            </div>
                            <div class="w-full h-1.5 bg-base-200 rounded-full overflow-hidden">
                                <div class="{{ $bc }} h-full rounded-full" style="width:{{ min($pct,100) }}%"></div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- ── Tab: Par niveau ────────────────────────────────────────────── --}}
    @if($activeTab === 'niveaux')
    <div class="space-y-4">
        @forelse($niveauRows as $nr)
        @php
            $avgColor = $nr['avg'] > 0 ? ($nr['avg'] >= 7 ? 'text-emerald-600' : ($nr['avg'] >= 5 ? 'text-amber-600' : 'text-red-500')) : 'text-base-content/30';
        @endphp
        <div class="card bg-base-100 shadow-sm overflow-hidden">
            {{-- Niveau header --}}
            <div class="bg-linear-to-r from-[#16363a]/5 to-transparent border-b border-base-200 px-5 py-3">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-3">
                        <span class="badge badge-lg badge-outline font-bold text-[#16363a] border-[#16363a]/40">{{ $nr['n']->code }}</span>
                        <div>
                            <h3 class="font-bold text-base-content">{{ $nr['n']->label }}</h3>
                            <p class="text-xs text-base-content/40">{{ $nr['classes']->count() }} classe(s) &bull; {{ $nr['total'] }} bulletins</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="text-right">
                            <div class="text-2xl font-black {{ $avgColor }}">{{ $nr['avg'] > 0 ? number_format($nr['avg'],2) : '—' }}<span class="text-sm text-base-content/40 font-normal">/10</span></div>
                            <div class="text-[11px] text-base-content/40">moy. globale</div>
                        </div>
                        @if($nr['taux'] !== null)
                        <div class="text-right">
                            <div class="text-2xl font-black {{ $nr['taux'] >= 50 ? 'text-emerald-600' : 'text-red-500' }}">{{ $nr['taux'] }}%</div>
                            <div class="text-[11px] text-base-content/40">réussite</div>
                        </div>
                        @endif
                        <div class="flex gap-2 text-xs">
                            <span class="px-2 py-1 rounded-lg bg-indigo-50 text-indigo-600 font-semibold">{{ $nr['pubCnt'] }} publiés</span>
                            @if($nr['pending'] > 0)
                            <span class="px-2 py-1 rounded-lg bg-amber-50 text-amber-600 font-semibold">{{ $nr['pending'] }} en attente</span>
                            @endif
                            @if($nr['draft'] > 0)
                            <span class="px-2 py-1 rounded-lg bg-slate-100 text-slate-500 font-semibold">{{ $nr['draft'] }} brouillons</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Per-period mini stats --}}
                <div class="flex gap-3 mt-3 pt-3 border-t border-base-200/60">
                    @foreach($nr['byPeriod'] as $p => $pdata)
                    <div class="flex items-center gap-2 text-xs">
                        <span class="badge badge-xs badge-ghost font-bold">{{ $p }}</span>
                        <span class="{{ $pdata['avg'] >= 5 ? 'text-emerald-600' : ($pdata['avg'] > 0 ? 'text-red-500' : 'text-base-content/30') }} font-bold">
                            {{ $pdata['avg'] > 0 ? number_format($pdata['avg'],2) : '—' }}
                        </span>
                        <span class="text-base-content/30">({{ $pdata['count'] }})</span>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Classes table --}}
            <div class="p-4">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-base-content/40 font-bold uppercase text-[10px] tracking-wide border-b border-base-200">
                                <th class="py-2 pr-3 text-left">Classe</th>
                                <th class="py-2 pr-3 text-left">Enseignant</th>
                                <th class="py-2 pr-3 text-center">Élèves</th>
                                <th class="py-2 pr-3 text-center">Publiés</th>
                                <th class="py-2 pr-3 text-center">Moyenne</th>
                                <th class="py-2 text-center">Réussite</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-200/60">
                            @foreach($nr['classes'] as $rank => $cls)
                            @php
                                $cMoy   = (float)($cls['avg'] ?? 0);
                                $cPct   = $cMoy / 10 * 100;
                                $cMoyC  = $cMoy >= 7 ? 'text-emerald-600' : ($cMoy >= 5 ? 'text-amber-600' : 'text-red-500');
                                $cBar   = $cMoy >= 7 ? 'bg-emerald-500' : ($cMoy >= 5 ? 'bg-amber-400' : 'bg-red-400');
                                $medal  = match($rank) { 0=>'🥇', 1=>'🥈', 2=>'🥉', default=>'' };
                            @endphp
                            <tr class="hover:bg-base-50">
                                <td class="py-2 pr-3 font-bold">{{ $medal }} {{ $cls['code'] }}</td>
                                <td class="py-2 pr-3 text-base-content/60 truncate max-w-[120px]">{{ $cls['teacher'] }}</td>
                                <td class="py-2 pr-3 text-center tabular-nums">{{ $cls['students'] }}</td>
                                <td class="py-2 pr-3 text-center tabular-nums">
                                    <span class="badge badge-ghost badge-xs">{{ $cls['published'] }}</span>
                                </td>
                                <td class="py-2 pr-3">
                                    <div class="flex items-center gap-2">
                                        <span class="font-black {{ $cMoyC }} w-10 text-right tabular-nums">
                                            {{ $cMoy > 0 ? number_format($cMoy, 2) : '—' }}
                                        </span>
                                        <div class="flex-1 h-1.5 bg-base-200 rounded-full overflow-hidden min-w-[50px]">
                                            <div class="{{ $cBar }} h-full rounded-full" style="width:{{ min($cPct,100) }}%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2 text-center">
                                    @if($cls['taux'] !== null)
                                    <span class="font-bold {{ $cls['taux'] >= 50 ? 'text-emerald-600' : 'text-red-500' }}">{{ $cls['taux'] }}%</span>
                                    @else
                                    <span class="text-base-content/30">—</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @empty
        <div class="card bg-base-100 shadow-sm p-12 text-center text-base-content/30">
            <p class="text-4xl mb-3">📋</p>
            <p class="font-semibold">Aucun niveau avec données publiées</p>
        </div>
        @endforelse
    </div>
    @endif

    {{-- ── Tab: Par période ───────────────────────────────────────────── --}}
    @if($activeTab === 'periodes')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        @foreach($periodComparison as $pc)
        @php
            $pGrad = match($pc['period']) {
                'T1' => 'from-[#16363a] to-teal-700',
                'T2' => 'from-[#c8913a] to-amber-600',
                'T3' => 'from-blue-700 to-indigo-700',
            };
            $avgC  = ($pc['avg'] ?? 0) >= 5 ? 'text-emerald-300' : 'text-red-300';
        @endphp
        <div class="card overflow-hidden shadow-sm">
            <div class="bg-linear-to-br {{ $pGrad }} p-5 text-white">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-lg font-black">{{ $pc['period'] }}</span>
                    <span class="text-xs opacity-70 font-semibold">{{ $pc['label'] }}</span>
                </div>
                <div class="text-4xl font-black tabular-nums {{ $avgC }}">
                    {{ ($pc['avg'] ?? 0) > 0 ? number_format($pc['avg'], 2) : '—' }}
                    <span class="text-lg opacity-50">/10</span>
                </div>
                <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                    <div>
                        <div class="text-xl font-bold tabular-nums">{{ $pc['count'] }}</div>
                        <div class="text-[10px] opacity-60">publiés</div>
                    </div>
                    <div>
                        <div class="text-xl font-bold tabular-nums text-emerald-300">{{ $pc['pass'] ?? 0 }}</div>
                        <div class="text-[10px] opacity-60">réussis</div>
                    </div>
                    <div>
                        <div class="text-xl font-bold tabular-nums text-red-300">{{ $pc['fail'] ?? 0 }}</div>
                        <div class="text-[10px] opacity-60">en échec</div>
                    </div>
                </div>
                @if($pc['taux'] !== null)
                <div class="mt-3 pt-3 border-t border-white/20">
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="opacity-70">Taux de réussite</span>
                        <span class="font-bold">{{ $pc['taux'] }}%</span>
                    </div>
                    <div class="w-full h-1.5 bg-white/20 rounded-full">
                        <div class="bg-white h-full rounded-full" style="width:{{ $pc['taux'] }}%"></div>
                    </div>
                </div>
                @endif
            </div>

            {{-- Per-niveau breakdown for this period --}}
            <div class="card-body p-4">
                <h4 class="text-xs font-bold text-base-content/40 uppercase tracking-wide mb-3">Par niveau</h4>
                <div class="space-y-2">
                    @foreach($niveauRows as $nr)
                    @php
                        $pd   = $nr['byPeriod'][$pc['period']] ?? ['avg'=>0,'count'=>0];
                        $pdC  = ($pd['avg'] ?? 0) >= 7 ? 'text-emerald-600' : (($pd['avg'] ?? 0) >= 5 ? 'text-amber-600' : 'text-red-500');
                        $pdB  = ($pd['avg'] ?? 0) >= 7 ? 'bg-emerald-500'   : (($pd['avg'] ?? 0) >= 5 ? 'bg-amber-400'   : 'bg-red-400');
                    @endphp
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-bold text-base-content/60 w-16 truncate">{{ $nr['n']->code }}</span>
                        <div class="flex-1 h-1.5 bg-base-200 rounded-full overflow-hidden">
                            <div class="{{ $pdB }} h-full rounded-full" style="width:{{ min(($pd['avg']??0)/10*100, 100) }}%"></div>
                        </div>
                        <span class="text-xs font-black {{ $pdC }} w-10 text-right tabular-nums">
                            {{ ($pd['avg'] ?? 0) > 0 ? number_format($pd['avg'], 1) : '—' }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- ── Tab: Distribution ──────────────────────────────────────────── --}}
    @if($activeTab === 'distribution')
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body p-5">
                <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide mb-4">Distribution des moyennes</h3>
                @if($distTotal > 0)
                <div class="space-y-3">
                    @foreach($distribution as $d)
                    <div>
                        <div class="flex items-center justify-between mb-1 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-sm {{ $d['color'] }} shrink-0"></span>
                                <span class="font-semibold text-base-content/70">{{ $d['range'] }}</span>
                                <span class="text-base-content/40">{{ $d['label'] }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-black text-base-content tabular-nums">{{ $d['count'] }}</span>
                                <span class="text-base-content/40 w-8 text-right tabular-nums">{{ $d['pct'] }}%</span>
                            </div>
                        </div>
                        <div class="w-full h-3 bg-base-200 rounded-full overflow-hidden">
                            <div class="{{ $d['color'] }} h-full rounded-full transition-all duration-700" style="width:{{ $d['pct'] }}%"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="mt-4 pt-4 border-t border-base-200 flex items-center gap-4 text-xs text-base-content/50">
                    <span><b class="text-base-content">{{ $distTotal }}</b> bulletins avec moyenne</span>
                    @php
                        $passCount = collect($distribution)->where('min','>=',5)->sum('count');
                        $failCount = $distTotal - $passCount;
                    @endphp
                    <span class="text-emerald-600 font-semibold"><b>{{ $passCount }}</b> réussis (≥5)</span>
                    <span class="text-red-500 font-semibold"><b>{{ $failCount }}</b> en échec</span>
                </div>
                @else
                <div class="py-16 text-center text-base-content/25 text-sm">
                    <p class="text-4xl mb-3">📊</p>
                    <p>Aucune donnée publiée disponible</p>
                </div>
                @endif
            </div>
        </div>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body p-5">
                <h3 class="font-bold text-sm text-base-content/60 uppercase tracking-wide mb-4">Visualisation donut</h3>
                @if($distTotal > 0)
                <div class="flex justify-center">
                    <div class="relative w-52 h-52">
                        <canvas id="chart-dist"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <span class="text-3xl font-black text-base-content">{{ $distTotal }}</span>
                            <span class="text-xs text-base-content/40">bulletins</span>
                        </div>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-1.5">
                    @foreach($distribution as $d)
                    @if($d['count'] > 0)
                    <div class="flex items-center gap-2 text-xs">
                        <span class="w-2 h-2 rounded-full shrink-0 {{ $d['color'] }}"></span>
                        <span class="text-base-content/60 truncate">{{ $d['range'] }}</span>
                        <span class="font-bold ml-auto tabular-nums">{{ $d['count'] }}</span>
                    </div>
                    @endif
                    @endforeach
                </div>
                @else
                <div class="py-16 text-center text-base-content/25 text-sm">Aucune donnée</div>
                @endif
            </div>
        </div>
    </div>
    @endif

</div>

<script>
function initRapportCharts() {
    var trendEl = document.getElementById('chart-trend');
    if (trendEl && !trendEl._chartInstance && @json(count($trendChartDatasets)) > 0) {
        trendEl._chartInstance = new Chart(trendEl, {
            type: 'line',
            data: { labels: @json($trendChartLabels), datasets: @json($trendChartDatasets) },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 14 } } },
                scales: {
                    y: { min: 0, max: 10, grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 } } },
                    x: { grid: { display: false }, ticks: { font: { size: 12, weight: '600' } } }
                }
            }
        });
    }

    var distEl = document.getElementById('chart-dist');
    if (distEl && !distEl._chartInstance) {
        var distData  = @json(collect($distribution)->pluck('count')->toArray());
        var distLabels = @json(collect($distribution)->pluck('range')->toArray());
        var distColors = ['#dc2626','#f87171','#fbbf24','#facc15','#a3e635','#10b981'];
        distEl._chartInstance = new Chart(distEl, {
            type: 'doughnut',
            data: { labels: distLabels, datasets: [{ data: distData, backgroundColor: distColors, borderWidth: 3, borderColor: '#fff', hoverOffset: 6 }] },
            options: { cutout: '65%', plugins: { legend: { display: false } }, animation: { animateRotate: true, duration: 600 } }
        });
    }
}

function destroyRapportCharts() {
    ['chart-trend','chart-dist'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el && el._chartInstance) { el._chartInstance.destroy(); el._chartInstance = null; }
    });
}

document.addEventListener('DOMContentLoaded', initRapportCharts);
document.addEventListener('livewire:navigated', function() { destroyRapportCharts(); initRapportCharts(); });
document.addEventListener('livewire:updated', function() { destroyRapportCharts(); setTimeout(initRapportCharts, 30); });
</script>
