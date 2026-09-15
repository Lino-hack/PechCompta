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

    <div class="section-title">1. Achats pirogues</div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Pêcheur</th>
                <th>Poisson</th>
                <th class="right">Poids (kg)</th>
                <th class="right">Prix (FCFA)</th>
            </tr>
        </thead>
        <tbody>
            @php
                $pirogues = $sources->filter(fn ($source) => $source->type === 'pirogue');
                $poidsPirogues = 0.0;
                $montantPirogues = 0.0;
            @endphp
            @forelse ($pirogues as $source)
                @foreach ($source->lignesAchats as $index => $ligne)
                    <tr>
                        @if ($index === 0)
                            <td rowspan="{{ $source->lignesAchats->count() }}">{{ $source->date }}</td>
                            <td rowspan="{{ $source->lignesAchats->count() }}">{{ $source->pecheur?->nom ?? '—' }}</td>
                        @endif
                        <td>{{ $ligne->typePoisson?->nom ?? '—' }}</td>
                        <td class="right">{{ number_format((float) $ligne->poids_kg, 2, ',', ' ') }}</td>
                        <td class="right">{{ number_format((float) $ligne->prix, 0, ',', ' ') }}</td>
                    </tr>
                    @php
                        $poidsPirogues += (float) $ligne->poids_kg;
                        $montantPirogues += (float) $ligne->prix;
                    @endphp
                @endforeach
            @empty
                <tr>
                    <td colspan="5">Aucun achat pirogue sur la période.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="3">Total pirogues</td>
                <td class="right">{{ number_format($poidsPirogues, 2, ',', ' ') }}</td>
                <td class="right">{{ number_format($montantPirogues, 0, ',', ' ') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">2. Achats détaillants</div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Détaillant</th>
                <th>Poisson</th>
                <th class="right">Poids (kg)</th>
                <th class="right">Prix (FCFA)</th>
            </tr>
        </thead>
        <tbody>
            @php
                $detaillants = $sources->filter(fn ($source) => $source->type === 'detaillant');
                $poidsDetaillants = 0.0;
                $montantDetaillants = 0.0;
            @endphp
            @forelse ($detaillants as $source)
                @foreach ($source->lignesAchats as $index => $ligne)
                    <tr>
                        @if ($index === 0)
                            <td rowspan="{{ $source->lignesAchats->count() }}">{{ $source->date }}</td>
                            <td rowspan="{{ $source->lignesAchats->count() }}">{{ $source->detaillant?->nom ?? '—' }}</td>
                        @endif
                        <td>{{ $ligne->typePoisson?->nom ?? '—' }}</td>
                        <td class="right">{{ number_format((float) $ligne->poids_kg, 2, ',', ' ') }}</td>
                        <td class="right">{{ number_format((float) $ligne->prix, 0, ',', ' ') }}</td>
                    </tr>
                    @php
                        $poidsDetaillants += (float) $ligne->poids_kg;
                        $montantDetaillants += (float) $ligne->prix;
                    @endphp
                @endforeach
            @empty
                <tr>
                    <td colspan="5">Aucun achat détaillant sur la période.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="3">Total détaillants</td>
                <td class="right">{{ number_format($poidsDetaillants, 2, ',', ' ') }}</td>
                <td class="right">{{ number_format($montantDetaillants, 0, ',', ' ') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">3. Charges journalières</div>
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
                <td colspan="6">Total charges journalières</td>
                <td class="right">{{ number_format($totaux['charges_total'], 0, ',', ' ') }}</td>
            </tr>
        </tbody>
    </table>

    @if ($include_cycles)
        <div class="section-title">4. Cycles camion</div>
        <table>
            <thead>
                <tr>
                    <th>Début</th>
                    <th>Fin</th>
                    <th class="right">Frais de route</th>
                    <th class="right">Autres frais</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cycles as $cycle)
                    <tr>
                        <td>{{ $cycle['date_debut'] }}</td>
                        <td>{{ $cycle['date_fin'] }}</td>
                        <td class="right">{{ number_format($cycle['frais_route'], 0, ',', ' ') }}</td>
                        <td class="right">{{ number_format($cycle['libres'], 0, ',', ' ') }}</td>
                        <td class="right">{{ number_format($cycle['total'], 0, ',', ' ') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">Aucun cycle camion sur la période.</td>
                    </tr>
                @endforelse
                <tr class="total-row">
                    <td colspan="4">Total frais des cycles camion</td>
                    <td class="right">{{ number_format($totaux['cycles_total'], 0, ',', ' ') }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <div class="section-title">5. Résumé</div>
    <table>
        <tbody>
            <tr>
                <td>Poids total des achats</td>
                <td class="right">{{ number_format($totaux['poids'], 2, ',', ' ') }} kg</td>
            </tr>
            <tr>
                <td>Montant des achats</td>
                <td class="right">{{ number_format($totaux['montant_achats'], 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td>Total des charges journalières</td>
                <td class="right">{{ number_format($totaux['charges_total'], 0, ',', ' ') }} FCFA</td>
            </tr>
            @if ($include_cycles)
                <tr>
                    <td>Total frais des cycles camion</td>
                    <td class="right">{{ number_format($totaux['cycles_total'], 0, ',', ' ') }} FCFA</td>
                </tr>
            @endif
            <tr class="grand-total">
                <td>Total des dépenses</td>
                <td class="right">{{ number_format($totaux['total_depenses'], 0, ',', ' ') }} FCFA</td>
            </tr>
        </tbody>
    </table>
</body>

</html>