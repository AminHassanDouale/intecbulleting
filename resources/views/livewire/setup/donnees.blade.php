<?php

use App\Models\AcademicYear;
use App\Models\Niveau;
use App\Models\Classroom;
use App\Models\User;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Competence;
use App\Models\Bulletin;
use App\Models\BulletinGrade;
use App\Enums\BulletinStatusEnum;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {

    public function with(): array
    {
        $yr = AcademicYear::current();

        $stats = [
            // ── Référentiel ────────────────────────────────────────────────
            [
                'group'   => 'Référentiel',
                'icon'    => '🗂️',
                'color'   => 'blue',
                'items'   => [
                    ['label' => 'Années scolaires', 'value' => AcademicYear::count(),
                     'link'  => route('setup.annees'), 'badge' => AcademicYear::current()?->label ?? '-'],
                    ['label' => 'Niveaux',           'value' => Niveau::count(),
                     'link'  => route('setup.niveaux'), 'badge' => null],
                    ['label' => 'Classes',            'value' => Classroom::count(),
                     'link'  => route('setup.classrooms'), 'badge' => null],
                    ['label' => 'Matières',           'value' => Subject::count(),
                     'link'  => route('setup.subjects'), 'badge' => null],
                    ['label' => 'Compétences',        'value' => Competence::count(),
                     'link'  => route('setup.competences'), 'badge' => null],
                ],
            ],
            // ── Personnes ──────────────────────────────────────────────────
            [
                'group'   => 'Personnes',
                'icon'    => '👥',
                'color'   => 'green',
                'items'   => [
                    ['label' => 'Utilisateurs',     'value' => User::count(),
                     'link'  => route('setup.teachers'), 'badge' => null],
                    ['label' => 'Enseignants',      'value' => User::role('teacher')->count(),
                     'link'  => route('setup.teachers'), 'badge' => null],
                    ['label' => 'Élèves',           'value' => Student::count(),
                     'link'  => route('setup.students'), 'badge' => null],
                    ['label' => 'Garçons',          'value' => Student::where('gender','M')->count(),
                     'link'  => null, 'badge' => null],
                    ['label' => 'Filles',           'value' => Student::where('gender','F')->count(),
                     'link'  => null, 'badge' => null],
                    ['label' => 'Assignations classe/enseignant', 'value' => \Illuminate\Support\Facades\DB::table('classroom_teacher')->count(),
                     'link'  => null, 'badge' => null],
                    ['label' => 'Assignations matière/enseignant', 'value' => \Illuminate\Support\Facades\DB::table('subject_teacher')->count(),
                     'link'  => null, 'badge' => null],
                ],
            ],
            // ── Bulletins ──────────────────────────────────────────────────
            [
                'group'   => 'Bulletins & Notes',
                'icon'    => '📋',
                'color'   => 'orange',
                'items'   => [
                    ['label' => 'Total bulletins',   'value' => Bulletin::count(),
                     'link'  => route('bulletins.index'), 'badge' => null],
                    ['label' => 'T1',                'value' => Bulletin::where('period','T1')->count(),
                     'link'  => null, 'badge' => null],
                    ['label' => 'T2',                'value' => Bulletin::where('period','T2')->count(),
                     'link'  => null, 'badge' => null],
                    ['label' => 'T3',                'value' => Bulletin::where('period','T3')->count(),
                     'link'  => null, 'badge' => null],
                    ['label' => 'Publiés',           'value' => Bulletin::where('status', BulletinStatusEnum::PUBLISHED)->count(),
                     'link'  => null, 'badge' => null],
                    ['label' => 'Approuvés',         'value' => Bulletin::where('status', BulletinStatusEnum::APPROVED)->count(),
                     'link'  => null, 'badge' => null],
                    ['label' => 'En cours',          'value' => Bulletin::whereNotIn('status', [BulletinStatusEnum::PUBLISHED, BulletinStatusEnum::APPROVED])->count(),
                     'link'  => null, 'badge' => null],
                    ['label' => 'Notes saisies',     'value' => BulletinGrade::count(),
                     'link'  => null, 'badge' => null],
                    ['label' => 'Notes numériques',  'value' => BulletinGrade::whereNotNull('score')->count(),
                     'link'  => null, 'badge' => null],
                    ['label' => 'Notes A/EVA/NA',    'value' => BulletinGrade::whereNotNull('competence_status')->count(),
                     'link'  => null, 'badge' => null],
                ],
            ],
        ];

        // ── Répartition élèves par niveau ──────────────────────────────────
        $elevesByNiveau = Niveau::withCount([
            'classrooms',
            'classrooms as students_count' => fn($q) =>
                $q->join('students','classrooms.id','=','students.classroom_id'),
        ])->orderBy('code')->get();

        // ── Bulletins par classe (top) ─────────────────────────────────────
        $bulletinsByClass = Bulletin::with('classroom')
            ->selectRaw('classroom_id, count(*) as total, sum(status = ?) as published', [BulletinStatusEnum::PUBLISHED->value])
            ->groupBy('classroom_id')
            ->orderByDesc('total')
            ->get();

        return compact('stats', 'elevesByNiveau', 'bulletinsByClass');
    }
}; ?>

<div class="space-y-6">

    {{-- Header --}}
    <div class="rounded-2xl bg-linear-to-r from-slate-700 to-slate-900 text-white px-6 py-5 shadow-lg">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-2xl backdrop-blur">📊</div>
            <div>
                <h1 class="text-xl font-bold">Vue d'ensemble des données</h1>
                <p class="text-white/70 text-sm">Compteurs de toutes les entités du système</p>
            </div>
        </div>
    </div>

    {{-- Stat groups --}}
    @foreach($stats as $group)
    <div class="card bg-base-100 shadow">
        <div class="card-body p-0">
            <div class="flex items-center gap-3 px-5 py-3 border-b border-base-200">
                <span class="text-xl">{{ $group['icon'] }}</span>
                <h2 class="font-bold text-base">{{ $group['group'] }}</h2>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-0 divide-x divide-y divide-base-200">
                @foreach($group['items'] as $item)
                <div class="p-4 {{ $item['link'] ? 'hover:bg-base-200/60 transition-colors' : '' }}">
                    @if($item['link'])
                    <a href="{{ $item['link'] }}" wire:navigate class="block">
                    @endif
                        <div class="text-2xl font-black text-base-content">{{ number_format($item['value']) }}</div>
                        <div class="text-xs text-base-content/50 mt-0.5 leading-tight">{{ $item['label'] }}</div>
                        @if($item['badge'])
                            <span class="badge badge-success badge-xs mt-1">{{ $item['badge'] }}</span>
                        @endif
                    @if($item['link'])
                    </a>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endforeach

    {{-- Répartition élèves par niveau --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <h2 class="font-bold text-base mb-4">Répartition par niveau scolaire</h2>
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr class="bg-base-200">
                            <th>Niveau</th>
                            <th>Code</th>
                            <th class="text-right">Classes</th>
                            <th class="text-right">Élèves</th>
                            <th class="text-right">Matières</th>
                            <th>Progression</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($elevesByNiveau as $n)
                        @php
                            $subjects  = \App\Models\Subject::where('niveau_id', $n->id)->count();
                            $bulletins = \App\Models\Bulletin::whereHas('student', fn($q) =>
                                $q->whereHas('classroom', fn($q2) => $q2->where('niveau_id', $n->id))
                            )->count();
                            $published = \App\Models\Bulletin::where('status', \App\Enums\BulletinStatusEnum::PUBLISHED)
                                ->whereHas('student', fn($q) =>
                                    $q->whereHas('classroom', fn($q2) => $q2->where('niveau_id', $n->id))
                                )->count();
                            $pct = $bulletins > 0 ? round($published / $bulletins * 100) : 0;
                        @endphp
                        <tr>
                            <td class="font-medium">{{ $n->label }}</td>
                            <td><span class="badge badge-primary badge-sm font-mono">{{ $n->code }}</span></td>
                            <td class="text-right">{{ $n->classrooms_count }}</td>
                            <td class="text-right font-bold">{{ $n->students_count }}</td>
                            <td class="text-right">{{ $subjects }}</td>
                            <td class="min-w-32">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-base-200 rounded-full h-1.5">
                                        <div class="h-1.5 rounded-full bg-success" style="width:{{ $pct }}%"></div>
                                    </div>
                                    <span class="text-xs text-base-content/50 w-8 text-right">{{ $pct }}%</span>
                                </div>
                                <div class="text-xs text-base-content/40 mt-0.5">{{ $published }}/{{ $bulletins }} publiés</div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-bold bg-base-200">
                            <td colspan="2">Total</td>
                            <td class="text-right">{{ $elevesByNiveau->sum('classrooms_count') }}</td>
                            <td class="text-right">{{ $elevesByNiveau->sum('students_count') }}</td>
                            <td class="text-right">{{ \App\Models\Subject::count() }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- Bulletins par classe --}}
    @if($bulletinsByClass->isNotEmpty())
    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <h2 class="font-bold text-base mb-4">Bulletins par classe</h2>
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr class="bg-base-200">
                            <th>Classe</th>
                            <th>Niveau</th>
                            <th class="text-right">Total bulletins</th>
                            <th class="text-right">Publiés</th>
                            <th>Progression</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bulletinsByClass as $row)
                        @php $pct = $row->total > 0 ? round($row->published / $row->total * 100) : 0; @endphp
                        <tr>
                            <td class="font-medium">{{ $row->classroom?->label ?? '—' }}</td>
                            <td>
                                <span class="badge badge-outline badge-sm">{{ $row->classroom?->niveau?->code ?? '—' }}</span>
                            </td>
                            <td class="text-right">{{ $row->total }}</td>
                            <td class="text-right text-success font-bold">{{ $row->published }}</td>
                            <td class="min-w-32">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-base-200 rounded-full h-1.5">
                                        <div class="h-1.5 rounded-full {{ $pct === 100 ? 'bg-success' : 'bg-primary' }}" style="width:{{ $pct }}%"></div>
                                    </div>
                                    <span class="text-xs text-base-content/50 w-8 text-right">{{ $pct }}%</span>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

</div>
