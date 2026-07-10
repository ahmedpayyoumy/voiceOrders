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
        $lines[] = 'The user may speak in Egyptian Arabic, Saudi Arabian Arabic, or English. Understand and parse their intent regardless of language.';
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
        $lines[] = 'IMPORTANT — Product vs Service type:';
        $lines[] = '- When creating a product (user says "create product", "add product", "new product", or similar in English, Arabic, or any accent), set data.type = 1';
        $lines[] = '- When creating a service (user says "create service", "add service", "new service", or similar in English, Arabic, or any accent), set data.type = 2';
        $lines[] = '- Arabic examples: "منتج" or "إضافة منتج" or "product" → type=1. "خدمة" or "إضافة خدمة" or "service" → type=2';
        $lines[] = '- If the user does not specify product or service, default to type=1 (product).';
        $lines[] = 'PRODUCT STOCK — IMPORTANT: Stock quantity operations use the "products" module with special fields below. Do NOT use the "stock_transactions" module for this.';
        $lines[] = '- When creating a product and the user mentions an initial stock quantity (e.g. "create a product called Widget with 50 in stock"), set module to "products" and include "quantity" in data (e.g. "quantity": 50).';
        $lines[] = '- Include "requisition_type" in data: 1 for inbound (adding stock), 2 for outbound (removing stock).';
        $lines[] = '- Also include "store_id" in data if the user mentions a warehouse.';
        $lines[] = '- When the user wants to update a product\'s stock quantity (e.g. "add 30 to stock of product X", "remove 10 from stock of product Y"), set module to "products" and include "quantity" in data with the change amount.';
        $lines[] = '- Include "requisition_type" in data: 1 for inbound (adding stock), 2 for outbound (removing stock).';
        $lines[] = '- Include "store_id" in data if the user mentions a warehouse.';
        $lines[] = 'TODAY\'S DATE: '.date('Y-m-d').'. Current month: '.date('F Y').'.';
        $lines[] = 'IMPORTANT — Always calculate date ranges relative to TODAY\'S DATE above, not your training data.';
        $lines[] = '';
        $lines[] = 'DATE RANGES — Always output BOTH "date_from" and "date_to" as flat top-level filters. NEVER use a single "date" key or nested "date" object.';
        $lines[] = '- "yesterday" → "date_from": "'.date('Y-m-d', strtotime('yesterday')).'", "date_to": "'.date('Y-m-d', strtotime('yesterday')).'"';
        $lines[] = '- "today" → "date_from": "'.date('Y-m-d').'", "date_to": "'.date('Y-m-d').'"';
        $lines[] = '- "last month" → "date_from": "'.date('Y-m-d', strtotime('first day of last month')).'", "date_to": "'.date('Y-m-d', strtotime('last day of last month')).'"';
        $lines[] = '- "this month" → "date_from": "'.date('Y-m-01').'", "date_to": "'.date('Y-m-t').'"';
        $lines[] = '- "last week" → "date_from": "'.date('Y-m-d', strtotime('-7 days')).'", "date_to": "'.date('Y-m-d').'"';
        $lines[] = '- "between X and Y" → "date_from": "X", "date_to": "Y"';
        $lines[] = 'CRITICAL — For a single-day query like "yesterday", "today", or a specific date, BOTH date_from AND date_to must be set to that same date. Example: {"type":"list","module":"invoices","filters":{"date_from":"'.date('Y-m-d', strtotime('yesterday')).'","date_to":"'.date('Y-m-d', strtotime('yesterday')).'"},"is_complete":true,"clarification":null}';
        $lines[] = '';
        $lines[] = 'Handle incomplete queries:';
        $lines[] = '- If the user doesn\'t specify what to do, set type to "unknown" and is_complete to false';
        $lines[] = '- If the user didn\'t specify a module, set module to "" and is_complete to false';
        $lines[] = '- Provide a clarification question when is_complete is false';

        return implode("\n", $lines);
    }
}
