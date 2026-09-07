<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport National - {{ $rapport->titre }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; margin: 20px; }
        h1 { color: #1e3a8a; font-size: 18px; border-bottom: 2px solid #1e3a8a; padding-bottom: 5px; }
        h2 { color: #1e40af; font-size: 14px; margin-top: 20px; border-bottom: 1px solid #ddd; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 15px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        th { background-color: #f1f5f9; font-weight: bold; }
        .text-right { text-align: right; }
        .badge { background-color: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-size: 10px; }
    </style>
</head>
<body>

    {{-- Helper Blade local pour formater les devises --}}
    @php
        function renderDevises($data) {
            if (is_numeric($data)) {
                return number_format($data, 2, ',', ' ');
            }
            if (is_array($data)) {
                if (empty($data)) return '0,00';
                $formatted = [];
                foreach ($data as $devise => $montant) {
                    $formatted[] = number_format($montant, 2, ',', ' ') . ' ' . $devise;
                }
                return implode(' | ', $formatted);
            }
            return (string) $data;
        }

        $contenu = $rapport->contenu ?? [];
        $resume = $contenu['resume'] ?? [];
        $financier = $contenu['financier'] ?? [];
        $physique = $contenu['physique'] ?? [];
        $climatique = $contenu['climatique'] ?? [];
    @endphp

    <h1>Rapport National : {{ $rapport->titre }}</h1>
    <p><strong>Année :</strong> {{ $rapport->annee ?? 'Toutes' }} | <strong>Généré le :</strong> {{ date('d/m/Y') }}</p>

    <!-- RESUME -->
    <h2>1. Résumé Exécutif</h2>
    <table>
        <tr>
            <th>Total Projets</th>
            <td>{{ $resume['total_projets'] ?? 0 }}</td>
        </tr>
        <tr>
            <th>Budget Total Approuvé</th>
            <td>{{ renderDevises($resume['budget_total_approuve'] ?? []) }}</td>
        </tr>
        <tr>
            <th>Budget Engagé</th>
            <td>{{ renderDevises($resume['budget_engage'] ?? []) }}</td>
        </tr>
        <tr>
            <th>Budget Décaissé</th>
            <td>{{ renderDevises($resume['budget_decaisse'] ?? []) }}</td>
        </tr>
    </table>

    <!-- REPARTITION PAR SECTEUR -->
    @if(!empty($financier['par_secteur']))
    <h2>2. Répartition par Secteur</h2>
    <table>
        <thead>
            <tr>
                <th>Secteur Climatique</th>
                <th class="text-right">Montant Approuvé</th>
            </tr>
        </thead>
        <tbody>
            @foreach($financier['par_secteur'] as $item)
            <tr>
                <td>{{ $item['secteur'] ?? 'N/A' }}</td>
                <td class="text-right">{{ renderDevises($item['totaux'] ?? []) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- REPARTITION PAR REGION -->
    @if(!empty($financier['par_region']))
    <h2>3. Répartition par Région</h2>
    <table>
        <thead>
            <tr>
                <th>Région</th>
                <th class="text-right">Montant Approuvé</th>
            </tr>
        </thead>
        <tbody>
            @foreach($financier['par_region'] as $item)
            <tr>
                <td>{{ $item['region'] ?? 'N/A' }}</td>
                <td class="text-right">{{ renderDevises($item['totaux'] ?? []) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- INDICATEURS PHYSIQUES & CLIMATIQUES -->
    <h2>4. Indicateurs d'Impact</h2>
    <table>
        <tr>
            <th>Total Bénévoles / Bénéficiaires</th>
            <td>{{ number_format($physique['total_beneficiaires'] ?? 0, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <th>Surfaces Restaurées (ha)</th>
            <td>{{ number_format($physique['surfaces_restaurees'] ?? 0, 2, ',', ' ') }} ha</td>
        </tr>
        <tr>
            <th>CO2 Évité (tCO2eq)</th>
            <td>{{ number_format($climatique['attenuation']['co2_evite'] ?? 0, 2, ',', ' ') }} tCO2eq</td>
        </tr>
    </table>

    <script>
        // Lance automatiquement l'impression si ouvert dans le navigateur
        window.onload = function() { window.print(); }
    </script>
</body>
</html>