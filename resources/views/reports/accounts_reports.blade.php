@extends('layouts.app')
@section('title', 'Accounts Reports')
@section('content')
<style>
    @media print { .no-print { display: none !important; } }
    .narration { font-size: 11px; color: #888; font-style: italic; }
    .tab-badge  { font-size: 10px; }
    .pl-section { border-left: 4px solid #2c3e50; padding-left: 10px; margin-bottom: 20px; }
    .bs-section { border-left: 4px solid #16a085; padding-left: 10px; margin-bottom: 20px; }
    .in-flow    { color: #27ae60; font-weight: 600; }
    .out-flow   { color: #e74c3c; font-weight: 600; }
</style>

{{-- ── Tab Navigation ──────────────────────────────────────── --}}
<ul class="nav nav-tabs flex-wrap">
    @foreach([
        'GL'       => 'General Ledger',
        'TB'       => 'Trial Balance',
        'PL'       => 'Profit & Loss',
        'BS'       => 'Balance Sheet',
        'PARTY'    => 'Party Ledger',
        'RECV'     => 'Receivables',
        'PAY'      => 'Payables',
        'CASH'     => 'Cash Book',
        'BANK'     => 'Bank Book',
        'JOURNAL'  => 'Journal / Day Book',
        'EXPENSE'  => 'Expense Analysis',
        'CASHFLOW' => 'Cash Flow',
    ] as $key => $label)
    <li class="nav-item">
        <a class="nav-link {{ $tab === $key ? 'active fw-bold' : '' }}"
           href="{{ request()->fullUrlWithQuery(['tab' => $key]) }}">
            {{ $label }}
        </a>
    </li>
    @endforeach
</ul>

<div class="tab-content mt-3">

{{-- ════════════════════════════════════════════════════════════
     GENERAL LEDGER
════════════════════════════════════════════════════════════ --}}
@if($tab === 'GL')
<div class="card">
  <div class="card-body">
    <form method="GET" action="{{ route('reports.accounts') }}" class="no-print">
      <input type="hidden" name="tab" value="GL">
      <div class="row g-2 mb-3">
        <div class="col-md-2"><label>From</label>
          <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}"></div>
        <div class="col-md-2"><label>To</label>
          <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}"></div>
        <div class="col-md-4"><label>Account (optional)</label>
          <select name="account_id" class="form-control select2-js">
            <option value="">-- All Accounts --</option>
            @foreach($allAccounts as $acc)
              <option value="{{ $acc->id }}" {{ $accountId == $acc->id ? 'selected' : '' }}>
                [{{ strtoupper($acc->account_type) }}] {{ $acc->name }}
              </option>
            @endforeach
          </select></div>
        <div class="col-md-2 d-flex align-items-end gap-2">
          <button type="submit" class="btn btn-primary w-100">Filter</button>
          <button type="button" class="btn btn-danger" onclick="exportPDF('gl-tbl','General Ledger','{{ $dateFrom }} to {{ $dateTo }}')"><i class="fas fa-file-pdf"></i></button>
        </div>
      </div>
    </form>
    <div class="table-responsive" id="gl-tbl">
      <table class="table table-sm table-bordered table-striped">
        <thead class="table-dark">
          <tr><th>Date</th><th>Account</th><th>Voucher/Ref</th><th>Narration</th>
              <th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr>
        </thead>
        <tbody>
        @forelse($ledger as $row)
        <tr>
          <td>{{ \Carbon\Carbon::parse($row->date)->format('d-M-Y') }}</td>
          <td>{{ $row->account_name ?? '—' }}</td>
          <td>{{ $row->reference }}</td>
          <td class="narration">{{ $row->remarks }}</td>
          <td class="text-end text-success">{{ $row->side === 'dr' ? number_format($row->amount,2) : '—' }}</td>
          <td class="text-end text-danger">{{ $row->side === 'cr' ? number_format($row->amount,2) : '—' }}</td>
          <td class="text-end fw-bold {{ $row->running_balance >= 0 ? 'text-primary' : 'text-danger' }}">
            {{ number_format($row->running_balance,2) }}</td>
        </tr>
        @empty
        <tr><td colspan="7" class="text-center text-muted py-3">No data found for the selected period.</td></tr>
        @endforelse
        </tbody>
        @if($ledger->count())
        <tfoot class="table-secondary fw-bold">
          <tr>
            <td colspan="4" class="text-end">Totals</td>
            <td class="text-end text-success">{{ number_format($ledger->where('side','dr')->sum('amount'),2) }}</td>
            <td class="text-end text-danger">{{ number_format($ledger->where('side','cr')->sum('amount'),2) }}</td>
            <td class="text-end">{{ number_format($ledger->last()->running_balance ?? 0,2) }}</td>
          </tr>
        </tfoot>
        @endif
      </table>
    </div>
  </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════════
     TRIAL BALANCE
════════════════════════════════════════════════════════════ --}}
@if($tab === 'TB')
<div class="card">
  <div class="card-body">
    <form method="GET" action="{{ route('reports.accounts') }}" class="no-print">
      <input type="hidden" name="tab" value="TB">
      <div class="row g-2 mb-3">
        <div class="col-md-2"><label>From</label><input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}"></div>
        <div class="col-md-2"><label>To</label><input type="date" name="date_to" class="form-control" value="{{ $dateTo }}"></div>
        <div class="col-md-2 d-flex align-items-end gap-2">
          <button type="submit" class="btn btn-primary w-100">Generate</button>
          <button type="button" class="btn btn-danger" onclick="exportPDF('tb-tbl','Trial Balance','{{ $dateFrom }} to {{ $dateTo }}')"><i class="fas fa-file-pdf"></i></button>
        </div>
      </div>
    </form>
    <div class="table-responsive" id="tb-tbl">
      <table class="table table-sm table-bordered">
        <thead class="table-dark">
          <tr><th>Code</th><th>Account</th><th>Type</th>
              <th class="text-end">Total DR</th><th class="text-end">Total CR</th><th class="text-end">Balance</th></tr>
        </thead>
        <tbody>
        @forelse($trialBalance as $tb)
        <tr>
          <td>{{ $tb['account_code'] }}</td><td>{{ $tb['name'] }}</td>
          <td><span class="badge bg-secondary">{{ $tb['account_type'] }}</span></td>
          <td class="text-end text-success">{{ number_format($tb['total_dr'],2) }}</td>
          <td class="text-end text-danger">{{ number_format($tb['total_cr'],2) }}</td>
          <td class="text-end fw-bold {{ $tb['balance'] >= 0 ? 'text-primary' : 'text-danger' }}">{{ number_format($tb['balance'],2) }}</td>
        </tr>
        @empty
        <tr><td colspan="6" class="text-center text-muted py-3">No data. Select dates and click Generate.</td></tr>
        @endforelse
        </tbody>
        @if($trialBalance->count())
        <tfoot class="table-light fw-bold">
          <tr>
            <td colspan="3" class="text-end">Totals</td>
            <td class="text-end text-success">{{ number_format($trialBalance->sum('total_dr'),2) }}</td>
            <td class="text-end text-danger">{{ number_format($trialBalance->sum('total_cr'),2) }}</td>
            <td class="text-end">{{ number_format($trialBalance->sum('balance'),2) }}</td>
          </tr>
        </tfoot>
        @endif
      </table>
    </div>
  </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════════
     PROFIT & LOSS
════════════════════════════════════════════════════════════ --}}
@if($tab === 'PL')
<div class="card">
  <div class="card-body">
    <form method="GET" action="{{ route('reports.accounts') }}" class="no-print">
      <input type="hidden" name="tab" value="PL">
      <div class="row g-2 mb-3">
        <div class="col-md-2"><label>From</label><input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}"></div>
        <div class="col-md-2"><label>To</label><input type="date" name="date_to" class="form-control" value="{{ $dateTo }}"></div>
        <div class="col-md-2 d-flex align-items-end gap-2">
          <button type="submit" class="btn btn-primary w-100">Generate</button>
          <button type="button" class="btn btn-danger" onclick="exportPDF('pl-tbl','Profit & Loss','{{ $dateFrom }} to {{ $dateTo }}')"><i class="fas fa-file-pdf"></i></button>
        </div>
      </div>
    </form>
    @if($plData)
    <div id="pl-tbl">
      <div class="row">
        <div class="col-md-6">
          {{-- Revenue --}}
          <div class="pl-section">
            <h6 class="fw-bold text-success">Revenue</h6>
            <table class="table table-sm table-bordered">
              <thead class="table-dark"><tr><th>Account</th><th class="text-end">Amount</th></tr></thead>
              <tbody>
              @foreach($plData['revenueAccounts'] as $r)
              <tr><td>{{ $r->name }}</td><td class="text-end text-success">{{ number_format($r->total,2) }}</td></tr>
              @endforeach
              </tbody>
              <tfoot class="table-light fw-bold"><tr><td class="text-end">Total Revenue</td><td class="text-end text-success">{{ number_format($plData['totalRevenue'],2) }}</td></tr></tfoot>
            </table>
          </div>
          {{-- COGS --}}
          <div class="pl-section">
            <h6 class="fw-bold text-warning">Cost of Goods Sold</h6>
            <table class="table table-sm table-bordered">
              <thead class="table-dark"><tr><th>Account</th><th class="text-end">Amount</th></tr></thead>
              <tbody>
              @foreach($plData['cogsAccounts'] as $r)
              <tr><td>{{ $r->name }}</td><td class="text-end text-warning">{{ number_format($r->total,2) }}</td></tr>
              @endforeach
              </tbody>
              <tfoot class="table-light fw-bold"><tr><td class="text-end">Total COGS</td><td class="text-end text-warning">{{ number_format($plData['totalCogs'],2) }}</td></tr></tfoot>
            </table>
          </div>
        </div>
        <div class="col-md-6">
          {{-- Expenses --}}
          <div class="pl-section">
            <h6 class="fw-bold text-danger">Operating Expenses</h6>
            <table class="table table-sm table-bordered">
              <thead class="table-dark"><tr><th>Account</th><th class="text-end">Amount</th></tr></thead>
              <tbody>
              @foreach($plData['expenseAccounts'] as $r)
              <tr><td>{{ $r->name }}</td><td class="text-end text-danger">{{ number_format($r->total,2) }}</td></tr>
              @endforeach
              </tbody>
              <tfoot class="table-light fw-bold"><tr><td class="text-end">Total Expenses</td><td class="text-end text-danger">{{ number_format($plData['totalExpenses'],2) }}</td></tr></tfoot>
            </table>
          </div>
          {{-- Summary --}}
          <table class="table table-sm table-bordered table-dark">
            <tr><td>Gross Profit (Revenue – COGS)</td>
                <td class="text-end fw-bold {{ $plData['grossProfit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($plData['grossProfit'],2) }}</td></tr>
            <tr><td>Less: Operating Expenses</td>
                <td class="text-end text-danger">{{ number_format($plData['totalExpenses'],2) }}</td></tr>
            <tr class="table-warning"><td class="fw-bold">Net Profit / (Loss)</td>
                <td class="text-end fw-bold {{ $plData['netProfit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($plData['netProfit'],2) }}</td></tr>
          </table>
        </div>
      </div>
    </div>
    @else
    <p class="text-muted">Select a date range and click Generate.</p>
    @endif
  </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════════
     BALANCE SHEET
════════════════════════════════════════════════════════════ --}}
@if($tab === 'BS')
<div class="card">
  <div class="card-body">
    <form method="GET" action="{{ route('reports.accounts') }}" class="no-print">
      <input type="hidden" name="tab" value="BS">
      <div class="row g-2 mb-3">
        <div class="col-md-2"><label>From</label><input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}"></div>
        <div class="col-md-2"><label>To</label><input type="date" name="date_to" class="form-control" value="{{ $dateTo }}"></div>
        <div class="col-md-2 d-flex align-items-end gap-2">
          <button type="submit" class="btn btn-primary w-100">Generate</button>
          <button type="button" class="btn btn-danger" onclick="exportPDF('bs-tbl','Balance Sheet','{{ $dateFrom }} to {{ $dateTo }}')"><i class="fas fa-file-pdf"></i></button>
        </div>
      </div>
    </form>
    @if($bsData)
    <div class="row" id="bs-tbl">
      <div class="col-md-6">
        <div class="bs-section">
          <h6 class="fw-bold">Assets</h6>
          <table class="table table-sm table-bordered">
            <thead class="table-dark"><tr><th>Account</th><th class="text-end">Balance</th></tr></thead>
            <tbody>
            @foreach($bsData['assets'] as $r)
            <tr><td>{{ $r['name'] }}</td><td class="text-end text-success">{{ number_format($r['balance'],2) }}</td></tr>
            @endforeach
            </tbody>
            <tfoot class="table-light fw-bold"><tr><td class="text-end">Total Assets</td><td class="text-end text-success">{{ number_format($bsData['totalAssets'],2) }}</td></tr></tfoot>
          </table>
        </div>
      </div>
      <div class="col-md-6">
        <div class="bs-section">
          <h6 class="fw-bold">Liabilities</h6>
          <table class="table table-sm table-bordered">
            <thead class="table-dark"><tr><th>Account</th><th class="text-end">Balance</th></tr></thead>
            <tbody>
            @foreach($bsData['liabilities'] as $r)
            <tr><td>{{ $r['name'] }}</td><td class="text-end text-danger">{{ number_format($r['balance'],2) }}</td></tr>
            @endforeach
            </tbody>
            <tfoot class="table-light fw-bold"><tr><td class="text-end">Total Liabilities</td><td class="text-end text-danger">{{ number_format($bsData['totalLiab'],2) }}</td></tr></tfoot>
          </table>
        </div>
        <div class="bs-section">
          <h6 class="fw-bold">Equity</h6>
          <table class="table table-sm table-bordered">
            <thead class="table-dark"><tr><th>Account</th><th class="text-end">Balance</th></tr></thead>
            <tbody>
            @foreach($bsData['equity'] as $r)
            <tr><td>{{ $r['name'] }}</td><td class="text-end text-primary">{{ number_format($r['balance'],2) }}</td></tr>
            @endforeach
            </tbody>
            <tfoot class="table-light fw-bold"><tr><td class="text-end">Total Equity</td><td class="text-end text-primary">{{ number_format($bsData['totalEquity'],2) }}</td></tr></tfoot>
          </table>
        </div>
      </div>
    </div>
    @else
    <p class="text-muted">Select a date range and click Generate.</p>
    @endif
  </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════════
     PARTY LEDGER
════════════════════════════════════════════════════════════ --}}
@if($tab === 'PARTY')
<div class="card">
  <div class="card-body">
    <form method="GET" action="{{ route('reports.accounts') }}" class="no-print">
      <input type="hidden" name="tab" value="PARTY">
      <div class="row g-2 mb-3">
        <div class="col-md-2"><label>From</label><input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}"></div>
        <div class="col-md-2"><label>To</label><input type="date" name="date_to" class="form-control" value="{{ $dateTo }}"></div>
        <div class="col-md-4"><label>Party Account <span class="text-danger">*</span></label>
          <select name="account_id" class="form-control select2-js" required>
            <option value="">-- Select Customer / Vendor --</option>
            @foreach($allAccounts->whereIn('account_type',['customer','vendor']) as $acc)
              <option value="{{ $acc->id }}" {{ $accountId == $acc->id ? 'selected' : '' }}>
                [{{ strtoupper($acc->account_type) }}] {{ $acc->name }}
              </option>
            @endforeach
          </select></div>
        <div class="col-md-2 d-flex align-items-end gap-2">
          <button type="submit" class="btn btn-primary w-100">Filter</button>
          <button type="button" class="btn btn-danger" onclick="exportPDF('party-tbl','Party Ledger','{{ $dateFrom }} to {{ $dateTo }}')"><i class="fas fa-file-pdf"></i></button>
        </div>
      </div>
    </form>
    <div class="table-responsive" id="party-tbl">
      <table class="table table-sm table-bordered table-striped">
        <thead class="table-dark">
          <tr><th>Date</th><th>Type</th><th>Reference</th><th>Opposite Account</th>
              <th class="text-end">DR</th><th class="text-end">CR</th><th class="text-end">Balance</th><th>Remarks</th></tr>
        </thead>
        <tbody>
        @if(!$accountId)
          <tr><td colspan="8" class="text-center text-muted py-3">Please select a party account.</td></tr>
        @elseif($ledger->count())
          @foreach($ledger as $row)
          <tr>
            <td>{{ \Carbon\Carbon::parse($row->date)->format('d-M-Y') }}</td>
            <td><span class="badge bg-secondary">{{ strtoupper($row->voucher_type) }}</span></td>
            <td>{{ $row->reference }}</td>
            <td>{{ $row->opposite_account }}</td>
            <td class="text-end text-success">{{ $row->side==='dr'?number_format($row->amount,2):'—' }}</td>
            <td class="text-end text-danger">{{ $row->side==='cr'?number_format($row->amount,2):'—' }}</td>
            <td class="text-end fw-bold {{ $row->running_balance>=0?'text-primary':'text-danger' }}">{{ number_format($row->running_balance,2) }}</td>
            <td class="narration">{{ $row->remarks }}</td>
          </tr>
          @endforeach
          <tfoot>
            <tr class="table-secondary fw-bold">
              <td colspan="4" class="text-end">Closing Balance</td>
              <td class="text-end text-success">{{ number_format($ledger->where('side','dr')->sum('amount'),2) }}</td>
              <td class="text-end text-danger">{{ number_format($ledger->where('side','cr')->sum('amount'),2) }}</td>
              <td class="text-end">{{ number_format($ledger->last()->running_balance??0,2) }}</td>
              <td></td>
            </tr>
          </tfoot>
        @else
          <tr><td colspan="8" class="text-center text-muted py-3">No transactions found in this period.</td></tr>
        @endif
        </tbody>
      </table>
    </div>
  </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════════
     RECEIVABLES
════════════════════════════════════════════════════════════ --}}
@if($tab === 'RECV')
<div class="card">
  <div class="card-body">
    <form method="GET" action="{{ route('reports.accounts') }}" class="no-print">
      <input type="hidden" name="tab" value="RECV">
      <div class="row g-2 mb-3">
        <div class="col-md-2"><label>From</label><input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}"></div>
        <div class="col-md-2"><label>To</label><input type="date" name="date_to" class="form-control" value="{{ $dateTo }}"></div>
        <div class="col-md-3"><label>Customer</label>
          <select name="account_id" class="form-control select2-js">
            <option value="">All Customers</option>
            @foreach($allAccounts->where('account_type','customer') as $acc)
              <option value="{{ $acc->id }}" {{ $accountId==$acc->id?'selected':'' }}>{{ $acc->name }}</option>
            @endforeach
          </select></div>
        <div class="col-md-2 d-flex align-items-end gap-2">
          <button type="submit" class="btn btn-primary w-100">Filter</button>
          <button type="button" class="btn btn-danger" onclick="exportPDF('recv-tbl','Receivables','{{ $dateFrom }} to {{ $dateTo }}')"><i class="fas fa-file-pdf"></i></button>
        </div>
      </div>
    </form>
    <div class="mb-2 text-end no-print">
      <strong class="text-danger">Total Outstanding: PKR {{ number_format($receivables->sum('outstanding'),2) }}</strong>
    </div>
    <div class="table-responsive" id="recv-tbl">
      <table class="table table-sm table-bordered table-striped">
        <thead class="table-dark">
          <tr><th>#</th><th>Customer</th><th class="text-end">Invoiced</th><th class="text-end text-warning">Returned</th>
              <th class="text-end text-success">Received</th><th class="text-end">Outstanding</th><th class="text-end">Credit Limit</th><th>Status</th></tr>
        </thead>
        <tbody>
        @forelse($receivables as $i => $r)
        @php $overLimit = $r['credit_limit'] > 0 && $r['outstanding'] > $r['credit_limit']; @endphp
        <tr class="{{ $overLimit?'table-danger':'' }}">
          <td>{{ $i+1 }}</td><td>{{ $r['name'] }}</td>
          <td class="text-end">{{ number_format($r['total_invoiced'],2) }}</td>
          <td class="text-end text-warning">{{ number_format($r['total_returned'],2) }}</td>
          <td class="text-end text-success">{{ number_format($r['total_received'],2) }}</td>
          <td class="text-end fw-bold {{ $r['outstanding']>0?'text-danger':'text-secondary' }}">{{ number_format($r['outstanding'],2) }}</td>
          <td class="text-end text-muted">{{ $r['credit_limit']>0?number_format($r['credit_limit'],2):'—' }}</td>
          <td>
            @if($overLimit)<span class="badge bg-danger">Over Limit</span>
            @elseif($r['outstanding']>0)<span class="badge bg-warning text-dark">Pending</span>
            @else<span class="badge bg-success">Clear</span>@endif
          </td>
        </tr>
        @empty
        <tr><td colspan="8" class="text-center text-muted">No receivables found.</td></tr>
        @endforelse
        </tbody>
        @if($receivables->count())
        <tfoot class="table-light fw-bold">
          <tr><td colspan="2" class="text-end">Total</td>
            <td class="text-end">{{ number_format($receivables->sum('total_invoiced'),2) }}</td>
            <td class="text-end text-warning">{{ number_format($receivables->sum('total_returned'),2) }}</td>
            <td class="text-end text-success">{{ number_format($receivables->sum('total_received'),2) }}</td>
            <td class="text-end text-danger">{{ number_format($receivables->sum('outstanding'),2) }}</td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
        @endif
      </table>
    </div>
  </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════════
     PAYABLES
════════════════════════════════════════════════════════════ --}}
@if($tab === 'PAY')
<div class="card">
  <div class="card-body">
    <form method="GET" action="{{ route('reports.accounts') }}" class="no-print">
      <input type="hidden" name="tab" value="PAY">
      <div class="row g-2 mb-3">
        <div class="col-md-2"><label>From</label><input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}"></div>
        <div class="col-md-2"><label>To</label><input type="date" name="date_to" class="form-control" value="{{ $dateTo }}"></div>
        <div class="col-md-3"><label>Vendor</label>
          <select name="account_id" class="form-control select2-js">
            <option value="">All Vendors</option>
            @foreach($allAccounts->where('account_type','vendor') as $acc)
              <option value="{{ $acc->id }}" {{ $accountId==$acc->id?'selected':'' }}>{{ $acc->name }}</option>
            @endforeach
          </select></div>
        <div class="col-md-2 d-flex align-items-end gap-2">
          <button type="submit" class="btn btn-primary w-100">Filter</button>
          <button type="button" class="btn btn-danger" onclick="exportPDF('pay-tbl','Payables','{{ $dateFrom }} to {{ $dateTo }}')"><i class="fas fa-file-pdf"></i></button>
        </div>
      </div>
    </form>
    <div class="mb-2 text-end no-print">
      <strong class="text-danger">Total Outstanding: PKR {{ number_format($payables->sum('outstanding'),2) }}</strong>
    </div>
    <div class="table-responsive" id="pay-tbl">
      <table class="table table-sm table-bordered table-striped">
        <thead class="table-dark">
          <tr><th>#</th><th>Vendor</th><th class="text-end">Purchased</th><th class="text-end text-warning">Returned</th>
              <th class="text-end text-success">Paid</th><th class="text-end">Outstanding</th></tr>
        </thead>
        <tbody>
        @forelse($payables as $i => $p)
        <tr>
          <td>{{ $i+1 }}</td><td>{{ $p['name'] }}</td>
          <td class="text-end">{{ number_format($p['total_purchased'],2) }}</td>
          <td class="text-end text-warning">{{ number_format($p['total_returned'],2) }}</td>
          <td class="text-end text-success">{{ number_format($p['total_paid'],2) }}</td>
          <td class="text-end fw-bold {{ $p['outstanding']>0?'text-danger':'text-secondary' }}">{{ number_format($p['outstanding'],2) }}</td>
        </tr>
        @empty
        <tr><td colspan="6" class="text-center text-muted">No payables found.</td></tr>
        @endforelse
        </tbody>
        @if($payables->count())
        <tfoot class="table-light fw-bold">
          <tr><td colspan="2" class="text-end">Total</td>
            <td class="text-end">{{ number_format($payables->sum('total_purchased'),2) }}</td>
            <td class="text-end text-warning">{{ number_format($payables->sum('total_returned'),2) }}</td>
            <td class="text-end text-success">{{ number_format($payables->sum('total_paid'),2) }}</td>
            <td class="text-end text-danger">{{ number_format($payables->sum('outstanding'),2) }}</td>
          </tr>
        </tfoot>
        @endif
      </table>
    </div>
  </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════════
     CASH BOOK
════════════════════════════════════════════════════════════ --}}
@if($tab === 'CASH')
@php $cashIn = $cashBook->where('direction','IN')->sum('amount'); $cashOut = $cashBook->where('direction','OUT')->sum('amount'); @endphp
<div class="card">
  <div class="card-body">
    <form method="GET" action="{{ route('reports.accounts') }}" class="no-print">
      <input type="hidden" name="tab" value="CASH">
      <div class="row g-2 mb-3">
        <div class="col-md-2"><label>From</label><input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}"></div>
        <div class="col-md-2"><label>To</label><input type="date" name="date_to" class="form-control" value="{{ $dateTo }}"></div>
        <div class="col-md-3"><label>Cash Account</label>
          <select name="account_id" class="form-control select2-js">
            <option value="">All Cash Accounts</option>
            @foreach($cashAccounts as $acc)
              <option value="{{ $acc->id }}" {{ $accountId==$acc->id?'selected':'' }}>{{ $acc->name }}</option>
            @endforeach
          </select></div>
        <div class="col-md-2 d-flex align-items-end gap-2">
          <button type="submit" class="btn btn-primary w-100">Filter</button>
          <button type="button" class="btn btn-danger" onclick="exportPDF('cash-tbl','Cash Book','{{ $dateFrom }} to {{ $dateTo }}')"><i class="fas fa-file-pdf"></i></button>
        </div>
      </div>
    </form>
    <div class="row mb-3 no-print">
      <div class="col-md-3"><div class="card text-center border-success"><div class="card-body py-2"><small>Cash In</small><h5 class="text-success mb-0">{{ number_format($cashIn,2) }}</h5></div></div></div>
      <div class="col-md-3"><div class="card text-center border-danger"><div class="card-body py-2"><small>Cash Out</small><h5 class="text-danger mb-0">{{ number_format($cashOut,2) }}</h5></div></div></div>
      <div class="col-md-3"><div class="card text-center {{ ($cashIn-$cashOut)>=0?'border-primary':'border-danger' }}"><div class="card-body py-2"><small>Net Balance</small><h5 class="{{ ($cashIn-$cashOut)>=0?'text-primary':'text-danger' }} mb-0">{{ number_format($cashIn-$cashOut,2) }}</h5></div></div></div>
    </div>
    <div class="table-responsive" id="cash-tbl">
      <table class="table table-sm table-bordered table-striped">
        <thead class="table-dark">
          <tr><th>Date</th><th>Reference</th><th>Account</th><th>Type</th>
              <th class="text-end in-flow">Cash In</th><th class="text-end out-flow">Cash Out</th><th>Remarks</th></tr>
        </thead>
        <tbody>
        @forelse($cashBook as $row)
        <tr>
          <td>{{ \Carbon\Carbon::parse($row->date)->format('d-M-Y') }}</td>
          <td>{{ $row->reference }}</td>
          <td>{{ $row->opposite_account }}</td>
          <td><span class="badge bg-secondary">{{ strtoupper($row->voucher_type) }}</span></td>
          <td class="text-end in-flow">{{ $row->direction==='IN'?number_format($row->amount,2):'—' }}</td>
          <td class="text-end out-flow">{{ $row->direction==='OUT'?number_format($row->amount,2):'—' }}</td>
          <td class="narration">{{ $row->remarks }}</td>
        </tr>
        @empty
        <tr><td colspan="7" class="text-center text-muted py-3">No cash transactions found.</td></tr>
        @endforelse
        </tbody>
        @if($cashBook->count())
        <tfoot class="table-light fw-bold">
          <tr><td colspan="4" class="text-end">Totals</td>
            <td class="text-end in-flow">{{ number_format($cashIn,2) }}</td>
            <td class="text-end out-flow">{{ number_format($cashOut,2) }}</td>
            <td></td>
          </tr>
        </tfoot>
        @endif
      </table>
    </div>
  </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════════
     BANK BOOK
════════════════════════════════════════════════════════════ --}}
@if($tab === 'BANK')
@php $bankIn = $bankBook->where('direction','IN')->sum('amount'); $bankOut = $bankBook->where('direction','OUT')->sum('amount'); @endphp
<div class="card">
  <div class="card-body">
    <form method="GET" action="{{ route('reports.accounts') }}" class="no-print">
      <input type="hidden" name="tab" value="BANK">
      <div class="row g-2 mb-3">
        <div class="col-md-2"><label>From</label><input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}"></div>
        <div class="col-md-2"><label>To</label><input type="date" name="date_to" class="form-control" value="{{ $dateTo }}"></div>
        <div class="col-md-3"><label>Bank Account</label>
          <select name="account_id" class="form-control select2-js">
            <option value="">All Bank Accounts</option>
            @foreach($bankAccounts as $acc)
              <option value="{{ $acc->id }}" {{ $accountId==$acc->id?'selected':'' }}>{{ $acc->name }}</option>
            @endforeach
          </select></div>
        <div class="col-md-2 d-flex align-items-end gap-2">
          <button type="submit" class="btn btn-primary w-100">Filter</button>
          <button type="button" class="btn btn-danger" onclick="exportPDF('bank-tbl','Bank Book','{{ $dateFrom }} to {{ $dateTo }}')"><i class="fas fa-file-pdf"></i></button>
        </div>
      </div>
    </form>
    <div class="row mb-3 no-print">
      <div class="col-md-3"><div class="card text-center border-success"><div class="card-body py-2"><small>Bank In</small><h5 class="text-success mb-0">{{ number_format($bankIn,2) }}</h5></div></div></div>
      <div class="col-md-3"><div class="card text-center border-danger"><div class="card-body py-2"><small>Bank Out</small><h5 class="text-danger mb-0">{{ number_format($bankOut,2) }}</h5></div></div></div>
      <div class="col-md-3"><div class="card text-center {{ ($bankIn-$bankOut)>=0?'border-primary':'border-danger' }}"><div class="card-body py-2"><small>Net Balance</small><h5 class="{{ ($bankIn-$bankOut)>=0?'text-primary':'text-danger' }} mb-0">{{ number_format($bankIn-$bankOut,2) }}</h5></div></div></div>
    </div>
    <div class="table-responsive" id="bank-tbl">
      <table class="table table-sm table-bordered table-striped">
        <thead class="table-dark">
          <tr><th>Date</th><th>Reference</th><th>Account</th><th>Type</th>
              <th class="text-end in-flow">Bank In</th><th class="text-end out-flow">Bank Out</th><th>Remarks</th></tr>
        </thead>
        <tbody>
        @forelse($bankBook as $row)
        <tr>
          <td>{{ \Carbon\Carbon::parse($row->date)->format('d-M-Y') }}</td>
          <td>{{ $row->reference }}</td>
          <td>{{ $row->opposite_account }}</td>
          <td><span class="badge bg-secondary">{{ strtoupper($row->voucher_type) }}</span></td>
          <td class="text-end in-flow">{{ $row->direction==='IN'?number_format($row->amount,2):'—' }}</td>
          <td class="text-end out-flow">{{ $row->direction==='OUT'?number_format($row->amount,2):'—' }}</td>
          <td class="narration">{{ $row->remarks }}</td>
        </tr>
        @empty
        <tr><td colspan="7" class="text-center text-muted py-3">No bank transactions found.</td></tr>
        @endforelse
        </tbody>
        @if($bankBook->count())
        <tfoot class="table-light fw-bold">
          <tr><td colspan="4" class="text-end">Totals</td>
            <td class="text-end in-flow">{{ number_format($bankIn,2) }}</td>
            <td class="text-end out-flow">{{ number_format($bankOut,2) }}</td>
            <td></td>
          </tr>
        </tfoot>
        @endif
      </table>
    </div>
  </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════════
     JOURNAL / DAY BOOK
════════════════════════════════════════════════════════════ --}}
@if($tab === 'JOURNAL')
<div class="card">
  <div class="card-body">
    <form method="GET" action="{{ route('reports.accounts') }}" class="no-print">
      <input type="hidden" name="tab" value="JOURNAL">
      <div class="row g-2 mb-3">
        <div class="col-md-2"><label>From</label><input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}"></div>
        <div class="col-md-2"><label>To</label><input type="date" name="date_to" class="form-control" value="{{ $dateTo }}"></div>
        <div class="col-md-2 d-flex align-items-end gap-2">
          <button type="submit" class="btn btn-primary w-100">Filter</button>
          <button type="button" class="btn btn-danger" onclick="exportPDF('jnl-tbl','Journal / Day Book','{{ $dateFrom }} to {{ $dateTo }}')"><i class="fas fa-file-pdf"></i></button>
        </div>
      </div>
    </form>
    <div class="table-responsive" id="jnl-tbl">
      <table class="table table-sm table-bordered table-striped">
        <thead class="table-dark">
          <tr><th>#</th><th>Date</th><th>Type</th><th>Reference</th>
              <th>DR Account</th><th>CR Account</th><th class="text-end">Amount</th><th>Remarks</th></tr>
        </thead>
        <tbody>
        @forelse($journal as $i => $row)
        <tr>
          <td>{{ $i+1 }}</td>
          <td>{{ \Carbon\Carbon::parse($row->date)->format('d-M-Y') }}</td>
          <td><span class="badge bg-secondary">{{ strtoupper($row->voucher_type) }}</span></td>
          <td>{{ $row->reference }}</td>
          <td class="text-success">{{ $row->dr_account }}</td>
          <td class="text-danger">{{ $row->cr_account }}</td>
          <td class="text-end fw-bold">{{ number_format($row->amount,2) }}</td>
          <td class="narration">{{ $row->remarks }}</td>
        </tr>
        @empty
        <tr><td colspan="8" class="text-center text-muted py-3">No journal entries found.</td></tr>
        @endforelse
        </tbody>
        @if($journal->count())
        <tfoot class="table-light fw-bold">
          <tr><td colspan="6" class="text-end">Total</td>
            <td class="text-end">{{ number_format($journal->sum('amount'),2) }}</td>
            <td></td>
          </tr>
        </tfoot>
        @endif
      </table>
    </div>
  </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════════
     EXPENSE ANALYSIS
════════════════════════════════════════════════════════════ --}}
@if($tab === 'EXPENSE')
<div class="card">
  <div class="card-body">
    <form method="GET" action="{{ route('reports.accounts') }}" class="no-print">
      <input type="hidden" name="tab" value="EXPENSE">
      <div class="row g-2 mb-3">
        <div class="col-md-2"><label>From</label><input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}"></div>
        <div class="col-md-2"><label>To</label><input type="date" name="date_to" class="form-control" value="{{ $dateTo }}"></div>
        <div class="col-md-2 d-flex align-items-end gap-2">
          <button type="submit" class="btn btn-primary w-100">Filter</button>
          <button type="button" class="btn btn-danger" onclick="exportPDF('exp-tbl','Expense Analysis','{{ $dateFrom }} to {{ $dateTo }}')"><i class="fas fa-file-pdf"></i></button>
        </div>
      </div>
    </form>
    <div class="table-responsive" id="exp-tbl">
      <table class="table table-sm table-bordered table-striped">
        <thead class="table-dark">
          <tr><th>#</th><th>Account Code</th><th>Expense Account</th>
              <th class="text-end">Transactions</th><th class="text-end">Total Amount</th><th class="text-end">% of Total</th></tr>
        </thead>
        <tbody>
        @php $expTotal = $expenseData->sum('total'); @endphp
        @forelse($expenseData as $i => $exp)
        <tr>
          <td>{{ $i+1 }}</td>
          <td>{{ $exp->account_code }}</td>
          <td>{{ $exp->name }}</td>
          <td class="text-end">{{ $exp->txn_count }}</td>
          <td class="text-end text-danger fw-bold">{{ number_format($exp->total,2) }}</td>
          <td class="text-end">{{ $expTotal > 0 ? round(($exp->total/$expTotal)*100,1) : 0 }}%</td>
        </tr>
        @empty
        <tr><td colspan="6" class="text-center text-muted py-3">No expense accounts found for this period.</td></tr>
        @endforelse
        </tbody>
        @if($expenseData->count())
        <tfoot class="table-light fw-bold">
          <tr><td colspan="3" class="text-end">Total</td>
            <td class="text-end">{{ $expenseData->sum('txn_count') }}</td>
            <td class="text-end text-danger">{{ number_format($expTotal,2) }}</td>
            <td class="text-end">100%</td>
          </tr>
        </tfoot>
        @endif
      </table>
    </div>
  </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════════
     CASH FLOW
════════════════════════════════════════════════════════════ --}}
@if($tab === 'CASHFLOW')
<div class="card">
  <div class="card-body">
    <form method="GET" action="{{ route('reports.accounts') }}" class="no-print">
      <input type="hidden" name="tab" value="CASHFLOW">
      <div class="row g-2 mb-3">
        <div class="col-md-2"><label>From</label><input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}"></div>
        <div class="col-md-2"><label>To</label><input type="date" name="date_to" class="form-control" value="{{ $dateTo }}"></div>
        <div class="col-md-2 d-flex align-items-end gap-2">
          <button type="submit" class="btn btn-primary w-100">Generate</button>
          <button type="button" class="btn btn-danger" onclick="exportPDF('cf-tbl','Cash Flow','{{ $dateFrom }} to {{ $dateTo }}')"><i class="fas fa-file-pdf"></i></button>
        </div>
      </div>
    </form>
    @if($cashFlow)
    @php $cf = $cashFlow; @endphp
    <div class="row mb-3 no-print">
      <div class="col-md-3"><div class="card text-center border-success"><div class="card-body py-2"><small>Total Inflow</small><h5 class="text-success mb-0">{{ number_format($cf['total_in'],2) }}</h5></div></div></div>
      <div class="col-md-3"><div class="card text-center border-danger"><div class="card-body py-2"><small>Total Outflow</small><h5 class="text-danger mb-0">{{ number_format($cf['total_out'],2) }}</h5></div></div></div>
      <div class="col-md-3"><div class="card text-center {{ $cf['net_cash_flow']>=0?'border-primary':'border-danger' }}"><div class="card-body py-2"><small>Net Cash Flow</small><h5 class="{{ $cf['net_cash_flow']>=0?'text-primary':'text-danger' }} mb-0">{{ number_format($cf['net_cash_flow'],2) }}</h5></div></div></div>
    </div>
    <div class="row" id="cf-tbl">
      <div class="col-md-6">
        <h6 class="fw-bold text-success">Cash Inflows</h6>
        <table class="table table-sm table-bordered">
          <thead class="table-dark"><tr><th>Type</th><th>From Account</th><th class="text-end">Amount</th></tr></thead>
          <tbody>
          @foreach($cf['cash_in'] as $row)
          <tr>
            <td><span class="badge bg-secondary">{{ strtoupper($row->voucher_type) }}</span></td>
            <td>{{ $row->from_account }}</td>
            <td class="text-end in-flow">{{ number_format($row->total,2) }}</td>
          </tr>
          @endforeach
          </tbody>
          <tfoot class="table-light fw-bold"><tr><td colspan="2" class="text-end">Total In</td><td class="text-end in-flow">{{ number_format($cf['total_in'],2) }}</td></tr></tfoot>
        </table>
      </div>
      <div class="col-md-6">
        <h6 class="fw-bold text-danger">Cash Outflows</h6>
        <table class="table table-sm table-bordered">
          <thead class="table-dark"><tr><th>Type</th><th>To Account</th><th class="text-end">Amount</th></tr></thead>
          <tbody>
          @foreach($cf['cash_out'] as $row)
          <tr>
            <td><span class="badge bg-secondary">{{ strtoupper($row->voucher_type) }}</span></td>
            <td>{{ $row->to_account }}</td>
            <td class="text-end out-flow">{{ number_format($row->total,2) }}</td>
          </tr>
          @endforeach
          </tbody>
          <tfoot class="table-light fw-bold"><tr><td colspan="2" class="text-end">Total Out</td><td class="text-end out-flow">{{ number_format($cf['total_out'],2) }}</td></tr></tfoot>
        </table>
      </div>
    </div>
    @else
    <p class="text-muted">Select a date range and click Generate.</p>
    @endif
  </div>
</div>
@endif

</div>{{-- end tab-content --}}

<script>
function exportPDF(tableId, title, period) {
    const el = document.getElementById(tableId);
    if (!el) return;
    const clone = el.cloneNode(true);
    clone.querySelectorAll('.no-print').forEach(e => e.remove());
    clone.querySelectorAll('.badge').forEach(b => b.replaceWith(document.createTextNode(b.textContent.trim())));
    clone.querySelectorAll('a').forEach(a => { const s=document.createElement('span'); s.textContent=a.textContent.trim(); a.replaceWith(s); });
    const html = `<!DOCTYPE html><html><head><meta charset="utf-8"><title>${title}</title>
    <style>body{font-family:Arial,sans-serif;font-size:11px;margin:20px}
    h2{font-size:14px;margin-bottom:4px}p{font-size:10px;color:#555;margin:0 0 10px}
    table{width:100%;border-collapse:collapse}
    th{background:#1a1a2e;color:#fff;padding:5px 7px;text-align:left}
    td{padding:4px 7px;border-bottom:0.5px solid #ddd}
    tr:nth-child(even) td{background:#f9f9f9}
    tfoot td{background:#f0f0f0;font-weight:bold}
    .text-end{text-align:right}.fw-bold{font-weight:bold}
    .narration{font-size:10px;color:#888;font-style:italic}
    .in-flow{color:#27ae60;font-weight:600}.out-flow{color:#e74c3c;font-weight:600}
    </style></head><body><h2>${title}</h2><p>${period}</p>
    ${clone.innerHTML}
    <script>window.onload=function(){window.print();}<\/script>
    </body></html>`;
    const win = window.open('', '_blank', 'width=1100,height=700');
    win.document.write(html); win.document.close();
}
$(document).ready(function () { $('.select2-js').select2({ width: '100%' }); });
</script>
@endsection