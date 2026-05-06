<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carnet d'Évaluation — INTEC École</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/in tech.jpg') }}">
    <link rel="shortcut icon" type="image/jpeg" href="{{ asset('images/in tech.jpg') }}">
    @livewireStyles
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        @page { size: A4; margin: 0; }
        body { margin: 0; padding: 0; background: #fff; }
        @media print {
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
        }
        img { display: block; }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    {{ $slot }}
    @livewireScripts
</body>
</html>
