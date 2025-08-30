<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;

class ProductImageController extends Controller
{
    /**
     * Получить главное фото продукта
     */
    public function main(int $productId): JsonResponse
    {
        $image = ProductImage::where('product_id', $productId)
            ->orderBy('position')
            ->first();

        if (!$image) {
            return response()->json([
                'success' => false,
                'message' => 'Фото не найдено',
                'data'    => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $image,
        ]);
    }

    /**
     * Получить все фото продукта
     */
    public function all(int $productId): JsonResponse
    {
        $images = ProductImage::where('product_id', $productId)
            ->orderBy('position')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $images,
        ]);
    }
}
