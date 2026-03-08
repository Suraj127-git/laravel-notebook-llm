<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Ai\Facades\Ai;

class ImageController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'prompt' => ['required', 'string', 'max:500'],
        ]);

        $response = Ai::images()->generate(
            prompt: $request->string('prompt'),
        );

        return response()->json([
            'url' => $response->url(),
        ]);
    }
}

