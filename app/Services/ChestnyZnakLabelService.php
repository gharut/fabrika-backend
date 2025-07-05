<?php

namespace App\Services;

use App\Models\ChestnyZnakLabel;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use TCPDF;
use Symfony\Component\HttpFoundation\Response;

class ChestnyZnakLabelService
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ChestnyZnakLabel::with(['size', 'usedBy', 'creator', 'editor']);
        if (!empty($filters['size_id'])) {
            $query->where('size_id', $filters['size_id']);
        }

        if (isset($filters['used'])) {
            $query->where('used', (bool) $filters['used']);
        }

        return $query->paginate($perPage);
    }

    public function getOne(int $id): ChestnyZnakLabel
    {
        return ChestnyZnakLabel::with()->findOrFail($id);
    }

    public function create(array $data): ChestnyZnakLabel
    {
        $rules = [
            'size_id' => 'required|exists:product_sizes,id',
            'code'    => [
                'required',
                'string',
                'unique:chestny_znak_labels,code',
                function($attribute, $value, $fail) {
                    if (!preg_match('/^01(\d{14})21([\x1D\x20-\x7E]+)$/', $value, $m)) {
                        return $fail('Неверная структура кода метки.');
                    }
                    $gtin = $m[1];
                    if (! $this->validateGtin14($gtin)) {
                        return $fail('Контрольная цифра GTIN некорректна.');
                    }
                },
            ],
        ];

        $validated = Validator::make($data, $rules)->validate();

        // $validated['created_by'] = auth()->id();
        $validated['created_by'] = 1;
        // $validated['updated_by'] = auth()->id();
        $validated['updated_by'] = 1;

        return ChestnyZnakLabel::create($validated);
    }

    public function delete(int $id): void
    {
        $label = ChestnyZnakLabel::findOrFail($id);
        $label->delete();
    }

    public function findByCode(string $code): ?ChestnyZnakLabel
    {
        return ChestnyZnakLabel::where('code', $code)->first();
    }

    public function markAsUsed(int $id, int $userId): ChestnyZnakLabel
    {
        $label = ChestnyZnakLabel::findOrFail($id);

        $label->used = true;
        $label->used_by = $userId;
        $label->used_at = Carbon::now();
        // $label->updated_by = auth()->id();
        $label->updated_by = 1;
        $label->save();

        return $label;
    }

    public function importCsv(int $sizeId, array $codes): array
    {
        $created = [];
        $errors  = [];

        foreach ($codes as $code) {
            try {
                $created[] = $this->create([
                    'size_id' => $sizeId,
                    'code'    => trim($code),
                ]);
            } catch (ValidationException $e) {
                $errors[$code] = $e->errors();
            } catch (\Throwable $e) {
                $errors[$code] = [$e->getMessage()];
            }
        }

        return compact('created','errors');
    }

    public function markAsUnused(array $ids): object
    {
        $labels = ChestnyZnakLabel::whereIn('id', $ids)->get();
        $foundIds = $labels->pluck('id')->all();
        $missing = array_diff($ids, $foundIds);
        if (!empty($missing)) {
            return (object)[
                'success' => false,
                'message' => 'Этикетки не найдены: ' . implode(', ', $missing),
                'updated' => 0,
            ];
        }

        $notUsedIds = $labels
            ->filter(fn(ChestnyZnakLabel $label) => !$label->used)
            ->pluck('id')
            ->all();

        foreach ($labels as $label) {
            $label->used = false;
            $label->used_by = null;
            $label->used_at = null;
            $label->updated_by = 1;
            $label->save();
        }

        $count = $labels->count();
        return (object)[
            'success' => true,
            'message' => 'Успешно обновлено этикеток: ' . $count,
            'updated' => $count,
        ];
    }

    private function validateGtin14(string $digits14): bool
    {
        $digits = array_map('intval', str_split($digits14));
        $check  = array_pop($digits);
        $sum = 0;
        foreach (array_reverse($digits) as $i => $d) {
            $sum += $d * ($i % 2 === 0 ? 3 : 1);
        }
        $calc = (10 - ($sum % 10)) % 10;
        return $calc === $check;
    }

    public function generatePdf(int $sizeId, int $quantity): Response
    {
        // 1) забираем N неиспользованных
        $labels = $this->getUnused($sizeId, $quantity);
        if ($labels->count() < $quantity) {
            abort(422, 'Недостаточно неиспользованных меток');
        }

        // 2) помечаем их как использованные
        // $userId = auth()->id();
        $userId = 1;
        foreach ($labels as $label) {
            $this->markAsUsed($label->id, $userId);
        }

        $pageW = 58;
        $pageH = 40;
        $pdf = new TCPDF('L', 'mm', [$pageW, $pageH], true, 'UTF-8', false);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->SetAutoPageBreak(false);

        foreach ($labels as $label) {
            $pdf->AddPage();

            $margin = 0;
            $dmW = $pageW - $margin*2;
            $dmH = $pageH - 12;

            $style = [
                'border'  => 0,
                'padding' => 0,
                'fgcolor' => [0,0,0],
                'bgcolor' => [255,255,255],
            ];

            $pdf->write2DBarcode(
                $label->code,
                'DATAMATRIX',
                $margin,
                $margin,
                $dmW,
                $dmH,
                $style,
                'N'
            );

            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY(0, $pageH - 8);
            $gtin14 = substr($label->code, 0, 14);
            $pdf->Cell($pageW, 6, $gtin14, 0, 0, 'C');
        }

        return response(
            $pdf->Output('', 'S'),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="labels.pdf"',
            ]
        );
    }

    public function getUnused(int $sizeId, int $quantity)
    {
        return ChestnyZnakLabel::where('size_id', $sizeId)
            ->where('used', false)
            ->orderBy('created_at')
            ->limit($quantity)
            ->get();
    }

    public function replaceSize(int $quantity, int $oldSizeId, int $newSizeId): int
    {
        return DB::transaction(function () use ($quantity, $oldSizeId, $newSizeId) {
            // Считаем доступные (неиспользованные) метки
            $available = ChestnyZnakLabel::where('size_id', $oldSizeId)
                ->where('used', false)
                ->count();

            if ($available < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ["Недостаточно меток для замены: доступно {$available}, запрошено {$quantity}"],
                ]);
            }

            // Берём ID первых $quantity меток (обычно по created_at или id)
            $ids = ChestnyZnakLabel::where('size_id', $oldSizeId)
                ->where('used', false)
                ->orderBy('created_at', 'asc')
                ->limit($quantity)
                ->pluck('id')
                ->all();

            // Обновляем размер
            $updated = ChestnyZnakLabel::whereIn('id', $ids)
                ->update(['size_id' => $newSizeId]);

            return $updated;
        });
    }
}