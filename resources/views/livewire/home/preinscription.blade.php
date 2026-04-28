<?php
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\PreInscription;

new #[Layout('components.layouts.public')] class extends Component {
    use WithFileUploads;

    // ── Élève ────────────────────────────────────────────
    public string $student_firstname = '';
    public string $student_lastname  = '';
    public string $student_birth_date = '';
    public string $student_gender    = '';
    public string $niveau_souhaite   = '';

    // ── Documents élève ───────────────────────────────────
    public $student_photo              = null;
    public $student_birth_certificate  = null;

    // ── Parent ────────────────────────────────────────────
    public string $parent_name  = '';
    public string $parent_phone = '';
    public string $parent_email = '';
    public string $message      = '';

    // ── Documents parent (multiple) ───────────────────────
    public array $parent_documents = [];

    public bool $submitted = false;

    public function removeParentDoc(int $index): void
    {
        array_splice($this->parent_documents, $index, 1);
    }

    public function submit(): void
    {
        $this->validate([
            'student_firstname'         => 'required|min:2|max:60',
            'student_lastname'          => 'required|min:2|max:60',
            'student_birth_date'        => 'nullable|date|before:today',
            'student_gender'            => 'nullable|in:M,F',
            'niveau_souhaite'           => 'required|in:PS,MS,GS,CP,CE1,CE2,CM1,CM2,6ème,5ème,4ème,3ème,2nde,1ère,Tle',
            'student_photo'             => 'required|image|max:2048',
            'student_birth_certificate' => 'required|mimes:pdf,jpg,jpeg,png|max:5120',
            'parent_name'               => 'required|min:3|max:80',
            'parent_phone'              => 'required|min:8|max:20',
            'parent_email'              => 'nullable|email|max:120',
            'message'                   => 'nullable|max:500',
            'parent_documents'          => 'required|array|min:1',
            'parent_documents.*'        => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            'student_photo.required'             => 'La photo de l\'élève est obligatoire.',
            'student_photo.image'                => 'La photo doit être une image (JPG, PNG, GIF).',
            'student_photo.max'                  => 'La photo ne doit pas dépasser 2 Mo.',
            'student_birth_certificate.required' => 'L\'acte de naissance est obligatoire.',
            'student_birth_certificate.mimes'    => 'L\'acte doit être PDF, JPG ou PNG.',
            'student_birth_certificate.max'      => 'L\'acte ne doit pas dépasser 5 Mo.',
            'parent_documents.required'          => 'Veuillez joindre au moins un document du parent (passeport, CIN, etc.).',
            'parent_documents.min'               => 'Veuillez joindre au moins un document du parent.',
            'parent_documents.*.mimes'           => 'Chaque document doit être PDF, JPG ou PNG.',
            'parent_documents.*.max'             => 'Chaque document ne doit pas dépasser 5 Mo.',
        ]);

        $photoPath     = $this->student_photo->store('pre-inscriptions/photos', 'public');
        $birthCertPath = $this->student_birth_certificate->store('pre-inscriptions/birth-certs', 'public');

        $parentDocPaths = [];
        foreach ($this->parent_documents as $doc) {
            $parentDocPaths[] = $doc->store('pre-inscriptions/parent-docs', 'public');
        }

        PreInscription::create([
            'academic_year'             => '2026-2027',
            'student_firstname'         => $this->student_firstname,
            'student_lastname'          => $this->student_lastname,
            'student_birth_date'        => $this->student_birth_date ?: null,
            'student_gender'            => $this->student_gender ?: null,
            'niveau_souhaite'           => $this->niveau_souhaite,
            'student_photo'             => $photoPath,
            'student_birth_certificate' => $birthCertPath,
            'parent_documents'          => $parentDocPaths,
            'parent_name'               => $this->parent_name,
            'parent_phone'              => $this->parent_phone,
            'parent_email'              => $this->parent_email ?: null,
            'message'                   => $this->message ?: null,
            'status'                    => 'pending',
        ]);

        $this->submitted = true;
    }

    public function reset_form(): void
    {
        $this->reset();
        $this->submitted = false;
    }
}; ?>

<div>

{{-- NAVBAR --}}
<nav id="main-nav" class="fixed top-0 left-0 right-0 z-50 navbar-scrolled">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <a href="{{ route('home') }}" class="flex items-center gap-3 group">
                <div class="w-9 h-9 rounded-xl overflow-hidden ring-2 ring-white/20 group-hover:ring-amber-400/60 transition-all shadow-lg">
                    <img src="{{ asset('images/in tech.jpg') }}" alt="INTEC" class="w-full h-full object-cover">
                </div>
                <div>
                    <p class="text-white font-black text-base leading-none">inTEC École</p>
                    <p class="text-white/40 text-[9px] tracking-widest uppercase">Pré-inscription 2026-2027</p>
                </div>
            </a>
            <a href="{{ route('home') }}"
               class="inline-flex items-center gap-2 text-white/70 hover:text-white text-sm font-medium transition-colors glass px-4 py-2 rounded-xl">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Retour à l'accueil
            </a>
        </div>
    </div>
</nav>

{{-- HERO --}}
<div class="hero-gradient pt-20 pb-12 relative overflow-hidden">
    <div class="hero-pattern absolute inset-0"></div>
    <div class="absolute top-0 right-0 w-64 h-64 rounded-full opacity-10 animate-float"
         style="background:radial-gradient(circle,#f59e0b,transparent 70%);"></div>
    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-10">
        <div class="section-badge mx-auto mb-6">
            <span class="w-1.5 h-1.5 bg-amber-400 rounded-full animate-pulse"></span>
            Inscriptions ouvertes — Année scolaire 2026-2027
        </div>
        <h1 class="text-4xl lg:text-5xl font-black text-white mb-4">
            Pré-inscription <span class="text-gradient">2026-2027</span>
        </h1>
        <p class="text-white/60 text-lg max-w-2xl mx-auto">
            Remplissez ce formulaire et notre équipe vous contactera dans les <strong class="text-white">48 heures</strong> pour finaliser l'inscription de votre enfant.
        </p>
        {{-- Steps indicator --}}
        <div class="flex items-center justify-center gap-2 mt-8">
            @foreach(['Élève','Documents','Parent','Documents parent','Envoi'] as $i => $step)
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-black glass text-white/70">{{ $i+1 }}</div>
                <span class="text-white/50 text-xs hidden sm:inline">{{ $step }}</span>
                @if($i < 4)<div class="w-6 h-px bg-white/20"></div>@endif
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- FORM SECTION --}}
<section class="py-12 min-h-screen" style="background:#f8fafc;">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

        @if($submitted)
        {{-- ── SUCCESS ───────────────────────────────────────── --}}
        <div class="bg-white rounded-3xl shadow-xl p-12 text-center border border-slate-100">
            <div class="w-24 h-24 rounded-full flex items-center justify-center text-5xl mx-auto mb-6 animate-fade-up"
                 style="background:linear-gradient(135deg,#1e3a8a,#0c4a6e);">✅</div>
            <h2 class="text-3xl font-black text-slate-900 mb-3 animate-fade-up delay-100">
                Pré-inscription enregistrée !
            </h2>
            <p class="text-slate-500 text-lg mb-8 animate-fade-up delay-200">
                Merci pour votre confiance. Notre équipe vous contactera dans les <strong>48 heures</strong> au numéro indiqué.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center animate-fade-up delay-300">
                <button wire:click="reset_form" class="btn-primary px-6 py-3 rounded-xl shadow-lg">
                    📝 Nouvelle pré-inscription
                </button>
                <a href="{{ route('home') }}"
                   class="px-6 py-3 rounded-xl font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors text-center">
                    🏠 Retour à l'accueil
                </a>
            </div>
            <div class="mt-8 p-4 rounded-2xl text-sm text-slate-500 bg-amber-50 border border-amber-100">
                <p>📞 Pour toute question urgente : <strong>77 08 79 79</strong> ou <strong>77 05 78 78</strong></p>
            </div>
        </div>

        @else
        {{-- ── FORM ──────────────────────────────────────────── --}}
        <form wire:submit="submit" class="space-y-6">

            {{-- Validation errors --}}
            @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-2xl p-4">
                <p class="text-red-600 font-semibold text-sm mb-2">⚠️ Veuillez corriger les erreurs :</p>
                <ul class="text-red-500 text-sm space-y-1 list-disc list-inside">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            {{-- ══ BLOC 1 : Élève ══════════════════════════════ --}}
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-8 py-4 border-b border-slate-100" style="background:linear-gradient(135deg,#0f172a,#1e3a8a);">
                    <h2 class="text-white font-bold flex items-center gap-2">
                        👶 Informations sur l'enfant
                    </h2>
                </div>
                <div class="p-8 grid sm:grid-cols-2 gap-6">
                    <div>
                        <label class="form-label">Prénom <span class="text-red-500">*</span></label>
                        <input wire:model="student_firstname" type="text" class="form-input" placeholder="Prénom de l'enfant">
                        @error('student_firstname')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label">Nom <span class="text-red-500">*</span></label>
                        <input wire:model="student_lastname" type="text" class="form-input" placeholder="Nom de famille">
                        @error('student_lastname')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label">Date de naissance</label>
                        <input wire:model="student_birth_date" type="date" class="form-input">
                        @error('student_birth_date')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label">Sexe</label>
                        <select wire:model="student_gender" class="form-input">
                            <option value="">— Choisir —</option>
                            <option value="M">Masculin</option>
                            <option value="F">Féminin</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="form-label">Niveau souhaité <span class="text-red-500">*</span></label>
                        <p class="text-xs font-bold text-amber-600 uppercase tracking-wide mt-3 mb-1.5">🌱 Préscolaire</p>
                        <div class="grid grid-cols-3 gap-2 mb-3">
                            @foreach(['PS'=>'🌱','MS'=>'🌿','GS'=>'🌳'] as $code => $icon)
                            <label class="cursor-pointer">
                                <input type="radio" wire:model="niveau_souhaite" value="{{ $code }}" class="sr-only peer">
                                <div class="rounded-xl border-2 border-slate-200 p-2 text-center text-xs font-bold transition-all
                                            peer-checked:border-amber-500 peer-checked:bg-amber-50 peer-checked:text-amber-700
                                            hover:border-amber-300 select-none">
                                    <div class="text-lg">{{ $icon }}</div><div>{{ $code }}</div>
                                </div>
                            </label>
                            @endforeach
                        </div>
                        <p class="text-xs font-bold text-emerald-700 uppercase tracking-wide mb-1.5">📚 Primaire</p>
                        <div class="grid grid-cols-5 gap-2 mb-3">
                            @foreach(['CP'=>'📖','CE1'=>'✏️','CE2'=>'📐','CM1'=>'🔬','CM2'=>'🏆'] as $code => $icon)
                            <label class="cursor-pointer">
                                <input type="radio" wire:model="niveau_souhaite" value="{{ $code }}" class="sr-only peer">
                                <div class="rounded-xl border-2 border-slate-200 p-2 text-center text-xs font-bold transition-all
                                            peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:text-emerald-700
                                            hover:border-emerald-300 select-none">
                                    <div class="text-lg">{{ $icon }}</div><div>{{ $code }}</div>
                                </div>
                            </label>
                            @endforeach
                        </div>
                        <p class="text-xs font-bold text-blue-700 uppercase tracking-wide mb-1.5">🏫 Collège</p>
                        <div class="grid grid-cols-4 gap-2 mb-3">
                            @foreach(['6ème'=>'🔭','5ème'=>'🧪','4ème'=>'📊','3ème'=>'🎯'] as $code => $icon)
                            <label class="cursor-pointer">
                                <input type="radio" wire:model="niveau_souhaite" value="{{ $code }}" class="sr-only peer">
                                <div class="rounded-xl border-2 border-slate-200 p-2 text-center text-xs font-bold transition-all
                                            peer-checked:border-blue-600 peer-checked:bg-blue-50 peer-checked:text-blue-700
                                            hover:border-blue-300 select-none">
                                    <div class="text-lg">{{ $icon }}</div><div>{{ $code }}</div>
                                </div>
                            </label>
                            @endforeach
                        </div>
                        <p class="text-xs font-bold text-violet-700 uppercase tracking-wide mb-1.5">🎓 Lycée</p>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach(['2nde'=>'⚗️','1ère'=>'📝','Tle'=>'🏅'] as $code => $icon)
                            <label class="cursor-pointer">
                                <input type="radio" wire:model="niveau_souhaite" value="{{ $code }}" class="sr-only peer">
                                <div class="rounded-xl border-2 border-slate-200 p-2 text-center text-xs font-bold transition-all
                                            peer-checked:border-violet-600 peer-checked:bg-violet-50 peer-checked:text-violet-700
                                            hover:border-violet-300 select-none">
                                    <div class="text-lg">{{ $icon }}</div><div>{{ $code }}</div>
                                </div>
                            </label>
                            @endforeach
                        </div>
                        @error('niveau_souhaite')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        @if($niveau_souhaite)
                        @php
                        $nivcol = in_array($niveau_souhaite,['PS','MS','GS']) ? ['#fffbeb','#b45309']
                                : (in_array($niveau_souhaite,['CP','CE1','CE2','CM1','CM2']) ? ['#ecfdf5','#065f46']
                                : (in_array($niveau_souhaite,['6ème','5ème','4ème','3ème']) ? ['#eff6ff','#1d4ed8']
                                : ['#f5f3ff','#5b21b6']));
                        @endphp
                        <div class="mt-3 px-4 py-2 rounded-xl text-sm font-medium" style="background:{{ $nivcol[0] }};color:{{ $nivcol[1] }};">
                            ℹ️ {{ \App\Models\PreInscription::niveaux()[$niveau_souhaite] ?? $niveau_souhaite }} — Année scolaire 2026-2027
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ══ BLOC 2 : Documents élève ════════════════════ --}}
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-8 py-4 border-b border-slate-100" style="background:linear-gradient(135deg,#1e3a8a,#0c4a6e);">
                    <h2 class="text-white font-bold flex items-center gap-2">
                        📁 Documents de l'élève
                        <span class="text-white/60 text-xs font-normal ml-1">— obligatoires</span>
                    </h2>
                </div>
                <div class="p-8 space-y-6">

                    {{-- Photo de l'élève --}}
                    <div>
                        <label class="form-label">
                            Photo de l'élève <span class="text-red-500">*</span>
                            <span class="text-slate-400 font-normal text-xs ml-1">(JPG, PNG — max 2 Mo)</span>
                        </label>
                        <div class="flex items-start gap-5 mt-2">
                            {{-- Preview --}}
                            <div class="shrink-0">
                                @if($student_photo)
                                <div class="relative w-24 h-24">
                                    <img src="{{ $student_photo->temporaryUrl() }}"
                                         class="w-24 h-24 rounded-2xl object-cover ring-2 ring-amber-400 shadow-md">
                                    <button type="button" wire:click="$set('student_photo', null)"
                                            class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-red-500 text-white flex items-center justify-center text-xs shadow hover:bg-red-600">✕</button>
                                </div>
                                @else
                                <div class="w-24 h-24 rounded-2xl border-2 border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 bg-slate-50">
                                    <svg class="w-8 h-8 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    <span class="text-[10px] text-center">Photo</span>
                                </div>
                                @endif
                            </div>
                            {{-- Drop zone --}}
                            <label class="flex-1 cursor-pointer">
                                <div class="border-2 border-dashed rounded-2xl p-5 text-center transition-all hover:border-amber-400 hover:bg-amber-50
                                            {{ $student_photo ? 'border-amber-400 bg-amber-50' : 'border-slate-200 bg-slate-50' }}">
                                    <div class="text-2xl mb-1">📷</div>
                                    <p class="text-sm font-semibold text-slate-600">
                                        {{ $student_photo ? 'Changer la photo' : 'Cliquez pour choisir' }}
                                    </p>
                                    <p class="text-xs text-slate-400 mt-0.5">JPG, PNG, GIF — max 2 Mo</p>
                                    <div wire:loading wire:target="student_photo" class="mt-2">
                                        <div class="inline-flex items-center gap-1.5 text-xs text-amber-600">
                                            <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                            </svg>
                                            Chargement...
                                        </div>
                                    </div>
                                </div>
                                <input type="file" wire:model="student_photo" accept="image/*" class="hidden">
                            </label>
                        </div>
                        @error('student_photo')<p class="text-red-500 text-xs mt-1.5">{{ $message }}</p>@enderror
                    </div>

                    {{-- Acte de naissance --}}
                    <div>
                        <label class="form-label">
                            Acte de naissance <span class="text-red-500">*</span>
                            <span class="text-slate-400 font-normal text-xs ml-1">(PDF, JPG, PNG — max 5 Mo)</span>
                        </label>
                        <div class="mt-2">
                            @if($student_birth_certificate)
                            @php $mime = $student_birth_certificate->getMimeType(); $isImg = str_starts_with($mime, 'image/'); @endphp
                            <div class="flex items-center gap-4 p-4 rounded-2xl border border-emerald-200 bg-emerald-50">
                                @if($isImg)
                                <img src="{{ $student_birth_certificate->temporaryUrl() }}"
                                     class="w-16 h-16 rounded-xl object-cover ring-1 ring-emerald-300 shadow">
                                @else
                                <div class="w-16 h-16 rounded-xl bg-red-100 flex items-center justify-center text-2xl shadow">📄</div>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-emerald-800 text-sm truncate">{{ $student_birth_certificate->getClientOriginalName() }}</p>
                                    <p class="text-xs text-emerald-600 mt-0.5">{{ round($student_birth_certificate->getSize() / 1024, 1) }} Ko · {{ strtoupper($student_birth_certificate->getClientOriginalExtension()) }}</p>
                                </div>
                                <button type="button" wire:click="$set('student_birth_certificate', null)"
                                        class="w-8 h-8 rounded-xl bg-red-100 text-red-600 flex items-center justify-center hover:bg-red-200 transition-colors shrink-0">✕</button>
                            </div>
                            @else
                            <label class="cursor-pointer block">
                                <div class="border-2 border-dashed border-slate-200 bg-slate-50 rounded-2xl p-6 text-center hover:border-blue-400 hover:bg-blue-50 transition-all">
                                    <div class="text-3xl mb-2">📋</div>
                                    <p class="text-sm font-semibold text-slate-600">Cliquez pour ajouter l'acte de naissance</p>
                                    <p class="text-xs text-slate-400 mt-0.5">PDF, JPG, PNG — max 5 Mo</p>
                                    <div wire:loading wire:target="student_birth_certificate" class="mt-2">
                                        <div class="inline-flex items-center gap-1.5 text-xs text-blue-600">
                                            <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                            </svg>
                                            Chargement...
                                        </div>
                                    </div>
                                </div>
                                <input type="file" wire:model="student_birth_certificate" accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                            </label>
                            @endif
                        </div>
                        @error('student_birth_certificate')<p class="text-red-500 text-xs mt-1.5">{{ $message }}</p>@enderror
                    </div>

                </div>
            </div>

            {{-- ══ BLOC 3 : Parent ═════════════════════════════ --}}
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-8 py-4 border-b border-slate-100" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                    <h2 class="text-white font-bold flex items-center gap-2">
                        👨‍👩‍👧 Informations du parent / tuteur
                    </h2>
                </div>
                <div class="p-8 grid sm:grid-cols-2 gap-6">
                    <div class="sm:col-span-2">
                        <label class="form-label">Nom complet <span class="text-red-500">*</span></label>
                        <input wire:model="parent_name" type="text" class="form-input" placeholder="Nom et prénom du parent ou tuteur">
                        @error('parent_name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label">Téléphone <span class="text-red-500">*</span></label>
                        <input wire:model="parent_phone" type="tel" class="form-input" placeholder="77 00 00 00">
                        @error('parent_phone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label">Email <span class="text-slate-400 font-normal">(optionnel)</span></label>
                        <input wire:model="parent_email" type="email" class="form-input" placeholder="exemple@email.com">
                        @error('parent_email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="form-label">Message <span class="text-slate-400 font-normal">(optionnel)</span></label>
                        <textarea wire:model="message" rows="3" class="form-input resize-none"
                                  placeholder="Questions, informations complémentaires..."></textarea>
                        @error('message')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            {{-- ══ BLOC 4 : Documents parent ═══════════════════ --}}
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-8 py-4 border-b border-slate-100" style="background:linear-gradient(135deg,#6d28d9,#4c1d95);">
                    <h2 class="text-white font-bold flex items-center gap-2">
                        🗂️ Pièces jointes du parent
                        <span class="text-white/60 text-xs font-normal ml-1">— obligatoires</span>
                    </h2>
                </div>
                <div class="p-8 space-y-4">

                    <div class="flex items-start gap-3 p-4 rounded-2xl bg-amber-50 border border-amber-200">
                        <span class="text-lg shrink-0">📌</span>
                        <p class="text-sm text-amber-800 leading-relaxed">
                            Joignez <strong>au minimum un document d'identité</strong> du parent ou tuteur légal : passeport, carte nationale d'identité, titre de séjour, etc.<br>
                            <span class="text-amber-600 text-xs">Formats acceptés : PDF, JPG, PNG — max 5 Mo par fichier.</span>
                        </p>
                    </div>

                    {{-- Existing files preview --}}
                    @if(count($parent_documents) > 0)
                    <div class="space-y-2">
                        @foreach($parent_documents as $idx => $doc)
                        @php $isImg = str_starts_with($doc->getMimeType(), 'image/'); @endphp
                        <div class="flex items-center gap-3 p-3 rounded-2xl border border-violet-200 bg-violet-50">
                            @if($isImg)
                            <img src="{{ $doc->temporaryUrl() }}" class="w-14 h-14 rounded-xl object-cover ring-1 ring-violet-300 shadow shrink-0">
                            @else
                            <div class="w-14 h-14 rounded-xl bg-red-100 flex items-center justify-center text-2xl shrink-0 shadow">📄</div>
                            @endif
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-violet-800 text-sm truncate">{{ $doc->getClientOriginalName() }}</p>
                                <p class="text-xs text-violet-500 mt-0.5">{{ round($doc->getSize() / 1024, 1) }} Ko · {{ strtoupper($doc->getClientOriginalExtension()) }}</p>
                            </div>
                            <button type="button" wire:click="removeParentDoc({{ $idx }})"
                                    class="w-8 h-8 rounded-xl bg-red-100 text-red-600 flex items-center justify-center hover:bg-red-200 transition-colors shrink-0">✕</button>
                        </div>
                        @endforeach
                    </div>
                    @endif

                    {{-- Add more / first upload --}}
                    <label class="cursor-pointer block">
                        <div class="border-2 border-dashed rounded-2xl p-6 text-center transition-all
                                    {{ count($parent_documents) > 0 ? 'border-violet-300 bg-violet-50 hover:border-violet-500' : 'border-slate-200 bg-slate-50 hover:border-violet-400 hover:bg-violet-50' }}">
                            <div class="text-3xl mb-2">{{ count($parent_documents) > 0 ? '➕' : '🪪' }}</div>
                            <p class="text-sm font-semibold text-slate-600">
                                {{ count($parent_documents) > 0 ? 'Ajouter d\'autres documents' : 'Cliquez pour ajouter vos documents' }}
                            </p>
                            <p class="text-xs text-slate-400 mt-0.5">Passeport, CIN, Titre de séjour... · PDF, JPG, PNG</p>
                            <div wire:loading wire:target="parent_documents" class="mt-2">
                                <div class="inline-flex items-center gap-1.5 text-xs text-violet-600">
                                    <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                    </svg>
                                    Chargement...
                                </div>
                            </div>
                        </div>
                        <input type="file" wire:model="parent_documents" accept=".pdf,.jpg,.jpeg,.png" multiple class="hidden">
                    </label>

                    @error('parent_documents')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    @error('parent_documents.*')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror

                </div>
            </div>

            {{-- ══ SUBMIT ═══════════════════════════════════════ --}}
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-8">
                <div class="flex items-start gap-3 mb-6 p-4 rounded-2xl bg-amber-50 border border-amber-100">
                    <span class="text-xl">📌</span>
                    <p class="text-sm text-amber-800 leading-relaxed">
                        Cette pré-inscription n'est <strong>pas définitive</strong>. Notre équipe vous contactera dans les <strong>48 heures</strong> pour vous guider dans la constitution du dossier complet et planifier un entretien.
                    </p>
                </div>

                {{-- Checklist --}}
                <div class="grid sm:grid-cols-2 gap-2 mb-6">
                    @foreach([
                        ['check'=>$student_firstname && $student_lastname,'label'=>'Informations élève'],
                        ['check'=>!is_null($student_photo),'label'=>'Photo de l\'élève'],
                        ['check'=>!is_null($student_birth_certificate),'label'=>'Acte de naissance'],
                        ['check'=>$parent_name && $parent_phone,'label'=>'Informations parent'],
                        ['check'=>count($parent_documents) > 0,'label'=>'Documents parent'],
                        ['check'=>(bool)$niveau_souhaite,'label'=>'Niveau souhaité'],
                    ] as $item)
                    <div class="flex items-center gap-2 text-sm {{ $item['check'] ? 'text-emerald-700' : 'text-slate-400' }}">
                        <span class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold shrink-0 {{ $item['check'] ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-400' }}">
                            {{ $item['check'] ? '✓' : '○' }}
                        </span>
                        {{ $item['label'] }}
                    </div>
                    @endforeach
                </div>

                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-70 cursor-not-allowed"
                        class="btn-primary w-full flex items-center justify-center gap-3 py-4 rounded-2xl text-lg shadow-xl">
                    <span wire:loading.remove>📝 Envoyer ma pré-inscription</span>
                    <span wire:loading class="flex items-center gap-2">
                        <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        Envoi en cours...
                    </span>
                </button>
            </div>

        </form>
        @endif

        {{-- Contact strip --}}
        <div class="mt-8 bg-white rounded-2xl border border-slate-100 p-6 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-sm">
            <p class="text-slate-500 text-sm">Besoin d'aide ? Contactez-nous directement :</p>
            <div class="flex gap-6">
                <a href="tel:+25377087979" class="flex items-center gap-2 text-amber-600 font-bold hover:text-amber-800 transition-colors">📞 77 08 79 79</a>
                <a href="tel:+25377057878" class="flex items-center gap-2 text-amber-600 font-bold hover:text-amber-800 transition-colors">📞 77 05 78 78</a>
            </div>
        </div>
    </div>
</section>

{{-- FOOTER --}}
<footer style="background:#0f172a;" class="py-6">
    <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-4">
        <p class="text-white/25 text-xs">© {{ date('Y') }} INTEC École — Djibouti</p>
        <a href="{{ route('home') }}" class="text-white/40 hover:text-amber-400 text-xs transition-colors">← Retour à l'accueil</a>
    </div>
</footer>

</div>
