<?php

use App\Enums\ClassroomEnum;
use App\Enums\PeriodEnum;
use App\Enums\ScaleTypeEnum;
use App\Exports\ProgrammeTemplateExport;
use App\Imports\ProgrammeImport;
use App\Models\Classroom;
use App\Models\Competence;
use App\Models\Niveau;
use App\Models\Subject;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Renderless;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Mary\Traits\Toast;

new #[Layout('components.layouts.app')] class extends Component {
    use Toast, WithFileUploads;

    public ?int $selectedNiveau = null;

    // ── Subject modal ──────────────────────────────────────────────────────
    public bool    $showSubjectModal = false;
    public ?int    $editSubjectId    = null;
    public string  $s_name           = '';
    public string  $s_code           = '';
    public ?string $s_classroom_code = null;
    public         $s_max_score      = 20;
    public string  $s_scale_type     = 'numeric';
    public int     $s_order          = 0;

    // ── Competence modal ───────────────────────────────────────────────────
    public bool    $showCompModal  = false;
    public ?int    $editCompId     = null;
    public ?int    $c_subject_id   = null;
    public string  $c_code         = '';
    public string  $c_description  = '';
    public         $c_max_score    = null;
    public ?string $c_period       = null;
    public int     $c_order        = 0;

    // ── Import ─────────────────────────────────────────────────────────────
    public $importFile = null;

    public function mount(): void
    {
        $this->selectedNiveau = Niveau::orderBy('order')->value('id');
    }

    // ── Subject CRUD ───────────────────────────────────────────────────────
    public function openSubjectModal(?int $id = null): void
    {
        $this->reset(['s_name','s_code','s_classroom_code','s_max_score','s_scale_type','s_order','editSubjectId']);
        $this->s_max_score  = 20;
        $this->s_scale_type = 'numeric';
        if ($id) {
            $s = Subject::findOrFail($id);
            $this->editSubjectId    = $s->id;
            $this->s_name           = $s->name;
            $this->s_code           = $s->code;
            $this->s_classroom_code = $s->classroom_code;
            $this->s_max_score      = $s->max_score;
            $this->s_scale_type     = $s->scale_type;
            $this->s_order          = $s->order;
        }
        $this->showSubjectModal = true;
    }

    public function saveSubject(): void
    {
        $this->validate([
            'selectedNiveau'   => 'required|exists:niveaux,id',
            's_name'           => 'required|string|max:100',
            's_code'           => 'required|string|max:20',
            's_classroom_code' => 'nullable|string',
            's_max_score'      => 'required|numeric|min:1',
            's_scale_type'     => 'required|in:numeric,competence',
            's_order'          => 'integer|min:0',
        ]);

        $payload = [
            'niveau_id'      => $this->selectedNiveau,
            'name'           => $this->s_name,
            'code'           => $this->s_code,
            'classroom_code' => $this->s_classroom_code ?: null,
            'max_score'      => $this->s_max_score,
            'scale_type'     => $this->s_scale_type,
            'order'          => $this->s_order,
        ];

        if ($this->editSubjectId) {
            Subject::findOrFail($this->editSubjectId)->update($payload);
            $this->success('Matière mise à jour.', icon: 'o-check-circle', position: 'toast-top toast-end');
        } else {
            Subject::create($payload);
            $this->success('Matière créée.', icon: 'o-check-circle', position: 'toast-top toast-end');
        }
        $this->showSubjectModal = false;
    }

    public function deleteSubject(int $id): void
    {
        Subject::findOrFail($id)->delete();
        $this->warning('Matière et ses compétences supprimées.', icon: 'o-trash', position: 'toast-top toast-end');
    }

    // ── Competence CRUD ────────────────────────────────────────────────────
    public function openCompModal(?int $subjectId = null, ?int $id = null): void
    {
        $this->reset(['c_code','c_description','c_max_score','c_period','c_order','editCompId']);
        $this->c_subject_id = $subjectId;
        if ($id) {
            $c = Competence::findOrFail($id);
            $this->editCompId    = $c->id;
            $this->c_subject_id  = $c->subject_id;
            $this->c_code        = $c->code;
            $this->c_description = $c->description;
            $this->c_max_score   = $c->max_score;
            $this->c_period      = $c->period;
            $this->c_order       = $c->order;
        }
        $this->showCompModal = true;
    }

    public function saveComp(): void
    {
        $this->validate([
            'c_subject_id'  => 'required|exists:subjects,id',
            'c_code'        => 'required|string|max:20',
            'c_description' => 'required|string',
            'c_max_score'   => 'nullable|numeric|min:1',
            'c_period'      => 'nullable|in:T1,T2,T3',
            'c_order'       => 'integer|min:0',
        ]);

        $payload = [
            'subject_id'  => $this->c_subject_id,
            'code'        => $this->c_code,
            'description' => $this->c_description,
            'max_score'   => $this->c_max_score ?: null,
            'period'      => $this->c_period ?: null,
            'order'       => $this->c_order,
        ];

        if ($this->editCompId) {
            Competence::findOrFail($this->editCompId)->update($payload);
            $this->success('Compétence mise à jour.', icon: 'o-check-circle', position: 'toast-top toast-end');
        } else {
            Competence::create($payload);
            $this->success('Compétence créée.', icon: 'o-check-circle', position: 'toast-top toast-end');
        }
        $this->showCompModal = false;
    }

    public function deleteComp(int $id): void
    {
        Competence::findOrFail($id)->delete();
        $this->warning('Compétence supprimée.', icon: 'o-trash', position: 'toast-top toast-end');
    }

    // ── Import / Export ────────────────────────────────────────────────────
    #[Renderless]
    public function downloadTemplate(): mixed
    {
        return Excel::download(new ProgrammeTemplateExport(), 'programme_template.xlsx');
    }

    public function importProgramme(): void
    {
        if (! $this->importFile) {
            $this->error('Aucun fichier sélectionné.', icon: 'o-exclamation-circle', position: 'toast-top toast-end');
            return;
        }

        $importer = new ProgrammeImport();

        try {
            $stored   = $this->importFile->store('programme-imports', 'local');
            $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($stored);
            Excel::import($importer, $fullPath);
            \Illuminate\Support\Facades\Storage::disk('local')->delete($stored);
            $this->importFile = null;

            $s = $importer->getStats();
            $msg = "{$s['subjects_created']} matière(s) créées, {$s['subjects_updated']} mises à jour, "
                 . "{$s['competences_created']} compétence(s) créées, {$s['competences_updated']} mises à jour.";

            if (! empty($s['errors'])) {
                $this->warning("Import terminé. {$msg}", implode(' | ', array_slice($s['errors'], 0, 3)), icon: 'o-exclamation-triangle', position: 'toast-top toast-end');
            } else {
                $this->success("Import réussi ! {$msg}", icon: 'o-arrow-up-tray', position: 'toast-top toast-end');
            }
        } catch (\Throwable $e) {
            $this->error('Erreur import', $e->getMessage(), icon: 'o-x-circle', position: 'toast-top toast-end');
        }
    }

    // ── Data ───────────────────────────────────────────────────────────────
    public function with(): array
    {
        $isTeacher = auth()->user()->hasRole('teacher');
        $niveaux   = Niveau::orderBy('order')->get();

        // For teachers: only show subjects they are assigned to
        $subjectsQuery = Subject::with([
            'competences' => fn($q) => $q->orderBy('order'),
            'teachers',
        ])->where('niveau_id', $this->selectedNiveau ?? 0)->orderBy('order');

        if ($isTeacher) {
            $subjectsQuery->whereHas('teachers', fn($q) => $q->where('users.id', auth()->id()));
        }

        $subjects = $this->selectedNiveau ? $subjectsQuery->get() : collect();

        // Classrooms for the selected niveau (for teacher info display)
        $classroomsForNiveau = $this->selectedNiveau
            ? Classroom::with('teacher')
                ->where('niveau_id', $this->selectedNiveau)
                ->orderBy('code')
                ->get()
            : collect();

        return [
            'niveaux'             => $niveaux,
            'subjects'            => $subjects,
            'classroomsForNiveau' => $classroomsForNiveau,
            'isTeacher'           => $isTeacher,
            'canEdit'             => ! $isTeacher,
            'scaleTypes'          => collect(ScaleTypeEnum::cases())->map(fn($s) => ['id' => $s->value, 'name' => $s->label()])->toArray(),
            'periodOptions'       => array_merge(
                [['id' => '', 'name' => '— Tous les trimestres —']],
                PeriodEnum::options()
            ),
            'classCodes'          => collect(ClassroomEnum::cases())
                ->map(fn($c) => ['id' => $c->value, 'name' => $c->label()])
                ->prepend(['id' => '', 'name' => '— Toutes les classes —'])
                ->toArray(),
        ];
    }
}; ?>

<div class="space-y-5">

    {{-- ── Page header ──────────────────────────────────────────────────── --}}
    <div class="rounded-2xl bg-linear-to-r from-teal-600 to-emerald-700 text-white px-6 py-5 shadow-lg">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-2xl backdrop-blur">📖</div>
                <div>
                    <h1 class="text-xl font-bold">Programme Scolaire</h1>
                    <p class="text-white/70 text-sm">
                        @if($isTeacher)
                            Vos matières et compétences assignées
                        @else
                            Gérer les matières et compétences par niveau
                        @endif
                    </p>
                </div>
            </div>
            @if($canEdit)
            <x-button label="Nouvelle matière" wire:click="openSubjectModal" class="btn-white text-teal-700 font-semibold" icon="o-plus" />
            @endif
        </div>
    </div>

    {{-- ── Import / Export bar (direction/admin only) ─────────────────── --}}
    @if($canEdit)
    <div class="card bg-base-100 shadow-sm border border-base-200">
        <div class="card-body py-3 px-4">
            <div class="flex flex-wrap items-center gap-3">
                <p class="font-semibold text-xs text-base-content/50 w-20 shrink-0">IMPORT</p>
                <x-button
                    label="Télécharger le modèle"
                    wire:click="downloadTemplate"
                    class="btn-outline btn-sm btn-success"
                    icon="o-document-arrow-down"
                    spinner="downloadTemplate"
                    tooltip="Télécharger le fichier Excel avec toutes les matières et compétences existantes" />
                <div class="flex items-center gap-2 flex-1 min-w-0">
                    <x-file wire:model="importFile" accept=".xlsx,.xls" class="file-input-sm flex-1" />
                    <x-button
                        label="Importer"
                        wire:click="importProgramme"
                        class="btn-sm btn-primary shrink-0"
                        icon="o-arrow-up-tray"
                        spinner="importProgramme"
                        :disabled="!$importFile" />
                </div>
            </div>
            <p class="text-xs text-base-content/40 mt-1">
                Le fichier doit contenir 2 feuilles : <strong>Matières</strong> et <strong>Compétences</strong> (format du modèle).
            </p>
        </div>
    </div>
    @endif

    {{-- ── Niveau tabs ─────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap gap-2">
        @foreach($niveaux as $n)
        @php
            $cnt = \App\Models\Subject::where('niveau_id', $n->id)
                ->when($isTeacher, fn($q) => $q->whereHas('teachers', fn($q2) => $q2->where('users.id', auth()->id())))
                ->count();
        @endphp
        <button
            wire:click="$set('selectedNiveau', {{ $n->id }})"
            class="px-4 py-2 rounded-lg font-semibold text-sm transition-all border
                {{ $selectedNiveau == $n->id
                    ? 'bg-teal-600 text-white border-teal-600 shadow'
                    : 'bg-base-100 text-base-content border-base-300 hover:border-teal-400 hover:text-teal-600' }}">
            {{ $n->label }}
            <span class="ml-1 badge badge-xs {{ $selectedNiveau == $n->id ? 'bg-white/30 text-white' : 'badge-ghost' }}">
                {{ $cnt }}
            </span>
        </button>
        @endforeach
    </div>

    {{-- ── Classrooms / teachers info for this niveau ──────────────────── --}}
    @if($selectedNiveau && $classroomsForNiveau->isNotEmpty())
    <div class="flex flex-wrap gap-2">
        @foreach($classroomsForNiveau as $cls)
        <div class="flex items-center gap-2 bg-base-100 border border-base-200 rounded-lg px-3 py-1.5 text-sm shadow-sm">
            <span class="font-bold text-teal-600">{{ $cls->code }}{{ $cls->section }}</span>
            <span class="text-base-content/40">—</span>
            <span class="text-base-content/70">{{ $cls->teacher?->name ?? '— Sans enseignant —' }}</span>
        </div>
        @endforeach
    </div>
    @endif

    {{-- ── Subject accordion ───────────────────────────────────────────── --}}
    @if($selectedNiveau)
        @forelse($subjects as $subject)
        <div class="card bg-base-100 shadow border border-base-200" wire:key="subject-{{ $subject->id }}"
             x-data="{ open: true }">

            {{-- Subject header --}}
            <div class="flex items-center gap-3 px-4 py-3 border-b border-base-200 bg-base-200/40">
                <button @click="open = !open" class="flex items-center gap-2 flex-1 min-w-0 text-left">
                    <span x-text="open ? '▾' : '▸'" class="text-base-content/40 w-4 shrink-0 text-xs"></span>
                    <span class="font-bold text-sm truncate">{{ $subject->name }}</span>
                    <span class="font-mono text-xs text-base-content/40 hidden sm:inline">{{ $subject->code }}</span>
                    @if($subject->classroom_code)
                        <span class="badge badge-outline badge-xs shrink-0">{{ $subject->classroom_code }}</span>
                    @endif
                    <span class="badge badge-xs shrink-0 {{ $subject->scale_type === 'competence' ? 'badge-warning' : 'badge-info' }}">
                        {{ $subject->scale_type === 'competence' ? 'A/EVA/NA' : '/' . $subject->max_score . ' pts' }}
                    </span>
                    <span class="badge badge-ghost badge-xs shrink-0">
                        {{ $subject->competences->count() }} compétence(s)
                    </span>
                </button>

                {{-- Teacher chips --}}
                <div class="hidden sm:flex flex-wrap gap-1 shrink-0 max-w-xs">
                    @forelse($subject->teachers as $t)
                        <span class="badge badge-sm badge-ghost text-xs gap-1">
                            <span>👤</span> {{ $t->name }}
                        </span>
                    @empty
                        <span class="text-xs text-base-content/30 italic">Sans enseignant</span>
                    @endforelse
                </div>

                {{-- Actions (direction/admin only) --}}
                @if($canEdit)
                <div class="flex gap-1 shrink-0">
                    <x-button wire:click="openCompModal({{ $subject->id }})" class="btn-xs btn-success" icon="o-plus" tooltip="Ajouter une compétence" />
                    <x-button wire:click="openSubjectModal({{ $subject->id }})" class="btn-xs btn-warning" icon="o-pencil" tooltip="Modifier" />
                    <x-button wire:click="deleteSubject({{ $subject->id }})" class="btn-xs btn-error" icon="o-trash"
                        wire:confirm="Supprimer '{{ $subject->name }}' et toutes ses compétences ?" tooltip="Supprimer" />
                </div>
                @endif
            </div>

            {{-- Competences table --}}
            <div x-show="open" x-transition>
                @if($subject->competences->isEmpty())
                <div class="px-5 py-4 text-sm text-base-content/40 italic flex items-center gap-2">
                    Aucune compétence définie.
                    @if($canEdit)
                        <button wire:click="openCompModal({{ $subject->id }})" class="link link-primary text-xs">+ Ajouter</button>
                    @endif
                </div>
                @else
                <div class="overflow-x-auto">
                    <table class="table table-sm w-full">
                        <thead>
                            <tr class="text-xs text-base-content/50 border-b border-base-200 bg-base-50">
                                <th class="w-24">Code</th>
                                <th>Description</th>
                                <th class="w-24 text-center">Barème</th>
                                <th class="w-24 text-center">Période</th>
                                <th class="w-16 text-center">Ordre</th>
                                @if($canEdit)
                                <th class="w-20 text-right">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($subject->competences as $comp)
                            <tr wire:key="comp-{{ $comp->id }}" class="hover:bg-base-50">
                                <td>
                                    <span class="font-mono font-bold text-teal-700 bg-teal-50 px-2 py-0.5 rounded text-xs">
                                        {{ $comp->code }}
                                    </span>
                                </td>
                                <td class="text-sm">{{ $comp->description }}</td>
                                <td class="text-center">
                                    @if($comp->max_score)
                                        <span class="badge badge-info badge-sm font-bold">/{{ $comp->max_score }}</span>
                                    @else
                                        <span class="badge badge-warning badge-sm text-xs">A/EVA/NA</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($comp->period)
                                        <span class="badge badge-ghost badge-sm">{{ $comp->period }}</span>
                                    @else
                                        <span class="text-base-content/30 text-xs">Tous</span>
                                    @endif
                                </td>
                                <td class="text-center text-xs text-base-content/40">{{ $comp->order }}</td>
                                @if($canEdit)
                                <td class="text-right">
                                    <div class="flex gap-1 justify-end">
                                        <x-button wire:click="openCompModal({{ $subject->id }}, {{ $comp->id }})" class="btn-xs btn-warning" icon="o-pencil" />
                                        <x-button wire:click="deleteComp({{ $comp->id }})" class="btn-xs btn-error" icon="o-trash"
                                            wire:confirm="Supprimer la compétence '{{ $comp->code }}' ?" />
                                    </div>
                                </td>
                                @endif
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
        @empty
        <div class="card bg-base-100 shadow">
            <div class="card-body text-center py-12 text-base-content/40">
                <div class="text-4xl mb-3">📚</div>
                @if($isTeacher)
                    <p class="font-semibold">Aucune matière ne vous est assignée pour ce niveau.</p>
                    <p class="text-sm">Contactez la direction pour être assigné à des matières.</p>
                @else
                    <p class="font-semibold">Aucune matière pour ce niveau.</p>
                    <p class="text-sm">Cliquez sur <strong>Nouvelle matière</strong> pour commencer.</p>
                @endif
            </div>
        </div>
        @endforelse
    @else
    <div class="card bg-base-100 shadow">
        <div class="card-body text-center py-12 text-base-content/40">
            <div class="text-4xl mb-3">🎓</div>
            <p>Sélectionnez un niveau pour voir les matières.</p>
        </div>
    </div>
    @endif

    {{-- ── Subject modal ───────────────────────────────────────────────── --}}
    <x-modal wire:model="showSubjectModal"
             title="{{ $editSubjectId ? '✏️ Modifier la matière' : '📚 Nouvelle matière' }}"
             subtitle="{{ $niveaux->firstWhere('id', $selectedNiveau)?->label ?? '' }}"
             class="backdrop-blur" box-class="max-w-xl">
        <x-form wire:submit="saveSubject" no-separator>
            <x-errors title="Veuillez corriger les erreurs." icon="o-face-frown" />
            <div class="grid grid-cols-2 gap-4">
                <x-input label="Nom de la matière *" wire:model="s_name" placeholder="MATHÉMATIQUES" icon="o-book-open" class="col-span-2" />
                <x-input label="Code *" wire:model="s_code" placeholder="MATHS" icon="o-tag" hint="Code court unique" />
                <x-input label="Note maximale *" wire:model="s_max_score" type="number" min="1" step="0.5" icon="o-star" />
                <x-choices label="Type de barème *" wire:model="s_scale_type" :options="$scaleTypes" single icon="o-chart-bar" />
                <x-choices label="Classe spécifique" wire:model="s_classroom_code" :options="$classCodes" single clearable icon="o-building-library" hint="Vide = toutes les classes" />
                <x-input label="Ordre d'affichage" wire:model="s_order" type="number" min="0" icon="o-arrows-up-down" class="col-span-2" />
            </div>
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showSubjectModal = false" />
                <x-button label="{{ $editSubjectId ? 'Mettre à jour' : 'Créer' }}" class="btn-primary" type="submit" spinner="saveSubject" icon="o-check" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- ── Competence modal ────────────────────────────────────────────── --}}
    <x-modal wire:model="showCompModal"
             title="{{ $editCompId ? '✏️ Modifier la compétence' : '🎯 Nouvelle compétence' }}"
             subtitle="{{ $c_subject_id ? ($subjects->firstWhere('id', $c_subject_id)?->name ?? '') : '' }}"
             class="backdrop-blur" box-class="max-w-xl">
        <x-form wire:submit="saveComp" no-separator>
            <x-errors title="Veuillez corriger les erreurs." icon="o-face-frown" />
            <x-choices
                label="Matière *"
                wire:model="c_subject_id"
                :options="$subjects->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->toArray()"
                single clearable icon="o-book-open" />
            <div class="grid grid-cols-2 gap-3 mt-3">
                <x-input label="Code *" wire:model="c_code" placeholder="CB1" icon="o-tag" hint="Code court" />
                <x-input label="Note max (vide = A/EVA/NA)" wire:model="c_max_score" type="number" min="1" icon="o-star" hint="Laisser vide pour préscolaire" />
            </div>
            <div class="mt-3">
                <x-textarea label="Description *" wire:model="c_description" rows="3"
                    placeholder="Résoudre une situation-problème faisant intervenir…" />
            </div>
            <div class="grid grid-cols-2 gap-3 mt-3">
                <x-choices label="Période" wire:model="c_period" :options="$periodOptions" single clearable icon="o-calendar" />
                <x-input label="Ordre" wire:model="c_order" type="number" min="0" icon="o-arrows-up-down" />
            </div>
            <x-slot:actions>
                <x-button label="Annuler" @click="$wire.showCompModal = false" />
                <x-button label="{{ $editCompId ? 'Mettre à jour' : 'Créer' }}" class="btn-primary" type="submit" spinner="saveComp" icon="o-check" />
            </x-slot:actions>
        </x-form>
    </x-modal>

</div>
