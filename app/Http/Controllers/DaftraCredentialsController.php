<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DaftraCredentialsController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'daftra_domain' => $user->daftra_domain,
            'has_api_key' => ! is_null($user->daftra_api_key),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'daftra_domain' => 'nullable|string|max:255',
            'daftra_api_key' => 'nullable|string|max:500',
        ]);

        $request->user()->update($data);

        return response()->json(['message' => 'Credentials saved']);
    }

    public function destroy(Request $request)
    {
        $request->user()->update([
            'daftra_domain' => null,
            'daftra_api_key' => null,
        ]);

        return response()->json(['message' => 'Credentials removed']);
    }
}
