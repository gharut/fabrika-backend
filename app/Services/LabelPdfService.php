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
        private LabelTemplateService $labelTemplateService,
        private PrinterService $printerService,
        private ProductSizeService $sizeService
    ) {}

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

    private function mm2pt(float $mm): float { return $mm * 72 / 25.4; }

    public function generateFromDesignerSchema(LabelPrintOptions $options, int $quantity)
    {
        $label   = $this->labelService->getOne($options->labelId);
        $product = $label->product;
        $userId  = Auth::id();
        $labelTemplateId = $label->label_template_id;

        $duplicateDM = $label->duplicate_chz;
        $includeSHK  = $label->print_single_ean13;
        $duplicateSHK  = $label->print_double_ean13;

        if (empty($labelTemplateId)) {
            throw ValidationException::withMessages([
                'schema' => ['Не указан идентификатор шаблона этикетки.']
            ]);
        }

        $labelTemplate = $this->labelTemplateService->getOne($labelTemplateId);
        if (!$labelTemplate) {
            throw ValidationException::withMessages([
                'schema' => ['Шаблон этикетки не найден.']
            ]);
        }

        if (empty($labelTemplate->content)) {
            throw ValidationException::withMessages([
                'schema' => ['Контент шаблона этикетки пустой.']
            ]);
        }

        $schema = json_decode($labelTemplate->content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !$schema || !is_array($schema)) {
            throw ValidationException::withMessages([
                'schema' => ['Шаблон этикетки повреждён или невалидный JSON.']
            ]);
        }

        $schemas = [$schema];

        if ($includeSHK || $duplicateSHK) {
            $SHKTemplate = $this->labelTemplateService->getOne(1);
            if (!$SHKTemplate) {
                throw ValidationException::withMessages([
                    'schema' => ['Шаблон этикетки не найден.']
                ]);
            }

            if (empty($SHKTemplate->content)) {
                throw ValidationException::withMessages([
                    'schema' => ['Контент шаблона этикетки пустой.']
                ]);
            }

            $SHKSchema = json_decode($SHKTemplate->content, true);

            if (json_last_error() !== JSON_ERROR_NONE || !$SHKSchema || !is_array($SHKSchema)) {
                throw ValidationException::withMessages([
                    'schema' => ['Шаблон этикетки повреждён или невалидный JSON.']
                ]);
            }
            $schemas[] = $SHKSchema;
        }

        $needsDM   = collect($schema['blocks'])->contains(fn($b)=> $b['type']==='datamatrix');
        $needsEAN  = collect($schema['blocks'])->contains(fn($b)=> $b['type']==='barcode');

        $gen2D = new DNS2D();
        $gen1D = new DNS1D();
        $labels = collect();

        if ($needsDM) {
            $CHZLabels = $this->CHZLabelService->getUnused($options->sizeId, $quantity);
            if ($CHZLabels->count() < $quantity) {
                throw ValidationException::withMessages(['CHZ' => ['Недостаточно Честных знаков для печати.']]);
            }

            foreach ($CHZLabels as $item) {
                $this->CHZLabelService->markAsUsed($item->id, $userId);
                $gtin = '';
                $serial = '';

                if (preg_match('/01(\d{14})21([^\x1D]+)/', $item->code, $matches)) {
                    $gtin = $matches[1];
                    $serial = $matches[2];
                }

                $ean = preg_replace('/\D/','', $item->size->barcode ?? '');
                $completeEan = substr($ean, 0, 12);
                $row = [
                    'name'        => $label->name,
                    'article'     => $product->article ?? null,
                    'color'       => $product->color,
                    'brand'       => !empty($product->brand) ? $product->brand->name : null,
                    'size'        => $this->formatSize($item->size, $label->size_display_type),
                    'client'      => $product->client->name ?? null,
                    'composition' => $product->composition ?? null,
                    'barcode'     => $completeEan,
                    'preview'     => false,
                    'gtin'        => $gtin,
                    'userId'      => $userId,
                    'serial'      => $serial,
                    'number'      => $item->number,
                    'duplicateDM' => $duplicateDM,
                    'includeSHK'  => $includeSHK,
                    'duplicateSHK'  => $duplicateSHK,
                ];

                $row['barcode2D'] = $gen2D->getBarcodePNG($item->code, 'DATAMATRIX', 2, 2);
                if (($needsEAN || $includeSHK || $duplicateSHK) && strlen($ean) >= 12) {
                    // $row['barcode1D'] = $gen1D->getBarcodePNG($completeEan, 'EAN13', 1, 35);
                    $row['barcode1D'] = $gen1D->getBarcodeHTML($completeEan, 'EAN13', 1, 35, 'black', true);
                }
                
                $logoPath = public_path('images/cz-logo.png');
                if (is_file($logoPath)) {
                    $row['czLogo'] = base64_encode(file_get_contents($logoPath));
                }

                $labels->push((object)$row);
            }
        } else {
            $size = $this->sizeService->getOne($options->sizeId);
            $ean  = preg_replace('/\D/','', $size->barcode ?? '');

            if ($needsEAN && strlen($ean) < 12) {
                throw ValidationException::withMessages(['barcode' => ['Баркод должен содержать как минимум 12 цифр.']]);
            }

            $completeEan = substr($ean, 0, 12);
            for ($i=0; $i<$quantity; $i++) {
                
                $row = [
                    'name'        => $label->name,
                    'article'     => $product->article ?? null,
                    'brand'       => !empty($product->brand) ? $product->brand->name : null,
                    'client'      => $product->client->name ?? null,
                    'composition' => $product->composition ?? null,
                    'color'       => $product->color ?? null,
                    'size'        => $this->formatSize($size, $label->size_display_type),
                    'barcode'     => $completeEan,
                    'preview'     => false,
                    'duplicateDM' => false,
                    'includeSHK'  => false,
                    'duplicateSHK'  => false,
                ];

                if ($needsEAN) {
                    // $row['barcode1D'] = $gen1D->getBarcodePNG($completeEan, 'EAN13', 1, 35);
                    $row['barcode1D'] = $gen1D->getBarcodeHTML($completeEan, 'EAN13', 1, 35, 'black', true);
                }

                $labels->push((object)$row);
            }
        }

        $w = (float)($schema['page']['w'] ?? 58);
        $h = (float)($schema['page']['h'] ?? 40);

        $pdf = Pdf::loadView('labels.dynamic', [
            'schemas' => $schemas,
            'labels' => $labels,
        ])->setPaper([0, 0, $this->mm2pt($w), $this->mm2pt($h)], 'portrait');

        $printerId = $label->printer_id;
        $totalPrinteredQuantity = $quantity;

        $duplicateDM = $label->duplicate_chz;
        $includeSHK  = $label->print_single_ean13;
        $duplicateSHK  = $label->print_double_ean13;

        if ($labelTemplateId == 2 || $labelTemplateId == 3) {
            if ($duplicateDM) {
                $totalPrinteredQuantity = $totalPrinteredQuantity + $quantity;
            }
            if ($includeSHK) {
                $totalPrinteredQuantity = $totalPrinteredQuantity + $quantity;
            } else if ($duplicateDM) {
                $totalPrinteredQuantity = $totalPrinteredQuantity + ($quantity * 2);
            } 

        }
        $this->decrementPrinterLabels($printerId, $totalPrinteredQuantity);

        return response(
            $pdf->stream('labels.pdf'), 200,
            ['Content-Type'=>'application/pdf', 'Content-Disposition'=>'inline; filename="labels.pdf"']
        );
    }

    private function decrementPrinterLabels(int $printerId, int $qty): void
    {
        if ($printerId <= 0) {
            throw ValidationException::withMessages([
                'printer' => ['Неверный идентификатор принтера.']
            ]);
        }
        
        $printer = Printer::find($printerId);
        
        if (!$printer) {
            throw ValidationException::withMessages([
                'printer' => ['Принтер не найден.']
            ]);
        }
        
        if ($printer->labels_count < $qty) {
            throw ValidationException::withMessages([
                'printer' => ['Недостаточно этикеток в принтере.']
            ]);
        }
        
        $printer->decrement('labels_count', $qty);
    }

    public function previewFromDesignerSchema(LabelPrintOptions $options)
    {
        $label   = $this->labelService->getOne($options->labelId);
        $product = $label->product;

        $labelTemplateId = $label->label_template_id;
        $labelTemplate   = $this->labelTemplateService->getOne($labelTemplateId);

        $schema = json_decode($labelTemplate->content, true);
        if (!$schema || !is_array($schema)) {
            throw ValidationException::withMessages(['schema' => ['Шаблон этикетки повреждён или невалидный JSON.'.$labelTemplate->content]]);
        }
        if (empty($schema['blocks'])) {
            throw ValidationException::withMessages(['schema' => ['В шаблоне нет блоков для печати.']]);
        }

        $needsDM   = collect($schema['blocks'] ?? [])->contains(fn($b)=> in_array(($b['type']??''), ['cz','datamatrix']));
        $needsEAN  = collect($schema['blocks'] ?? [])->contains(fn($b)=> ($b['type']??'') === 'barcode');

        $gen2D = new DNS2D();
        $gen1D = new DNS1D();
        $labels = collect();
        $size = $this->sizeService->getOne($options->sizeId);
        $ean  = preg_replace('/\D/','', $size->barcode ?? '');
        if ($needsEAN && strlen($ean) < 12) {
            throw ValidationException::withMessages(['barcode' => ['Баркод должен содержать как минимум 12 цифр.']]);
        }
        
        $completeEan = substr($ean, 0, 12);
        $row = [
            'name'        => $label->name,
            'article'     => $product->article ?? 'ART123',
            'brand'       => !empty($product->brand) ? $product->brand->name : null,
            'client'      => $product->client->name ?? 'Клиент',
            'composition' => $product->composition ?? 'Состав: хлопок',
            'color'       => $product->color ?? 'Цвет',
            'size'        => $this->formatSize($size, $label->size_display_type),
            'barcode'     => $completeEan,
            'preview'     => true,
            'gtin'        => '0123456789012',
            'serial'      => 'A1B2C3D4E5',
            'number'      => 23,
            'duplicateDM' => false,
            'includeSHK'  => false,
            'duplicateSHK'  => false,
        ];

        if ($needsDM) {
            $row['barcode2D'] = $gen2D->getBarcodePNG('010412345690345123456?gbB&Y=6FNF123456Gewd/m1234564eSlLgd+nrth7Mw1234567', 'DATAMATRIX', 2, 2);
            $row['czLogo'] = base64_encode(file_get_contents(public_path('images/cz-logo.png')));
        }

        if ($needsEAN) {
            // $row['barcode1D'] = $gen1D->getBarcodePNG('123456789012', 'EAN13', 1, 35);
            $row['barcode1D'] = $gen1D->getBarcodeHTML($completeEan, 'EAN13', 1, 35, 'black', true);
        }

        $labels->push((object)$row);

        $w = (float)($schema['page']['w'] ?? 58);
        $h = (float)($schema['page']['h'] ?? 40);

        $pdf = Pdf::loadView('labels.dynamic', [
            'schemas' => [$schema],
            'labels' => $labels,
        ])->setPaper([0, 0, $this->mm2pt($w), $this->mm2pt($h)], 'portrait');

        return response(
            $pdf->stream('preview.pdf'), 200,
            ['Content-Type'=>'application/pdf', 'Content-Disposition'=>'inline; filename="preview.pdf"']
        );
    }
}