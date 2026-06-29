<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TranscriptController extends Controller
{
    public function suggest(Request $request)
    {
        $request->validate([
            'transcript' => 'required|string',
        ]);

        $key = config('services.openai.api_key');

        if (! $key) {
            return response()->json(['error' => 'OpenAI API key not configured'], 500);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$key,
            'Content-Type' => 'application/json',
        ])->timeout(15)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a speech transcription corrector. Fix transcription errors caused by background noise, similar-sounding words, or unclear speech. Correct any misheard words based on context and natural language flow. Return ONLY the corrected text, with no explanation or quotation marks.',
                ],
                [
                    'role' => 'user',
                    'content' => 'Please correct any transcription errors in this voice transcript: "'.$request->transcript.'"',
                ],
            ],
            'max_tokens' => 500,
            'temperature' => 0.3,
        ]);

        if (! $response->successful()) {
            return response()->json([
                'error' => 'Failed to get suggestion: '.$response->body(),
            ], $response->status());
        }

        $data = $response->json();
        $corrected = $data['choices'][0]['message']['content'] ?? null;

        if (! $corrected) {
            return response()->json(['error' => 'Empty response from OpenAI'], 500);
        }

        return response()->json([
            'corrected' => trim($corrected),
        ]);
    }
}
