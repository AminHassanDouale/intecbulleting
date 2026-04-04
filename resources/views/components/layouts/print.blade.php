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
        @media print {
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
        }
        @page { size: A4; margin: 1cm; }
        body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }

        /* Logo styling for print */
        .logo-container {
            width: 96px;
            height: 96px;
            border: 2px solid black;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f3f4f6;
            overflow: hidden;
            flex-shrink: 0;
        }

        .logo-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 4px;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    {{ $slot }}
    @livewireScripts
</body>
</html>
