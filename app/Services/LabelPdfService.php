<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;
use TCPDF2DBarcode;
use Milon\Barcode\DNS2D;
use Milon\Barcode\DNS1D;
use Illuminate\Support\Str;
use App\Models\LabelPrintOptions;
use App\Models\Printer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LabelPdfService
{
    public function __construct(
        private ChestnyZnakLabelService $CHZLabelService,
        private LabelService $labelService,
        private PrinterService $printerService,
        private ProductSizeService $sizeService
    ) {}

    public function generateFromHtml(LabelPrintOptions $options, int $quantity): Response
    {
        $includeDM   = $options->includeDM;
        $duplicateDM = $options->duplicateDM;
        $includeSHK  = $options->includeSHK;
        $duplicateSHK  = $options->duplicateSHK;
        $printerId = $options->printerId;

        $userId = Auth::id();

        if (! $includeDM && ! $includeSHK) {
            throw ValidationException::withMessages([
                'format' => ['Необходимо выбрать хотя бы один формат: DataMatrix или штрихкод EAN-13.'],
            ]);
        }

        if ($duplicateDM && ! $includeDM) {
            throw ValidationException::withMessages([
                'duplicateDM' => ['Нельзя дублировать DataMatrix, пока генерация DataMatrix отключена.'],
            ]);
        }

        $label = $this->labelService->getOne($options->labelId);
        $product = $label->product;
        $gen2D = new DNS2D();
        $gen1D = new DNS1D();
        $labels = collect();

        if ($includeDM) {
            $CHZLabels = $this->CHZLabelService->getUnused($options->sizeId, $quantity);
            if ($CHZLabels->count() < $quantity) {
                throw ValidationException::withMessages([
                    'CHZ' => ['Недостаточно Честных знаков для печати.'],
                ]);
            }

            foreach ($CHZLabels as $item) {
                $this->CHZLabelService->markAsUsed($item->id, $userId);
                $barcode = $item->size->barcode;
                $item->name = $label->name;
                $item->color = $product->color;
                $item->size = $item->size->value;
                $item->packerId = $userId;
                $item->id = $item->id;

                $code = $item->code;
                $item->gtin14 = substr($code, 0, 16);
                $item->serialNumber = substr($code, 16, 15);

                $item->barcode2D = $gen2D->getBarcodePNG($code, 'DATAMATRIX', 2, 2);
                if ($includeSHK || $duplicateSHK) {
                    $eanSource = preg_replace('/\D/', '', $barcode);

                    if (strlen($eanSource) < 12) {
                        throw ValidationException::withMessages([
                            'barcode' => ["Баркод должен содержать как минимум 12 цифр."],
                        ]);
                    }

                    $item->barcode1D = $gen1D->getBarcodePNG(substr($eanSource, 0, 12), 'EAN13', 1, 35);
                    $item->article = $product->article;
                    $item->client = $product->client->name;
                    $item->composition = $product->composition;
                }

                // логотип
                $logoPath     = public_path('images/cz-logo.png');
                $item->czLogo = base64_encode(file_get_contents($logoPath));

                $labels->push($item);
            }
        }
        elseif ($includeSHK) {
            $size = $this->sizeService->getOne($options->sizeId);
            for ($i = 0; $i < $quantity; $i++) {
                $item = new \stdClass();
                $item->name   = $label->name;
                $item->article   = $product->article;
                $item->client  = $product->client->name;
                $item->composition  = $product->composition;
                $item->color  = $product->color;
                $barcode = $size->barcode;
                $item->size = $size->value;
                
                if (strlen($barcode) < 12) {
                    throw ValidationException::withMessages([
                        'barcode' => ["Баркод должен содержать как минимум 12 цифр."],
                    ]);
                }

                // штрихкод
                $item->barcode1D = $gen1D->getBarcodePNG(substr($barcode, 0, 12), 'EAN13', 1, 35);

                // логотип (если нужен)
                $logoPath     = public_path('images/cz-logo.png');
                $item->czLogo = base64_encode(file_get_contents($logoPath));

                $labels->push($item);
            }
        }

        $pdf = Pdf::loadView('pdf.label', [
                'labels' => $labels,
                'withDM' => $includeDM,
                'dupDM' => $duplicateDM,
                'dupC128' => $duplicateSHK,
                'withC128' => $includeSHK,
            ])
            ->setPaper([0, 0, 58 * 3.78, 40 * 3.78], 'portrait');
        
        $totalQuantity = $quantity;
        if ($includeDM) {
            if ($duplicateDM) $totalQuantity += $quantity;
            if ($includeSHK) $totalQuantity += $quantity;
            if ($duplicateSHK) $totalQuantity += $quantity * 2;
        }

        $printer = Printer::findOrFail($printerId);
        if ($printer->labels_count < $totalQuantity) {
            throw ValidationException::withMessages([
                'printer' => ['Недостаточно этикеток в принтере.'],
            ]);
        }
        $printer->decrement('labels_count', $totalQuantity);

        return response(
            $pdf->stream('labels.pdf'),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="labels.pdf"',
            ]
        );
    }
}
