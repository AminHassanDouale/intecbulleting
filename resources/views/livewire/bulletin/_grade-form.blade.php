{{--
  Partial: _grade-form.blade.php
  Variables (from parent scope or passed explicitly):
    $canEdit           bool      — whether input fields are editable
    $subjects          Collection — teacher's subjects with loaded competences
    $grades            array     — wire:model, keyed by competence_id
    $teacherComment    string    — wire:model
    $bulletin          Bulletin  — current bulletin (may be null)
    $competenceOptions array     — A/EVA/NA options for prescolaire selects
--}}

@if($subjects->isEmpty())
    <div class="flex items-center gap-3 p-3 bg-base-200 rounded-xl text-sm text-base-content/60">
        <svg class="w-5 h-5 shrink-0 text-base-content/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/>
        </svg>
        Aucune matière assignée à votre compte pour ce niveau.
    </div>
@else

{{-- Subjects & competences grid --}}
<div class="space-y-3">
    @foreach($subjects as $subject)
    <div class="border border-base-200 rounded-xl overflow-hidden">

        {{-- Subject header --}}
        <div class="flex items-center justify-between px-3 py-2
                    {{ $subject->scale_type === 'competence' ? 'bg-violet-50 border-b border-violet-100' : 'bg-primary/5 border-b border-primary/10' }}">
            <div class="flex items-center gap-2">
                <span class="font-bold text-sm {{ $subject->scale_type === 'competence' ? 'text-violet-700' : 'text-primary' }}">
                    {{ $subject->name }}
                </span>
                <span class="badge badge-xs {{ $subject->scale_type === 'competence' ? 'badge-warning' : 'badge-info' }}">
                    {{ $subject->scale_type === 'competence' ? 'A/EVA/NA' : 'Numérique' }}
                </span>
            </div>
            @if($subject->scale_type !== 'competence' && $subject->max_score)
                <span class="text-xs font-mono text-base-content/40">/ {{ $subject->max_score }}</span>
            @endif
        </div>

        {{-- Competences list --}}
        <div class="divide-y divide-base-100">
            @forelse($subject->competences as $competence)
            <div class="flex items-center gap-3 px-3 py-2 hover:bg-base-50 transition-colors">
                <div class="flex-1 min-w-0">
                    <span class="text-xs font-mono text-primary/60 mr-1">{{ $competence->code }}</span>
                    <span class="text-sm text-base-content/80">{{ $competence->description }}</span>
                </div>

                @if($subject->isPrescolaire())
                    {{-- Prescolaire: A / EVA / NA --}}
                    @if($canEdit)
                        <select
                            wire:model.live="grades.{{ $competence->id }}"
                            class="select select-bordered select-xs w-28"
                        >
                            <option value="">—</option>
                            @foreach($competenceOptions as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                            @endforeach
                        </select>
                    @else
                        @php $val = $grades[$competence->id] ?? null; @endphp
                        <span class="badge badge-sm font-semibold
                            {{ $val === 'A'   ? 'badge-success' :
                               ($val === 'EVA' ? 'badge-warning' :
                               ($val === 'NA'  ? 'badge-error'   : 'badge-ghost')) }}">
                            {{ $val === 'A' ? 'Acquis' : ($val === 'EVA' ? 'Moyen' : ($val === 'NA' ? 'Très faible' : '—')) }}
                        </span>
                    @endif
                @else
                    {{-- Numeric score --}}
                    @if($canEdit)
                        <div class="flex items-center gap-1.5 shrink-0">
                            <input
                                type="number"
                                wire:model.lazy="grades.{{ $competence->id }}"
                                min="0"
                                max="{{ $competence->max_score }}"
                                step="0.5"
                                class="input input-bordered input-xs w-20 text-center font-mono"
                                placeholder="—"
                            />
                            <span class="text-xs text-base-content/40">/ {{ $competence->max_score }}</span>
                        </div>
                    @else
                        @php $score = $grades[$competence->id] ?? null; @endphp
                        <span class="font-mono text-sm shrink-0
                            {{ $score !== null ? ($score >= ($competence->max_score / 2) ? 'text-success font-bold' : 'text-error font-bold') : 'text-base-content/30' }}">
                            {{ $score !== null ? $score : '—' }}
                            <span class="text-xs text-base-content/40 font-normal">/ {{ $competence->max_score }}</span>
                        </span>
                    @endif
                @endif
            </div>
            @empty
            <div class="px-3 py-2 text-xs text-base-content/40 italic">Aucune compétence définie.</div>
            @endforelse
        </div>
    </div>
    @endforeach
</div>

{{-- Teacher comment --}}
<div class="mt-1">
    <label class="text-xs font-semibold text-base-content/60 uppercase tracking-wide block mb-1.5">
        💬 Commentaire <span class="font-normal normal-case text-base-content/40">(optionnel)</span>
    </label>
    @if($canEdit)
        <textarea
            wire:model.lazy="teacherComment"
            rows="2"
            class="textarea textarea-bordered w-full text-sm resize-none"
            placeholder="Appréciation sur l'élève…"
        >{{ $teacherComment }}</textarea>
    @else
        <div class="p-2.5 rounded-xl border border-base-200 bg-base-200/40 text-sm text-base-content/70 min-h-10">
            {{ $teacherComment ?: '—' }}
        </div>
    @endif
</div>

{{-- Actions --}}
@if($canEdit)
<div class="mt-3 pt-3 border-t border-base-200">
    {{-- Info strip --}}
    <div class="flex items-start gap-2 mb-3 p-2.5 rounded-xl bg-primary/5 border border-primary/10 text-xs text-primary/80">
        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>
            En enregistrant, vos notes sont <strong>définitivement soumises</strong> pour ce trimestre.
            Quand tous les enseignants ont soumis, le bulletin est automatiquement transmis à la pédagogie.
        </span>
    </div>
    <div class="flex justify-end">
        <x-button
            label="Enregistrer & Soumettre"
            wire:click="saveGrades"
            class="btn-primary btn-sm"
            spinner="saveGrades"
            icon="o-paper-airplane"
            wire:confirm="Enregistrer et soumettre vos notes ? Vous ne pourrez plus les modifier."
        />
    </div>
</div>
@endif

@endif
