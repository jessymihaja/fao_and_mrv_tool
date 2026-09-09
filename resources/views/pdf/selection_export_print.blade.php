<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport des projets</title>
    <style>
        @page { margin: 13mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.35; color: #263238; margin: 0; }
        h1 { color: #123b55; font-size: 21px; margin: 0 0 4px; }
        h2 { color: #123b55; font-size: 14px; margin: 0 0 8px; }
        h3 { color: #26706d; font-size: 10px; margin: 0 0 6px; text-transform: uppercase; }
        p { margin: 3px 0; }
        .cover { border-bottom: 3px solid #26706d; margin-bottom: 18px; padding-bottom: 12px; }
        .subtitle { color: #60747d; font-size: 10px; }
        .period { background: #eef5f4; border-left: 4px solid #26706d; color: #38545b; margin-top: 10px; padding: 7px 9px; }
        .project { border: 1px solid #d5e0e2; margin: 0 0 16px; page-break-inside: avoid; }
        .project-header { background: #123b55; color: #fff; padding: 9px 11px; }
        .project-header h2 { color: #fff; margin: 0 0 3px; }
        .project-code { color: #b8d8d4; font-size: 8px; }
        .section { border-top: 1px solid #e2e8e9; padding: 10px 11px; }
        .grid { display: table; table-layout: fixed; width: 100%; }
        .grid-item { display: table-cell; padding: 0 10px 5px 0; vertical-align: top; width: 50%; }
        .label { color: #708188; display: block; font-size: 7.5px; text-transform: uppercase; }
        .value { color: #263238; font-size: 9px; font-weight: bold; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #e8f1f0; color: #123b55; font-size: 8px; text-align: left; }
        th, td { border-bottom: 1px solid #dce5e6; padding: 5px 6px; vertical-align: top; }
        td { font-size: 8px; }
        .mini-table { margin-top: 5px; }
        .mini-table th, .mini-table td { padding: 4px 5px; }
        .empty { color: #78888d; font-style: italic; }
        .tag { background: #e6f2ee; color: #26706d; display: inline-block; margin: 1px 3px 1px 0; padding: 2px 5px; }
        .amount { color: #123b55; font-weight: bold; }
        .footer { color: #849398; font-size: 7px; margin-top: 8px; text-align: right; }
    </style>
</head>
<body>
    @php
        $labels = [
            'date_approbation' => 'Date d’approbation', 'date_annonce' => 'Date d’annonce',
            'date_reference' => 'Date de référence', 'type_financement' => 'Type de financement',
            'mode_contribution' => 'Mode de contribution', 'source_financement' => 'Source de financement',
            'budget_approuve' => 'Budget approuvé', 'montant' => 'Montant',
            'valeur_realisee' => 'Valeur réalisée', 'nom' => 'Nom', 'titre' => 'Titre',
            'description' => 'Description', 'statut' => 'Statut',
        ];
        $display = function ($value) {
            if ($value === null || $value === '') return 'Non renseigné';
            if (is_bool($value)) return $value ? 'Oui' : 'Non';
            if (is_array($value)) return implode(', ', array_map(fn ($item) => is_scalar($item) ? (string) $item : '', $value));
            return (string) $value;
        };
        $field = fn ($item, $key) => is_array($item) ? ($item[$key] ?? null) : null;
        $rowsFor = fn ($items) => $items instanceof \Illuminate\Support\Collection ? $items->all() : (is_array($items) ? $items : []);
    @endphp

    <div class="cover">
        <h1>Rapport des projets et financements</h1>
        <div class="subtitle">Synthèse des données sélectionnées</div>
        <div class="period"><strong>Période étudiée :</strong> {{ $date1 ?: 'Depuis le début des données' }} au {{ $date2 ?: 'Aujourd’hui' }}<br><strong>Nombre de financements :</strong> {{ count($rows) }}</div>
    </div>

    @if (empty($rows))
        <p>Aucune donnee ne correspond aux financements selectionnes.</p>
    @else
        @foreach ($rows as $row)
            @php
                $project = $row['projet'] ?? [];
                $financing = $row['financement'] ?? [];
                $activities = $rowsFor($row['activites'] ?? []);
                $components = $rowsFor($row['composantes'] ?? []);
                $indicators = $rowsFor($row['indicateurs'] ?? []);
                $budgets = $row['budgets'] ?? [];
            @endphp
            <div class="project">
                <div class="project-header">
                    <h2>{{ $row['project_title'] ?: 'Projet sans titre' }}</h2>
                    <div class="project-code">Code projet : {{ $row['project_code'] ?: 'Non renseigné' }} | Financement n° {{ $row['financement_id'] }}</div>
                </div>

                @if (in_array('projet', $fields, true))
                    <div class="section">
                        <h3>Présentation du projet</h3>
                        <div class="grid">
                            <div class="grid-item"><span class="label">Statut</span><span class="value">{{ $display($field($project, 'statut')) }}</span></div>
                            <div class="grid-item"><span class="label">Secteur climatique</span><span class="value">{{ $display($field($project, 'secteur_climatique')) }}</span></div>
                        </div>
                        <div class="grid">
                            <div class="grid-item"><span class="label">Région</span><span class="value">{{ $display($field($project, 'region')) }}</span></div>
                            <div class="grid-item"><span class="label">Province</span><span class="value">{{ $display($field($project, 'province')) }}</span></div>
                        </div>
                        @if ($field($project, 'description'))
                            <p><span class="label">Description</span>{{ $field($project, 'description') }}</p>
                        @endif
                    </div>
                @endif

                @if (in_array('financement', $fields, true))
                    <div class="section">
                        <h3>Financement</h3>
                        <div class="grid">
                            <div class="grid-item"><span class="label">Type</span><span class="value">{{ $display($field($financing, 'type_financement')) }}</span></div>
                            <div class="grid-item"><span class="label">Source</span><span class="value">{{ $display($field($financing, 'source_financement')) }}</span></div>
                        </div>
                        <div class="grid">
                            <div class="grid-item"><span class="label">Budget approuvé</span><span class="value amount">{{ $display($field($financing, 'budget_approuve')) }} {{ $display($field($financing, 'devise')) }}</span></div>
                            <div class="grid-item"><span class="label">Catégorie</span><span class="value">{{ $display($field($financing, 'categorie')) }}</span></div>
                        </div>
                    </div>
                @endif

                @if (in_array('composantes', $fields, true) && count($components))
                    <div class="section">
                        <h3>Composantes</h3>
                        @foreach ($components as $component)
                            <span class="tag">{{ $display($field($component, 'nom') ?? $field($component, 'titre') ?? $field($component, 'libelle') ?? 'Composante') }}</span>
                        @endforeach
                    </div>
                @endif

                @if (in_array('activites', $fields, true))
                    <div class="section">
                        <h3>Activités</h3>
                        @if (count($activities))
                            <table class="mini-table">
                                <thead><tr><th>Activité</th><th>Description</th><th>Statut</th></tr></thead>
                                <tbody>
                                    @foreach ($activities as $activity)
                                        <tr><td>{{ $display($field($activity, 'nom') ?? $field($activity, 'titre')) }}</td><td>{{ $display($field($activity, 'description')) }}</td><td>{{ $display($field($activity, 'statut')) }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <p class="empty">Aucune activité renseignée.</p>
                        @endif
                    </div>
                @endif

                @if (in_array('indicateurs', $fields, true))
                    <div class="section">
                        <h3>Indicateurs et résultats</h3>
                        @if (count($indicators))
                            <table class="mini-table">
                                <thead><tr><th>Indicateur</th><th>Valeur réalisée</th><th>Unité</th><th>Date de référence</th></tr></thead>
                                <tbody>
                                    @foreach ($indicators as $indicator)
                                        <tr><td>{{ $display($field($indicator, 'nom') ?? $field($indicator, 'titre')) }}</td><td>{{ $display($field($indicator, 'valeur_realisee')) }}</td><td>{{ $display($field($indicator, 'unite')) }}</td><td>{{ $display($field($indicator, 'date_reference')) }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <p class="empty">Aucun indicateur renseigné.</p>
                        @endif
                    </div>
                @endif

                @if (in_array('budgets', $fields, true))
                    <div class="section">
                        <h3>Suivi budgétaire</h3>
                        <table class="mini-table">
                            <thead><tr><th>Type</th><th>Montant</th><th>Date</th><th>Statut</th></tr></thead>
                            <tbody>
                                @foreach (['pledges' => 'Promesses de financement', 'approbations' => 'Approbations'] as $key => $title)
                                    @foreach ($rowsFor($budgets[$key] ?? []) as $budget)
                                        <tr><td>{{ $title }}</td><td class="amount">{{ $display($field($budget, 'montant') ?? $field($budget, 'budget_approuve')) }} {{ $display($field($budget, 'devise')) }}</td><td>{{ $display($field($budget, 'date_annonce') ?? $field($budget, 'date_approbation')) }}</td><td>{{ $display($field($budget, 'statut')) }}</td></tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @foreach (['engagements' => 'Engagements', 'decaissements' => 'Décaissements', 'depenses' => 'Dépenses'] as $block => $title)
                    @if (in_array($block, $fields, true))
                        @php $items = $rowsFor($row[$block] ?? []); @endphp
                        <div class="section">
                            <h3>{{ $title }}</h3>
                            @if (count($items))
                                <table class="mini-table">
                                    <thead><tr><th>Date</th><th>Montant</th><th>Objet / description</th><th>Statut</th></tr></thead>
                                    <tbody>
                                        @foreach ($items as $item)
                                            <tr><td>{{ $display($field($item, 'date')) }}</td><td class="amount">{{ $display($field($item, 'montant')) }} {{ $display($field($item, 'devise')) }}</td><td>{{ $display($field($item, 'objet') ?? $field($item, 'description')) }}</td><td>{{ $display($field($item, 'statut')) }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <p class="empty">Aucune donnée sur la période.</p>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        @endforeach
    @endif
    <div class="footer">Document généré automatiquement le {{ now()->format('d/m/Y à H:i') }}</div>
</body>
</html>
