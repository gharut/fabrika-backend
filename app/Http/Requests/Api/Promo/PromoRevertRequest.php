<?php

namespace App\Http\Requests\Api\Promo;

use Illuminate\Foundation\Http\FormRequest;

class PromoRevertRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array {
      return [
          'campaignId' => ['required','integer','exists:promo_campaigns,id'],
          'wbToken'    => ['required','string','max:1000'],
      ];
    }
}
