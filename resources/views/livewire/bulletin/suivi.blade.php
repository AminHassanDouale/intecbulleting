<?php

use App\Models\AcademicYear;
use App\Models\Bulletin;
use App\Models\BulletinTeacherSubmission;
use App\Models\Classroom;
use App\Models\Niveau;
use App\Models\Subject;
use App\Enums\BulletinStatusEnum;
use App\Enums\PeriodEnum;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {

    public string $filterYear    = '';
    public string $filterPeriod  = '';
    public string $filterNiveau  = '';
    public string $filterClass   = '';
    public string $filterSection = '';
    public bool   $showFilters   = false;

    const TZ = 'Africa/Djibouti';

    public function mount(): void
    {
        $yr = AcademicYear::current();
        if ($yr) $this->filterYear = (string) $yr->id;
        $this->filterPeriod = PeriodEnum::current()->value;
    }

    public function with(): array
    {
        // ── Filter options ─────────────────────────────────────────────────
        $years   = AcademicYear::orderByDesc('start_date')->get();
        $niveaux = Niveau::orderBy('code')->get();
        $yearId   = (int) $this->filterYear;
        $niveauId = (int) $this->filterNiveau;
        $classId  = (int) $this->filterClass;

        $classes = Classroom::query()
            ->when($yearId,   fn($q) => $q->where('academic_year_id', $yearId))
            ->when($niveauId, fn($q) => $q->where('niveau_id', $niveauId))
            ->orderBy('code')->get();
        $sections = Classroom::query()
            ->when($yearId,   fn($q) => $q->where('academic_year_id', $yearId))
            ->when($niveauId, fn($q) => $q->where('niveau_id', $niveauId))
            ->whereNotNull('section')->distinct()->orderBy('section')
            ->pluck('section')->values();

        // ── Load classrooms to display ─────────────────────────────────────
        $classrooms = Classroom::with(['niveau', 'teacher:id,name'])
            ->when($yearId,              fn($q) => $q->where('academic_year_id', $yearId))
            ->when($niveauId,            fn($q) => $q->where('niveau_id', $niveauId))
            ->when($classId,             fn($q) => $q->where('id', $classId))
            ->when($this->filterSection, fn($q) => $q->where('section', $this->filterSection))
            ->orderBy('niveau_id')->orderBy('code')
            ->get();

        // ── Pre-load data for all classrooms in one pass ───────────────────
        $allBulletins = Bulletin::whereIn('classroom_id', $classrooms->pluck('id'))
            ->when($this->filterPeriod, fn($q) => $q->where('period', $this->filterPeriod))
            ->when($yearId,             fn($q) => $q->where('academic_year_id', $yearId))
            ->get()
            ->groupBy('classroom_id');

        $allBulletinIds = $allBulletins->flatten()->pluck('id');
        $allTeacherSubs = $allBulletinIds->isNotEmpty()
            ? BulletinTeacherSubmission::whereIn('bulletin_id', $allBulletinIds)
                ->with('teacher:id,name')
                ->get()
                ->groupBy('bulletin_id')
            : collect();

        $niveauTeachers = Subject::with('teachers:id,name')->get()
            ->groupBy('niveau_id')
            ->map(fn($subjects) => $subjects->flatMap->teachers->unique('id'));

        $fmt = fn($dt) => $dt
            ? \Carbon\Carbon::parse($dt)->setTimezone(self::TZ)->format('d/m H:i')
            : null;

        $state = fn(int $count, int $total): string => match(true) {
            $total === 0   => 'pending',
            $count >= $total => 'done',
            $count > 0     => 'partial',
            default        => 'pending',
        };

        // ── Build per-class stats ──────────────────────────────────────────
        $classStats = [];

        foreach ($classrooms as $classroom) {
            $bulletins = $allBulletins[$classroom->id] ?? collect();
            if ($bulletins->isEmpty()) continue;

            $total = $bulletins->count();
            $bIds  = $bulletins->pluck('id');

            $statusGroups = $bulletins->groupBy(fn($b) => $b->status->value)
                ->map->count()->toArray();

            $classSubs = collect();
            foreach ($bIds as $bid) {
                if ($allTeacherSubs->has($bid)) {
                    $classSubs = $classSubs->merge($allTeacherSubs[$bid]);
                }
            }
            $allNiveauTeachers   = $niveauTeachers[$classroom->niveau_id] ?? collect();
            $totalTeachers       = $allNiveauTeachers->count();
            $submittedTeacherIds = $classSubs->where('status', 'submitted')->pluck('teacher_id')->unique();
            $submittedTeachers   = $submittedTeacherIds->count();
            $lastTeacherSubAt    = $classSubs->where('status', 'submitted')
                ->whereNotNull('submitted_at')
                ->sortByDesc('submitted_at')->first()?->submitted_at;

            $submitted_s  = ['submitted','pedagogie_review','pedagogie_approved','finance_review','finance_approved','direction_review','approved','published'];
            $ped_s        = ['pedagogie_approved','finance_review','finance_approved','direction_review','approved','published'];
            $fin_s        = ['finance_approved','direction_review','approved','published'];
            $dir_s        = ['approved','published'];

            $submittedCount   = $bulletins->filter(fn($b) => in_array($b->status->value, $submitted_s))->count();
            $pedApprovedCount = $bulletins->filter(fn($b) => in_array($b->status->value, $ped_s))->count();
            $finApprovedCount = $bulletins->filter(fn($b) => in_array($b->status->value, $fin_s))->count();
            $dirApprovedCount = $bulletins->filter(fn($b) => in_array($b->status->value, $dir_s))->count();
            $publishedCount   = $statusGroups[BulletinStatusEnum::PUBLISHED->value] ?? 0;
            $rejectedCount    = $statusGroups[BulletinStatusEnum::REJECTED->value]  ?? 0;

            $submittedAt = $bulletins->whereNotNull('submitted_at')->sortBy('submitted_at')->first()?->submitted_at;
            $pedApprAt   = $bulletins->whereNotNull('pedagogie_approved_at')->sortBy('pedagogie_approved_at')->first()?->pedagogie_approved_at;
            $finApprAt   = $bulletins->whereNotNull('finance_approved_at')->sortBy('finance_approved_at')->first()?->finance_approved_at;
            $dirApprAt   = $bulletins->whereNotNull('direction_approved_at')->sortBy('direction_approved_at')->first()?->direction_approved_at;
            $publishedAt = $bulletins->whereNotNull('published_at')->sortBy('published_at')->first()?->published_at;

            $overallStatus = match(true) {
                $publishedCount === $total && $total > 0 => ['Publié',    'badge-success'],
                $dirApprovedCount > 0                    => ['Direction', 'badge-info'],
                $finApprovedCount > 0                    => ['Finance',   'badge-warning'],
                $pedApprovedCount > 0                    => ['Pédagogie', 'badge-secondary'],
                $submittedCount > 0                      => ['Soumis',    'badge-primary'],
                $rejectedCount > 0                       => ['Rejeté',    'badge-error'],
                default                                  => ['Brouillon', 'badge-ghost'],
            };

            $moyenne = $bulletins->where('status', BulletinStatusEnum::PUBLISHED)
                ->whereNotNull('moyenne')->avg('moyenne');

            $steps = [
                [
                    'icon'  => '👩‍🏫',
                    'label' => 'Enseignants',
                    'sub'   => $totalTeachers > 0 ? "{$submittedTeachers}/{$totalTeachers}" : '0/0',
                    'time'  => $fmt($lastTeacherSubAt),
                    'extra' => 'ont soumis',
                    'state' => $totalTeachers > 0 ? $state($submittedTeachers, $totalTeachers) : 'pending',
                ],
                [
                    'icon'  => '📤',
                    'label' => '→ Pédagogie',
                    'sub'   => "{$submittedCount}/{$total}",
                    'time'  => $fmt($submittedAt),
                    'extra' => 'soumis',
                    'state' => $state($submittedCount, $total),
                ],
                [
                    'icon'  => '📚',
                    'label' => '→ Finance',
                    'sub'   => "{$pedApprovedCount}/{$total}",
                    'time'  => $fmt($pedApprAt),
                    'extra' => 'par pédag.',
                    'state' => $state($pedApprovedCount, $total),
                ],
                [
                    'icon'  => '💰',
                    'label' => '→ Direction',
                    'sub'   => "{$finApprovedCount}/{$total}",
                    'time'  => $fmt($finApprAt),
                    'extra' => 'par finance',
                    'state' => $state($finApprovedCount, $total),
                ],
                [
                    'icon'  => '🎓',
                    'label' => 'Publication',
                    'sub'   => "{$publishedCount}/{$total}",
                    'time'  => $fmt($publishedAt),
                    'extra' => 'par direction',
                    'state' => $state($publishedCount, $total),
                ],
            ];

            if ($rejectedCount > 0) {
                $steps[] = [
                    'icon'  => '❌',
                    'label' => 'Rejetés',
                    'sub'   => "{$rejectedCount}/{$total}",
                    'time'  => null,
                    'extra' => 'à retraiter',
                    'state' => 'rejected',
                ];
            }

            $classStats[] = compact(
                'classroom','total','steps','overallStatus',
                'rejectedCount','publishedCount','moyenne','submittedTeachers','totalTeachers'
            );
        }

        $yearOptions    = $years->map(fn($y)  => ['id' => $y->id,  'name' => $y->label])->toArray();
        $niveauOptions  = $niveaux->map(fn($n) => ['id' => $n->id, 'name' => $n->code.' — '.$n->label])->toArray();
        $classOptions   = $classes->map(fn($c) => ['id' => $c->id, 'name' => $c->code])->toArray();
        $sectionOptions = $sections->map(fn($s) => ['id' => $s,    'name' => 'Section '.$s])->toArray();
        $periodOptions  = PeriodEnum::options();

        // ── Global approval counts by trimester ───────────────────────────────
        $currentYearId = (int) ($this->filterYear ?: AcademicYear::current()?->id);
        $approvalStats = [];
        foreach (['T1', 'T2', 'T3'] as $p) {
            $bs = Bulletin::where('academic_year_id', $currentYearId)->where('period', $p)->get();
            $approvalStats[$p] = [
                'total'     => $bs->count(),
                'draft'     => $bs->filter(fn($b) => $b->status === BulletinStatusEnum::DRAFT)->count(),
                'submitted' => $bs->filter(fn($b) => $b->status === BulletinStatusEnum::SUBMITTED)->count(),
                'ped'       => $bs->filter(fn($b) => $b->status === BulletinStatusEnum::PEDAGOGIE_APPROVED)->count(),
                'fin'       => $bs->filter(fn($b) => $b->status === BulletinStatusEnum::FINANCE_APPROVED)->count(),
                'approved'  => $bs->filter(fn($b) => $b->status === BulletinStatusEnum::APPROVED)->count(),
                'published' => $bs->filter(fn($b) => $b->status === BulletinStatusEnum::PUBLISHED)->count(),
                'rejected'  => $bs->filter(fn($b) => $b->status === BulletinStatusEnum::REJECTED)->count(),
            ];
        }

        return compact(
            'classStats', 'approvalStats',
            'yearOptions','niveauOptions','classOptions','sectionOptions','periodOptions'
        );
    }
}; ?>

<div class="space-y-5">

    {{-- ── Page header ──────────────────────────────────────────────────── --}}
    <div class="relative overflow-hidden rounded-2xl bg-linear-to-br from-teal-600 via-cyan-700 to-blue-800 text-white px-6 py-6 shadow-xl">
        <div class="absolute -right-10 -top-10 w-48 h-48 bg-white/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute right-1/3 bottom-0 w-36 h-36 bg-cyan-300/10 rounded-full blur-2xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-13 h-13 bg-white/15 rounded-2xl flex items-center justify-center text-3xl backdrop-blur shrink-0 p-2.5">
                    📈
                </div>
                <div>
                    <h1 class="text-xl font-black tracking-tight">Suivi du Workflow par Classe</h1>
                    <p class="text-white/60 text-xs mt-0.5">Progression des bulletins · Heure Djibouti (EAT, UTC+3)</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="bg-white/10 backdrop-blur-sm border border-white/10 rounded-xl px-3 py-1.5 text-xs font-semibold flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-white/70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    <span class="text-white/70">Classes :</span>
                    <span class="text-white font-black">{{ count($classStats) }}</span>
                </div>
                <div class="bg-white/10 backdrop-blur-sm border border-white/10 rounded-xl px-3 py-1.5 text-xs font-semibold flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-white/70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-white/70">EAT :</span>
                    <span class="text-white font-mono">{{ \Carbon\Carbon::now('Africa/Djibouti')->format('d/m H:i') }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Approval stats by trimester ────────────────────────────────────── --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach(['T1' => '1er Trimestre', 'T2' => '2ème Trimestre', 'T3' => '3ème Trimestre'] as $period => $label)
        @php $s = $approvalStats[$period]; @endphp
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            {{-- Header --}}
            <div class="px-4 py-3 flex items-center justify-between border-b border-slate-100"
                 style="background: linear-gradient(135deg,#1e3a8a,#1d4ed8);">
                <span class="text-white font-black text-sm">{{ $label }}</span>
                <span class="text-white/60 text-xs font-mono">{{ $s['total'] }} bulletins</span>
            </div>
            {{-- Rows --}}
            <div class="divide-y divide-slate-50">
                @foreach([
                    ['icon'=>'👩‍🏫','label'=>'Enseignants','key'=>'draft',     'badge'=>'bg-slate-100 text-slate-600',  'hint'=>'en brouillon'],
                    ['icon'=>'📚','label'=>'Pédagogie',   'key'=>'submitted', 'badge'=>'bg-orange-100 text-orange-700','hint'=>'en attente'],
                    ['icon'=>'💰','label'=>'Finance',     'key'=>'ped',       'badge'=>'bg-blue-100 text-blue-700',    'hint'=>'en attente'],
                    ['icon'=>'🎓','label'=>'Direction',   'key'=>'fin',       'badge'=>'bg-violet-100 text-violet-700','hint'=>'en attente'],
                    ['icon'=>'✅','label'=>'Approuvés',   'key'=>'approved',  'badge'=>'bg-green-100 text-green-700',  'hint'=>'prêts'],
                    ['icon'=>'📄','label'=>'Publiés',     'key'=>'published', 'badge'=>'bg-emerald-100 text-emerald-700','hint'=>'publiés'],
                ] as $row)
                @php $count = $s[$row['key']]; @endphp
                <div class="flex items-center gap-3 px-4 py-2.5">
                    <span class="text-base w-6 text-center shrink-0">{{ $row['icon'] }}</span>
                    <span class="flex-1 text-sm text-slate-600">{{ $row['label'] }}</span>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-xs font-bold {{ $row['badge'] }}">
                        {{ $count }}
                        @if($count > 0)
                        <span class="font-normal opacity-70">{{ $row['hint'] }}</span>
                        @endif
                    </span>
                </div>
                @endforeach
                @if($s['rejected'] > 0)
                <div class="flex items-center gap-3 px-4 py-2.5 bg-red-50/50">
                    <span class="text-base w-6 text-center shrink-0">❌</span>
                    <span class="flex-1 text-sm text-red-600">Rejetés</span>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-xs font-bold bg-red-100 text-red-700">
                        {{ $s['rejected'] }} <span class="font-normal opacity-70">à corriger</span>
                    </span>
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- ── Filter button + active count ──────────────────────────────────── --}}
    <div class="flex items-center gap-3">
        <div class="relative">
            <x-button icon="o-funnel" label="Filtres" @click="$wire.showFilters = true" class="btn-outline" />
            @php $activeFilters = ($filterYear ? 1 : 0) + ($filterPeriod ? 1 : 0) + ($filterNiveau ? 1 : 0) + ($filterClass ? 1 : 0) + ($filterSection ? 1 : 0); @endphp
            @if($activeFilters)
            <span class="absolute -top-1.5 -right-1.5 badge badge-warning badge-xs font-bold">{{ $activeFilters }}</span>
            @endif
        </div>
        @if($activeFilters)
        <div class="flex flex-wrap gap-2 text-xs">
            @if($filterYear)    <span class="badge badge-ghost">📅 Année</span> @endif
            @if($filterPeriod)  <span class="badge badge-ghost">🕐 {{ $filterPeriod }}</span> @endif
            @if($filterNiveau)  <span class="badge badge-ghost">📚 Niveau</span> @endif
            @if($filterClass)   <span class="badge badge-ghost">🏫 Classe</span> @endif
            @if($filterSection) <span class="badge badge-ghost">🔖 {{ $filterSection }}</span> @endif
        </div>
        @endif
        @if(count($classStats) > 0)
        <div class="ml-auto flex items-center gap-3 text-xs text-base-content/40">
            <span>{{ count($classStats) }} classe(s)</span>
            <span>·</span>
            <span>{{ collect($classStats)->sum('total') }} bulletin(s)</span>
            <span>·</span>
            <span>{{ collect($classStats)->sum('publishedCount') }} publié(s)</span>
        </div>
        @endif
    </div>

    {{-- Filter drawer --}}
    <x-filter-drawer model="showFilters" title="Filtres" subtitle="Affiner le suivi des bulletins">
        <x-choices label="Année scolaire" wire:model.live="filterYear"    :options="$yearOptions"    single clearable icon="o-calendar"         placeholder="Toutes les années" />
        <x-choices label="Trimestre"      wire:model.live="filterPeriod"  :options="$periodOptions"  single clearable icon="o-clock"            placeholder="Tous les trimestres" />
        <x-choices label="Niveau"         wire:model.live="filterNiveau"  :options="$niveauOptions"  single clearable icon="o-academic-cap"     placeholder="Tous les niveaux" />
        <x-choices wire:key="filterClass-{{ $filterNiveau }}" label="Classe" wire:model.live="filterClass" :options="$classOptions" single clearable icon="o-building-library" placeholder="Toutes les classes" />
        <x-choices label="Section"        wire:model.live="filterSection" :options="$sectionOptions" single clearable icon="o-tag"              placeholder="Toutes les sections" />
        <x-slot:actions>
            <x-button label="Réinitialiser" wire:click="$set('filterNiveau',''); $set('filterClass',''); $set('filterSection',''); $set('filterYear',''); $set('filterPeriod','')" icon="o-arrow-path" />
            <x-button label="Fermer" @click="$wire.showFilters = false" class="btn-primary" icon="o-check" />
        </x-slot:actions>
    </x-filter-drawer>

    {{-- ── Class cards ──────────────────────────────────────────────────── --}}
    @forelse($classStats as $stat)
    @php
        // Per-state visual config
        $stateStyle = [
            'done'     => [
                'bg'     => 'bg-emerald-500',
                'text'   => 'text-white',
                'light'  => 'bg-emerald-50',
                'border' => 'border-emerald-200',
                'label'  => 'text-emerald-700',
                'bar'    => 'bg-emerald-500',
                'ring'   => 'ring-2 ring-emerald-200',
            ],
            'partial'  => [
                'bg'     => 'bg-amber-400',
                'text'   => 'text-white',
                'light'  => 'bg-amber-50',
                'border' => 'border-amber-200',
                'label'  => 'text-amber-700',
                'bar'    => 'bg-amber-400',
                'ring'   => 'ring-2 ring-amber-200',
            ],
            'pending'  => [
                'bg'     => 'bg-base-200',
                'text'   => 'text-base-content/30',
                'light'  => 'bg-base-50',
                'border' => 'border-base-200',
                'label'  => 'text-base-content/30',
                'bar'    => 'bg-base-300',
                'ring'   => '',
            ],
            'rejected' => [
                'bg'     => 'bg-red-500',
                'text'   => 'text-white',
                'light'  => 'bg-red-50',
                'border' => 'border-red-200',
                'label'  => 'text-red-700',
                'bar'    => 'bg-red-500',
                'ring'   => 'ring-2 ring-red-200',
            ],
        ];

        // Left accent border color
        $accentBorder = match($stat['overallStatus'][0]) {
            'Publié'    => 'border-l-emerald-500',
            'Direction' => 'border-l-indigo-500',
            'Finance'   => 'border-l-violet-500',
            'Pédagogie' => 'border-l-blue-500',
            'Soumis'    => 'border-l-cyan-500',
            'Rejeté'    => 'border-l-red-500',
            default     => 'border-l-base-300',
        };

        // Overall pipeline progress
        $doneSteps  = collect($stat['steps'])->where('state', 'done')->count();
        $totalSteps = count($stat['steps']);
        $pipelinePct = $totalSteps > 0 ? round($doneSteps / $totalSteps * 100) : 0;
        $pipelineColor = $pipelinePct === 100 ? 'bg-emerald-500' : ($pipelinePct >= 60 ? 'bg-amber-400' : 'bg-blue-400');
        $pipelineTextColor = $pipelinePct === 100 ? 'text-emerald-600' : ($pipelinePct >= 60 ? 'text-amber-600' : 'text-blue-500');

        // Avatar initials
        $initials = strtoupper(preg_replace('/[^A-Z]/i', '', $stat['classroom']->code));
        $initials = substr($initials, 0, 2) ?: '??';
    @endphp

    <div class="card bg-base-100 shadow-sm border border-base-200 border-l-4 {{ $accentBorder }}
                hover:shadow-md transition-all duration-200">
        <div class="card-body p-4 sm:p-5 gap-0">

            {{-- ── Class header ─────────────────────────────────────────── --}}
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div class="flex items-center gap-3 min-w-0">
                    {{-- Avatar --}}
                    <div class="w-12 h-12 rounded-2xl bg-linear-to-br from-teal-500 to-blue-600
                                text-white flex items-center justify-center font-black text-sm shrink-0 shadow-sm">
                        {{ $initials }}
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5 mb-0.5">
                            <h3 class="font-black text-base-content text-base leading-tight">
                                {{ $stat['classroom']->code }}
                            </h3>
                            <span class="badge {{ $stat['overallStatus'][1] }} badge-sm font-semibold">
                                {{ $stat['overallStatus'][0] }}
                            </span>
                            @if($stat['rejectedCount'] > 0)
                            <span class="badge badge-error badge-sm">⚠ {{ $stat['rejectedCount'] }} rejeté(s)</span>
                            @endif
                        </div>
                        <p class="text-xs text-base-content/50 truncate">
                            {{ $stat['classroom']->niveau->label ?? '—' }}
                            @if($stat['classroom']->section)
                                · Sect.&nbsp;{{ $stat['classroom']->section }}
                            @endif
                            · {{ $stat['total'] }} élève(s)
                            @if($stat['classroom']->teacher)
                                · {{ $stat['classroom']->teacher->name }}
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Stats chips (right side) --}}
                <div class="flex items-center gap-3 shrink-0">
                    @if($stat['moyenne'] !== null)
                    <div class="text-center px-3 py-1 rounded-xl
                        {{ $stat['moyenne'] >= 7 ? 'bg-emerald-50 border border-emerald-200' : ($stat['moyenne'] >= 5 ? 'bg-amber-50 border border-amber-200' : 'bg-red-50 border border-red-200') }}">
                        <div class="text-xl font-black tabular-nums leading-none
                            {{ $stat['moyenne'] >= 7 ? 'text-emerald-600' : ($stat['moyenne'] >= 5 ? 'text-amber-600' : 'text-red-500') }}">
                            {{ number_format($stat['moyenne'], 1) }}
                        </div>
                        <div class="text-[10px] text-base-content/40 mt-0.5">moy./10</div>
                    </div>
                    @endif
                    <div class="text-center px-3 py-1 rounded-xl bg-indigo-50 border border-indigo-200">
                        <div class="text-xl font-black tabular-nums text-indigo-600 leading-none">
                            {{ $stat['publishedCount'] }}/{{ $stat['total'] }}
                        </div>
                        <div class="text-[10px] text-base-content/40 mt-0.5">publiés</div>
                    </div>
                    @if($stat['publishedCount'] === $stat['total'] && $stat['total'] > 0)
                    <div class="w-9 h-9 rounded-full bg-emerald-100 flex items-center justify-center
                                text-emerald-600 border border-emerald-200" title="Workflow complété">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    @endif
                </div>
            </div>

            {{-- ── Pipeline progress bar ────────────────────────────────── --}}
            <div class="flex items-center gap-3 mb-4">
                <span class="text-[11px] font-semibold text-base-content/40 shrink-0 w-16">Pipeline</span>
                <div class="flex-1 h-2.5 bg-base-200 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-700 {{ $pipelineColor }}"
                         style="width: {{ $pipelinePct }}%"></div>
                </div>
                <span class="text-xs font-black tabular-nums w-9 text-right {{ $pipelineTextColor }}">
                    {{ $pipelinePct }}%
                </span>
            </div>

            {{-- ── Workflow steps — responsive grid ────────────────────── --}}
            <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                @foreach($stat['steps'] as $step)
                @php
                    $sc  = $stateStyle[$step['state']] ?? $stateStyle['pending'];
                    $isDone    = $step['state'] === 'done';
                    $isPartial = $step['state'] === 'partial';
                    $isRejected= $step['state'] === 'rejected';
                    [$cnt, $tot] = explode('/', $step['sub']);
                    $stepPct = (int)$tot > 0 ? round((int)$cnt / (int)$tot * 100) : 0;
                @endphp
                <div class="rounded-xl border {{ $sc['border'] }} {{ $sc['light'] }}
                            flex flex-col items-center gap-1.5 p-2.5
                            {{ $isPartial ? 'animate-pulse' : '' }}
                            transition-all duration-200">

                    {{-- Icon circle --}}
                    <div class="w-10 h-10 rounded-full {{ $sc['bg'] }} {{ $sc['text'] }}
                                flex items-center justify-center text-sm font-bold shrink-0
                                {{ $sc['ring'] }}">
                        {{ $isDone ? '✓' : $step['icon'] }}
                    </div>

                    {{-- Label --}}
                    <p class="text-[11px] font-bold text-center leading-tight text-base-content/80">
                        {{ $step['label'] }}
                    </p>

                    {{-- Count --}}
                    <p class="text-sm font-black {{ $sc['label'] }} tabular-nums leading-none">
                        {{ $step['sub'] }}
                    </p>

                    {{-- Mini progress bar --}}
                    <div class="w-full h-1.5 bg-white/70 rounded-full overflow-hidden">
                        <div class="h-full rounded-full {{ $sc['bar'] }} transition-all duration-500"
                             style="width: {{ $stepPct }}%"></div>
                    </div>

                    {{-- Timestamp --}}
                    @if($step['time'])
                    <p class="text-[9px] text-base-content/50 font-mono text-center leading-tight">
                        {{ $step['time'] }} EAT
                    </p>
                    @else
                    <p class="text-[9px] text-base-content/20 text-center">—</p>
                    @endif
                </div>
                @endforeach
            </div>

            {{-- ── Teacher strip ───────────────────────────────────────── --}}
            @if($stat['totalTeachers'] > 0)
            @php
                $teStep = $stat['steps'][0];
                [$tsub, $ttot] = explode('/', $teStep['sub']);
                $allTeachersSubmitted = (int)$tsub === (int)$ttot && (int)$ttot > 0;
            @endphp
            <div class="mt-4 pt-3 border-t border-base-200 flex flex-wrap items-center gap-2 text-xs">
                <svg class="w-3.5 h-3.5 text-base-content/30 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span class="text-base-content/40 font-semibold uppercase tracking-wide">Enseignants :</span>
                <span class="badge badge-sm {{ $allTeachersSubmitted ? 'badge-success' : 'badge-warning' }}">
                    {{ $tsub }}/{{ $ttot }} ont soumis
                </span>
                @if($teStep['time'])
                <span class="text-base-content/40 font-mono">· Dernier : {{ $teStep['time'] }} EAT</span>
                @endif
                @if($stat['publishedCount'] === $stat['total'] && $stat['total'] > 0)
                <span class="ml-auto text-emerald-600 font-bold flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Workflow complété
                </span>
                @endif
            </div>
            @endif

        </div>
    </div>
    @empty
    <div class="card bg-base-100 shadow-sm border border-base-200">
        <div class="card-body py-20 flex flex-col items-center text-base-content/25 gap-3">
            <div class="text-7xl">📭</div>
            <p class="font-black text-lg text-base-content/30">Aucune classe trouvée</p>
            <p class="text-sm text-center text-base-content/30 max-w-xs">
                Ajustez les filtres ci-dessus pour voir les classes et leur progression de workflow.
            </p>
        </div>
    </div>
    @endforelse

    {{-- ── Legend ───────────────────────────────────────────────────────── --}}
    @if(count($classStats) > 0)
    <div class="card bg-base-100 shadow-sm border border-base-200">
        <div class="card-body p-4">
            <p class="text-[11px] font-bold text-base-content/40 uppercase tracking-wider mb-3">Légende</p>
            <div class="flex flex-wrap gap-x-5 gap-y-2">
                <div class="flex items-center gap-2 text-xs">
                    <div class="w-5 h-5 rounded-full bg-emerald-500 ring-2 ring-emerald-200 flex items-center justify-center text-white text-[10px] font-black shrink-0">✓</div>
                    <span class="text-base-content/60">Étape complétée</span>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <div class="w-5 h-5 rounded-full bg-amber-400 ring-2 ring-amber-200 flex items-center justify-center text-white text-[10px] shrink-0">⚡</div>
                    <span class="text-base-content/60">En cours (partiel)</span>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <div class="w-5 h-5 rounded-full bg-base-200 flex items-center justify-center text-base-content/30 text-[10px] shrink-0">○</div>
                    <span class="text-base-content/60">En attente</span>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <div class="w-5 h-5 rounded-full bg-red-500 ring-2 ring-red-200 flex items-center justify-center text-white text-[10px] shrink-0">✕</div>
                    <span class="text-base-content/60">Rejeté — à retraiter</span>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <svg class="w-4 h-4 text-base-content/30 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-base-content/60">Heures en EAT (Africa/Djibouti, UTC+3)</span>
                </div>
            </div>
        </div>
    </div>
    @endif

</div>
