<?php

namespace App\Http\Requests\Api\ChestnyZnakLabel;

use Illuminate\Foundation\Http\FormRequest;

class ChestnyZnakLabelImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'size_id'     => ['required', 'array', 'min:1'],
            'size_id.*'   => ['required', 'integer', 'exists:product_sizes,id'],
            'file'        => ['required', 'array', 'min:1'],
            'file.*'      => ['required', 'file'],
        ];
    }

    public function messages(): array
    {
        return [
            'size_id.required'     => 'Необходимо указать хотя бы один ID размера продукта.',
            'size_id.array'        => 'Поле size_id должно быть массивом.',
            'size_id.min'          => 'Нужно передать минимум один ID размера.',
            'size_id.*.required'   => 'Каждый элемент массива size_id обязателен.',
            'size_id.*.integer'    => 'Каждый ID размера должен быть числом.',
            'size_id.*.exists'     => 'Размер продукта с таким ID не найден.',

            'file.required'        => 'Нужно загрузить хотя бы один CSV-файл.',
            'file.array'           => 'Файлы должны передаваться массивом.',
            'file.min'             => 'Нужно загрузить минимум один файл.',
            'file.*.required'      => 'Каждый элемент file обязателен.',
            'file.*.file'          => 'Загруженный элемент не является файлом.',
        ];
    }
}
