<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Ai\Facades\Ai;

class AudioController extends Controller
{
    public function transcribe(Request $request)
    {
        $request->validate([
            'audio' => ['required', 'file', 'mimes:mp3,wav,m4a'],
        ]);

        $path = $request->file('audio')->store('audio', 'local');

        $response = Ai::audio()->transcribe(storage_path('app/'.$path));

        return response()->json([
            'text' => $response->text(),
        ]);
    }
}

