<?php

namespace App\Services\Daftra\Interpreter;

use App\Services\Daftra\ModuleRegistry;
use Illuminate\Support\Facades\Http;

class QueryInterpreter
{
    public function interpret(string $transcript): Intent
    {
        if (empty(trim($transcript))) {
            return new Intent(
                type: IntentType::Unknown,
                module: '',
                isComplete: false,
                clarification: 'I didn\'t catch that. What would you like to do?',
            );
        }

        $key = config('services.openai.api_key');

        if (! $key) {
            return $this->fallbackParse($transcript);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$key,
            'Content-Type' => 'application/json',
        ])->timeout(15)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => "Query: \"{$transcript}\"",
                ],
            ],
            'max_tokens' => 500,
            'temperature' => 0.1,
        ]);

        if (! $response->successful()) {
            return $this->fallbackParse($transcript);
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? null;

        if (! $content) {
            return $this->fallbackParse($transcript);
        }

        $parsed = json_decode(trim($content), true);

        if (! is_array($parsed) || ! isset($parsed['type'])) {
            return $this->fallbackParse($transcript);
        }

        return new Intent(
            type: IntentType::tryFrom($parsed['type']) ?? IntentType::Unknown,
            module: $parsed['module'] ?? '',
            filters: $parsed['filters'] ?? [],
            data: $parsed['data'] ?? null,
            originalQuery: $transcript,
            isComplete: $parsed['is_complete'] ?? true,
            clarification: $parsed['clarification'] ?? null,
        );
    }

    private function fallbackParse(string $transcript): Intent
    {
        $lower = strtolower($transcript);

        $matchedModule = ModuleRegistry::moduleFromKeyword($lower);

        $type = IntentType::Unknown;
        if (preg_match('/\b(get|show|list|find|search|display|view|all)\b/', $lower)) {
            $type = IntentType::List;
        } elseif (preg_match('/\b(create|add|new|make|insert)\b/', $lower)) {
            $type = IntentType::Create;
        } elseif (preg_match('/\b(update|edit|change|modify)\b/', $lower)) {
            $type = IntentType::Update;
        } elseif (preg_match('/\b(delete|remove|cancel|erase)\b/', $lower)) {
            $type = IntentType::Delete;
        }

        return new Intent(
            type: $type,
            module: $matchedModule ?? '',
            filters: [],
            originalQuery: $transcript,
            isComplete: $matchedModule !== null && $type !== IntentType::Unknown,
            clarification: $matchedModule === null
                ? 'I\'m not sure which module you\'re referring to. Try: clients, invoices, products, etc.'
                : ($type === IntentType::Unknown
                    ? 'What would you like to do with '.$matchedModule.'? (list, create, update, delete)'
                    : null),
        );
    }

    private function systemPrompt(): string
    {
        $modules = ModuleRegistry::all();
        $lines = ['You are a Daftra API query interpreter. Parse natural language queries into structured intents.'];
        $lines[] = '';
        $lines[] = 'Available modules and their fields:';

        foreach ($modules as $key => $class) {
            $fields = ModuleRegistry::fieldsFor($key);
            $fieldList = implode(', ', array_map(
                fn ($f) => "{$f['name']} ({$f['type']})",
                $fields,
            ));
            $lines[] = "- {$key}: {$fieldList}";
        }

        $lines[] = '';
        $lines[] = 'Return ONLY a JSON object (no markdown, no explanation):';
        $lines[] = '{';
        $lines[] = '  "type": "list" | "get" | "create" | "update" | "delete" | "count" | "unknown",';
        $lines[] = '  "module": "module_name",';
        $lines[] = '  "filters": { "field": "value" },';
        $lines[] = '  "data": { ... } | null,';
        $lines[] = '  "is_complete": true/false,';
        $lines[] = '  "clarification": "question to ask user if incomplete, or null"';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'Use field names from the module\'s field list above for filters and data payloads.';
        $lines[] = 'For date ranges, convert natural language:';
        $lines[] = '- "last month" → date_from and date_to';
        $lines[] = '- "this month" → first/last day of current month';
        $lines[] = '- "last week" → 7 days ago to today';
        $lines[] = '- "between X and Y" → date_from/date_to';
        $lines[] = '';
        $lines[] = 'Handle incomplete queries:';
        $lines[] = '- If the user doesn\'t specify what to do, set type to "unknown" and is_complete to false';
        $lines[] = '- If the user didn\'t specify a module, set module to "" and is_complete to false';
        $lines[] = '- Provide a clarification question when is_complete is false';

        return implode("\n", $lines);
    }
}
