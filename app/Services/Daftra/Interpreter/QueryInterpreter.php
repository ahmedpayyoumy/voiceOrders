<?php

namespace App\Services\Daftra\Interpreter;

use App\Services\Daftra\Query\DaftraQuery;
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

        $modules = [
            'client' => 'clients',
            'customer' => 'clients',
            'product' => 'products',
            'item' => 'products',
            'invoice' => 'invoices',
            'bill' => 'invoices',
            'estimate' => 'estimates',
            'quote' => 'estimates',
            'credit' => 'credit_notes',
            'refund' => 'refund_receipts',
            'purchase' => 'purchase_invoices',
            'supplier' => 'suppliers',
            'vendor' => 'suppliers',
            'stock' => 'stock_transactions',
            'inventory' => 'stock_transactions',
            'warehouse' => 'stores',
            'store' => 'stores',
            'work order' => 'work_orders',
            'expense' => 'expenses',
            'income' => 'incomes',
            'payment' => 'client_payments',
            'staff' => 'staff',
            'employee' => 'staff',
            'journal' => 'journals',
            'tax' => 'taxes',
            'note' => 'notes',
            'appointment' => 'client_appointments',
            'requisition' => 'purchase_invoices',
        ];

        $matchedModule = null;
        foreach ($modules as $keyword => $module) {
            if (str_contains($lower, $keyword)) {
                $matchedModule = $module;
                break;
            }
        }

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
        return <<<'PROMPT'
You are a Daftra API query interpreter. Parse natural language queries into structured intents.

Available modules:
- clients (customers)
- products (items, inventory)
- product_categories
- invoices (bills, sales)
- estimates (quotes)
- credit_notes
- refund_receipts
- purchase_invoices (purchase orders, requisitions)
- purchase_refunds
- suppliers (vendors)
- work_orders
- stores (warehouses)
- stock_transactions (inventory movements)
- expenses
- incomes
- journals
- journal_accounts
- taxes
- treasuries
- client_payments
- invoice_payments
- staff (employees)
- notes
- time_tracking
- client_appointments
- follow_up_actions
- follow_up_statuses

Return ONLY a JSON object (no markdown, no explanation):
{
  "type": "list" | "get" | "create" | "update" | "delete" | "count" | "unknown",
  "module": "module_name",
  "filters": { "field": "value" },
  "data": { ... } | null,
  "is_complete": true/false,
  "clarification": "question to ask user if incomplete, or null"
}

Filter field mapping:
- Client name/number → "client_id" or "client_business_name" 
- Product name/id → "product_id" or "item"
- Date range → include "date_from" and "date_to" in filters
- Status → "status"
- Supplier → "supplier_id" or "business_name"
- Store/warehouse → "store_id"
- Search term → "search"

For date ranges, convert natural language:
- "last month" → date_from and date_to
- "this month" → first/last day of current month
- "last week" → 7 days ago to today
- "between X and Y" → date_from/date_to

Handle incomplete queries:
- If the user doesn't specify what to do, set type to "unknown" and is_complete to false
- If the user didn't specify a module, set module to "" and is_complete to false
- Provide a clarification question when is_complete is false

Map "requisitions" to purchase_invoices module.
Map "warehouse" to stores module.
Map "inventory" to stock_transactions module.
PROMPT;
    }
}
