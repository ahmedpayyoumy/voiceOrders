<?php

namespace App\Services\Daftra;

use App\Services\Daftra\Data\AppointmentData;
use App\Services\Daftra\Data\ClientData;
use App\Services\Daftra\Data\ClientPaymentData;
use App\Services\Daftra\Data\CreditNoteData;
use App\Services\Daftra\Data\EstimateData;
use App\Services\Daftra\Data\ExpenseData;
use App\Services\Daftra\Data\FollowUpActionData;
use App\Services\Daftra\Data\FollowUpStatusData;
use App\Services\Daftra\Data\IncomeData;
use App\Services\Daftra\Data\InvoiceData;
use App\Services\Daftra\Data\InvoicePaymentData;
use App\Services\Daftra\Data\JournalAccountData;
use App\Services\Daftra\Data\JournalData;
use App\Services\Daftra\Data\NoteData;
use App\Services\Daftra\Data\ProductCategoryData;
use App\Services\Daftra\Data\ProductData;
use App\Services\Daftra\Data\PurchaseInvoiceData;
use App\Services\Daftra\Data\PurchaseRefundData;
use App\Services\Daftra\Data\RefundReceiptData;
use App\Services\Daftra\Data\StaffData;
use App\Services\Daftra\Data\StockTransactionData;
use App\Services\Daftra\Data\StoreData;
use App\Services\Daftra\Data\SupplierData;
use App\Services\Daftra\Data\TaxData;
use App\Services\Daftra\Data\TimeTrackingData;
use App\Services\Daftra\Data\TreasuryData;
use App\Services\Daftra\Data\WorkOrderData;

class ModuleRegistry
{
    private static ?array $fieldsCache = null;

    public static function all(): array
    {
        return [
            'clients' => ClientData::class,
            'products' => ProductData::class,
            'product_categories' => ProductCategoryData::class,
            'invoices' => InvoiceData::class,
            'estimates' => EstimateData::class,
            'credit_notes' => CreditNoteData::class,
            'refund_receipts' => RefundReceiptData::class,
            'purchase_invoices' => PurchaseInvoiceData::class,
            'purchase_refunds' => PurchaseRefundData::class,
            'suppliers' => SupplierData::class,
            'work_orders' => WorkOrderData::class,
            'stores' => StoreData::class,
            'stock_transactions' => StockTransactionData::class,
            'expenses' => ExpenseData::class,
            'incomes' => IncomeData::class,
            'journals' => JournalData::class,
            'journal_accounts' => JournalAccountData::class,
            'taxes' => TaxData::class,
            'treasuries' => TreasuryData::class,
            'client_payments' => ClientPaymentData::class,
            'invoice_payments' => InvoicePaymentData::class,
            'staff' => StaffData::class,
            'notes' => NoteData::class,
            'time_tracking' => TimeTrackingData::class,
            'client_appointments' => AppointmentData::class,
            'follow_up_actions' => FollowUpActionData::class,
            'follow_up_statuses' => FollowUpStatusData::class,
        ];
    }

    public static function keywords(): array
    {
        return [
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
    }

    public static function fieldsFor(string $module): array
    {
        $all = self::all();

        if (! isset($all[$module])) {
            return [];
        }

        if (self::$fieldsCache !== null && isset(self::$fieldsCache[$module])) {
            return self::$fieldsCache[$module];
        }

        if (self::$fieldsCache === null) {
            self::$fieldsCache = [];
        }

        $class = $all[$module];

        try {
            $ref = new \ReflectionMethod($class, '__construct');
        } catch (\ReflectionException) {
            return [];
        }

        $fields = [];
        foreach ($ref->getParameters() as $param) {
            $type = $param->getType();
            $typeName = $type instanceof \ReflectionNamedType
                ? ($type->isBuiltin() ? $type->getName() : 'object')
                : 'mixed';

            $fields[] = [
                'name' => $param->getName(),
                'type' => $typeName,
            ];
        }

        self::$fieldsCache[$module] = $fields;

        return $fields;
    }

    public static function moduleFromKeyword(string $keyword): ?string
    {
        $lower = strtolower(trim($keyword));

        foreach (self::keywords() as $kw => $module) {
            if (str_contains($lower, $kw)) {
                return $module;
            }
        }

        return null;
    }
}
