<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;
use TCPDF2DBarcode;
use Milon\Barcode\DNS2D;
use Milon\Barcode\DNS1D;
use Illuminate\Support\Str;
use App\Models\LabelPrintOptions;
use Illuminate\Support\Facades\Auth;

class LabelPdfService
{
    public function __construct(private ChestnyZnakLabelService $CHZLabelService, private LabelService $labelService, private ProductSizeService $sizeService) {}

    public function generateFromHtml(LabelPrintOptions $options, int $quantity): Response
    {
        $includeDM   = $options->includeDM;
        $duplicateDM = $options->duplicateDM;
        $includeSHK  = $options->includeSHK;
        $userId = Auth::id();

        if (! $includeDM && ! $includeSHK) {
            abort(422, 'Необходимо выбрать хотя бы один формат: DataMatrix или штрихкод EAN-13.');
        }

        if ($duplicateDM && ! $includeDM) {
            abort(422, 'Нельзя дублировать DataMatrix, пока генерация DataMatrix отключена.');
        }

        $label = $this->labelService->getOne($options->labelId);
        $gen2D = new DNS2D();
        $gen1D = new DNS1D();
        $labels = collect();

        if ($includeDM) {
            $CHZLabels = $this->CHZLabelService->getUnused($options->sizeId, $quantity);
            abort_if($CHZLabels->count() < $quantity, 422, 'Недостаточно Честных знаков.');

            foreach ($CHZLabels as $item) {
                $this->CHZLabelService->markAsUsed($item->id, $userId);

                $item->name = $label->name;
                $item->color = $label->color;
                $item->size = $item->size->value;
                $item->packerId = $userId;
                $item->id = $item->id;

                $code = $item->code;
                $item->gtin14 = substr($code, 0, 16);
                $item->serialNumber = substr($code, 16, 15);

                $item->barcode2D = $gen2D->getBarcodePNG($code, 'DATAMATRIX', 2, 2);
                if ($includeSHK) {
                    $item->barcode1D = $gen1D->getBarcodePNG('4445645656', 'C128', 1, 35);
                    $item->article   = $label->article;
                    $item->client  = $label->client->name;
                    $item->composition  = $label->composition;
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
                $item->article   = $label->article;
                $item->client  = $label->client->name;
                $item->composition  = $label->composition;
                $item->color  = $label->color;
                $item->size   = $size->value;

                // штрихкод
                $item->barcode1D = $gen1D->getBarcodePNG('4445645656', 'C128', 1, 35);

                // логотип (если нужен)
                $logoPath     = public_path('images/cz-logo.png');
                $item->czLogo = base64_encode(file_get_contents($logoPath));

                $labels->push($item);
            }
        }

        $pdf = Pdf::loadView('pdf.label', [
                'labels'    => $labels,
                'withDM'    => $includeDM,
                'dupDM'     => $duplicateDM,
                'withC128'  => $includeSHK,
            ])
            ->setPaper([0, 0, 58 * 3.78, 40 * 3.78], 'portrait');

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
