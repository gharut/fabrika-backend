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

    private function mm2pt(float $mm): float { return $mm * 72 / 25.4; }

    public function generateFromDesignerSchema(LabelPrintOptions $options, int $quantity)
    {
        $label   = $this->labelService->getOne($options->labelId);
        $product = $label->product;
        $userId  = Auth::id();
        $labelTemplateId = $label->label_template_id;

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

        // $labelTemplateId = $label->label_template_id;
        // $labelTemplate   = $this->labelTemplateService->getOne($labelTemplateId);

        // $schema = json_decode($labelTemplate->content, true);
        // if (!$schema || !is_array($schema)) {
        //     throw ValidationException::withMessages(['schema' => ['Шаблон этикетки повреждён или невалидный JSON.'.$labelTemplate->content]]);
        // }
        // if (empty($schema['blocks'])) {
        //     throw ValidationException::withMessages(['schema' => ['В шаблоне нет блоков для печати.']]);
        // }

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

                $ean = preg_replace('/\D/','', $item->size->barcode ?? '');
                $completeEan = substr($ean, 0, 12);
                $row = [
                    'name'        => $label->name,
                    'color'       => $product->color,
                    'brand'       => !empty($product->brand) ? $product->brand->name : null,
                    'size'        => $this->formatSize($item->size, $label->size_display_type),
                    'client'      => $product->client->name ?? null,
                    'composition' => $product->composition ?? null,
                    'barcode'     => $completeEan,
                    'preview'     => false,
                    'id'          => $item->id,
                ];

                $row['barcode2D'] = $gen2D->getBarcodePNG($item->code, 'DATAMATRIX', 2, 2);
                if ($needsEAN && strlen($ean) >= 12) {
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
                    'id'          => $item->id,
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
            'schema' => $schema,
            'labels' => $labels,
        ])->setPaper([0, 0, $this->mm2pt($w), $this->mm2pt($h)], 'portrait');

        $printerId = $label->printer_id;
        $this->decrementPrinterLabels($printerId, $quantity);

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
            'client'      => $product->client->name ?? 'Клиент',
            'composition' => $product->composition ?? 'Состав: хлопок',
            'color'       => $product->color ?? 'Цвет',
            'size'        => $this->formatSize($size, $label->size_display_type),
            'barcode'     => $completeEan,
            'preview'     => true,
            'id'          => 234,
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
            'schema' => $schema,
            'labels' => $labels,
        ])->setPaper([0, 0, $this->mm2pt($w), $this->mm2pt($h)], 'portrait');

        return response(
            $pdf->stream('preview.pdf'), 200,
            ['Content-Type'=>'application/pdf', 'Content-Disposition'=>'inline; filename="preview.pdf"']
        );
    }
}