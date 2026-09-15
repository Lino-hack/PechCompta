<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Rapport des achats</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1e293b;
        }

        h1 {
            font-size: 20px;
            margin: 0 0 4px;
            color: #0f172a;
        }

        .period {
            margin-bottom: 16px;
            color: #64748b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
        }

        th {
            background: #0ea5e9;
            color: #fff;
        }

        tr:nth-child(even) td {
            background: #f1f5f9;
        }

        .total-row td {
            font-weight: bold;
            background: #e0f2fe;
        }

        .grand-total td {
            font-weight: bold;
            background: #bae6fd;
        }

        .right {
            text-align: right;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            margin: 18px 0 8px;
        }
    </style>
</head>

<body>
    <h1>Rapport des achats de poisson - PêchCompta</h1>
    <div class="period">Période : {{ $from }} → {{ $to }}</div>

    <div class="section-title">Achats</div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Source</th>
                <th>Nom</th>
                <th>Poisson</th>
                <th class="right">Poids (kg)</th>
                <th class="right">Prix (FCFA)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sources as $source)
                @foreach ($source->lignesAchats as $index => $ligne)
                    <tr>
                        @if ($index === 0)
                            <td rowspan="{{ $source->lignesAchats->count() }}">{{ $source->date }}</td>
                            <td rowspan="{{ $source->lignesAchats->count() }}">
                                {{ $source->type === 'pirogue' ? 'Pêcheur' : 'Détaillant' }}
                            </td>
                            <td rowspan="{{ $source->lignesAchats->count() }}">
                                {{ $source->type === 'pirogue' ? ($source->pecheur?->nom ?? '—') : ($source->detaillant?->nom ?? '—') }}
                            </td>
                        @endif
                        <td>{{ $ligne->typePoisson?->nom ?? '—' }}</td>
                        <td class="right">{{ number_format((float) $ligne->poids_kg, 2, ',', ' ') }}</td>
                        <td class="right">{{ number_format((float) $ligne->prix, 0, ',', ' ') }}</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="6">Aucun achat sur la période.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="4">Total achats</td>
                <td class="right">{{ number_format($totaux['poids'], 2, ',', ' ') }}</td>
                <td class="right">{{ number_format($totaux['montant_achats'], 0, ',', ' ') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Charges</div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th class="right">Bacs glace</th>
                <th class="right">Prix / bac</th>
                <th class="right">Glace total</th>
                <th class="right">Transport</th>
                <th class="right">Charges libres</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($charges as $charge)
                <tr>
                    <td>{{ $charge['date'] }}</td>
                    <td class="right">{{ $charge['nb_bagues_glace'] }}</td>
                    <td class="right">{{ number_format($charge['prix_bague_utilise'], 0, ',', ' ') }}</td>
                    <td class="right">{{ number_format($charge['frais_glace'], 0, ',', ' ') }}</td>
                    <td class="right">{{ number_format($charge['transport'], 0, ',', ' ') }}</td>
                    <td class="right">{{ number_format($charge['libres_total'], 0, ',', ' ') }}</td>
                    <td class="right">{{ number_format($charge['total'], 0, ',', ' ') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Aucune charge sur la période.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="6">Total charges</td>
                <td class="right">{{ number_format($totaux['charges_total'], 0, ',', ' ') }}</td>
            </tr>
            <tr class="grand-total">
                <td colspan="6">Total dépenses</td>
                <td class="right">{{ number_format($totaux['total_depenses'], 0, ',', ' ') }}</td>
            </tr>
        </tbody>
    </table>
</body>

</html>