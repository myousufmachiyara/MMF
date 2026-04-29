<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoice;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Models\ChartOfAccounts;
use App\Models\Voucher;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today     = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();

        // ── Sales figures ─────────────────────────────────────────
        // Sum net amounts from sale invoice journal vouchers.
        // We read from vouchers (not sale_invoices) because vouchers
        // are the authoritative accounting record.

        $todaySales = Voucher::where('voucher_type', 'journal')
            ->whereDate('date', $today)
            ->where('ac_cr_sid', function ($q) {
                $q->select('id')->from('chart_of_accounts')
                  ->where('account_code', '401001')->limit(1);
            })
            ->sum('amount');

        $monthSales = Voucher::where('voucher_type', 'journal')
            ->whereBetween('date', [$monthStart, $today])
            ->where('ac_cr_sid', function ($q) {
                $q->select('id')->from('chart_of_accounts')
                  ->where('account_code', '401001')->limit(1);
            })
            ->sum('amount');

        // ── Receivables & Payables ────────────────────────────────
        // Total outstanding across all customer accounts.
        $totalReceivables = ChartOfAccounts::where('account_type', 'customer')
            ->selectRaw('SUM(receivables - payables) as net')
            ->value('net') ?? 0;

        // Total outstanding across all vendor accounts.
        $totalPayables = ChartOfAccounts::where('account_type', 'vendor')
            ->selectRaw('SUM(payables - receivables) as net')
            ->value('net') ?? 0;

        // ── Recent sale invoices (last 10) ────────────────────────
        $recentSaleInvoices = SaleInvoice::with('account')
            ->latest()
            ->limit(10)
            ->get();

        // ── Recent purchase invoices (last 10) ────────────────────
        $recentPurchaseInvoices = PurchaseInvoice::with('vendor')
            ->latest()
            ->limit(10)
            ->get();

        // ── Drop-ship invoices with no sale invoice yet ───────────
        // These are PIs where goods went directly to a customer
        // but no sale invoice has been created to bill them.
        // Shown as an alert on the dashboard.
        $unbilledDropships = PurchaseInvoice::with(['vendor', 'dropoffCustomer'])
            ->whereNotNull('dropoff_customer_id')
            ->whereDoesntHave('saleInvoice')
            ->latest()
            ->get();

        // ── Purchase Orders pending action ────────────────────────
        $pendingPOs = \App\Models\PurchaseOrder::with('vendor')
            ->whereIn('status', ['draft', 'ordered'])
            ->latest()
            ->limit(10)
            ->get();

        return view('home', compact(
            'todaySales',
            'monthSales',
            'totalReceivables',
            'totalPayables',
            'recentSaleInvoices',
            'recentPurchaseInvoices',
            'unbilledDropships',
            'pendingPOs',
        ));
    }
}