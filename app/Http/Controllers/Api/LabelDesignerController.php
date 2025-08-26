<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LabelPdfService;
use App\Models\LabelPrintOptions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LabelDesignerController extends Controller
{
    public function __construct(private LabelPdfService $pdf) {}

    public function preview(Request $request)
    {
        $data = $request->validate([
            'label_id'   => 'required|integer|exists:labels,id',
            'size_id'    => 'nullable|integer|exists:product_sizes,id',
        ]);

        $options = new LabelPrintOptions(
            $data['size_id'] !== null ? (int)$data['size_id'] : 0,
            $data['label_id'] !== null ? (int)$data['label_id'] : null
        );

        try {
            return $this->pdf->previewFromDesignerSchema($options);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'preview' => ['Ошибка предпросмотра: '.$e->getMessage()],
            ]);
        }
    }

    public function print(Request $r)
    {
      $data = $r->validate([
        'label_id' => ['required','integer'],
        'size_id'  => ['nullable','integer'],
        'quantity' => ['required','integer','min:1','max:500'],
      ]);

        $options = new LabelPrintOptions(
            $data['size_id'] !== null ? (int)$data['size_id'] : 0,
            $data['label_id'] !== null ? (int)$data['label_id'] : null
        );

      return $this->pdf->generateFromDesignerSchema($options, (int)$data['quantity']);
    }
}
