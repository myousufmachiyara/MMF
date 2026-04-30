<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoice;
use App\Models\ChartOfAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseReportController extends Controller
{
    public function purchaseReports(Request $request)
    {
        // ── Filters ───────────────────────────────────────────────
        // $tab drives which pane is active — matches blade tab keys
        $tab      = $request->input('tab', 'PUR'); // PUR | PR | PAY | VWP
        $vendorId = $request->input('vendor_id');
        $dateFrom = $request->input('date_from', date('Y-m-01'));
        $dateTo   = $request->input('date_to',   date('Y-m-d'));

        // ── Purchase Invoices (tab PUR) ───────────────────────────
        // DR Inventory / CR Vendor
        $invoicesQuery = PurchaseInvoice::with(['vendor', 'items', 'purchaseOrder'])
            ->whereNull('deleted_at');

        if ($vendorId) $invoicesQuery->where('vendor_id', $vendorId);
        if ($dateFrom) $invoicesQuery->where('invoice_date', '>=', $dateFrom);
        if ($dateTo)   $invoicesQuery->where('invoice_date', '<=', $dateTo);

        $invoices = $tab === 'PUR'
            ? $invoicesQuery->latest('invoice_date')->get()
            : collect();

        // ── Purchase Returns (tab PR) ─────────────────────────────
        // DR Vendor / CR Inventory → voucher_type=journal, reference like PR-%
        $returnsQuery = DB::table('vouchers')
            ->join('chart_of_accounts as vendor', 'vouchers.ac_cr_sid', '=', 'vendor.id')
            ->select(
                'vouchers.id',
                'vouchers.date',
                'vouchers.amount',
                'vouchers.reference',
                'vouchers.remarks',
                'vendor.name as vendor_name',
                'vendor.id as vendor_id'
            )
            ->where('vouchers.voucher_type', 'journal')
            ->where('vouchers.reference', 'like', 'PR-%')
            ->whereNull('vouchers.deleted_at');

        if ($vendorId) $returnsQuery->where('vouchers.ac_cr_sid', $vendorId);
        if ($dateFrom) $returnsQuery->where('vouchers.date', '>=', $dateFrom);
        if ($dateTo)   $returnsQuery->where('vouchers.date', '<=', $dateTo);

        $returns = $tab === 'PR'
            ? $returnsQuery->orderByDesc('vouchers.date')->get()
            : collect();

        // ── Payments to Vendors (tab PAY) ─────────────────────────
        // DR Vendor / CR Cash/Bank → voucher_type=payment
        $paymentsQuery = DB::table('vouchers')
            ->join('chart_of_accounts as vendor',   'vouchers.ac_dr_sid', '=', 'vendor.id')
            ->join('chart_of_accounts as cashbank',  'vouchers.ac_cr_sid', '=', 'cashbank.id')
            ->select(
                'vouchers.id',
                'vouchers.date',
                'vouchers.amount',
                'vouchers.reference',
                'vouchers.remarks',
                'vendor.name as vendor_name',
                'vendor.id as vendor_id',
                'cashbank.name as cashbank_name'
            )
            ->where('vouchers.voucher_type', 'payment')
            ->whereNull('vouchers.deleted_at');

        if ($vendorId) $paymentsQuery->where('vouchers.ac_dr_sid', $vendorId);
        if ($dateFrom) $paymentsQuery->where('vouchers.date', '>=', $dateFrom);
        if ($dateTo)   $paymentsQuery->where('vouchers.date', '<=', $dateTo);

        $payments = $tab === 'PAY'
            ? $paymentsQuery->orderByDesc('vouchers.date')->get()
            : collect();

        // ── Vendor-wise summary (tab VWP) ─────────────────────────
        // Need data from all three sources for this tab
        $vendorSummary = [];

        if ($tab === 'VWP') {
            // Re-run all three queries without tab restriction for summary
            $allInvoices = $invoicesQuery->latest('invoice_date')->get();
            $allReturns  = $returnsQuery->orderByDesc('vouchers.date')->get();
            $allPayments = $paymentsQuery->orderByDesc('vouchers.date')->get();

            foreach ($allInvoices as $inv) {
                $vid = $inv->vendor_id;
                $amt = $inv->items->sum(fn($i) => $i->quantity * $i->price);
                $vendorSummary[$vid]['name']      = $inv->vendor->name ?? '—';
                $vendorSummary[$vid]['purchased'] = ($vendorSummary[$vid]['purchased'] ?? 0) + $amt;
                $vendorSummary[$vid]['returned']  = $vendorSummary[$vid]['returned'] ?? 0;
                $vendorSummary[$vid]['paid']      = $vendorSummary[$vid]['paid']     ?? 0;
            }
            foreach ($allReturns as $ret) {
                $vid = $ret->vendor_id;
                $vendorSummary[$vid]['name']      = $ret->vendor_name;
                $vendorSummary[$vid]['purchased'] = $vendorSummary[$vid]['purchased'] ?? 0;
                $vendorSummary[$vid]['returned']  = ($vendorSummary[$vid]['returned'] ?? 0) + $ret->amount;
                $vendorSummary[$vid]['paid']      = $vendorSummary[$vid]['paid']     ?? 0;
            }
            foreach ($allPayments as $pay) {
                $vid = $pay->vendor_id;
                $vendorSummary[$vid]['name']      = $pay->vendor_name;
                $vendorSummary[$vid]['purchased'] = $vendorSummary[$vid]['purchased'] ?? 0;
                $vendorSummary[$vid]['returned']  = $vendorSummary[$vid]['returned'] ?? 0;
                $vendorSummary[$vid]['paid']      = ($vendorSummary[$vid]['paid']    ?? 0) + $pay->amount;
            }
            foreach ($vendorSummary as &$v) {
                $v['net_payable'] = $v['purchased'] - $v['returned'] - $v['paid'];
            }
        }

        // ── Totals (only meaningful for the active tab) ───────────
        $totalPurchased = $invoices->sum(fn($inv) =>
            $inv->items->sum(fn($i) => $i->quantity * $i->price)
        );
        $totalReturned = $returns->sum('amount');
        $totalPaid     = $payments->sum('amount');

        $vendors = ChartOfAccounts::where('account_type', 'vendor')->orderBy('name')->get();

        // View name matches the actual blade file: resources/views/reports/purchase_reports.blade.php
        return view('reports.purchase_reports', compact(
            'tab',
            'invoices', 'returns', 'payments',
            'totalPurchased', 'totalReturned', 'totalPaid',
            'vendorSummary', 'vendors',
            'vendorId', 'dateFrom', 'dateTo'
        ));
    }
}