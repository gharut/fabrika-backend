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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

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
                'error' => ['Недостаточно этикеток в принтере.'],
            ]);
        }
        $printer->decrement('labels_count', $totalQuantity);
    }

    private function mm2pt(float $mm): float { return $mm * 72 / 25.4; }

    public function generateFromDesignerSchema(LabelPrintOptions $options, int $quantity)
    {
        ini_set('memory_limit', '512M');
        $label = $this->labelService->getOne($options->labelId);

        if (!$label) {
            throw ValidationException::withMessages([
                'error' => ["Этикетка по Id {$options->labelId} не найдена."]
            ]);
        }

        $printerId = $label->printer_id;
        $labelTemplateId = $label->label_template_id;
        $duplicateDM = $label->duplicate_chz;
        $includeSHK  = $label->print_single_ean13;
        $duplicateSHK  = $label->print_double_ean13;

        $totalPrinteredQuantity = $quantity;

        if ($labelTemplateId == 2 || $labelTemplateId == 3 || $labelTemplateId == 4) {
            if ($duplicateDM) {
                $totalPrinteredQuantity = $totalPrinteredQuantity + $quantity;
            }
            if ($includeSHK) {
                $totalPrinteredQuantity = $totalPrinteredQuantity + $quantity;
            } else if ($duplicateDM) {
                $totalPrinteredQuantity = $totalPrinteredQuantity + ($quantity * 2);
            }
        }

        if ($totalPrinteredQuantity > 500 || $totalPrinteredQuantity <= 0) {
            throw ValidationException::withMessages([
                'error' => [
                    "Недопустимое количество для печати. С учетом указаных опций получается: {$totalPrinteredQuantity} шт. Максимально допустимое: 500 шт."
                ]
            ]);
        }

        return DB::transaction(function () use (
            $options, 
            $quantity, 
            $label, 
            $labelTemplateId, 
            $includeSHK, 
            $duplicateSHK, 
            $duplicateDM, 
            $printerId, 
            $totalPrinteredQuantity
        ) {
            $product = $label->product;
            $userId  = Auth::id();
            $shkTemplateId = 1;
            
            if (empty($labelTemplateId)) {
                throw ValidationException::withMessages([
                    'error' => ['Не указан идентификатор шаблона этикетки.']
                ]);
            }

            $schema = $this->validateAndGetSchema($labelTemplateId);
            $schemas = [$schema];

            if ($includeSHK || $duplicateSHK) {
                $SHKSchema = $this->validateAndGetSchema($shkTemplateId);
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
                    throw ValidationException::withMessages(['error' => ['Недостаточно Честных знаков для печати.']]);
                }

                foreach ($CHZLabels as $item) {
                    $this->CHZLabelService->markAsUsed($item->id, $userId);
                    $gtin = '';
                    $serial = '';

                    if (preg_match('/[^0-9]*01(\d{14})21([^\x1D]+)/', $item->code, $matches)) {
                        $gtin = $matches[1];
                        $serial = $matches[2];
                    }

                    $ean = preg_replace('/\D/','', $item->size->barcode ?? '');
                    $completeEan = substr($ean, 0, 12);
                    $row = [
                        'name'        => $label->name,
                        'country'          => $label->country ?? null,
                        'manufacturer'     => $label->manufacturer ?? null,
                        'manufactureDate'  => $this->formatDate($label->manufacture_date),
                        'article'     => $product->article ?? null,
                        'color'       => $product->color,
                        'brand'       => !empty($product->brand) ? $product->brand->name : null,
                        'size'        => $this->formatSize($item->size, $label->size_display_type),
                        'client'      => $product->client->short_name ?? null,
                        'shortAddress'     => $product->client->short_address ?? null,
                        'shortAddress'     => $product->client->short_address ?? null,
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
                    
                    $labels->push((object)$row);
                }
            } else {
                $size = $this->sizeService->getOne($options->sizeId);
                $ean  = preg_replace('/\D/','', $size->barcode ?? '');

                if ($needsEAN && strlen($ean) < 12) {
                    throw ValidationException::withMessages(['error' => ['Баркод должен содержать как минимум 12 цифр.']]);
                }

                $completeEan = substr($ean, 0, 12);
                for ($i=0; $i<$quantity; $i++) {
                    
                    $row = [
                        'name'        => $label->name,
                        'country'          => $label->country ?? null,
                        'manufacturer'     => $label->manufacturer ?? null,
                        'manufactureDate' => $this->formatDate($label->manufacture_date),
                        'article'     => $product->article ?? null,
                        'brand'       => !empty($product->brand) ? $product->brand->name : null,
                        'client'      => $product->client->short_name ?? null,
                        'shortAddress'     => $product->client->short_address ?? null,
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
                'labels' => $labels,
                'schemas' => $schemas,
            ])->setPaper([0, 0, $this->mm2pt($w), $this->mm2pt($h)], 'portrait');

            $this->decrementPrinterLabels($printerId, $totalPrinteredQuantity);

            return response(
                $pdf->stream('labels.pdf'), 200,
                ['Content-Type'=>'application/pdf', 'Content-Disposition'=>'inline; filename="labels.pdf"']
            );
        });
    }

    private function validateAndGetSchema(int $templateId): array
    {
        $labelTemplate = $this->labelTemplateService->getOne($templateId);
        if (!$labelTemplate) {
            throw ValidationException::withMessages([
                'error' => ['Шаблон этикетки не найден.']
            ]);
        }

        if (empty($labelTemplate->content)) {
            throw ValidationException::withMessages([
                'error' => ['Пустое содержимое шаблона этикетки.']
            ]);
        }

        $schema = json_decode($labelTemplate->content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !$schema || !is_array($schema)) {
            throw ValidationException::withMessages([
                'error' => ['Шаблон этикетки повреждён или невалидный JSON.']
            ]);
        }

        return $schema;
    }

    private function decrementPrinterLabels(int $printerId, int $qty): void
    {
        if ($printerId <= 0) {
            throw ValidationException::withMessages([
                'error' => ['Неверный идентификатор принтера.']
            ]);
        }
        
        $printer = Printer::find($printerId);
        
        if (!$printer) {
            throw ValidationException::withMessages([
                'error' => ['Принтер не найден.']
            ]);
        }
        
        if ($printer->labels_count < $qty) {
            throw ValidationException::withMessages([
                'error' => ['Недостаточно этикеток в принтере.']
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
            throw ValidationException::withMessages(['error' => ['Шаблон этикетки повреждён или невалидный JSON.'.$labelTemplate->content]]);
        }
        if (empty($schema['blocks'])) {
            throw ValidationException::withMessages(['error' => ['В шаблоне нет блоков для печати.']]);
        }

        $needsDM   = collect($schema['blocks'] ?? [])->contains(fn($b)=> in_array(($b['type']??''), ['cz','datamatrix']));
        $needsEAN  = collect($schema['blocks'] ?? [])->contains(fn($b)=> ($b['type']??'') === 'barcode');

        $gen2D = new DNS2D();
        $gen1D = new DNS1D();
        $labels = collect();
        $size = $this->sizeService->getOne($options->sizeId);
        $ean  = preg_replace('/\D/','', $size->barcode ?? '');
        if ($needsEAN && strlen($ean) < 12) {
            throw ValidationException::withMessages(['error' => ['Баркод должен содержать как минимум 12 цифр.']]);
        }

        $completeEan = substr($ean, 0, 12);
        $row = [
            'name'        => $label->name,
            'country'          => $label->country ?? 'Россия',
            'manufacturer'     => $label->manufacturer ?? 'ИП Иванов',
            'manufactureDate'  => $this->formatDate($label->manufacture_date),
            'article'     => $product->article ?? 'ART123',
            'brand'       => !empty($product->brand) ? $product->brand->name : null,
            'client'      => $product->client->short_name ?? 'Клиент',
            'shortAddress'     => $product->client->short_address ?? null,
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

    private function formatDate($date, $format = 'd.m.Y')
    {
        if (empty($date)) {
            return '';
        }
        
        try {
            return \Carbon\Carbon::parse($date)->format($format);
        } catch (\Exception $e) {
            return '';
        }
    }
}