<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Users\UserCreateRequest;
use App\Http\Requests\Api\Users\UserUpdateRequest;
use App\Mail\UserActivationMail;
use App\Models\Attribute;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;


class AttributesController extends Controller
{

    public function list(): JsonResponse
    {
        $list = Attribute::all();
        return response()->json(['data' => $list ]);
    }


}
