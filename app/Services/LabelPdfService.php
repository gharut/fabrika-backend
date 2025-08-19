<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;
use TCPDF2DBarcode;
use Milon\Barcode\DNS2D;
use Milon\Barcode\DNS1D;
use Illuminate\Support\Str;
use App\Models\LabelPrintOptions;
use App\Enums\SizeDisplayType;
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
        $label = $this->labelService->getOne($options->labelId);
        $templateId = $label->label_template_id;
        
        $includeDM   = true;
        $duplicateDM = $label->duplicate_chz;
        $includeSHK  = $label->print_single_ean13;
        $duplicateSHK  = $label->print_double_ean13;
        $printerId = $label->printer_id;

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

        $product = $label->product;
        $gen2D = new DNS2D();
        $gen1D = new DNS1D();
        $labels = collect();

        switch ($templateId) {
            case 1:
                $includeDM   = false;
                $duplicateDM = false;
                $includeSHK  = true;
                $duplicateSHK  = false;

                $size = $this->sizeService->getOne($options->sizeId);
                for ($i = 0; $i < $quantity; $i++) {
                    $item = new \stdClass();
                    $item->name   = $label->name;
                    $item->article   = $product->article;
                    $item->client  = $product->client->name;
                    $item->composition  = $product->composition;
                    $item->color  = $product->color;
                    $barcode = $size->barcode;
                    $item->size = $this->formatSize($size, $label->size_display_type);
                    
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
                break;
            case 2:
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
                    $item->size = $this->formatSize($item->size, $label->size_display_type);
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

                    $logoPath     = public_path('images/cz-logo.png');
                    $item->czLogo = base64_encode(file_get_contents($logoPath));

                    $labels->push($item);
                }
                break;
        }

        $pdf = Pdf::loadView('pdf.label', [
                'labels' => $labels,
                'templateId' => $templateId,
                'withDM' => $includeDM,
                'dupDM' => $duplicateDM,
                'dupC128' => $duplicateSHK,
                'withC128' => $includeSHK,
            ])
            ->setPaper([0, 0, 58 * 3.78, 40 * 3.78], 'portrait');
        
        $this->updatePrinterLabelsCount($printerId, $quantity, $includeDM, $duplicateDM, $includeSHK, $duplicateSHK);

        return response(
            $pdf->stream('labels.pdf'), 200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="labels.pdf"',
            ]
        );
    }

    private function formatSize($size, SizeDisplayType $type): string
    {
        $tech = $size->tech_size ?? $size->techSize ?? null;
        $rus  = $size->value ?? null;

        return match ($type) {
            SizeDisplayType::RUS  => (string) $rus,
            SizeDisplayType::TECH => (string) $tech,
            SizeDisplayType::BOTH => trim(implode(' / ', array_filter([$tech, $rus]))),
        };
    }

    private function updatePrinterLabelsCount(
        int $printerId,
        int $quantity,
        bool $includeDM,
        bool $duplicateDM,
        bool $includeSHK,
        bool $duplicateSHK
    ): void {
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
    }
}
