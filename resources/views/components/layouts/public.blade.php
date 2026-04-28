<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'INTEC École — École internationale à Djibouti' }}</title>
    <meta name="description" content="INTEC École, école internationale pour les langues et les technologies à Djibouti. De la Petite Section au Lycée. Pré-inscription 2026-2027 ouverte.">
    <link rel="icon" type="image/jpeg" href="{{ asset('images/in tech.jpg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        :root {
            --navy:   #0f172a;
            --navy2:  #1e3a8a;
            --teal:   #0c4a6e;
            --amber:  #f59e0b;
            --amber2: #fbbf24;
            --sky:    #60a5fa;
            --white:  #ffffff;
            --slate:  #f8fafc;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; background:#fff; color:#1e293b; }

        /* ── Keyframes ────────────────────────────────── */
        @keyframes float {
            0%,100% { transform: translateY(0) rotate(0deg); }
            50%      { transform: translateY(-20px) rotate(2deg); }
        }
        @keyframes float-b {
            0%,100% { transform: translateY(0) rotate(0deg); }
            50%      { transform: translateY(-12px) rotate(-3deg); }
        }
        @keyframes pulse-glow {
            0%,100% { box-shadow: 0 0 20px rgba(245,158,11,.3), 0 0 40px rgba(245,158,11,.1); }
            50%      { box-shadow: 0 0 40px rgba(245,158,11,.6), 0 0 80px rgba(245,158,11,.2); }
        }
        @keyframes pulse-glow-border {
            0%,100% { border-color: rgba(245,158,11,.4); }
            50%      { border-color: rgba(245,158,11,.9); }
        }
        @keyframes gradient-shift {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        @keyframes shimmer {
            0%   { background-position: -200% 0; }
            100% { background-position:  200% 0; }
        }
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(40px); }
            to   { opacity:1; transform:translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity:0; }
            to   { opacity:1; }
        }
        @keyframes slideLeft {
            from { opacity:0; transform:translateX(-50px); }
            to   { opacity:1; transform:translateX(0); }
        }
        @keyframes slideRight {
            from { opacity:0; transform:translateX(50px); }
            to   { opacity:1; transform:translateX(0); }
        }
        @keyframes spin-slow { to { transform: rotate(360deg); } }
        @keyframes ping-ring {
            75%,100% { transform:scale(1.8); opacity:0; }
        }

        /* ── Animation classes ────────────────────────── */
        .animate-float     { animation: float    5s ease-in-out infinite; }
        .animate-float-b   { animation: float-b  7s ease-in-out infinite; }
        .animate-pulse-glow{ animation: pulse-glow 2.5s ease-in-out infinite; }
        .animate-gradient  { animation: gradient-shift 6s ease infinite; background-size:200% 200%; }
        .animate-spin-slow { animation: spin-slow 25s linear infinite; }
        .animate-fade-up   { animation: fadeUp   .8s ease both; }
        .animate-fade-in   { animation: fadeIn   .6s ease both; }
        .animate-slide-left { animation: slideLeft .8s ease both; }
        .animate-slide-right{ animation: slideRight .8s ease both; }

        .delay-100 { animation-delay:.1s; }
        .delay-200 { animation-delay:.2s; }
        .delay-300 { animation-delay:.3s; }
        .delay-400 { animation-delay:.4s; }
        .delay-500 { animation-delay:.5s; }
        .delay-600 { animation-delay:.6s; }
        .delay-700 { animation-delay:.7s; }

        /* ── Scroll reveal ────────────────────────────── */
        .reveal       { opacity:0; transform:translateY(30px);  transition:opacity .7s ease, transform .7s ease; }
        .reveal.visible { opacity:1; transform:translateY(0); }
        .reveal-left  { opacity:0; transform:translateX(-40px); transition:opacity .7s ease, transform .7s ease; }
        .reveal-left.visible  { opacity:1; transform:translateX(0); }
        .reveal-right { opacity:0; transform:translateX(40px);  transition:opacity .7s ease, transform .7s ease; }
        .reveal-right.visible { opacity:1; transform:translateX(0); }

        /* ── Text gradient ────────────────────────────── */
        .text-gradient {
            background: linear-gradient(90deg, var(--sky) 0%, var(--amber2) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .text-gradient-green {
            background: linear-gradient(90deg, #4ade80 0%, #86efac 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* ── Hero ─────────────────────────────────────── */
        .hero-gradient {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 40%, #0c4a6e 70%, #0f172a 100%);
        }
        .hero-pattern {
            background-image: radial-gradient(circle, rgba(255,255,255,.04) 1px, transparent 1px);
            background-size: 28px 28px;
        }

        /* ── Glass ────────────────────────────────────── */
        .glass {
            background: rgba(255,255,255,.08);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255,255,255,.12);
        }
        .glass-dark {
            background: rgba(0,0,0,.25);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255,255,255,.08);
        }

        /* ── Navbar ───────────────────────────────────── */
        #main-nav {
            background: rgba(15,23,42,.2);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,.06);
            transition: all .3s ease;
        }
        #main-nav.nav-scrolled {
            background: rgba(15,23,42,.97) !important;
            border-bottom-color: rgba(255,255,255,.1);
            box-shadow: 0 4px 30px rgba(0,0,0,.4);
        }
        .nav-link {
            position: relative;
            color: rgba(255,255,255,.8);
            font-weight: 500;
            font-size: 15px;
            transition: color .2s;
            padding: 4px 0;
        }
        .nav-link::after {
            content:''; position:absolute; bottom:-2px; left:0;
            width:0; height:2px;
            background: linear-gradient(90deg, var(--sky), var(--amber));
            transition: width .25s ease;
            border-radius: 2px;
        }
        .nav-link:hover { color:#fff; }
        .nav-link:hover::after { width:100%; }

        /* ── Buttons ──────────────────────────────────── */
        .btn-primary {
            background: linear-gradient(135deg, var(--amber) 0%, #d97706 100%);
            color: #fff;
            font-weight: 700;
            border-radius: 12px;
            padding: 14px 32px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            animation: pulse-glow 2.5s ease-in-out infinite;
            transition: transform .2s, opacity .2s;
        }
        .btn-primary:hover { transform: translateY(-2px); opacity: .95; }

        .btn-outline {
            background: rgba(255,255,255,.08);
            color: #fff;
            font-weight: 600;
            border-radius: 12px;
            padding: 14px 32px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255,255,255,.2);
            backdrop-filter: blur(8px);
            transition: all .2s;
            animation: pulse-glow-border 2.5s ease-in-out infinite;
        }
        .btn-outline:hover { background: rgba(255,255,255,.15); }

        /* ── Program cards ────────────────────────────── */
        .card-3d {
            transform-style: preserve-3d;
            transition: transform .35s ease, box-shadow .35s ease;
        }
        .card-3d:hover {
            transform: translateY(-10px) rotateX(2deg) rotateY(2deg);
            box-shadow: 0 30px 60px rgba(0,0,0,.2);
        }

        /* ── Stats glow ───────────────────────────────── */
        .stat-number {
            background: linear-gradient(90deg, var(--sky), var(--amber2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            filter: drop-shadow(0 0 12px rgba(245,158,11,.4));
        }

        /* ── Section divider ──────────────────────────── */
        .section-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 20px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            background: rgba(245,158,11,.12);
            color: var(--amber);
            border: 1px solid rgba(245,158,11,.3);
            margin-bottom: 16px;
        }

        /* ── Gallery ──────────────────────────────────── */
        .gallery-item { overflow:hidden; border-radius:16px; position:relative; cursor:pointer; }
        .gallery-item .gallery-inner { transition:transform .5s ease; }
        .gallery-item:hover .gallery-inner { transform:scale(1.08); }
        .gallery-overlay {
            position:absolute; inset:0;
            background:linear-gradient(to top, rgba(15,23,42,.8) 0%, transparent 60%);
            opacity:0; transition:opacity .3s ease;
            display:flex; align-items:flex-end; padding:16px;
        }
        .gallery-item:hover .gallery-overlay { opacity:1; }

        /* ── Quote cards ──────────────────────────────── */
        .quote-card {
            background: #fff;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 4px 20px rgba(0,0,0,.06);
            border-top: 3px solid;
            border-image: linear-gradient(90deg, var(--sky), var(--amber)) 1;
            transition: transform .3s, box-shadow .3s;
        }
        .quote-card:hover { transform:translateY(-6px); box-shadow:0 20px 50px rgba(0,0,0,.1); }

        /* ── Form ─────────────────────────────────────── */
        .form-input {
            width:100%; padding:12px 16px;
            border:2px solid #e2e8f0; border-radius:10px;
            font-size:15px; transition:border-color .2s, box-shadow .2s; outline:none;
        }
        .form-input:focus {
            border-color: var(--amber);
            box-shadow: 0 0 0 3px rgba(245,158,11,.15);
        }
        .form-label { display:block; font-weight:600; font-size:14px; color:#374151; margin-bottom:6px; }

        /* ── Shimmer button (pré-inscription page) ────── */
        .btn-shimmer {
            background: linear-gradient(90deg, #f59e0b 0%, #fbbf24 25%, #f59e0b 50%, #f59e0b 100%);
            background-size: 200% auto;
            animation: shimmer 2.5s linear infinite;
        }

        /* ── Niveau chips ─────────────────────────────── */
        .niveau-chip {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 4px 10px; border-radius: 999px;
            font-size: 11px; font-weight: 700; letter-spacing: .03em;
        }

        /* ── Navbar scrolled for preinscription page ──── */
        .navbar-scrolled {
            background: rgba(15,23,42,.97) !important;
            box-shadow: 0 4px 30px rgba(0,0,0,.4);
        }
    </style>
</head>
<body class="bg-white text-slate-800 antialiased">

{{ $slot }}

@livewireScripts
<script>
// Scroll reveal
const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            e.target.classList.add('visible');
            revealObserver.unobserve(e.target);
        }
    });
}, { threshold: 0.1 });
document.querySelectorAll('.reveal,.reveal-left,.reveal-right').forEach(el => revealObserver.observe(el));

// Navbar scroll
const nav = document.getElementById('main-nav');
if (nav) {
    window.addEventListener('scroll', () => {
        nav.classList.toggle('nav-scrolled', window.scrollY > 60);
    }, { passive: true });
}

// Counter animation
function animateCounter(el) {
    const target = parseInt(el.dataset.target) || 0;
    const suffix = el.dataset.suffix || '';
    const duration = 2000;
    const steps = 60;
    const increment = target / steps;
    let current = 0;
    let step = 0;
    const timer = setInterval(() => {
        step++;
        current = Math.min(Math.round(increment * step), target);
        el.textContent = current + suffix;
        if (step >= steps) clearInterval(timer);
    }, duration / steps);
}
const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            animateCounter(e.target);
            counterObserver.unobserve(e.target);
        }
    });
}, { threshold: 0.5 });
document.querySelectorAll('[data-target]').forEach(el => counterObserver.observe(el));

// Re-run on Livewire navigate
document.addEventListener('livewire:navigated', () => {
    document.querySelectorAll('.reveal,.reveal-left,.reveal-right').forEach(el => revealObserver.observe(el));
    document.querySelectorAll('[data-target]').forEach(el => counterObserver.observe(el));
});
</script>
</body>
</html>
