<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChargeJournaliere;
use App\Models\SourceAchat;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportController extends Controller
{
    private function period(Request $request): array
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $today = now()->toDateString();

        return [
            'from' => $validated['from'] ?? $today,
            'to' => $validated['to'] ?? $today,
        ];
    }

    public function exportExcel(Request $request)
    {
        ['from' => $from, 'to' => $to] = $this->period($request);
        $data = $this->reportData($from, $to);

        $spreadsheet = new Spreadsheet;

        $this->writeAchatsSheet($spreadsheet->getActiveSheet(), $data['sources'], $data['totaux'], $from, $to);
        $this->writePiroguesSheet($spreadsheet->createSheet(), $data['sources']);
        $this->writeChargesSheet($spreadsheet->createSheet(), $data['charges'], $data['totaux']);
        $this->writeResumeSheet($spreadsheet->createSheet(), $data['totaux'], $from, $to);

        $filename = 'rapport-achats-'.$from.'-'.$to.'.xlsx';
        $writer = new Xlsx($spreadsheet);

        $tempFile = tempnam(sys_get_temp_dir(), 'peche');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->deleteFileAfterSend(true);
    }

    private function writeAchatsSheet($sheet, $sources, array $totaux, string $from, string $to): void
    {
        $sheet->setTitle('Achats');

        $sheet->setCellValue('A1', 'Rapport des achats de poisson');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->setCellValue('A2', 'Période : '.$from.' → '.$to);
        $sheet->getStyle('A2')->getFont()->setColor(new Color('FF64748B'));
        $sheet->getRowDimension(2)->setVisible(true);

        $headers = ['N°', 'Date', 'Source', 'Nom', 'Poisson', 'Poids (kg)', 'Prix (FCFA)'];
        $headerRow = 4;
        foreach ($headers as $col => $header) {
            $column = chr(65 + $col);
            $sheet->setCellValue("{$column}{$headerRow}", $header);
        }
        $this->styleHeader($sheet, "A{$headerRow}:G{$headerRow}");

        $row = $headerRow + 1;
        $index = 1;

        foreach ($sources as $source) {
            $type = $source->type === 'pirogue' ? 'Pêcheur' : 'Détaillant';
            $nom = $source->type === 'pirogue'
                ? ($source->pecheur?->nom ?? '—')
                : ($source->detaillant?->nom ?? '—');

            foreach ($source->lignesAchats as $ligne) {
                $sheet->setCellValue("A{$row}", $index);
                $sheet->setCellValue("B{$row}", $source->date);
                $sheet->setCellValue("C{$row}", $type);
                $sheet->setCellValue("D{$row}", $nom);
                $sheet->setCellValue("E{$row}", $ligne->typePoisson?->nom ?? '—');
                $sheet->setCellValue("F{$row}", (float) $ligne->poids_kg);
                $sheet->setCellValue("G{$row}", (float) $ligne->prix);
                $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('0.00');
                $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('#,##0');
                $row++;
                $index++;
            }
        }

        $sheet->setCellValue("E{$row}", 'Total achats');
        $sheet->setCellValue("F{$row}", $totaux['poids']);
        $sheet->setCellValue("G{$row}", $totaux['montant_achats']);
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('#,##0');
        $this->styleTotal($sheet, "A{$row}:G{$row}");

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function writePiroguesSheet($sheet, $sources): void
    {
        $sheet->setTitle('Pirogues');

        $sheet->setCellValue('A1', 'Liste des pirogues');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->setCellValue('A2', 'Achats par pirogue sur la période');
        $sheet->getStyle('A2')->getFont()->setColor(new Color('FF64748B'));

        $headers = ['N°', 'Date', 'Pirogue', 'Nb lignes', 'Poids (kg)', 'Montant (FCFA)'];
        $headerRow = 4;
        foreach ($headers as $col => $header) {
            $column = chr(65 + $col);
            $sheet->setCellValue("{$column}{$headerRow}", $header);
        }
        $this->styleHeader($sheet, "A{$headerRow}:F{$headerRow}");

        $pirogues = $sources->filter(fn (SourceAchat $source) => $source->type === 'pirogue');

        $row = $headerRow + 1;
        $index = 1;
        foreach ($pirogues as $source) {
            $lignes = $source->lignesAchats;
            $sheet->setCellValue("A{$row}", $index);
            $sheet->setCellValue("B{$row}", $source->date);
            $sheet->setCellValue("C{$row}", $source->pecheur?->nom ?? '—');
            $sheet->setCellValue("D{$row}", $lignes->count());
            $sheet->setCellValue("E{$row}", (float) $lignes->sum('poids_kg'));
            $sheet->setCellValue("F{$row}", (float) $lignes->sum('prix'));
            $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('0.00');
            $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0');
            $row++;
            $index++;
        }

        $nbLignes = $pirogues->flatMap(fn (SourceAchat $source) => $source->lignesAchats)->count();
        $poidsTotal = (float) $pirogues->flatMap(fn (SourceAchat $source) => $source->lignesAchats)->sum('poids_kg');
        $montantTotal = (float) $pirogues->flatMap(fn (SourceAchat $source) => $source->lignesAchats)->sum('prix');

        $sheet->setCellValue("C{$row}", 'Total pirogues');
        $sheet->setCellValue("D{$row}", $nbLignes);
        $sheet->setCellValue("E{$row}", $poidsTotal);
        $sheet->setCellValue("F{$row}", $montantTotal);
        $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0');
        $this->styleTotal($sheet, "A{$row}:F{$row}");

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function writeChargesSheet($sheet, $charges, array $totaux): void
    {
        $sheet->setTitle('Charges');

        $headers = ['Date', 'Bacs glace', 'Prix / bac (FCFA)', 'Glace (FCFA)', 'Transport (FCFA)', 'Charges libres (FCFA)', 'Total (FCFA)'];
        $headerRow = 1;
        foreach ($headers as $col => $header) {
            $column = chr(65 + $col);
            $sheet->setCellValue("{$column}{$headerRow}", $header);
        }
        $this->styleHeader($sheet, "A{$headerRow}:G{$headerRow}");

        $row = $headerRow + 1;
        foreach ($charges as $charge) {
            $sheet->setCellValue("A{$row}", $charge['date']);
            $sheet->setCellValue("B{$row}", $charge['nb_bagues_glace']);
            $sheet->setCellValue("C{$row}", $charge['prix_bague_utilise']);
            $sheet->setCellValue("D{$row}", $charge['frais_glace']);
            $sheet->setCellValue("E{$row}", $charge['transport']);
            $sheet->setCellValue("F{$row}", $charge['libres_total']);
            $sheet->setCellValue("G{$row}", $charge['total']);
            foreach (['C', 'D', 'E', 'F', 'G'] as $column) {
                $sheet->getStyle("{$column}{$row}")->getNumberFormat()->setFormatCode('#,##0');
            }
            $row++;
        }

        $sheet->setCellValue("F{$row}", 'Total charges');
        $sheet->setCellValue("G{$row}", $totaux['charges_total']);
        $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('#,##0');
        $this->styleTotal($sheet, "A{$row}:G{$row}");
        $row++;

        $sheet->setCellValue("E{$row}", 'Total dépenses');
        $sheet->setCellValue("F{$row}", $totaux['total_depenses']);
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0');
        $this->styleTotal($sheet, "A{$row}:G{$row}");

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function writeResumeSheet($sheet, array $totaux, string $from, string $to): void
    {
        $sheet->setTitle('Résumé');

        $sheet->setCellValue('A1', 'Résumé de la période');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->setCellValue('A2', 'Période : '.$from.' → '.$to);
        $sheet->getStyle('A2')->getFont()->setColor(new Color('FF64748B'));

        $rows = [
            'Poids total des achats' => $totaux['poids'].' kg',
            'Montant des achats' => number_format($totaux['montant_achats'], 0, ',', ' ').' FCFA',
            'Total des charges' => number_format($totaux['charges_total'], 0, ',', ' ').' FCFA',
            'Total des dépenses' => number_format($totaux['total_depenses'], 0, ',', ' ').' FCFA',
        ];

        $row = 4;
        foreach ($rows as $label => $value) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }

        foreach (range('A', 'B') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function styleHeader($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF0369A1');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function styleTotal($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0F2FE');
    }

    public function exportPdf(Request $request)
    {
        ['from' => $from, 'to' => $to] = $this->period($request);
        $data = $this->reportData($from, $to);

        $pdf = Pdf::loadView('exports.rapport', [
            'from' => $from,
            'to' => $to,
            'sources' => $data['sources'],
            'charges' => $data['charges'],
            'totaux' => $data['totaux'],
        ]);

        return $pdf->download('rapport-achats-'.$from.'-'.$to.'.pdf');
    }

    private function reportData(string $from, string $to): array
    {
        $sources = SourceAchat::with(['pecheur', 'detaillant', 'lignesAchats.typePoisson'])
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->orderByRaw("CASE WHEN type = 'pirogue' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get();

        $sources = $sources->sortBy(function (SourceAchat $source): array {
            $nom = strtolower($source->type === 'pirogue'
                ? ($source->pecheur?->nom ?? '')
                : ($source->detaillant?->nom ?? ''));

            return [$source->date, $source->type === 'pirogue' ? 0 : 1, $nom];
        })->values();

        $chargesModel = ChargeJournaliere::with('chargesLibres')
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get();

        $charges = $chargesModel->map(function (ChargeJournaliere $charge): array {
            $fraisGlace = round((float) $charge->nb_bagues_glace * (float) $charge->prix_bague_utilise, 2);
            $libres = round((float) $charge->chargesLibres->sum('montant'), 2);
            $total = round($fraisGlace + (float) $charge->transport + $libres, 2);

            return [
                'date' => $charge->date,
                'nb_bagues_glace' => $charge->nb_bagues_glace,
                'prix_bague_utilise' => (float) $charge->prix_bague_utilise,
                'frais_glace' => $fraisGlace,
                'transport' => (float) $charge->transport,
                'libres_total' => $libres,
                'total' => $total,
            ];
        });

        $montantAchats = round((float) $sources->flatMap(fn ($source) => $source->lignesAchats)->sum('prix'), 2);
        $poids = round((float) $sources->flatMap(fn ($source) => $source->lignesAchats)->sum('poids_kg'), 2);
        $chargesTotal = round((float) $charges->sum('total'), 2);

        return [
            'sources' => $sources,
            'charges' => $charges,
            'totaux' => [
                'poids' => $poids,
                'montant_achats' => $montantAchats,
                'charges_total' => $chargesTotal,
                'total_depenses' => round($montantAchats + $chargesTotal, 2),
            ],
        ];
    }
}
