<?php

use App\Actions\Bulletin\GenerateAnnualBulletinPdfAction;
use App\Enums\BulletinStatusEnum;
use App\Enums\PeriodEnum;
use App\Models\AcademicYear;
use App\Models\Bulletin;
use App\Models\Classroom;
use App\Models\Niveau;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentPromotion;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new #[Layout('components.layouts.app')] class extends Component {
    use Toast;

    public ?int    $selectedYear      = null;
    public ?string $selectedNiveau    = null;
    public ?int    $selectedClassroom = null;
    public bool    $showFilters       = false;

    /** [studentId => 'passe'|'redoublant'|'en_attente'] */
    public array $decisions     = [];
    /** [studentId => nextClassroomId] */
    public array $nextClassrooms = [];

    public function mount(): void
    {
        $year = AcademicYear::current();
        $this->selectedYear = $year?->id;
    }

    public function updatedSelectedNiveau(): void
    {
        $this->selectedClassroom = null;
    }

    public function updatedSelectedClassroom(): void
    {
        $this->loadDecisions();
    }

    // ── Promotion decisions ────────────────────────────────────────────────────

    private function loadDecisions(): void
    {
        $this->decisions     = [];
        $this->nextClassrooms = [];

        if (! $this->selectedClassroom || ! $this->selectedYear) {
            return;
        }

        $promotions = StudentPromotion::where('academic_year_id', $this->selectedYear)
            ->whereHas('student', fn($q) => $q->where('classroom_id', $this->selectedClassroom))
            ->get();

        foreach ($promotions as $p) {
            $this->decisions[$p->student_id]      = $p->decision;
            $this->nextClassrooms[$p->student_id] = $p->next_classroom_id;
        }
    }

    public function setDecision(int $studentId, string $decision): void
    {
        $this->decisions[$studentId] = $decision;

        // Auto-assign next classroom suggestion when promoting
        if ($decision === 'passe' && empty($this->nextClassrooms[$studentId])) {
            $this->nextClassrooms[$studentId] = $this->suggestNextClassroom($studentId);
        }
    }

    public function saveDecisions(): void
    {
        $this->authorize('create', Bulletin::class);

        foreach ($this->decisions as $studentId => $decision) {
            StudentPromotion::updateOrCreate(
                ['student_id' => $studentId, 'academic_year_id' => $this->selectedYear],
                [
                    'decision'          => $decision,
                    'next_classroom_id' => $this->nextClassrooms[$studentId] ?? null,
                    'decided_by'        => auth()->id(),
                    'decided_at'        => now(),
                ]
            );
        }

        $this->success('Décisions enregistrées !', icon: 'o-check-circle', position: 'toast-top toast-end');
    }

    public function applyPromotions(): void
    {
        $this->authorize('create', Bulletin::class);

        $promoted    = 0;
        $redoublants = 0;
        $errors      = [];

        foreach ($this->decisions as $studentId => $decision) {
            $student = Student::find($studentId);
            if (! $student) {
                continue;
            }

            if ($decision === 'passe') {
                $nextId = $this->nextClassrooms[$studentId] ?? null;
                if ($nextId) {
                    $student->update(['classroom_id' => $nextId]);
                    $promoted++;
                } else {
                    $errors[] = $student->full_name . ' (classe suivante non définie)';
                }
            } elseif ($decision === 'redoublant') {
                // Student stays in same classroom — nothing to change in class
                $redoublants++;
            }
        }

        $msg = "Promu(s) : {$promoted} · Redoublant(s) : {$redoublants}";
        if ($errors) {
            $msg .= ' · Ignorés : ' . implode(', ', $errors);
        }

        $this->success('Promotions appliquées !', $msg, icon: 'o-academic-cap', position: 'toast-top toast-end');
    }

    private function suggestNextClassroom(int $studentId): ?int
    {
        $student = Student::with('classroom')->find($studentId);
        if (! $student?->classroom) {
            return null;
        }

        $nextCode = StudentPromotion::nextClassCode($student->classroom->code);
        if (! $nextCode) {
            return null;
        }

        // Look for a classroom with the same section in the same year first
        return Classroom::where('code', $nextCode)
            ->where('section', $student->classroom->section)
            ->where('academic_year_id', $this->selectedYear)
            ->value('id');
    }

    // ── Annual PDF ─────────────────────────────────────────────────────────────

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

        // All available classrooms for "next class" dropdown
        $allClassrooms = Classroom::where('academic_year_id', $this->selectedYear)
            ->orderBy('code')->orderBy('section')
            ->get()
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->label . ' ' . $c->section]);

        $seuil   = (float) SchoolSetting::get('seuil_admission', 10);
        $students = collect();

        if ($this->selectedClassroom && $this->selectedYear) {
            $yearId   = $this->selectedYear;
            $students = Student::where('classroom_id', $this->selectedClassroom)
                ->orderBy('full_name')
                ->get()
                ->map(function ($student) use ($yearId, $seuil) {
                    $bulletins = Bulletin::where('student_id', $student->id)
                        ->where('academic_year_id', $yearId)
                        ->whereIn('period', [
                            PeriodEnum::TRIMESTRE_1->value,
                            PeriodEnum::TRIMESTRE_2->value,
                            PeriodEnum::TRIMESTRE_3->value,
                        ])
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

                    // Auto-suggest decision based on annual average vs seuil
                    $avg = $student->annualBulletin?->moyenne;
                    $student->suggestedDecision = $avg !== null
                        ? ($avg >= $seuil ? 'passe' : 'redoublant')
                        : 'en_attente';

                    return $student;
                });
        }

        $years = AcademicYear::all()->map(fn($y) => ['id' => $y->id, 'name' => $y->label]);

        $generatedCount = $students->filter(fn($s) => $s->annualBulletin?->getPdfUrl())->count();
        $pendingCount   = $students->filter(fn($s) => $s->canGenerate && ! $s->annualBulletin)?->count() ?? 0;

        $passedCount     = collect($this->decisions)->filter(fn($d) => $d === 'passe')->count();
        $redoublantCount = collect($this->decisions)->filter(fn($d) => $d === 'redoublant')->count();
        $pendingDecision = $students->count() - $passedCount - $redoublantCount;

        return [
            'years'           => $years,
            'niveaux'         => $niveaux,
            'classrooms'      => $classrooms,
            'allClassrooms'   => $allClassrooms,
            'students'        => $students,
            'generatedCount'  => $generatedCount,
            'pendingCount'    => $pendingCount,
            'seuil'           => $seuil,
            'passedCount'     => $passedCount,
            'redoublantCount' => $redoublantCount,
            'pendingDecision' => $pendingDecision,
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
                    <h1 class="text-xl font-bold">Bilan Annuel & Promotions</h1>
                    <p class="text-white/70 text-sm">Bulletin annuel · Seuil d'admission : {{ $seuil }}/20</p>
                </div>
            </div>
            @if($selectedClassroom && $students->isNotEmpty())
            <div class="flex gap-2 flex-wrap self-start sm:self-auto">
                <div class="bg-white/20 rounded-xl px-3 py-2 text-center">
                    <div class="text-2xl font-black">{{ $generatedCount }}</div>
                    <div class="text-xs text-white/70">Générés</div>
                </div>
                <div class="bg-emerald-400/30 rounded-xl px-3 py-2 text-center">
                    <div class="text-2xl font-black">{{ $passedCount }}</div>
                    <div class="text-xs text-white/70">Promus</div>
                </div>
                <div class="bg-red-400/30 rounded-xl px-3 py-2 text-center">
                    <div class="text-2xl font-black">{{ $redoublantCount }}</div>
                    <div class="text-xs text-white/70">Redoublants</div>
                </div>
                <div class="bg-white/20 rounded-xl px-3 py-2 text-center">
                    <div class="text-2xl font-black">{{ $students->count() }}</div>
                    <div class="text-xs text-white/70">Élèves</div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Selector button --}}
    <div class="flex items-center gap-3">
        <div class="relative">
            <x-button icon="o-adjustments-horizontal" label="Sélection" @click="$wire.showFilters = true" class="btn-outline" />
            @php $activeSelectors = ($selectedYear ? 1 : 0) + ($selectedNiveau ? 1 : 0) + ($selectedClassroom ? 1 : 0); @endphp
            @if($activeSelectors)
            <span class="absolute -top-1.5 -right-1.5 badge badge-primary badge-xs font-bold">{{ $activeSelectors }}</span>
            @endif
        </div>
        @if($selectedClassroom)
        <span class="text-sm font-medium text-base-content/60">
            {{ $classrooms->firstWhere('id', $selectedClassroom)?->name ?? '' }}
        </span>
        @elseif($selectedNiveau)
        <span class="text-sm text-base-content/40">— Choisir une classe</span>
        @else
        <span class="text-sm text-base-content/30">— Choisir un niveau et une classe</span>
        @endif
    </div>

    {{-- Selector drawer --}}
    <x-filter-drawer model="showFilters" title="Sélection" subtitle="Choisir l'année, le niveau et la classe">
        <x-choices
            label="Année scolaire"
            wire:model.live="selectedYear"
            :options="$years"
            single clearable
            placeholder="Sélectionner…"
            icon="o-calendar"
        />
        <x-select
            label="Niveau"
            wire:model.live="selectedNiveau"
            :options="$niveaux"
            placeholder="Sélectionner…"
            icon="o-academic-cap"
            class="select-bordered bg-base-100"
        />
        <x-select
            label="Classe"
            wire:model.live="selectedClassroom"
            :options="$classrooms->toArray()"
            placeholder="{{ $selectedNiveau ? 'Sélectionner…' : '— Choisir un niveau —' }}"
            :disabled="!$selectedNiveau"
            icon="o-building-library"
            class="select-bordered bg-base-100"
        />
        <x-slot:actions>
            <x-button label="Réinitialiser" wire:click="$set('selectedNiveau', null); $set('selectedClassroom', null)" icon="o-arrow-path" />
            <x-button label="Appliquer" @click="$wire.showFilters = false" class="btn-primary" icon="o-check" />
        </x-slot:actions>
    </x-filter-drawer>

    {{-- Students table --}}
    @if($students->isNotEmpty())

    {{-- Legend --}}
    <div class="flex flex-wrap gap-3 text-xs text-base-content/60 px-1">
        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-success inline-block"></span> Publié</div>
        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-warning inline-block"></span> En cours de validation</div>
        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-base-300 inline-block"></span> Non saisi</div>
        <span class="ml-auto text-base-content/40">Seuil d'admission : {{ $seuil }}/20</span>
    </div>

    <div class="card bg-base-100 shadow">
        <div class="card-body p-0">
            <div class="overflow-x-auto">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr>
                            <th class="bg-base-200 rounded-tl-xl">Élève</th>
                            <th class="bg-base-200 text-center"><span class="badge badge-outline badge-sm">T1</span></th>
                            <th class="bg-base-200 text-center"><span class="badge badge-outline badge-sm">T2</span></th>
                            <th class="bg-base-200 text-center"><span class="badge badge-outline badge-sm">T3</span></th>
                            <th class="bg-base-200 text-center">Bilan Annuel</th>
                            <th class="bg-base-200 text-center">Décision</th>
                            <th class="bg-base-200 text-center rounded-tr-xl">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($students as $student)
                        @php
                            $decision  = $decisions[$student->id]      ?? $student->suggestedDecision;
                            $nextClsId = $nextClassrooms[$student->id] ?? null;
                        @endphp
                        <tr class="hover">
                            {{-- Student name --}}
                            <td>
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold shrink-0
                                        {{ $student->gender === 'M' ? 'bg-blue-100 text-blue-700' : 'bg-pink-100 text-pink-700' }}">
                                        {{ strtoupper(substr($student->full_name, 0, 1)) }}
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
                                    <span class="badge {{ $student->t1->status->color() }} badge-sm block mx-auto">{{ $student->t1->status->label() }}</span>
                                    @if($student->t1->moyenne !== null)
                                        <span class="text-xs font-bold mt-0.5 block {{ $student->t1->moyenne >= $seuil ? 'text-success' : 'text-error' }}">{{ $student->t1->moyenne }}/20</span>
                                    @endif
                                @else
                                    <span class="text-base-content/25 text-sm">—</span>
                                @endif
                            </td>

                            {{-- T2 --}}
                            <td class="text-center">
                                @if($student->t2)
                                    <span class="badge {{ $student->t2->status->color() }} badge-sm block mx-auto">{{ $student->t2->status->label() }}</span>
                                    @if($student->t2->moyenne !== null)
                                        <span class="text-xs font-bold mt-0.5 block {{ $student->t2->moyenne >= $seuil ? 'text-success' : 'text-error' }}">{{ $student->t2->moyenne }}/20</span>
                                    @endif
                                @else
                                    <span class="text-base-content/25 text-sm">—</span>
                                @endif
                            </td>

                            {{-- T3 --}}
                            <td class="text-center">
                                @if($student->t3)
                                    <span class="badge {{ $student->t3->status->color() }} badge-sm block mx-auto">{{ $student->t3->status->label() }}</span>
                                    @if($student->t3->moyenne !== null)
                                        <span class="text-xs font-bold mt-0.5 block {{ $student->t3->moyenne >= $seuil ? 'text-success' : 'text-error' }}">{{ $student->t3->moyenne }}/20</span>
                                    @endif
                                @else
                                    <span class="text-base-content/25 text-sm">—</span>
                                @endif
                            </td>

                            {{-- Annual moyenne + mention --}}
                            <td class="text-center">
                                @if($student->annualBulletin?->moyenne !== null)
                                    @php
                                        $avg = $student->annualBulletin->moyenne;
                                        $seuilTresBien  = (float) \App\Models\SchoolSetting::get('seuil_tres_bien', 16);
                                        $seuilBien      = (float) \App\Models\SchoolSetting::get('seuil_bien', 14);
                                        $seuilAssezBien = (float) \App\Models\SchoolSetting::get('seuil_assez_bien', 12);
                                        $mention = $avg >= $seuilTresBien ? '🏆 Très Bien'
                                                 : ($avg >= $seuilBien     ? '⭐ Bien'
                                                 : ($avg >= $seuilAssezBien ? '👍 Assez Bien'
                                                 : ($avg >= $seuil          ? 'Passable'
                                                 : '❌ Insuffisant')));
                                    @endphp
                                    <span class="text-lg font-black {{ $avg >= $seuil ? 'text-success' : 'text-error' }}">
                                        {{ $avg }}<span class="text-xs text-base-content/40">/20</span>
                                    </span>
                                    <div class="text-xs text-base-content/50 mt-0.5">{{ $mention }}</div>
                                @else
                                    <span class="text-base-content/30 text-xs italic">Non généré</span>
                                @endif
                            </td>

                            {{-- Promotion decision --}}
                            <td class="text-center min-w-44">
                                <div class="flex flex-col gap-1.5 items-center">
                                    {{-- Decision badge + change buttons --}}
                                    <div class="flex gap-1 items-center flex-wrap justify-center">
                                        <button
                                            wire:click="setDecision({{ $student->id }}, 'passe')"
                                            class="btn btn-xs {{ $decision === 'passe' ? 'btn-success' : 'btn-outline btn-success opacity-40' }}"
                                        >✓ Promu</button>
                                        <button
                                            wire:click="setDecision({{ $student->id }}, 'redoublant')"
                                            class="btn btn-xs {{ $decision === 'redoublant' ? 'btn-error' : 'btn-outline btn-error opacity-40' }}"
                                        >↩ Redoublant</button>
                                    </div>

                                    {{-- Suggestion indicator --}}
                                    @if(! isset($decisions[$student->id]) && $student->suggestedDecision !== 'en_attente')
                                    <span class="text-[10px] text-base-content/40 italic">Suggestion auto</span>
                                    @endif

                                    {{-- Next classroom selector (only for promoted) --}}
                                    @if($decision === 'passe')
                                    <select
                                        wire:model="nextClassrooms.{{ $student->id }}"
                                        class="select select-bordered select-xs w-full max-w-40 mt-0.5"
                                    >
                                        <option value="">— Classe suivante</option>
                                        @foreach($allClassrooms as $cls)
                                        <option value="{{ $cls['id'] }}">{{ $cls['name'] }}</option>
                                        @endforeach
                                    </select>
                                    @endif
                                </div>
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
                                    @if(! $student->canGenerate)
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

    {{-- Promotion action bar --}}
    <div class="card bg-base-100 shadow border border-base-200">
        <div class="card-body py-3">
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                <div class="flex-1 text-sm text-base-content/60">
                    <span class="badge badge-success badge-sm mr-1">{{ $passedCount }}</span> promu(s) ·
                    <span class="badge badge-error badge-sm mx-1">{{ $redoublantCount }}</span> redoublant(s) ·
                    <span class="badge badge-ghost badge-sm mx-1">{{ max(0, $pendingDecision) }}</span> sans décision
                </div>
                <div class="flex gap-2 flex-wrap">
                    <x-button
                        label="Enregistrer les décisions"
                        wire:click="saveDecisions"
                        class="btn-primary btn-sm"
                        spinner="saveDecisions"
                        icon="o-check"
                        wire:confirm="Enregistrer toutes les décisions de promotion ?"
                    />
                    <x-button
                        label="Appliquer les promotions"
                        wire:click="applyPromotions"
                        class="btn-success btn-sm"
                        spinner="applyPromotions"
                        icon="o-academic-cap"
                        wire:confirm="Appliquer les promotions ? Les élèves promus seront déplacés dans leur nouvelle classe."
                    />
                </div>
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
