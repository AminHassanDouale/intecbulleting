<?php

use App\Actions\Bulletin\GenerateAnnualBulletinPdfAction;
use App\Enums\BulletinStatusEnum;
use App\Enums\PeriodEnum;
use App\Models\AcademicYear;
use App\Models\Bulletin;
use App\Models\Classroom;
use App\Models\Niveau;
use App\Models\Student;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new #[Layout('components.layouts.app')] class extends Component {
    use Toast;

    public ?int    $selectedYear      = null;
    public ?string $selectedNiveau    = null;
    public ?int    $selectedClassroom = null;

    public function mount(): void
    {
        $year = AcademicYear::current();
        $this->selectedYear = $year?->id;
    }

    public function updatedSelectedNiveau(): void
    {
        $this->selectedClassroom = null;
    }

    public function generateAnnual(int $studentId): void
    {
        $this->authorize('create', Bulletin::class);

        if (! $this->selectedYear) {
            $this->error('Année scolaire requise.', icon: 'o-exclamation-circle', position: 'toast-top toast-end');
            return;
        }

        $student = Student::findOrFail($studentId);

        try {
            app(GenerateAnnualBulletinPdfAction::class)->execute($student, $this->selectedYear);
            $this->success(
                'Bulletin annuel généré !',
                $student->full_name . ' — Bilan des 3 trimestres disponible.',
                icon: 'o-document-check',
                position: 'toast-top toast-end'
            );
        } catch (\Throwable $e) {
            $this->error('Erreur', $e->getMessage(), icon: 'o-x-circle', position: 'toast-top toast-end');
        }
    }

    public function with(): array
    {
        $niveaux = Niveau::all()->map(fn($n) => ['id' => $n->code, 'name' => $n->label]);

        $classrooms = $this->selectedNiveau
            ? Classroom::whereHas('niveau', fn($q) => $q->where('code', $this->selectedNiveau))
                ->where('academic_year_id', $this->selectedYear)
                ->get()
                ->map(fn($c) => ['id' => $c->id, 'name' => $c->label . ' — ' . $c->section])
            : collect();

        $students = collect();

        if ($this->selectedClassroom && $this->selectedYear) {
            $students = Student::where('classroom_id', $this->selectedClassroom)
                ->orderBy('last_name')
                ->get()
                ->map(function ($student) {
                    $yearId = $this->selectedYear;

                    $bulletins = Bulletin::where('student_id', $student->id)
                        ->where('academic_year_id', $yearId)
                        ->whereIn('period', [PeriodEnum::TRIMESTRE_1->value, PeriodEnum::TRIMESTRE_2->value, PeriodEnum::TRIMESTRE_3->value])
                        ->get()
                        ->keyBy('period');

                    $student->t1 = $bulletins[PeriodEnum::TRIMESTRE_1->value] ?? null;
                    $student->t2 = $bulletins[PeriodEnum::TRIMESTRE_2->value] ?? null;
                    $student->t3 = $bulletins[PeriodEnum::TRIMESTRE_3->value] ?? null;

                    $student->annualBulletin = Bulletin::where('student_id', $student->id)
                        ->where('academic_year_id', $yearId)
                        ->where('period', PeriodEnum::ANNUEL->value)
                        ->first();

                    $student->canGenerate = ($student->t1 || $student->t2 || $student->t3);

                    return $student;
                });
        }

        $years = AcademicYear::all()->map(fn($y) => ['id' => $y->id, 'name' => $y->label]);

        $generatedCount = $students->filter(fn($s) => $s->annualBulletin?->getPdfUrl())->count();
        $pendingCount   = $students->filter(fn($s) => $s->canGenerate && !$s->annualBulletin)?->count() ?? 0;

        return [
            'years'          => $years,
            'niveaux'        => $niveaux,
            'classrooms'     => $classrooms,
            'students'       => $students,
            'generatedCount' => $generatedCount,
            'pendingCount'   => $pendingCount,
        ];
    }
}; ?>

<div class="space-y-5">

    {{-- Page header --}}
    <div class="rounded-2xl bg-linear-to-r from-teal-600 to-cyan-600 text-white px-6 py-5 shadow-lg">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('bulletins.index') }}"
                   class="w-10 h-10 bg-white/20 hover:bg-white/30 rounded-xl flex items-center justify-center transition-colors shrink-0">
                    ←
                </a>
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-2xl backdrop-blur">📋</div>
                <div>
                    <h1 class="text-xl font-bold">Bilan Annuel</h1>
                    <p class="text-white/70 text-sm">Générer le bulletin consolidé T1 + T2 + T3 par élève</p>
                </div>
            </div>
            @if($selectedClassroom && $students->isNotEmpty())
            <div class="flex gap-2 flex-wrap self-start sm:self-auto">
                <div class="bg-white/20 rounded-xl px-3 py-2 text-center">
                    <div class="text-2xl font-black">{{ $generatedCount }}</div>
                    <div class="text-xs text-white/70">Générés</div>
                </div>
                <div class="bg-white/20 rounded-xl px-3 py-2 text-center">
                    <div class="text-2xl font-black">{{ $pendingCount }}</div>
                    <div class="text-xs text-white/70">En attente</div>
                </div>
                <div class="bg-white/20 rounded-xl px-3 py-2 text-center">
                    <div class="text-2xl font-black">{{ $students->count() }}</div>
                    <div class="text-xs text-white/70">Élèves</div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Selectors --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body py-4 px-5">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-select
                    label="Année scolaire"
                    wire:model.live="selectedYear"
                    :options="$years"
                    placeholder="Sélectionner…"
                    icon="o-calendar"
                />
                <x-select
                    label="Niveau"
                    wire:model.live="selectedNiveau"
                    :options="$niveaux"
                    placeholder="Sélectionner…"
                    icon="o-academic-cap"
                />
                <x-select
                    label="Classe"
                    wire:model.live="selectedClassroom"
                    :options="$classrooms->toArray()"
                    placeholder="{{ $selectedNiveau ? 'Sélectionner…' : '— Choisir un niveau —' }}"
                    :disabled="!$selectedNiveau"
                    icon="o-building-library"
                />
            </div>
        </div>
    </div>

    {{-- Legend --}}
    @if($selectedClassroom)
    <div class="flex flex-wrap gap-3 text-xs text-base-content/60 px-1">
        <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-success inline-block"></span>
            Publié
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-warning inline-block"></span>
            En cours de validation
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-base-300 inline-block"></span>
            Non saisi
        </div>
    </div>
    @endif

    {{-- Students table --}}
    @if($students->isNotEmpty())
    <div class="card bg-base-100 shadow">
        <div class="card-body p-0">
            <div class="overflow-x-auto">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr>
                            <th class="bg-base-200 rounded-tl-xl">Élève</th>
                            <th class="bg-base-200 text-center">
                                <span class="badge badge-outline badge-sm">T1</span>
                            </th>
                            <th class="bg-base-200 text-center">
                                <span class="badge badge-outline badge-sm">T2</span>
                            </th>
                            <th class="bg-base-200 text-center">
                                <span class="badge badge-outline badge-sm">T3</span>
                            </th>
                            <th class="bg-base-200 text-center">Bilan Annuel</th>
                            <th class="bg-base-200 text-center rounded-tr-xl">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($students as $student)
                        <tr class="hover">
                            {{-- Student name --}}
                            <td>
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold shrink-0
                                        {{ $student->gender === 'M' ? 'bg-blue-100 text-blue-700' : 'bg-pink-100 text-pink-700' }}">
                                        {{ strtoupper(substr($student->first_name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-sm">{{ $student->full_name }}</p>
                                        <p class="text-xs text-base-content/40 font-mono">{{ $student->matricule }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- T1 --}}
                            <td class="text-center">
                                @if($student->t1)
                                    <span class="badge {{ $student->t1->status->color() }} badge-sm block mx-auto">
                                        {{ $student->t1->status->label() }}
                                    </span>
                                    @if($student->t1->moyenne !== null)
                                        <span class="text-xs font-bold mt-0.5 block {{ $student->t1->moyenne >= 10 ? 'text-success' : 'text-error' }}">
                                            {{ $student->t1->moyenne }}/20
                                        </span>
                                    @endif
                                @else
                                    <span class="text-base-content/25 text-sm">—</span>
                                @endif
                            </td>

                            {{-- T2 --}}
                            <td class="text-center">
                                @if($student->t2)
                                    <span class="badge {{ $student->t2->status->color() }} badge-sm block mx-auto">
                                        {{ $student->t2->status->label() }}
                                    </span>
                                    @if($student->t2->moyenne !== null)
                                        <span class="text-xs font-bold mt-0.5 block {{ $student->t2->moyenne >= 10 ? 'text-success' : 'text-error' }}">
                                            {{ $student->t2->moyenne }}/20
                                        </span>
                                    @endif
                                @else
                                    <span class="text-base-content/25 text-sm">—</span>
                                @endif
                            </td>

                            {{-- T3 --}}
                            <td class="text-center">
                                @if($student->t3)
                                    <span class="badge {{ $student->t3->status->color() }} badge-sm block mx-auto">
                                        {{ $student->t3->status->label() }}
                                    </span>
                                    @if($student->t3->moyenne !== null)
                                        <span class="text-xs font-bold mt-0.5 block {{ $student->t3->moyenne >= 10 ? 'text-success' : 'text-error' }}">
                                            {{ $student->t3->moyenne }}/20
                                        </span>
                                    @endif
                                @else
                                    <span class="text-base-content/25 text-sm">—</span>
                                @endif
                            </td>

                            {{-- Annual --}}
                            <td class="text-center">
                                @if($student->annualBulletin?->moyenne !== null)
                                    @php $avg = $student->annualBulletin->moyenne; @endphp
                                    <span class="text-lg font-black {{ $avg >= 10 ? 'text-success' : 'text-error' }}">
                                        {{ $avg }}<span class="text-xs text-base-content/40">/20</span>
                                    </span>
                                    <div class="text-xs text-base-content/50 mt-0.5">
                                        {{ $avg >= 16 ? '🏆 Très Bien' : ($avg >= 14 ? '⭐ Bien' : ($avg >= 12 ? '👍 Assez Bien' : ($avg >= 10 ? 'Passable' : '❌ Insuffisant'))) }}
                                    </div>
                                @else
                                    <span class="text-base-content/30 text-xs italic">Non généré</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td>
                                <div class="flex gap-1 justify-center flex-wrap">
                                    @if($student->canGenerate)
                                        <x-button
                                            label="{{ $student->annualBulletin ? '🔄' : '📄 Générer' }}"
                                            wire:click="generateAnnual({{ $student->id }})"
                                            class="btn-xs {{ $student->annualBulletin ? 'btn-outline btn-secondary' : 'btn-primary' }}"
                                            spinner="generateAnnual({{ $student->id }})"
                                            wire:confirm="Générer le bilan annuel pour {{ $student->full_name }} ?"
                                        />
                                    @endif
                                    @if($student->annualBulletin?->getPdfUrl())
                                        <a href="{{ route('bulletins.download', $student->annualBulletin->id) }}"
                                           class="btn btn-xs btn-success"
                                           target="_blank" rel="noopener">👁️</a>
                                        <a href="{{ route('bulletins.download', $student->annualBulletin->id) }}?download=1"
                                           class="btn btn-xs btn-outline">⬇️</a>
                                    @endif
                                    @if(!$student->canGenerate)
                                        <span class="text-xs text-base-content/30 italic">Aucun trimestre</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @elseif($selectedClassroom)
        <x-alert title="Aucun élève trouvé dans cette classe." class="alert-info" icon="o-information-circle" />
    @elseif($selectedNiveau)
        <x-alert title="Sélectionnez une classe pour afficher les élèves." class="alert-info" icon="o-building-library" />
    @else
        <x-alert title="Sélectionnez un niveau puis une classe pour commencer." class="alert-info" icon="o-academic-cap" />
    @endif
</div>
