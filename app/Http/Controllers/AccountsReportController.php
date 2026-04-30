<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountsReportController extends Controller
{
    public function accounts(Request $request)
    {
        // ── Filters ───────────────────────────────────────────────
        $tab       = $request->input('tab', 'GL');
        $accountId = $request->input('account_id');
        $dateFrom  = $request->input('date_from', date('Y-m-01'));
        $dateTo    = $request->input('date_to',   date('Y-m-d'));

        $allAccounts  = ChartOfAccounts::orderBy('account_type')->orderBy('name')->get();
        $cashAccounts = ChartOfAccounts::where('account_type', 'cash')->orderBy('name')->get();
        $bankAccounts = ChartOfAccounts::where('account_type', 'bank')->orderBy('name')->get();

        // ── Defaults ──────────────────────────────────────────────
        $ledger       = collect();
        $trialBalance = collect();
        $receivables  = collect();
        $payables     = collect();
        $cashBook     = collect();
        $bankBook     = collect();
        $journal      = collect();
        $expenseData  = collect();
        $cashFlow     = [];
        $plData       = [];
        $bsData       = [];

        // ─────────────────────────────────────────────────────────
        // TAB: GENERAL LEDGER
        //
        // Shows every voucher line once per account side.
        // When no account selected: shows all voucher lines (each
        // voucher appears as TWO rows — one DR, one CR).
        // When account selected: shows only lines affecting that account.
        // Running balance is meaningful only when one account is selected.
        // ─────────────────────────────────────────────────────────
        if ($tab === 'GL') {

            // DR side rows
            $drQ = DB::table('vouchers')
                ->join('chart_of_accounts as dr_acc', 'vouchers.ac_dr_sid', '=', 'dr_acc.id')
                ->join('chart_of_accounts as cr_acc', 'vouchers.ac_cr_sid', '=', 'cr_acc.id')
                ->select(
                    DB::raw('vouchers.id as id'),
                    DB::raw('vouchers.date as date'),
                    DB::raw('vouchers.voucher_type as voucher_type'),
                    DB::raw('vouchers.reference as reference'),
                    DB::raw('vouchers.remarks as remarks'),
                    DB::raw('vouchers.amount as amount'),
                    DB::raw("'dr' as side"),
                    DB::raw('dr_acc.name as account_name'),
                    DB::raw('cr_acc.name as opposite_account')
                )
                ->whereNull('vouchers.deleted_at')
                ->whereBetween('vouchers.date', [$dateFrom, $dateTo]);

            // CR side rows
            $crQ = DB::table('vouchers')
                ->join('chart_of_accounts as dr_acc', 'vouchers.ac_dr_sid', '=', 'dr_acc.id')
                ->join('chart_of_accounts as cr_acc', 'vouchers.ac_cr_sid', '=', 'cr_acc.id')
                ->select(
                    DB::raw('vouchers.id as id'),
                    DB::raw('vouchers.date as date'),
                    DB::raw('vouchers.voucher_type as voucher_type'),
                    DB::raw('vouchers.reference as reference'),
                    DB::raw('vouchers.remarks as remarks'),
                    DB::raw('vouchers.amount as amount'),
                    DB::raw("'cr' as side"),
                    DB::raw('cr_acc.name as account_name'),
                    DB::raw('dr_acc.name as opposite_account')
                )
                ->whereNull('vouchers.deleted_at')
                ->whereBetween('vouchers.date', [$dateFrom, $dateTo]);

            if ($accountId) {
                // Only rows where this account is affected
                $drQ->where('vouchers.ac_dr_sid', $accountId);
                $crQ->where('vouchers.ac_cr_sid', $accountId);
            }

            $ledger = $drQ->union($crQ)->orderBy('date')->orderBy('id')->get();

            // Running balance — only makes sense when one account is selected
            $runningBalance = 0;
            $ledger = $ledger->map(function ($row) use (&$runningBalance, $accountId) {
                if ($accountId) {
                    $runningBalance += $row->side === 'dr' ? $row->amount : -$row->amount;
                    $row->running_balance = $runningBalance;
                } else {
                    $row->running_balance = null; // not meaningful across all accounts
                }
                return $row;
            });
        }

        // ─────────────────────────────────────────────────────────
        // TAB: TRIAL BALANCE
        //
        // Each account: total DR movements, total CR movements,
        // and net balance. Sorted by account type then name.
        // ─────────────────────────────────────────────────────────
        if ($tab === 'TB') {
            $drTotals = DB::table('vouchers')
                ->select('ac_dr_sid as account_id', DB::raw('SUM(amount) as total_dr'))
                ->whereNull('deleted_at')
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->groupBy('ac_dr_sid')->get()->keyBy('account_id');

            $crTotals = DB::table('vouchers')
                ->select('ac_cr_sid as account_id', DB::raw('SUM(amount) as total_cr'))
                ->whereNull('deleted_at')
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->groupBy('ac_cr_sid')->get()->keyBy('account_id');

            $allIds = $drTotals->keys()->merge($crTotals->keys())->unique();
            $coaMap = ChartOfAccounts::whereIn('id', $allIds)->get()->keyBy('id');

            // Natural balance direction per account type:
            // DR-nature: asset, expense, cogs — positive balance = DR > CR
            // CR-nature: liability, equity, revenue, vendor, customer — positive balance = CR > DR
            $drNature = ['asset', 'expense', 'cogs', 'cash', 'bank', 'inventory'];

            $trialBalance = $allIds->map(function ($id) use ($drTotals, $crTotals, $coaMap, $drNature) {
                $dr   = (float) ($drTotals[$id]->total_dr ?? 0);
                $cr   = (float) ($crTotals[$id]->total_cr ?? 0);
                $coa  = $coaMap[$id] ?? null;
                $type = $coa->account_type ?? '';

                // Balance shown in natural direction
                $isDrNature = in_array($type, $drNature);
                $balance    = $isDrNature ? ($dr - $cr) : ($cr - $dr);

                return [
                    'id'           => $id,
                    'name'         => $coa->name         ?? '—',
                    'account_code' => $coa->account_code ?? '—',
                    'account_type' => $type,
                    'total_dr'     => $dr,
                    'total_cr'     => $cr,
                    'balance'      => $balance,
                    'balance_side' => $isDrNature ? 'DR' : 'CR',
                ];
            })->sortBy([['account_type', 'asc'], ['name', 'asc']])->values();
        }

        // ─────────────────────────────────────────────────────────
        // TAB: PROFIT & LOSS
        //
        // Revenue  = CR side of vouchers where CR account type = 'revenue'
        // COGS     = DR side of vouchers where DR account type = 'cogs'
        // Expenses = DR side of vouchers where DR account type = 'expense'
        //
        // Using account_type filter (not reference pattern) avoids
        // the inventory/stock-in-hand bleed-in problem.
        // ─────────────────────────────────────────────────────────
        if ($tab === 'PL') {

            // Revenue accounts (CR nature, account_type = 'revenue')
            $revenueAccounts = DB::table('vouchers')
                ->join('chart_of_accounts as coa', 'vouchers.ac_cr_sid', '=', 'coa.id')
                ->select('coa.id', 'coa.name', 'coa.account_code',
                         DB::raw('SUM(vouchers.amount) as total'))
                ->where('coa.account_type', 'revenue')
                ->whereNull('vouchers.deleted_at')
                ->whereBetween('vouchers.date', [$dateFrom, $dateTo])
                ->groupBy('coa.id', 'coa.name', 'coa.account_code')
                ->orderBy('coa.name')
                ->get();

            // COGS accounts (DR nature, account_type = 'cogs')
            $cogsAccounts = DB::table('vouchers')
                ->join('chart_of_accounts as coa', 'vouchers.ac_dr_sid', '=', 'coa.id')
                ->select('coa.id', 'coa.name', 'coa.account_code',
                         DB::raw('SUM(vouchers.amount) as total'))
                ->where('coa.account_type', 'cogs')
                ->whereNull('vouchers.deleted_at')
                ->whereBetween('vouchers.date', [$dateFrom, $dateTo])
                ->groupBy('coa.id', 'coa.name', 'coa.account_code')
                ->orderBy('coa.name')
                ->get();

            // Expense accounts (DR nature, account_type = 'expense')
            $expenseAccounts = DB::table('vouchers')
                ->join('chart_of_accounts as coa', 'vouchers.ac_dr_sid', '=', 'coa.id')
                ->select('coa.id', 'coa.name', 'coa.account_code',
                         DB::raw('SUM(vouchers.amount) as total'))
                ->where('coa.account_type', 'expense')
                ->whereNull('vouchers.deleted_at')
                ->whereBetween('vouchers.date', [$dateFrom, $dateTo])
                ->groupBy('coa.id', 'coa.name', 'coa.account_code')
                ->orderBy('coa.name')
                ->get();

            $totalRevenue  = (float) $revenueAccounts->sum('total');
            $totalCogs     = (float) $cogsAccounts->sum('total');
            $grossProfit   = $totalRevenue - $totalCogs;
            $totalExpenses = (float) $expenseAccounts->sum('total');
            $netProfit     = $grossProfit - $totalExpenses;

            $plData = compact(
                'revenueAccounts', 'cogsAccounts', 'expenseAccounts',
                'totalRevenue', 'totalCogs', 'grossProfit', 'totalExpenses', 'netProfit'
            );
        }

        // ─────────────────────────────────────────────────────────
        // TAB: BALANCE SHEET
        //
        // Assets    = DR-nature accounts (asset, cash, bank, inventory)
        //             Balance = total DR movements - total CR movements
        // Liabilities = CR-nature accounts (liability, vendor)
        //             Balance = total CR - total DR
        // Equity    = CR-nature accounts (equity)
        //             Balance = total CR - total DR
        //
        // Using CUMULATIVE (all-time) data for BS — not date-filtered —
        // because BS is a snapshot of position, not a period summary.
        // ─────────────────────────────────────────────────────────
        if ($tab === 'BS') {

            $assetTypes     = ['asset', 'cash', 'bank', 'inventory'];
            $liabilityTypes = ['liability', 'vendor'];
            $equityTypes    = ['equity'];
            $bsTypes        = array_merge($assetTypes, $liabilityTypes, $equityTypes);

            // Cumulative DR per account (all time up to $dateTo)
            $drMap = DB::table('vouchers')
                ->join('chart_of_accounts as coa', 'vouchers.ac_dr_sid', '=', 'coa.id')
                ->select('coa.id', DB::raw('SUM(vouchers.amount) as total'))
                ->whereNull('vouchers.deleted_at')
                ->where('vouchers.date', '<=', $dateTo)
                ->whereIn('coa.account_type', $bsTypes)
                ->groupBy('coa.id')
                ->get()->keyBy('id');

            $crMap = DB::table('vouchers')
                ->join('chart_of_accounts as coa', 'vouchers.ac_cr_sid', '=', 'coa.id')
                ->select('coa.id', DB::raw('SUM(vouchers.amount) as total'))
                ->whereNull('vouchers.deleted_at')
                ->where('vouchers.date', '<=', $dateTo)
                ->whereIn('coa.account_type', $bsTypes)
                ->groupBy('coa.id')
                ->get()->keyBy('id');

            $allBsIds = $drMap->keys()->merge($crMap->keys())->unique();
            $coaMap   = ChartOfAccounts::whereIn('id', $allBsIds)->get()->keyBy('id');

            $assets      = collect();
            $liabilities = collect();
            $equity      = collect();

            foreach ($allBsIds as $id) {
                $dr  = (float) ($drMap[$id]->total ?? 0);
                $cr  = (float) ($crMap[$id]->total ?? 0);
                $coa = $coaMap[$id] ?? null;
                if (!$coa) continue;
                $type = $coa->account_type;

                if (in_array($type, $assetTypes)) {
                    $balance = $dr - $cr; // DR-normal
                    if ($balance != 0) {
                        $assets->push(['name' => $coa->name, 'account_code' => $coa->account_code, 'balance' => $balance]);
                    }
                } elseif (in_array($type, $liabilityTypes)) {
                    $balance = $cr - $dr; // CR-normal
                    if ($balance != 0) {
                        $liabilities->push(['name' => $coa->name, 'account_code' => $coa->account_code, 'balance' => $balance]);
                    }
                } elseif (in_array($type, $equityTypes)) {
                    $balance = $cr - $dr; // CR-normal
                    if ($balance != 0) {
                        $equity->push(['name' => $coa->name, 'account_code' => $coa->account_code, 'balance' => $balance]);
                    }
                }
            }

            // Net profit flows into equity on Balance Sheet
            $netProfitForBs = (float) DB::table('vouchers')
                ->join('chart_of_accounts as coa', 'vouchers.ac_cr_sid', '=', 'coa.id')
                ->where('coa.account_type', 'revenue')
                ->whereNull('vouchers.deleted_at')
                ->where('vouchers.date', '<=', $dateTo)
                ->sum('vouchers.amount')
                - (float) DB::table('vouchers')
                ->join('chart_of_accounts as coa', 'vouchers.ac_dr_sid', '=', 'coa.id')
                ->whereIn('coa.account_type', ['cogs', 'expense'])
                ->whereNull('vouchers.deleted_at')
                ->where('vouchers.date', '<=', $dateTo)
                ->sum('vouchers.amount');

            $bsData = [
                'assets'          => $assets->sortBy('name')->values(),
                'liabilities'     => $liabilities->sortBy('name')->values(),
                'equity'          => $equity->sortBy('name')->values(),
                'totalAssets'     => $assets->sum('balance'),
                'totalLiab'       => $liabilities->sum('balance'),
                'totalEquity'     => $equity->sum('balance'),
                'netProfitForBs'  => $netProfitForBs,
            ];
        }

        // ─────────────────────────────────────────────────────────
        // TAB: PARTY LEDGER
        // Per-party statement with running balance
        // ─────────────────────────────────────────────────────────
        if ($tab === 'PARTY' && $accountId) {

            $drQ = DB::table('vouchers')
                ->join('chart_of_accounts as cr_acc', 'vouchers.ac_cr_sid', '=', 'cr_acc.id')
                ->select(
                    DB::raw('vouchers.id as id'),
                    DB::raw('vouchers.date as date'),
                    DB::raw('vouchers.voucher_type as voucher_type'),
                    DB::raw('vouchers.reference as reference'),
                    DB::raw('vouchers.remarks as remarks'),
                    DB::raw('vouchers.amount as amount'),
                    DB::raw("'dr' as side"),
                    DB::raw('cr_acc.name as opposite_account')
                )
                ->where('vouchers.ac_dr_sid', $accountId)
                ->whereNull('vouchers.deleted_at')
                ->whereBetween('vouchers.date', [$dateFrom, $dateTo]);

            $crQ = DB::table('vouchers')
                ->join('chart_of_accounts as dr_acc', 'vouchers.ac_dr_sid', '=', 'dr_acc.id')
                ->select(
                    DB::raw('vouchers.id as id'),
                    DB::raw('vouchers.date as date'),
                    DB::raw('vouchers.voucher_type as voucher_type'),
                    DB::raw('vouchers.reference as reference'),
                    DB::raw('vouchers.remarks as remarks'),
                    DB::raw('vouchers.amount as amount'),
                    DB::raw("'cr' as side"),
                    DB::raw('dr_acc.name as opposite_account')
                )
                ->where('vouchers.ac_cr_sid', $accountId)
                ->whereNull('vouchers.deleted_at')
                ->whereBetween('vouchers.date', [$dateFrom, $dateTo]);

            $ledger = $drQ->union($crQ)->orderBy('date')->orderBy('id')->get();

            $runningBalance = 0;
            $ledger = $ledger->map(function ($row) use (&$runningBalance) {
                $runningBalance += $row->side === 'dr' ? $row->amount : -$row->amount;
                $row->running_balance = $runningBalance;
                return $row;
            });
        }

        // ─────────────────────────────────────────────────────────
        // TAB: RECEIVABLES
        // ─────────────────────────────────────────────────────────
        if ($tab === 'RECV') {
            $recvQuery = ChartOfAccounts::where('account_type', 'customer')->orderBy('name');
            if ($accountId) $recvQuery->where('id', $accountId);

            $receivables = $recvQuery->get()->map(function ($coa) use ($dateFrom, $dateTo) {
                $q = fn($col) => DB::table('vouchers')
                    ->where($col, $coa->id)->whereNull('deleted_at')
                    ->whereBetween('date', [$dateFrom, $dateTo]);

                $invoiced = (float) $q('ac_dr_sid')->where('voucher_type','journal')->where('reference','like','SI-%')->sum('amount');
                $returned = (float) $q('ac_cr_sid')->where('voucher_type','journal')->where('reference','like','SR-%')->sum('amount');
                $received = (float) $q('ac_cr_sid')->where('voucher_type','receipt')->sum('amount');

                return [
                    'name'           => $coa->name,
                    'account_code'   => $coa->account_code,
                    'credit_limit'   => (float) ($coa->credit_limit ?? 0),
                    'total_invoiced' => $invoiced,
                    'total_returned' => $returned,
                    'total_received' => $received,
                    'outstanding'    => $invoiced - $returned - $received,
                ];
            })->filter(fn($r) => $r['total_invoiced'] > 0 || $accountId)->values();
        }

        // ─────────────────────────────────────────────────────────
        // TAB: PAYABLES
        // ─────────────────────────────────────────────────────────
        if ($tab === 'PAY') {
            $payQuery = ChartOfAccounts::where('account_type', 'vendor')->orderBy('name');
            if ($accountId) $payQuery->where('id', $accountId);

            $payables = $payQuery->get()->map(function ($coa) use ($dateFrom, $dateTo) {
                $q = fn($col) => DB::table('vouchers')
                    ->where($col, $coa->id)->whereNull('deleted_at')
                    ->whereBetween('date', [$dateFrom, $dateTo]);

                $purchased = (float) $q('ac_cr_sid')->where('voucher_type','journal')->where('reference','like','PI-%')->sum('amount');
                $returned  = (float) $q('ac_dr_sid')->where('voucher_type','journal')->where('reference','like','PR-%')->sum('amount');
                $paid      = (float) $q('ac_dr_sid')->where('voucher_type','payment')->sum('amount');

                return [
                    'name'            => $coa->name,
                    'account_code'    => $coa->account_code,
                    'total_purchased' => $purchased,
                    'total_returned'  => $returned,
                    'total_paid'      => $paid,
                    'outstanding'     => $purchased - $returned - $paid,
                ];
            })->filter(fn($r) => $r['total_purchased'] > 0 || $accountId)->values();
        }

        // ─────────────────────────────────────────────────────────
        // TAB: CASH BOOK
        //
        // Only RECEIPT and PAYMENT vouchers touching cash accounts.
        // Journal vouchers are excluded — they are not cash movements.
        // Cash IN  = DR cash account (money received)
        // Cash OUT = CR cash account (money paid out)
        // ─────────────────────────────────────────────────────────
        if ($tab === 'CASH') {
            $cashIds = $accountId ? collect([$accountId]) : $cashAccounts->pluck('id');

            $inQ = DB::table('vouchers')
                ->join('chart_of_accounts as opp', 'vouchers.ac_cr_sid', '=', 'opp.id')
                ->select(
                    DB::raw('vouchers.id as id'),
                    DB::raw('vouchers.date as date'),
                    DB::raw('vouchers.reference as reference'),
                    DB::raw('vouchers.remarks as remarks'),
                    DB::raw('vouchers.amount as amount'),
                    DB::raw('opp.name as opposite_account'),
                    DB::raw("'IN' as direction"),
                    DB::raw('vouchers.voucher_type as voucher_type')
                )
                ->whereIn('vouchers.ac_dr_sid', $cashIds)
                ->whereIn('vouchers.voucher_type', ['receipt', 'payment'])
                ->whereNull('vouchers.deleted_at')
                ->whereBetween('vouchers.date', [$dateFrom, $dateTo]);

            $outQ = DB::table('vouchers')
                ->join('chart_of_accounts as opp', 'vouchers.ac_dr_sid', '=', 'opp.id')
                ->select(
                    DB::raw('vouchers.id as id'),
                    DB::raw('vouchers.date as date'),
                    DB::raw('vouchers.reference as reference'),
                    DB::raw('vouchers.remarks as remarks'),
                    DB::raw('vouchers.amount as amount'),
                    DB::raw('opp.name as opposite_account'),
                    DB::raw("'OUT' as direction"),
                    DB::raw('vouchers.voucher_type as voucher_type')
                )
                ->whereIn('vouchers.ac_cr_sid', $cashIds)
                ->whereIn('vouchers.voucher_type', ['receipt', 'payment'])
                ->whereNull('vouchers.deleted_at')
                ->whereBetween('vouchers.date', [$dateFrom, $dateTo]);

            $cashBook = $inQ->union($outQ)->orderBy('date')->orderBy('id')->get();
        }

        // ─────────────────────────────────────────────────────────
        // TAB: BANK BOOK
        // Same logic as Cash Book but for bank accounts
        // ─────────────────────────────────────────────────────────
        if ($tab === 'BANK') {
            $bankIds = $accountId ? collect([$accountId]) : $bankAccounts->pluck('id');

            $inQ = DB::table('vouchers')
                ->join('chart_of_accounts as opp', 'vouchers.ac_cr_sid', '=', 'opp.id')
                ->select(
                    DB::raw('vouchers.id as id'),
                    DB::raw('vouchers.date as date'),
                    DB::raw('vouchers.reference as reference'),
                    DB::raw('vouchers.remarks as remarks'),
                    DB::raw('vouchers.amount as amount'),
                    DB::raw('opp.name as opposite_account'),
                    DB::raw("'IN' as direction"),
                    DB::raw('vouchers.voucher_type as voucher_type')
                )
                ->whereIn('vouchers.ac_dr_sid', $bankIds)
                ->whereIn('vouchers.voucher_type', ['receipt', 'payment'])
                ->whereNull('vouchers.deleted_at')
                ->whereBetween('vouchers.date', [$dateFrom, $dateTo]);

            $outQ = DB::table('vouchers')
                ->join('chart_of_accounts as opp', 'vouchers.ac_dr_sid', '=', 'opp.id')
                ->select(
                    DB::raw('vouchers.id as id'),
                    DB::raw('vouchers.date as date'),
                    DB::raw('vouchers.reference as reference'),
                    DB::raw('vouchers.remarks as remarks'),
                    DB::raw('vouchers.amount as amount'),
                    DB::raw('opp.name as opposite_account'),
                    DB::raw("'OUT' as direction"),
                    DB::raw('vouchers.voucher_type as voucher_type')
                )
                ->whereIn('vouchers.ac_cr_sid', $bankIds)
                ->whereIn('vouchers.voucher_type', ['receipt', 'payment'])
                ->whereNull('vouchers.deleted_at')
                ->whereBetween('vouchers.date', [$dateFrom, $dateTo]);

            $bankBook = $inQ->union($outQ)->orderBy('date')->orderBy('id')->get();
        }

        // ─────────────────────────────────────────────────────────
        // TAB: JOURNAL / DAY BOOK
        // All vouchers grouped by voucher (one row per voucher)
        // ─────────────────────────────────────────────────────────
        if ($tab === 'JOURNAL') {
            $journal = DB::table('vouchers')
                ->join('chart_of_accounts as dr_acc', 'vouchers.ac_dr_sid', '=', 'dr_acc.id')
                ->join('chart_of_accounts as cr_acc', 'vouchers.ac_cr_sid', '=', 'cr_acc.id')
                ->select(
                    'vouchers.id', 'vouchers.date', 'vouchers.voucher_type',
                    'vouchers.reference', 'vouchers.remarks', 'vouchers.amount',
                    'dr_acc.name as dr_account', 'cr_acc.name as cr_account'
                )
                ->whereNull('vouchers.deleted_at')
                ->whereBetween('vouchers.date', [$dateFrom, $dateTo])
                ->orderBy('vouchers.date')
                ->orderBy('vouchers.id')
                ->get();
        }

        // ─────────────────────────────────────────────────────────
        // TAB: EXPENSE ANALYSIS
        // ─────────────────────────────────────────────────────────
        if ($tab === 'EXPENSE') {
            $expenseData = DB::table('vouchers')
                ->join('chart_of_accounts as coa', 'vouchers.ac_dr_sid', '=', 'coa.id')
                ->select(
                    'coa.id', 'coa.name', 'coa.account_code',
                    DB::raw('SUM(vouchers.amount) as total'),
                    DB::raw('COUNT(vouchers.id) as txn_count')
                )
                ->where('coa.account_type', 'expense')
                ->whereNull('vouchers.deleted_at')
                ->whereBetween('vouchers.date', [$dateFrom, $dateTo])
                ->groupBy('coa.id', 'coa.name', 'coa.account_code')
                ->orderByDesc('total')
                ->get();
        }

        // ─────────────────────────────────────────────────────────
        // TAB: CASH FLOW
        // Only RECEIPT and PAYMENT vouchers through cash/bank accounts.
        // Summarised by voucher_type and counter-party account type.
        // ─────────────────────────────────────────────────────────
        if ($tab === 'CASHFLOW') {
            $cashBankIds = ChartOfAccounts::whereIn('account_type', ['cash', 'bank'])->pluck('id');

            $cfInRows = DB::table('vouchers')
                ->join('chart_of_accounts as opp', 'vouchers.ac_cr_sid', '=', 'opp.id')
                ->select(
                    'vouchers.date', 'vouchers.reference', 'vouchers.remarks',
                    'vouchers.voucher_type', 'vouchers.amount',
                    'opp.name as from_account', 'opp.account_type as from_type'
                )
                ->whereIn('vouchers.ac_dr_sid', $cashBankIds)
                ->whereIn('vouchers.voucher_type', ['receipt', 'payment'])
                ->whereNull('vouchers.deleted_at')
                ->whereBetween('vouchers.date', [$dateFrom, $dateTo])
                ->orderBy('vouchers.date')
                ->get();

            $cfOutRows = DB::table('vouchers')
                ->join('chart_of_accounts as opp', 'vouchers.ac_dr_sid', '=', 'opp.id')
                ->select(
                    'vouchers.date', 'vouchers.reference', 'vouchers.remarks',
                    'vouchers.voucher_type', 'vouchers.amount',
                    'opp.name as to_account', 'opp.account_type as to_type'
                )
                ->whereIn('vouchers.ac_cr_sid', $cashBankIds)
                ->whereIn('vouchers.voucher_type', ['receipt', 'payment'])
                ->whereNull('vouchers.deleted_at')
                ->whereBetween('vouchers.date', [$dateFrom, $dateTo])
                ->orderBy('vouchers.date')
                ->get();

            $cashFlow = [
                'cash_in'       => $cfInRows,
                'cash_out'      => $cfOutRows,
                'total_in'      => $cfInRows->sum('amount'),
                'total_out'     => $cfOutRows->sum('amount'),
                'net_cash_flow' => $cfInRows->sum('amount') - $cfOutRows->sum('amount'),
            ];
        }

        return view('reports.accounts_reports', compact(
            'tab', 'accountId', 'dateFrom', 'dateTo',
            'allAccounts', 'cashAccounts', 'bankAccounts',
            'ledger', 'trialBalance',
            'receivables', 'payables',
            'cashBook', 'bankBook',
            'journal', 'expenseData',
            'cashFlow', 'plData', 'bsData'
        ));
    }
}