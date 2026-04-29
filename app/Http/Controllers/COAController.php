<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChartOfAccounts;
use App\Models\SubHeadOfAccounts;
use App\Services\CreditLimitService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class COAController extends Controller
{
    private const ACCOUNT_TYPES = [
        'customer',
        'vendor',
        'cash',
        'bank',
        'inventory',
        'liability',
        'equity',
        'revenue',
        'cogs',
        'expenses',
        'receivable',
        'payable',
    ];

    // ── Common validation rules (DRY) ────────────────────────────────
    private function baseRules(bool $forUpdate = false, ?int $ignoreId = null): array
    {
        $uniqueNameRule = Rule::unique('chart_of_accounts')->whereNull('deleted_at');
        if ($forUpdate && $ignoreId) {
            $uniqueNameRule = $uniqueNameRule->ignore($ignoreId);
        }

        return [
            'shoa_id'      => 'required|exists:sub_head_of_accounts,id',
            'name'         => ['required', 'string', 'max:255', $uniqueNameRule],
            'account_type' => ['nullable', 'string', Rule::in(self::ACCOUNT_TYPES)],
            'receivables'  => 'required|numeric',
            'payables'     => 'required|numeric',
            'credit_limit' => 'required|numeric|min:0',
            'credit_days'  => 'required|integer|min:0',   // ← NEW
            'opening_date' => 'required|date',
            'remarks'      => 'nullable|string|max:800',
            'address'      => 'nullable|string|max:250',
            'contact_no'   => 'nullable|string|max:250',
        ];
    }

    public function index(Request $request)
    {
        $subHeadOfAccounts = SubHeadOfAccounts::with('headOfAccount')->orderBy('id')->get();

        $query = ChartOfAccounts::with('subHeadOfAccount');

        if ($request->filled('subhead') && $request->subhead !== 'all') {
            $query->where('shoa_id', $request->subhead);
        }

        $chartOfAccounts = $query->latest()->get();

        return view('accounts.coa', compact('chartOfAccounts', 'subHeadOfAccounts'));
    }

    public function store(Request $request)
    {
        try {
            Log::info('[COA] Store called', ['user_id' => auth()->id()]);

            $validated = $request->validate($this->baseRules());

            // ── Auto-generate account code ────────────────────────
            $subHead  = SubHeadOfAccounts::findOrFail($request->shoa_id);
            $prefix   = $subHead->hoa_id . str_pad($subHead->id, 2, '0', STR_PAD_LEFT);

            $existingCodes = ChartOfAccounts::withTrashed()
                ->where('account_code', 'like', $prefix . '%')
                ->pluck('account_code')
                ->map(fn($code) => intval(substr($code, strlen($prefix))))
                ->sort()
                ->values();

            $nextNumber  = ($existingCodes->isEmpty() ? 0 : $existingCodes->last()) + 1;
            $accountCode = $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            Log::info('[COA] Generated account code', ['code' => $accountCode]);

            $account = ChartOfAccounts::create([
                'shoa_id'      => $request->shoa_id,
                'account_code' => $accountCode,
                'name'         => $request->name,
                'account_type' => $request->account_type,
                'receivables'  => $request->receivables,
                'payables'     => $request->payables,
                'credit_limit' => $request->credit_limit,
                'credit_days'  => $request->credit_days,   // ← NEW
                'opening_date' => $request->opening_date,
                'remarks'      => $request->remarks,
                'address'      => $request->address,
                'contact_no'   => $request->contact_no,
                'created_by'   => auth()->id(),
                'updated_by'   => auth()->id(),
            ]);

            Log::info('[COA] Account created', ['id' => $account->id, 'code' => $accountCode]);

            return redirect()->route('coa.index')
                ->with('success', 'Account created successfully.');

        } catch (\Exception $e) {
            Log::error('[COA] Store error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $account = ChartOfAccounts::with('subHeadOfAccount')->findOrFail($id);
        return response()->json($account);
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate($this->baseRules(forUpdate: true, ignoreId: (int) $id));

            $account = ChartOfAccounts::findOrFail($id);

            $account->update([
                'shoa_id'      => $request->shoa_id,
                'name'         => $request->name,
                'account_type' => $request->account_type,
                'receivables'  => $request->receivables,
                'payables'     => $request->payables,
                'credit_limit' => $request->credit_limit,
                'credit_days'  => $request->credit_days,   // ← NEW
                'opening_date' => $request->opening_date,
                'remarks'      => $request->remarks,
                'address'      => $request->address,
                'contact_no'   => $request->contact_no,
                'updated_by'   => auth()->id(),
            ]);

            Log::info('[COA] Account updated', ['id' => $id, 'user' => auth()->id()]);

            return redirect()->route('coa.index')
                ->with('success', 'Account updated successfully.');

        } catch (\Exception $e) {
            Log::error('[COA] Update error', ['message' => $e->getMessage()]);
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $account = ChartOfAccounts::with('subHeadOfAccount')->findOrFail($id);
        return response()->json($account);
    }

    public function destroy($id)
    {
        try {
            $account = ChartOfAccounts::findOrFail($id);

            $systemCodes = ['101001','102001','104001','201001','202001','301001','401001','402001','501001'];
            if (in_array($account->account_code, $systemCodes)) {
                return redirect()->back()
                    ->with('error', 'System account "' . $account->name . '" cannot be deleted.');
            }

            $account->delete();
            return redirect()->route('coa.index')->with('success', 'Account deleted successfully.');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // API endpoint — call this from your invoice controller BEFORE
    // saving a new invoice to check credit status for a customer.
    //
    // GET /coa/{id}/credit-check?invoice_date=2024-06-01
    // Returns JSON so the invoice form can show a live warning via AJAX.
    // ─────────────────────────────────────────────────────────────────
    public function creditCheck(Request $request, int $id)
    {
        $request->validate(['invoice_date' => 'nullable|date']);

        $result = CreditLimitService::check($id, $request->input('invoice_date'));

        return response()->json($result, $result['blocked'] ? 422 : 200);
    }
}