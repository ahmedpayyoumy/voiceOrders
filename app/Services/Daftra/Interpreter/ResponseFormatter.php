<?php

namespace App\Services\Daftra\Interpreter;

use App\Services\Daftra\DaftraResponse;

class ResponseFormatter
{
    public function format(DaftraResponse $response, Intent $intent): string
    {
        if (! $response->successful()) {
            return "Daftra returned an error: code {$response->code}.";
        }

        return match ($intent->type) {
            IntentType::List => $this->formatList($response, $intent),
            IntentType::Get => $this->formatGet($response, $intent),
            IntentType::Create => $this->formatCreate($response, $intent),
            IntentType::Update => $this->formatUpdate($response, $intent),
            IntentType::Delete => $this->formatDelete($response, $intent),
            IntentType::Count => $this->formatCount($response, $intent),
            default => 'Operation completed successfully.',
        };
    }

    private function formatList(DaftraResponse $response, Intent $intent): string
    {
        $module = $intent->module;
        $entities = $response->entityList(ucfirst($module));

        if (empty($entities)) {
            return "No {$module} found matching your query.";
        }

        $total = $response->pagination['total'] ?? count($entities);
        $count = count($entities);

        $lines = ["Found {$total} {$module} (showing {$count}):"];

        foreach (array_slice($entities, 0, 5) as $entity) {
            $lines[] = $this->summarize($entity, $module);
        }

        if ($count > 5) {
            $lines[] = "... and " . ($total - 5) . " more.";
        }

        return implode("\n", $lines);
    }

    private function formatGet(DaftraResponse $response, Intent $intent): string
    {
        $entity = $response->entity(ucfirst($intent->module));
        if (! $entity) {
            return "{$intent->module} not found.";
        }

        return $this->summarize($entity, $intent->module);
    }

    private function formatCreate(DaftraResponse $response, Intent $intent): string
    {
        $id = $response->id;
        $module = $intent->module;
        return "{$module} created successfully" . ($id ? " (ID: {$id})." : ".");
    }

    private function formatUpdate(DaftraResponse $response, Intent $intent): string
    {
        return "{$intent->module} updated successfully.";
    }

    private function formatDelete(DaftraResponse $response, Intent $intent): string
    {
        return "{$intent->module} deleted successfully.";
    }

    private function formatCount(DaftraResponse $response, Intent $intent): string
    {
        $total = $response->pagination['total'] ?? count($response->entityList(ucfirst($intent->module)));
        return "Total {$intent->module}: {$total}.";
    }

    private function summarize(array $entity, string $module): string
    {
        return match ($module) {
            'clients' => sprintf(
                "- #%s %s %s (%s)",
                $entity['id'] ?? '?',
                $entity['first_name'] ?? '',
                $entity['last_name'] ?? $entity['business_name'] ?? '',
                $entity['email'] ?? 'no email',
            ),
            'invoices', 'estimates', 'credit_notes', 'refund_receipts',
            'purchase_invoices', 'purchase_refunds' => sprintf(
                "- #%s (%s) - %s - Total: %s",
                $entity['id'] ?? '?',
                $entity['no'] ?? $entity['name'] ?? 'no number',
                $entity['date'] ?? '',
                $entity['summary_total'] ?? $entity['total'] ?? '0',
            ),
            'products' => sprintf(
                "- #%s %s (SKU: %s) - %s",
                $entity['id'] ?? '?',
                $entity['name'] ?? '',
                $entity['sku'] ?? 'N/A',
                $entity['price'] ?? '0',
            ),
            'suppliers' => sprintf(
                "- #%s %s (%s)",
                $entity['id'] ?? '?',
                $entity['business_name'] ?? "{$entity['first_name']} {$entity['last_name']}",
                $entity['email'] ?? 'no email',
            ),
            'stock_transactions' => sprintf(
                "- #%s Product #%s: %s x %s",
                $entity['id'] ?? '?',
                $entity['product_id'] ?? '?',
                $entity['quantity'] ?? '0',
                $entity['type'] ?? 'movement',
            ),
            default => sprintf("- #%s %s", $entity['id'] ?? '?', json_encode($entity)),
        };
    }
}
