<?php

namespace App\Services;

use App\Models\ChestnyZnakLabel;
use App\Models\FileOperation;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
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

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
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
            'status' => 'required|string|in:available,blocked',
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
            'operation_id' => 'nullable|integer|exists:file_operations,id',
            'number' => 'nullable|integer|min:1',
        ];

        $validated = Validator::make($data, $rules)->validate();

        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

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

        $label->status = 'used';
        $label->used_by = $userId;
        $label->used_at = Carbon::now();
        $label->updated_by = Auth::id();
        $label->save();

        return $label;
    }

    public function importCsv(int $sizeId, array $codes): array
    {   
        $userId = Auth::id();
        $operation = FileOperation::create([
            'operation_type'  => 'import',
            'file_name'       => '',
            'file_extension'  => 'csv',
            'file_size'       => null,
            'user_id'         => $userId,
            'status'          => FileOperation::STATUS_IN_PROGRESS,
            'related_to'      => 'ChestnyZnakLabel',
        ]);

        $createdCount = 0;
        $errors = [];
        $seq = 1;

        foreach ($codes as $code) {
            $code = trim($code);

            try {
                $this->create([
                    'status' => 'available',
                    'size_id'     => $sizeId,
                    'code'        => $code,
                    'operation_id'=> $operation->id,
                    'number'  => $seq,
                ]);
                $createdCount++;
            } catch (ValidationException $e) {
                $errors[] = [
                    'seq' => $seq,
                    'code' => $code,
                    'message' => $this->mapValidationError($e),
                ];
            } catch (\Throwable $e) {
                $errors[] = [
                    'seq' => $seq,
                    'code' => $code,
                    'message' => 'Непредвиденная ошибка: ' . $e->getMessage(),
                ];
            }

            $seq++;
        }

        $operation->update([
            'status' => empty($errors)
                ? FileOperation::STATUS_SUCCESS
                : ($createdCount > 0 ? 'partial' : FileOperation::STATUS_FAILED),
            'error_message' => empty($errors) ? null : 'Есть ошибки при обработке.',
            'finished_at' => now(),
        ]);

        return [
            'operation_id'   => $operation->id,
            'created_count'  => $createdCount,
            'errors'         => $errors,
        ];
    }

    private function mapValidationError(ValidationException $e): string
    {
        $messages = [];

        foreach ($e->errors() as $fieldErrors) {
            foreach ($fieldErrors as $msg) {
                $messages[] = match (true) {
                    str_contains($msg, 'already been taken') => 'Уже ранее были загружены',
                    str_contains($msg, 'required') => 'Код обязателен',
                    str_contains($msg, 'unique') => 'Код должен быть уникальным',
                    str_contains($msg, 'format') => 'Недопустимый формат кода',
                    default => $msg,
                };
            }
        }

        return implode('; ', $messages);
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
            ->filter(fn(ChestnyZnakLabel $label) => $label->status === 'available')
            ->pluck('id')
            ->all();

        foreach ($labels as $label) {
            $label->status = 'available';
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

    public function getUnused(int $sizeId, int $quantity)
    {
        return ChestnyZnakLabel::where('size_id', $sizeId)
            ->where('status', 'available')
            ->orderBy('created_at', 'asc')
            ->orderBy('number', 'asc')
            ->limit($quantity)
            ->get();
    }

    public function replaceSize(int $quantity, int $oldSizeId, int $newSizeId): int
    {
        return DB::transaction(function () use ($quantity, $oldSizeId, $newSizeId) {
            $available = ChestnyZnakLabel::where('size_id', $oldSizeId)
                ->where('status', 'available')
                ->count();

            if ($available < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ["Недостаточно меток для замены: доступно {$available}, запрошено {$quantity}"],
                ]);
            }

            $ids = ChestnyZnakLabel::where('size_id', $oldSizeId)
                ->where('status', 'available')
                ->orderBy('created_at', 'asc')
                ->limit($quantity)
                ->pluck('id')
                ->all();

            $updated = ChestnyZnakLabel::whereIn('id', $ids)
                ->update(['size_id' => $newSizeId]);

            return $updated;
        });
    }
}