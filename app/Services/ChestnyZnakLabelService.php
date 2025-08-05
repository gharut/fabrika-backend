<?php

namespace App\Services;

use App\Models\ChestnyZnakLabel;
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

        $label->used = true;
        $label->used_by = $userId;
        $label->used_at = Carbon::now();
        $label->updated_by = Auth::id();
        $label->save();

        return $label;
    }

    public function importCsv(int $sizeId, array $codes): array
    {
        $createdCount = 0;
        $errors = [];

        foreach ($codes as $code) {
            $code = trim($code);

            try {
                $this->create([
                    'size_id' => $sizeId,
                    'code' => $code,
                ]);
                $createdCount++;
            } catch (ValidationException $e) {
                $errors[] = [
                    'code' => $code,
                    'message' => $this->mapValidationError($e),
                ];
            } catch (\Throwable $e) {
                $errors[] = [
                    'code' => $code,
                    'message' => 'Непредвиденная ошибка: ' . $e->getMessage(),
                ];
            }
        }

        return [
            'created_count' => $createdCount,
            'errors' => $errors,
        ];
    }

    private function mapValidationError(ValidationException $e): string
    {
        $messages = [];

        foreach ($e->errors() as $fieldErrors) {
            foreach ($fieldErrors as $msg) {
                $messages[] = match (true) {
                    str_contains($msg, 'already been taken') => 'Код уже существует',
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
            $available = ChestnyZnakLabel::where('size_id', $oldSizeId)
                ->where('used', false)
                ->count();

            if ($available < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ["Недостаточно меток для замены: доступно {$available}, запрошено {$quantity}"],
                ]);
            }

            $ids = ChestnyZnakLabel::where('size_id', $oldSizeId)
                ->where('used', false)
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