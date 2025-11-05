<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LabelPdfService;
use App\Models\LabelPrintOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

    public function print(Request $request)
    {
        try {
            $data = $request->validate([
                'label_id' => ['required','integer'],
                'size_id'  => ['nullable','integer'],
                'quantity' => ['required','integer','min:1','max:500'],
            ]);

            $options = new LabelPrintOptions(
                $data['size_id'] !== null ? (int)$data['size_id'] : 0,
                $data['label_id'] !== null ? (int)$data['label_id'] : null
            );

            return $this->pdf->generateFromDesignerSchema($options, (int)$data['quantity']);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            Log::error('Ошибка валидации данных при генерации PDF', ['trace' => $e->errors()]);
            if (isset($errors['error']) && !empty($errors['error'])) {
                $errorMessage = $errors['error'][0];
                
                return response()->json([
                    'success' => false,
                    'error' => $errorMessage,
                ], 422);
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Ошибка валидации данных',
                'messages' => $errors,
            ], 422);
        } catch (\RuntimeException $e) {
            Log::error('Ошибка генерации PDF: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'error' => 'Ошибка при создании PDF',
                'message' => $e->getMessage(),
            ], 500);
        } catch (\Throwable $e) {
            Log::error('Необработанная ошибка при печати', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Внутренняя ошибка сервера',
                'message' => 'При печати произошла непредвиденная ошибка. Повторите попытку позже или обратитесь к администратору.',
            ], 500);
        }
    }
}
