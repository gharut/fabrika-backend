<?php

namespace App\Traits;

trait StrategyValidationTrait
{
    protected function getValidationRules(string $type = 'store'): array
    {
        $rules = [
            'name' => 'string|max:255',
            'type' => 'nullable|string|in:time_discount',
            'status' => 'nullable|string|in:draft,active,paused',
            'order_by_field' => 'nullable|string|max:64',
            'order_direction' => 'nullable|string|in:asc,desc',
            'account_id' => 'nullable|integer|exists:marketplace_accounts,id',
        ];

        if ($type === 'store') {
            $rules['name'] = 'required|' . $rules['name'];
        } else {
            $rules['name'] = 'sometimes|required|' . $rules['name'];
        }

        return $rules;
    }

    protected function getValidationMessages(): array
    {
        return [
            'name.required' => 'Название обязательно для заполнения',
            'name.string' => 'Название должно быть строкой',
            'name.max' => 'Название не должно превышать 255 символов',
            'type.in' => 'Некорректное значение для поля Тип',
            'status.in' => 'Некорректное значение для поля Статус',
            'order_by_field.max' => 'Поле для сортировки не должно превышать 64 символов',
            'order_direction.in' => 'Направление сортировки должно быть asc или desc',
            'account_id.exists' => 'Указанный аккаунт не существует',
            'required' => 'Поле :attribute обязательно для заполнения',
            'string' => 'Поле :attribute должно быть строкой',
            'max' => 'Поле :attribute не должно превышать :max символов',
            'in' => 'Некорректное значение для поля :attribute',
            'integer' => 'Поле :attribute должно быть целым числом',
        ];
    }

    protected function errorResponse(string $message, int $code = 500)
    {
        return response()->json([
            'data' => null,
            'success' => false,
            'message' => $message
        ], $code, [], JSON_UNESCAPED_UNICODE);
    }

    protected function handleValidationException(\Illuminate\Validation\ValidationException $e)
    {
        $errors = implode(', ', array_map(function ($error) {
            return is_array($error) ? implode(', ', $error) : $error;
        }, $e->errors()));
        
        return $this->errorResponse('Ошибка валидации: ' . $errors, 422);
    }
}