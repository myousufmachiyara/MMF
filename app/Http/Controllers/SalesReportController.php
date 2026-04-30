<?php

namespace App\Http\Controllers;

use App\Models\SaleInvoice;
use App\Models\ChartOfAccounts;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesReportController extends Controller
{
    public function saleReports(Request $request)
    {
        // ── Common filters ────────────────────────────────────────
        $customerId = $request->input('customer_id');
        $dateFrom   = $request->input('date_from');
        $dateTo     = $request->input('date_to');
        $type       = $request->input('type', 'all');

        // Active tab — includes new profit tabs
        $tab = $request->input('tab', 'SR'); // SR | SRET | CW | PROFIT_PRODUCT | PROFIT_CATEGORY

        // Profit-specific filters (prefixed to avoid collision)
        $profitYear       = (int) $request->input('profit_year', Carbon::now()->year);
        $profitCategoryId = $request->input('profit_category_id');
        $profitProductId  = $request->input('profit_product_id');

        // ── Sale Invoices (tab SR) ────────────────────────────────
        $invoicesQuery = SaleInvoice::with(['account', 'items.product', 'purchaseInvoice'])
            ->whereNull('deleted_at');
        if ($customerId) $invoicesQuery->where('account_id', $customerId);
        if ($dateFrom)   $invoicesQuery->where('date', '>=', $dateFrom);
        if ($dateTo)     $invoicesQuery->where('date', '<=', $dateTo);

        $invoices = $tab === 'SR'
            ? $invoicesQuery->latest('date')->get()
            : collect();

        // ── Sale Returns (tab SRET) ───────────────────────────────
        // DR Sales Revenue / CR Customer  →  voucher_type=journal, reference like SR-%
        $returnsQuery = DB::table('vouchers')
            ->join('chart_of_accounts as customer', 'vouchers.ac_cr_sid', '=', 'customer.id')
            ->select(
                'vouchers.id', 'vouchers.date', 'vouchers.amount',
                'vouchers.reference', 'vouchers.remarks',
                'customer.name as customer_name', 'customer.id as customer_id'
            )
            ->where('vouchers.voucher_type', 'journal')
            ->where('vouchers.reference', 'like', 'SR-%')
            ->whereNull('vouchers.deleted_at');
        if ($customerId) $returnsQuery->where('vouchers.ac_cr_sid', $customerId);
        if ($dateFrom)   $returnsQuery->where('vouchers.date', '>=', $dateFrom);
        if ($dateTo)     $returnsQuery->where('vouchers.date', '<=', $dateTo);

        $returns = $tab === 'SRET'
            ? $returnsQuery->orderByDesc('vouchers.date')->get()
            : collect();

        // ── Receipts from Customers (tab CW) ─────────────────────
        // DR Cash/Bank / CR Customer  →  voucher_type=receipt
        $receiptsQuery = DB::table('vouchers')
            ->join('chart_of_accounts as customer', 'vouchers.ac_cr_sid', '=', 'customer.id')
            ->join('chart_of_accounts as cashbank',  'vouchers.ac_dr_sid', '=', 'cashbank.id')
            ->select(
                'vouchers.id', 'vouchers.date', 'vouchers.amount',
                'vouchers.reference', 'vouchers.remarks',
                'customer.name as customer_name', 'customer.id as customer_id',
                'cashbank.name as cashbank_name'
            )
            ->where('vouchers.voucher_type', 'receipt')
            ->whereNull('vouchers.deleted_at');
        if ($customerId) $receiptsQuery->where('vouchers.ac_cr_sid', $customerId);
        if ($dateFrom)   $receiptsQuery->where('vouchers.date', '>=', $dateFrom);
        if ($dateTo)     $receiptsQuery->where('vouchers.date', '<=', $dateTo);

        $receipts = $tab === 'CW'
            ? $receiptsQuery->orderByDesc('vouchers.date')->get()
            : collect();

        // ── Totals ────────────────────────────────────────────────
        $totalSold = $tab === 'SR'
            ? $invoices->sum(fn($inv) =>
                $inv->items->sum(fn($i) => $i->sale_price * $i->quantity) - ($inv->discount ?? 0))
            : 0;
        $totalReturned = $returns->sum('amount');
        $totalReceived = $receipts->sum('amount');
        $netReceivable = $totalSold - $totalReturned - $totalReceived;

        // ── Customer-wise summary ─────────────────────────────────
        $customerSummary = [];
        if ($tab === 'CW') {
            foreach ($invoicesQuery->latest('date')->get() as $inv) {
                $cid = $inv->account_id;
                $amt = $inv->items->sum(fn($i) => $i->sale_price * $i->quantity) - ($inv->discount ?? 0);
                $customerSummary[$cid]['name']     = $inv->account->name ?? '—';
                $customerSummary[$cid]['sold']     = ($customerSummary[$cid]['sold']     ?? 0) + $amt;
                $customerSummary[$cid]['returned'] =  $customerSummary[$cid]['returned'] ?? 0;
                $customerSummary[$cid]['received'] =  $customerSummary[$cid]['received'] ?? 0;
            }
            foreach ($returnsQuery->get() as $ret) {
                $cid = $ret->customer_id;
                $customerSummary[$cid]['name']     = $ret->customer_name;
                $customerSummary[$cid]['sold']     =  $customerSummary[$cid]['sold']     ?? 0;
                $customerSummary[$cid]['returned'] = ($customerSummary[$cid]['returned'] ?? 0) + $ret->amount;
                $customerSummary[$cid]['received'] =  $customerSummary[$cid]['received'] ?? 0;
            }
            foreach ($receiptsQuery->get() as $rec) {
                $cid = $rec->customer_id;
                $customerSummary[$cid]['name']     = $rec->customer_name;
                $customerSummary[$cid]['sold']     =  $customerSummary[$cid]['sold']     ?? 0;
                $customerSummary[$cid]['returned'] =  $customerSummary[$cid]['returned'] ?? 0;
                $customerSummary[$cid]['received'] = ($customerSummary[$cid]['received'] ?? 0) + $rec->amount;
            }
            foreach ($customerSummary as &$c) {
                $c['net_receivable'] = $c['sold'] - $c['returned'] - $c['received'];
            }
        }

        // ─────────────────────────────────────────────────────────
        // PROFIT REPORT  (only computed when those tabs are active)
        // ─────────────────────────────────────────────────────────
        $productReport    = [];
        $categoryReport   = [];
        $grandMonthly     = array_fill(1, 12, ['revenue' => 0, 'cogs' => 0, 'profit' => 0]);
        $profitMonths     = collect();
        $profitCategories = collect();
        $profitProducts   = collect();

        if (in_array($tab, ['PROFIT_PRODUCT', 'PROFIT_CATEGORY'])) {

            $profitCategories = ProductCategory::orderBy('name')->get();
            $profitProducts   = Product::orderBy('name')
                ->when($profitCategoryId, fn($q) => $q->where('category_id', $profitCategoryId))
                ->get();

            $profitMonths = collect(range(1, 12))->map(fn($m) => [
                'num'   => $m,
                'label' => Carbon::create($profitYear, $m)->format('M'),
            ]);

            // Step 1 — sales by product + month for the chosen year
            $salesQuery = DB::table('sale_invoice_items')
                ->join('sale_invoices',      'sale_invoice_items.sale_invoice_id', '=', 'sale_invoices.id')
                ->join('products',           'sale_invoice_items.product_id',      '=', 'products.id')
                ->join('product_categories', 'products.category_id',               '=', 'product_categories.id')
                ->whereNull('sale_invoices.deleted_at')
                ->whereYear('sale_invoices.date', $profitYear)
                ->select(
                    'products.id as product_id',
                    'products.name as product_name',
                    'product_categories.id as category_id',
                    'product_categories.name as category_name',
                    'sale_invoice_items.variation_id',
                    DB::raw('MONTH(sale_invoices.date) as month'),
                    DB::raw('SUM(sale_invoice_items.sale_price * sale_invoice_items.quantity) as revenue'),
                    DB::raw('SUM(sale_invoice_items.quantity) as qty_sold')
                )
                ->groupBy(
                    'products.id', 'products.name',
                    'product_categories.id', 'product_categories.name',
                    'sale_invoice_items.variation_id',
                    DB::raw('MONTH(sale_invoices.date)')
                );

            if ($profitCategoryId) $salesQuery->where('products.category_id', $profitCategoryId);
            if ($profitProductId)  $salesQuery->where('products.id', $profitProductId);

            $salesRows = $salesQuery->get();

            // Step 2 — latest purchase price per product (+ variation)
            // Fetched all at once to avoid N+1
            $latestPrices = DB::table('purchase_invoice_items')
                ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
                ->whereNull('purchase_invoices.deleted_at')
                ->select(
                    'purchase_invoice_items.item_id as product_id',
                    'purchase_invoice_items.variation_id',
                    // SUBSTRING_INDEX trick: latest price = last element of DESC-ordered list
                    DB::raw('SUBSTRING_INDEX(
                        GROUP_CONCAT(purchase_invoice_items.price ORDER BY purchase_invoice_items.id DESC),
                        ",", 1
                    ) as latest_price')
                )
                ->groupBy('purchase_invoice_items.item_id', 'purchase_invoice_items.variation_id')
                ->get()
                ->keyBy(fn($r) => $r->product_id . '_' . ($r->variation_id ?? 'null'));

            // Step 3 — attach COGS + profit to each row
            $salesRows = $salesRows->map(function ($row) use ($latestPrices) {
                $key         = $row->product_id . '_' . ($row->variation_id ?? 'null');
                $unitCost    = (float) ($latestPrices[$key]->latest_price ?? 0);
                $row->cogs   = $unitCost * (float) $row->qty_sold;
                $row->profit = (float) $row->revenue - $row->cogs;
                return $row;
            });

            // Step 4 — pivot into product report structure
            foreach ($salesRows as $row) {
                $pid = $row->product_id;
                $mon = (int) $row->month;

                if (!isset($productReport[$pid])) {
                    $productReport[$pid] = [
                        'name'     => $row->product_name,
                        'category' => $row->category_name,
                        'months'   => array_fill(1, 12, ['revenue' => 0, 'cogs' => 0, 'profit' => 0, 'qty' => 0]),
                        'total'    => ['revenue' => 0, 'cogs' => 0, 'profit' => 0, 'qty' => 0],
                    ];
                }
                $productReport[$pid]['months'][$mon]['revenue'] += (float) $row->revenue;
                $productReport[$pid]['months'][$mon]['cogs']    += (float) $row->cogs;
                $productReport[$pid]['months'][$mon]['profit']  += (float) $row->profit;
                $productReport[$pid]['months'][$mon]['qty']     += (float) $row->qty_sold;
                $productReport[$pid]['total']['revenue']        += (float) $row->revenue;
                $productReport[$pid]['total']['cogs']           += (float) $row->cogs;
                $productReport[$pid]['total']['profit']         += (float) $row->profit;
                $productReport[$pid]['total']['qty']            += (float) $row->qty_sold;
            }
            uasort($productReport, fn($a, $b) => $b['total']['revenue'] <=> $a['total']['revenue']);

            // Step 5 — pivot into category report structure
            foreach ($salesRows as $row) {
                $cid = $row->category_id;
                $mon = (int) $row->month;

                if (!isset($categoryReport[$cid])) {
                    $categoryReport[$cid] = [
                        'name'   => $row->category_name,
                        'months' => array_fill(1, 12, ['revenue' => 0, 'cogs' => 0, 'profit' => 0, 'qty' => 0]),
                        'total'  => ['revenue' => 0, 'cogs' => 0, 'profit' => 0, 'qty' => 0],
                    ];
                }
                $categoryReport[$cid]['months'][$mon]['revenue'] += (float) $row->revenue;
                $categoryReport[$cid]['months'][$mon]['cogs']    += (float) $row->cogs;
                $categoryReport[$cid]['months'][$mon]['profit']  += (float) $row->profit;
                $categoryReport[$cid]['months'][$mon]['qty']     += (float) $row->qty_sold;
                $categoryReport[$cid]['total']['revenue']        += (float) $row->revenue;
                $categoryReport[$cid]['total']['cogs']           += (float) $row->cogs;
                $categoryReport[$cid]['total']['profit']         += (float) $row->profit;
                $categoryReport[$cid]['total']['qty']            += (float) $row->qty_sold;
            }
            uasort($categoryReport, fn($a, $b) => $b['total']['revenue'] <=> $a['total']['revenue']);

            // Grand monthly totals
            foreach ($salesRows as $row) {
                $m = (int) $row->month;
                $grandMonthly[$m]['revenue'] += (float) $row->revenue;
                $grandMonthly[$m]['cogs']    += (float) $row->cogs;
                $grandMonthly[$m]['profit']  += (float) $row->profit;
            }
        }

        $customers = ChartOfAccounts::where('account_type', 'customer')->orderBy('name')->get();

        return view('reports.sales_reports', compact(
            // Sales data
            'invoices', 'returns', 'receipts',
            'totalSold', 'totalReturned', 'totalReceived', 'netReceivable',
            'customerSummary', 'customers',
            'customerId', 'dateFrom', 'dateTo', 'type', 'tab',
            // Profit data
            'productReport', 'categoryReport', 'grandMonthly',
            'profitMonths', 'profitYear',
            'profitCategories', 'profitProducts',
            'profitCategoryId', 'profitProductId'
        ));
    }
}