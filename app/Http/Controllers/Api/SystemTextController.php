<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemText;
use Illuminate\Http\Request;

class SystemTextController extends Controller
{
    public function index()
    {
        return SystemText::all();
    }

    public function show(string $key)
    {
        return SystemText::where('key', $key)->firstOrFail();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'key'     => 'required|string|unique:system_texts,key',
            'title'   => 'required|string|max:255',
            'content' => 'nullable|string',
        ]);

        return SystemText::create($validated);
    }

    public function update(Request $request, SystemText $systemText)
    {
        $validated = $request->validate([
            'key'     => 'required|string|unique:system_texts,key,' . $systemText->id,
            'title'   => 'required|string|max:255',
            'content' => 'nullable|string',
        ]);

        $systemText->update($validated);
        return $systemText;
    }

    public function destroy(SystemText $systemText)
    {
        $systemText->delete();
        return response()->json(['message' => 'System text deleted']);
    }
}
