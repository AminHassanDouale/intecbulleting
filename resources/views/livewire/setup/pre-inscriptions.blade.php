<?php

use App\Models\PreInscription;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('components.layouts.app')] class extends Component {
    use Toast, WithPagination;

    public string  $search      = '';
    public string  $filterStatus = '';
    public string  $filterNiveau = '';

    // Detail modal
    public bool    $showDetail  = false;
    public ?int    $detailId    = null;
    public string  $adminNotes  = '';
    public string  $newStatus   = '';

    public function updatedSearch(): void      { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterNiveau(): void { $this->resetPage(); }

    public function openDetail(int $id): void
    {
        $pi = PreInscription::findOrFail($id);
        $this->detailId   = $pi->id;
        $this->adminNotes = $pi->admin_notes ?? '';
        $this->newStatus  = $pi->status;
        $this->showDetail = true;
    }

    public function saveDetail(): void
    {
        $this->validate([
            'newStatus'  => 'required|in:pending,contacted,accepted,rejected',
            'adminNotes' => 'nullable|max:1000',
        ]);

        PreInscription::findOrFail($this->detailId)->update([
            'status'      => $this->newStatus,
            'admin_notes' => $this->adminNotes ?: null,
        ]);

        $this->showDetail = false;
        $this->success('Pré-inscription mise à jour.');
    }

    public function delete(int $id): void
    {
        PreInscription::findOrFail($id)->delete();
        $this->success('Pré-inscription supprimée.');
    }

    public function with(): array
    {
        $query = PreInscription::query()
            ->when($this->search, fn($q) => $q->where(function($q2) {
                $q2->where('student_firstname', 'like', '%'.$this->search.'%')
                   ->orWhere('student_lastname',  'like', '%'.$this->search.'%')
                   ->orWhere('parent_name',        'like', '%'.$this->search.'%')
                   ->orWhere('parent_phone',       'like', '%'.$this->search.'%');
            }))
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterNiveau, fn($q) => $q->where('niveau_souhaite', $this->filterNiveau))
            ->latest();

        return [
            'items'   => $query->paginate(15),
            'counts'  => [
                'total'     => PreInscription::count(),
                'pending'   => PreInscription::where('status','pending')->count(),
                'contacted' => PreInscription::where('status','contacted')->count(),
                'accepted'  => PreInscription::where('status','accepted')->count(),
                'rejected'  => PreInscription::where('status','rejected')->count(),
            ],
            'detail'  => $this->detailId ? PreInscription::find($this->detailId) : null,
        ];
    }
}; ?>

<div>
    {{-- ── Page header ─────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Pré-inscriptions</h1>
            <p class="text-sm text-slate-500 mt-0.5">Gestion des demandes de pré-inscription 2026-2027</p>
        </div>
    </div>

    {{-- ── Stat cards ──────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
        @foreach([
            ['label'=>'Total',     'key'=>'total',     'color'=>'bg-slate-100 text-slate-700',   'icon'=>'📋'],
            ['label'=>'En attente','key'=>'pending',   'color'=>'bg-orange-50 text-orange-700',  'icon'=>'⏳'],
            ['label'=>'Contactés', 'key'=>'contacted', 'color'=>'bg-blue-50 text-blue-700',      'icon'=>'📞'],
            ['label'=>'Acceptés',  'key'=>'accepted',  'color'=>'bg-green-50 text-green-700',    'icon'=>'✅'],
            ['label'=>'Refusés',   'key'=>'rejected',  'color'=>'bg-red-50 text-red-700',        'icon'=>'❌'],
        ] as $stat)
        <div class="rounded-2xl p-4 {{ $stat['color'] }} border border-current/10 cursor-pointer transition-all hover:scale-[1.02]"
             wire:click="$set('filterStatus', '{{ $stat['key'] === 'total' ? '' : $stat['key'] }}')">
            <div class="text-2xl font-black">{{ $counts[$stat['key']] }}</div>
            <div class="text-xs font-medium mt-0.5 flex items-center gap-1">
                <span>{{ $stat['icon'] }}</span> {{ $stat['label'] }}
            </div>
        </div>
        @endforeach
    </div>

    {{-- ── Filters ──────────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap gap-3 mb-4">
        <div class="relative flex-1 min-w-48">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input wire:model.live.debounce.300ms="search" type="text"
                   placeholder="Rechercher nom, parent, téléphone…"
                   class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
        </div>
        <select wire:model.live="filterStatus"
                class="px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
            <option value="">— Tous statuts —</option>
            <option value="pending">En attente</option>
            <option value="contacted">Contacté</option>
            <option value="accepted">Accepté</option>
            <option value="rejected">Refusé</option>
        </select>
        <select wire:model.live="filterNiveau"
                class="px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
            <option value="">— Tous niveaux —</option>
            @foreach(\App\Models\PreInscription::niveaux() as $code => $label)
            <option value="{{ $code }}">{{ $code }} — {{ $label }}</option>
            @endforeach
        </select>
        @if($search || $filterStatus || $filterNiveau)
        <button wire:click="$set('search',''); $set('filterStatus',''); $set('filterNiveau','')"
                class="px-3 py-2 rounded-xl text-sm text-slate-500 hover:text-slate-800 border border-slate-200 bg-white hover:bg-slate-50">
            ✕ Réinitialiser
        </button>
        @endif
    </div>

    {{-- ── Table ───────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wide">#</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wide">Élève</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wide">Niveau</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wide">Parent / Contact</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wide">Statut</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 text-xs uppercase tracking-wide">Date</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($items as $pi)
                    <tr class="hover:bg-slate-50 transition-colors group">
                        <td class="px-4 py-3 text-slate-400 text-xs">{{ $pi->id }}</td>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-800">{{ $pi->student_firstname }} {{ $pi->student_lastname }}</div>
                            @if($pi->student_birth_date)
                            <div class="text-xs text-slate-400">{{ $pi->student_birth_date->format('d/m/Y') }}
                                @if($pi->student_gender) · {{ $pi->student_gender === 'M' ? 'Masculin' : 'Féminin' }} @endif
                            </div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @php
                            [$nbg,$nco] = in_array($pi->niveau_souhaite,['PS','MS','GS'])
                                ? ['#fff8f0','#c05600']
                                : (in_array($pi->niveau_souhaite,['CP','CE1','CE2','CM1','CM2'])
                                    ? ['#ecfdf5','#065f46']
                                    : (in_array($pi->niveau_souhaite,['6ème','5ème','4ème','3ème'])
                                        ? ['#eff6ff','#1d4ed8']
                                        : ['#f5f3ff','#5b21b6']));
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold"
                                  style="background:{{ $nbg }};color:{{ $nco }};">
                                {{ $pi->niveau_souhaite }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-700">{{ $pi->parent_name }}</div>
                            <div class="text-xs text-slate-400 flex items-center gap-2 mt-0.5">
                                <span>📞 {{ $pi->parent_phone }}</span>
                                @if($pi->parent_email)
                                <span class="hidden sm:inline">· ✉ {{ $pi->parent_email }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $statusConfig = [
                                    'pending'   => ['bg-orange-100 text-orange-700', '⏳ En attente'],
                                    'contacted' => ['bg-blue-100 text-blue-700',    '📞 Contacté'],
                                    'accepted'  => ['bg-green-100 text-green-800',  '✅ Accepté'],
                                    'rejected'  => ['bg-red-100 text-red-700',      '❌ Refusé'],
                                ];
                                [$cls, $lbl] = $statusConfig[$pi->status] ?? ['bg-slate-100 text-slate-600', $pi->status];
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold {{ $cls }}">
                                {{ $lbl }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap">
                            {{ $pi->created_at->format('d/m/Y') }}<br>
                            <span class="text-[11px]">{{ $pi->created_at->diffForHumans() }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button wire:click="openDetail({{ $pi->id }})"
                                        class="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors" title="Voir / modifier">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 11l6-6 3 3-6 6H9v-3z"/>
                                    </svg>
                                </button>
                                <button wire:click="delete({{ $pi->id }})"
                                        wire:confirm="Supprimer cette pré-inscription ?"
                                        class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Supprimer">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center text-slate-400">
                            <div class="text-4xl mb-3">📋</div>
                            <p class="font-medium">Aucune pré-inscription trouvée</p>
                            @if($search || $filterStatus || $filterNiveau)
                            <p class="text-sm mt-1">Essayez de modifier vos filtres</p>
                            @else
                            <p class="text-sm mt-1">Les demandes reçues via le formulaire public apparaîtront ici</p>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($items->hasPages())
        <div class="px-4 py-3 border-t border-slate-100">
            {{ $items->links() }}
        </div>
        @endif
    </div>

    {{-- ── Detail / Edit Modal ─────────────────────────────────────────────── --}}
    @if($showDetail && $detail)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.4);">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden" wire:click.outside="$set('showDetail',false)">

            {{-- Header --}}
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between"
                 style="background:linear-gradient(135deg,#1a5c00,#237300);">
                <h2 class="text-white font-bold">📝 Pré-inscription #{{ $detail->id }}</h2>
                <button wire:click="$set('showDetail',false)" class="text-white/70 hover:text-white text-xl leading-none">&times;</button>
            </div>

            <div class="p-6 space-y-5 max-h-[70vh] overflow-y-auto">

                {{-- Student info --}}
                <div class="rounded-xl bg-slate-50 p-4 space-y-1.5">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">Élève</p>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Nom complet</span>
                        <span class="font-semibold text-slate-800">{{ $detail->student_firstname }} {{ $detail->student_lastname }}</span>
                    </div>
                    @if($detail->student_birth_date)
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Date de naissance</span>
                        <span class="font-medium text-slate-700">{{ $detail->student_birth_date->format('d/m/Y') }}</span>
                    </div>
                    @endif
                    @if($detail->student_gender)
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Sexe</span>
                        <span class="font-medium text-slate-700">{{ $detail->student_gender === 'M' ? 'Masculin' : 'Féminin' }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Niveau souhaité</span>
                        <span class="font-bold" style="color:#1a5c00;">{{ $detail->niveau_souhaite }} — {{ \App\Models\PreInscription::niveaux()[$detail->niveau_souhaite] ?? '' }}</span>
                    </div>
                </div>

                {{-- Parent info --}}
                <div class="rounded-xl bg-orange-50 p-4 space-y-1.5">
                    <p class="text-xs font-bold text-orange-400 uppercase tracking-wide mb-2">Parent / Tuteur</p>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Nom</span>
                        <span class="font-semibold text-slate-800">{{ $detail->parent_name }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Téléphone</span>
                        <a href="tel:{{ $detail->parent_phone }}" class="font-bold text-green-700 hover:underline">{{ $detail->parent_phone }}</a>
                    </div>
                    @if($detail->parent_email)
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Email</span>
                        <a href="mailto:{{ $detail->parent_email }}" class="font-medium text-blue-600 hover:underline">{{ $detail->parent_email }}</a>
                    </div>
                    @endif
                </div>

                {{-- Message from parent --}}
                @if($detail->message)
                <div class="rounded-xl border border-slate-200 p-4">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">Message du parent</p>
                    <p class="text-sm text-slate-700 leading-relaxed">{{ $detail->message }}</p>
                </div>
                @endif

                {{-- Documents --}}
                @if($detail->student_photo || $detail->student_birth_certificate || !empty($detail->parent_documents))
                <div class="rounded-xl border border-slate-200 p-4 space-y-4">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wide">Documents joints</p>

                    {{-- Photo élève --}}
                    @if($detail->student_photo)
                    <div>
                        <p class="text-xs font-semibold text-slate-500 mb-2">📷 Photo de l'élève</p>
                        <div class="flex items-center gap-3">
                            @php $ext = pathinfo($detail->student_photo, PATHINFO_EXTENSION); @endphp
                            @if(in_array(strtolower($ext), ['jpg','jpeg','png','gif','webp']))
                            <img src="{{ Storage::url($detail->student_photo) }}"
                                 class="w-20 h-20 rounded-xl object-cover ring-1 ring-slate-200 shadow cursor-pointer hover:scale-105 transition-transform"
                                 onclick="window.open(this.src,'_blank')">
                            @endif
                            <a href="{{ Storage::url($detail->student_photo) }}" target="_blank"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-50 text-blue-700 text-xs font-semibold hover:bg-blue-100 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Télécharger
                            </a>
                        </div>
                    </div>
                    @endif

                    {{-- Acte de naissance --}}
                    @if($detail->student_birth_certificate)
                    <div>
                        <p class="text-xs font-semibold text-slate-500 mb-2">📋 Acte de naissance</p>
                        <div class="flex items-center gap-3">
                            @php $ext2 = strtolower(pathinfo($detail->student_birth_certificate, PATHINFO_EXTENSION)); @endphp
                            @if(in_array($ext2, ['jpg','jpeg','png','gif','webp']))
                            <img src="{{ Storage::url($detail->student_birth_certificate) }}"
                                 class="w-16 h-16 rounded-xl object-cover ring-1 ring-slate-200 shadow cursor-pointer hover:scale-105 transition-transform"
                                 onclick="window.open(this.src,'_blank')">
                            @elseif($ext2 === 'pdf')
                            <div class="w-16 h-16 rounded-xl bg-red-50 border border-red-200 flex flex-col items-center justify-center">
                                <span class="text-2xl">📄</span>
                                <span class="text-[9px] font-bold text-red-600 uppercase">PDF</span>
                            </div>
                            @endif
                            <a href="{{ Storage::url($detail->student_birth_certificate) }}" target="_blank"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-50 text-blue-700 text-xs font-semibold hover:bg-blue-100 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Télécharger / Ouvrir
                            </a>
                        </div>
                    </div>
                    @endif

                    {{-- Documents parent --}}
                    @if(!empty($detail->parent_documents))
                    <div>
                        <p class="text-xs font-semibold text-slate-500 mb-2">🗂️ Documents parent ({{ count($detail->parent_documents) }})</p>
                        <div class="space-y-2">
                            @foreach($detail->parent_documents as $idx => $docPath)
                            @php $dext = strtolower(pathinfo($docPath, PATHINFO_EXTENSION)); @endphp
                            <div class="flex items-center gap-3 p-2.5 rounded-xl bg-violet-50 border border-violet-100">
                                @if(in_array($dext, ['jpg','jpeg','png','gif','webp']))
                                <img src="{{ Storage::url($docPath) }}"
                                     class="w-10 h-10 rounded-lg object-cover ring-1 ring-violet-200 cursor-pointer hover:scale-105 transition-transform"
                                     onclick="window.open(this.src,'_blank')">
                                @else
                                <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center text-lg">📄</div>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-violet-700 truncate">Document {{ $idx + 1 }}</p>
                                    <p class="text-[10px] text-violet-400 uppercase">{{ $dext }}</p>
                                </div>
                                <a href="{{ Storage::url($docPath) }}" target="_blank"
                                   class="text-xs text-blue-600 hover:text-blue-800 font-semibold shrink-0">Ouvrir</a>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
                @else
                <div class="rounded-xl border border-slate-200 p-4 text-center">
                    <p class="text-xs text-slate-400">Aucun document joint (ancienne demande)</p>
                </div>
                @endif

                {{-- Status update --}}
                <div class="space-y-3">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide">Statut</label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach([
                            'pending'   => ['⏳ En attente',  'border-orange-300 bg-orange-50 text-orange-700'],
                            'contacted' => ['📞 Contacté',    'border-blue-300 bg-blue-50 text-blue-700'],
                            'accepted'  => ['✅ Accepté',     'border-green-400 bg-green-50 text-green-800'],
                            'rejected'  => ['❌ Refusé',      'border-red-300 bg-red-50 text-red-700'],
                        ] as $val => [$lbl, $cls])
                        <label class="cursor-pointer">
                            <input type="radio" wire:model="newStatus" value="{{ $val }}" class="sr-only peer">
                            <div class="rounded-xl border-2 px-3 py-2 text-center text-sm font-semibold transition-all
                                        {{ $cls }}
                                        peer-checked:ring-2 peer-checked:ring-offset-1 peer-checked:ring-current
                                        hover:opacity-80">
                                {{ $lbl }}
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>

                {{-- Admin notes --}}
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide">Notes internes</label>
                    <textarea wire:model="adminNotes" rows="3"
                              placeholder="Notes visibles uniquement par l'administration..."
                              class="w-full px-3 py-2 rounded-xl border border-slate-200 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-green-500 bg-white"></textarea>
                </div>

            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 border-t border-slate-100 flex gap-3 bg-slate-50">
                <button wire:click="saveDetail"
                        class="flex-1 py-2.5 rounded-xl font-bold text-white text-sm shadow transition-opacity hover:opacity-90"
                        style="background:linear-gradient(135deg,#1a5c00,#237300);">
                    💾 Enregistrer
                </button>
                <button wire:click="$set('showDetail',false)"
                        class="px-5 py-2.5 rounded-xl font-semibold text-slate-600 bg-white border border-slate-200 text-sm hover:bg-slate-100">
                    Annuler
                </button>
            </div>
        </div>
    </div>
    @endif

    <x-toast />
</div>
