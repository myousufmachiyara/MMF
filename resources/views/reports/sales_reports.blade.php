@extends('layouts.app')
@section('title', 'Sales Reports')
@section('content')
<style>
    @media print { .no-print { display: none !important; } }
    .ref-link { text-decoration: none; font-weight: 500; }
    .ref-link:hover { text-decoration: underline; }
    .profit-table { font-size: 12px; }
    .profit-table th, .profit-table td { white-space: nowrap; padding: 4px 6px !important; }
    .profit-table thead th { text-align: center; }
    .profit-table .col-fixed { min-width: 150px; text-align: left !important; }
    .text-profit { color: #28a745; font-weight: 600; }
    .text-loss   { color: #dc3545; font-weight: 600; }
    .grand-row   { background: #e9ecef !important; font-weight: bold; }
    .month-hdr   { background: #2c3e50 !important; color: #fff !important; }
    .total-hdr   { background: #c0392b !important; color: #fff !important; }
</style>

<div class="tabs">
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'SR'             ? 'active fw-bold' : '' }}"
               href="{{ request()->fullUrlWithQuery(['tab' => 'SR']) }}">
               Sales Register
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'SRET'           ? 'active fw-bold' : '' }}"
               href="{{ request()->fullUrlWithQuery(['tab' => 'SRET']) }}">
               Sales Returns
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'CW'             ? 'active fw-bold' : '' }}"
               href="{{ request()->fullUrlWithQuery(['tab' => 'CW']) }}">
               Customer-wise
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'PROFIT_PRODUCT' ? 'active fw-bold' : '' }}"
               href="{{ request()->fullUrlWithQuery(['tab' => 'PROFIT_PRODUCT']) }}">
               📦 Product Profit
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'PROFIT_CATEGORY'? 'active fw-bold' : '' }}"
               href="{{ request()->fullUrlWithQuery(['tab' => 'PROFIT_CATEGORY']) }}">
               🗂 Category Profit
            </a>
        </li>
    </ul>

    <div class="tab-content mt-3">

        {{-- ══════════════════════════════════════════════════════
             TAB: SALES REGISTER
        ══════════════════════════════════════════════════════ --}}
        <div id="SR" class="tab-pane fade {{ $tab === 'SR' ? 'show active' : '' }}">
            <form method="GET" action="{{ route('reports.sale') }}" class="no-print">
                <input type="hidden" name="tab" value="SR">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label>Customer</label>
                        <select name="customer_id" class="form-control select2-js">
                            <option value="">All Customers</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" {{ $customerId == $c->id ? 'selected' : '' }}>
                                    {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>From Date</label>
                        <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                    </div>
                    <div class="col-md-2">
                        <label>To Date</label>
                        <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                        <button type="button" class="btn btn-danger"
                                onclick="exportPDF('sr-table','Sales Register','{{ $dateFrom }} to {{ $dateTo }}')">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                    </div>
                </div>
            </form>

            <div class="mb-2 text-end no-print">
                <h4>Total: <span class="text-success">PKR {{ number_format($totalSold, 2) }}</span></h4>
            </div>

            <div id="sr-table">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th><th>Date</th><th>SI #</th><th>Customer</th>
                            <th>Type</th><th>PI Ref</th>
                            <th class="text-end">Net Amount</th>
                            <th class="no-print text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($invoices as $i => $inv)
                    @php $netAmt = $inv->items->sum(fn($item) => $item->sale_price * $item->quantity) - ($inv->discount ?? 0); @endphp
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ \Carbon\Carbon::parse($inv->date)->format('d-M-Y') }}</td>
                        <td><strong>SI-{{ $inv->invoice_no }}</strong></td>
                        <td>{{ $inv->account->name ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $inv->type === 'credit' ? 'bg-warning text-dark' : 'bg-success' }}">
                                {{ ucfirst($inv->type) }}
                            </span>
                        </td>
                        <td>
                            @if($inv->purchaseInvoice)
                                <span class="badge bg-info text-dark">
                                    PI-{{ $inv->purchaseInvoice->invoice_no }}
                                </span>
                            @else —
                            @endif
                        </td>
                        <td class="text-end fw-bold">{{ number_format($netAmt, 2) }}</td>
                        <td class="text-center no-print">
                            <a href="{{ route('sale_invoices.print', $inv->id) }}"
                               target="_blank" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-print"></i>
                            </a>
                            <a href="{{ route('sale_invoices.edit', $inv->id) }}"
                               class="btn btn-outline-primary btn-sm ms-1">
                                <i class="fas fa-edit"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted">No sales found.</td></tr>
                    @endforelse
                    </tbody>
                    @if($invoices->count())
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="6" class="text-end">Grand Total</td>
                            <td class="text-end">{{ number_format($totalSold, 2) }}</td>
                            <td class="no-print"></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════
             TAB: SALES RETURNS
        ══════════════════════════════════════════════════════ --}}
        <div id="SRET" class="tab-pane fade {{ $tab === 'SRET' ? 'show active' : '' }}">
            <form method="GET" action="{{ route('reports.sale') }}" class="no-print">
                <input type="hidden" name="tab" value="SRET">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label>Customer</label>
                        <select name="customer_id" class="form-control select2-js">
                            <option value="">All Customers</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" {{ $customerId == $c->id ? 'selected' : '' }}>
                                    {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>From Date</label>
                        <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                    </div>
                    <div class="col-md-2">
                        <label>To Date</label>
                        <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                        <button type="button" class="btn btn-danger"
                                onclick="exportPDF('sret-table','Sales Returns','{{ $dateFrom }} to {{ $dateTo }}')">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                    </div>
                </div>
            </form>

            <div id="sret-table">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th><th>Date</th><th>Reference</th>
                            <th>Customer</th>
                            <th class="text-end">Amount</th><th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($returns as $i => $ret)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ \Carbon\Carbon::parse($ret->date)->format('d-M-Y') }}</td>
                        <td>{{ $ret->reference }}</td>
                        <td>{{ $ret->customer_name }}</td>
                        <td class="text-end text-warning fw-bold">{{ number_format($ret->amount, 2) }}</td>
                        <td>{{ $ret->remarks }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted">No returns found.</td></tr>
                    @endforelse
                    </tbody>
                    @if($returns->count())
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="4" class="text-end">Total Returns</td>
                            <td class="text-end text-warning">{{ number_format($totalReturned, 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════
             TAB: CUSTOMER-WISE
        ══════════════════════════════════════════════════════ --}}
        <div id="CW" class="tab-pane fade {{ $tab === 'CW' ? 'show active' : '' }}">
            <form method="GET" action="{{ route('reports.sale') }}" class="no-print">
                <input type="hidden" name="tab" value="CW">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label>Customer</label>
                        <select name="customer_id" class="form-control select2-js">
                            <option value="">All Customers</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" {{ $customerId == $c->id ? 'selected' : '' }}>
                                    {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>From Date</label>
                        <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                    </div>
                    <div class="col-md-2">
                        <label>To Date</label>
                        <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                        <button type="button" class="btn btn-danger"
                                onclick="exportPDF('cw-table','Customer-wise Sales','{{ $dateFrom }} to {{ $dateTo }}')">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                    </div>
                </div>
            </form>

            <div id="cw-table">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th><th>Customer</th>
                            <th class="text-end">Sold</th>
                            <th class="text-end text-warning">Returned</th>
                            <th class="text-end text-success">Received</th>
                            <th class="text-end">Net Receivable</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($customerSummary as $cs)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $cs['name'] }}</td>
                        <td class="text-end">{{ number_format($cs['sold'], 2) }}</td>
                        <td class="text-end text-warning">{{ number_format($cs['returned'], 2) }}</td>
                        <td class="text-end text-success">{{ number_format($cs['received'], 2) }}</td>
                        <td class="text-end fw-bold {{ $cs['net_receivable'] > 0 ? 'text-danger' : 'text-secondary' }}">
                            {{ number_format($cs['net_receivable'], 2) }}
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted">No data found.</td></tr>
                    @endforelse
                    </tbody>
                    @if(count($customerSummary))
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="2" class="text-end">Total</td>
                            <td class="text-end">{{ number_format(array_sum(array_column($customerSummary,'sold')), 2) }}</td>
                            <td class="text-end text-warning">{{ number_format(array_sum(array_column($customerSummary,'returned')), 2) }}</td>
                            <td class="text-end text-success">{{ number_format(array_sum(array_column($customerSummary,'received')), 2) }}</td>
                            <td class="text-end text-danger">{{ number_format(array_sum(array_column($customerSummary,'net_receivable')), 2) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════
             TAB: PRODUCT-WISE MONTHLY PROFIT
        ══════════════════════════════════════════════════════ --}}
        <div id="PROFIT_PRODUCT" class="tab-pane fade {{ $tab === 'PROFIT_PRODUCT' ? 'show active' : '' }}">
        @php
            $gRev  = array_sum(array_column($grandMonthly, 'revenue'));
            $gCogs = array_sum(array_column($grandMonthly, 'cogs'));
            $gProf = array_sum(array_column($grandMonthly, 'profit'));
            $gMarg = $gRev > 0 ? round(($gProf / $gRev) * 100, 1) : 0;
        @endphp

            {{-- Filter --}}
            <form method="GET" action="{{ route('reports.sale') }}" class="no-print">
                <input type="hidden" name="tab" value="PROFIT_PRODUCT">
                <div class="row g-3 mb-3">
                    <div class="col-md-2">
                        <label>Year</label>
                        <select name="profit_year" class="form-control">
                            @foreach(range(date('Y'), 2020) as $y)
                                <option value="{{ $y }}" {{ $profitYear == $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Category</label>
                        <select name="profit_category_id" class="form-control select2-js">
                            <option value="">All Categories</option>
                            @foreach($profitCategories as $cat)
                                <option value="{{ $cat->id }}"
                                    {{ $profitCategoryId == $cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Product</label>
                        <select name="profit_product_id" class="form-control select2-js">
                            <option value="">All Products</option>
                            @foreach($profitProducts as $p)
                                <option value="{{ $p->id }}"
                                    {{ $profitProductId == $p->id ? 'selected' : '' }}>
                                    {{ $p->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                        <button type="button" class="btn btn-danger"
                                onclick="exportPDF('pp-table','Product-wise Monthly Profit','{{ $profitYear }}')">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                    </div>
                </div>
            </form>

            {{-- Summary cards --}}
            <div class="row mb-3 no-print">
                <div class="col-md-3"><div class="card text-center border-success"><div class="card-body py-2">
                    <small class="text-muted">Total Revenue</small>
                    <h5 class="text-success mb-0">PKR {{ number_format($gRev, 0) }}</h5>
                </div></div></div>
                <div class="col-md-3"><div class="card text-center border-warning"><div class="card-body py-2">
                    <small class="text-muted">Total COGS</small>
                    <h5 class="text-warning mb-0">PKR {{ number_format($gCogs, 0) }}</h5>
                </div></div></div>
                <div class="col-md-3"><div class="card text-center {{ $gProf >= 0 ? 'border-primary' : 'border-danger' }}"><div class="card-body py-2">
                    <small class="text-muted">Total Profit</small>
                    <h5 class="{{ $gProf >= 0 ? 'text-primary' : 'text-danger' }} mb-0">PKR {{ number_format($gProf, 0) }}</h5>
                </div></div></div>
                <div class="col-md-3"><div class="card text-center border-secondary"><div class="card-body py-2">
                    <small class="text-muted">Profit Margin</small>
                    <h5 class="{{ $gMarg >= 0 ? 'text-primary' : 'text-danger' }} mb-0">{{ $gMarg }}%</h5>
                </div></div></div>
            </div>

            <div class="table-responsive" id="pp-table">
                <table class="table table-bordered profit-table mb-0">
                    <thead>
                        <tr>
                            <th class="col-fixed month-hdr" rowspan="2">#</th>
                            <th class="col-fixed month-hdr" rowspan="2">Product</th>
                            <th class="col-fixed month-hdr" rowspan="2">Category</th>
                            @foreach($profitMonths as $m)
                                <th colspan="3" class="text-center month-hdr">{{ $m['label'] }}</th>
                            @endforeach
                            <th colspan="4" class="text-center total-hdr">Annual Total</th>
                        </tr>
                        <tr>
                            @foreach($profitMonths as $m)
                                <th style="background:#2c3e50;color:#aed6f1;font-size:10px;" class="text-end">Rev</th>
                                <th style="background:#2c3e50;color:#f9ca24;font-size:10px;" class="text-end">COGS</th>
                                <th style="background:#2c3e50;color:#6bcb77;font-size:10px;" class="text-end">Profit</th>
                            @endforeach
                            <th style="background:#c0392b;color:#aed6f1;font-size:10px;" class="text-end">Revenue</th>
                            <th style="background:#c0392b;color:#f9ca24;font-size:10px;" class="text-end">COGS</th>
                            <th style="background:#c0392b;color:#6bcb77;font-size:10px;" class="text-end">Profit</th>
                            <th style="background:#c0392b;color:#fff;font-size:10px;"    class="text-end">Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($productReport as $row)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td><strong>{{ $row['name'] }}</strong></td>
                        <td><span class="badge bg-secondary">{{ $row['category'] }}</span></td>
                        @foreach($profitMonths as $m)
                            @php $mon = $row['months'][$m['num']]; @endphp
                            <td class="text-end {{ $mon['revenue'] > 0 ? '' : 'text-muted' }}">
                                {{ $mon['revenue'] > 0 ? number_format($mon['revenue'], 0) : '—' }}
                            </td>
                            <td class="text-end {{ $mon['cogs'] > 0 ? 'text-warning' : 'text-muted' }}">
                                {{ $mon['cogs'] > 0 ? number_format($mon['cogs'], 0) : '—' }}
                            </td>
                            <td class="text-end {{ $mon['profit'] > 0 ? 'text-profit' : ($mon['profit'] < 0 ? 'text-loss' : 'text-muted') }}">
                                {{ $mon['revenue'] > 0 ? number_format($mon['profit'], 0) : '—' }}
                            </td>
                        @endforeach
                        @php
                            $tRev  = $row['total']['revenue'];
                            $tCogs = $row['total']['cogs'];
                            $tProf = $row['total']['profit'];
                            $tMarg = $tRev > 0 ? round(($tProf / $tRev) * 100, 1) : 0;
                        @endphp
                        <td class="text-end fw-bold text-success">{{ number_format($tRev,  0) }}</td>
                        <td class="text-end fw-bold text-warning">{{ number_format($tCogs, 0) }}</td>
                        <td class="text-end fw-bold {{ $tProf >= 0 ? 'text-primary' : 'text-danger' }}">{{ number_format($tProf, 0) }}</td>
                        <td class="text-end fw-bold {{ $tMarg >= 0 ? 'text-primary' : 'text-danger' }}">{{ $tMarg }}%</td>
                    </tr>
                    @empty
                    <tr><td colspan="99" class="text-center text-muted py-4">No sales data found for {{ $profitYear }}.</td></tr>
                    @endforelse
                    </tbody>
                    @if(count($productReport))
                    <tfoot>
                        <tr class="grand-row">
                            <td colspan="3" class="text-end">GRAND TOTAL</td>
                            @foreach($profitMonths as $m)
                                @php $gm = $grandMonthly[$m['num']]; @endphp
                                <td class="text-end">{{ $gm['revenue'] > 0 ? number_format($gm['revenue'], 0) : '—' }}</td>
                                <td class="text-end text-warning">{{ $gm['cogs'] > 0 ? number_format($gm['cogs'], 0) : '—' }}</td>
                                <td class="text-end {{ $gm['profit'] >= 0 ? 'text-profit' : 'text-loss' }}">
                                    {{ $gm['profit'] != 0 ? number_format($gm['profit'], 0) : '—' }}
                                </td>
                            @endforeach
                            <td class="text-end text-success">{{ number_format($gRev,  0) }}</td>
                            <td class="text-end text-warning">{{ number_format($gCogs, 0) }}</td>
                            <td class="text-end {{ $gProf >= 0 ? 'text-primary' : 'text-danger' }}">{{ number_format($gProf, 0) }}</td>
                            <td class="text-end {{ $gMarg >= 0 ? 'text-primary' : 'text-danger' }}">{{ $gMarg }}%</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════
             TAB: CATEGORY-WISE MONTHLY PROFIT
        ══════════════════════════════════════════════════════ --}}
        <div id="PROFIT_CATEGORY" class="tab-pane fade {{ $tab === 'PROFIT_CATEGORY' ? 'show active' : '' }}">
        @php
            $gRev  = array_sum(array_column($grandMonthly, 'revenue'));
            $gCogs = array_sum(array_column($grandMonthly, 'cogs'));
            $gProf = array_sum(array_column($grandMonthly, 'profit'));
            $gMarg = $gRev > 0 ? round(($gProf / $gRev) * 100, 1) : 0;
        @endphp

            {{-- Filter --}}
            <form method="GET" action="{{ route('reports.sale') }}" class="no-print">
                <input type="hidden" name="tab" value="PROFIT_CATEGORY">
                <div class="row g-3 mb-3">
                    <div class="col-md-2">
                        <label>Year</label>
                        <select name="profit_year" class="form-control">
                            @foreach(range(date('Y'), 2020) as $y)
                                <option value="{{ $y }}" {{ $profitYear == $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Category</label>
                        <select name="profit_category_id" class="form-control select2-js">
                            <option value="">All Categories</option>
                            @foreach($profitCategories as $cat)
                                <option value="{{ $cat->id }}"
                                    {{ $profitCategoryId == $cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                        <button type="button" class="btn btn-danger"
                                onclick="exportPDF('cp-table','Category-wise Monthly Profit','{{ $profitYear }}')">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                    </div>
                </div>
            </form>

            {{-- Summary cards --}}
            <div class="row mb-3 no-print">
                <div class="col-md-3"><div class="card text-center border-success"><div class="card-body py-2">
                    <small class="text-muted">Total Revenue</small>
                    <h5 class="text-success mb-0">PKR {{ number_format($gRev, 0) }}</h5>
                </div></div></div>
                <div class="col-md-3"><div class="card text-center border-warning"><div class="card-body py-2">
                    <small class="text-muted">Total COGS</small>
                    <h5 class="text-warning mb-0">PKR {{ number_format($gCogs, 0) }}</h5>
                </div></div></div>
                <div class="col-md-3"><div class="card text-center {{ $gProf >= 0 ? 'border-primary' : 'border-danger' }}"><div class="card-body py-2">
                    <small class="text-muted">Total Profit</small>
                    <h5 class="{{ $gProf >= 0 ? 'text-primary' : 'text-danger' }} mb-0">PKR {{ number_format($gProf, 0) }}</h5>
                </div></div></div>
                <div class="col-md-3"><div class="card text-center border-secondary"><div class="card-body py-2">
                    <small class="text-muted">Profit Margin</small>
                    <h5 class="{{ $gMarg >= 0 ? 'text-primary' : 'text-danger' }} mb-0">{{ $gMarg }}%</h5>
                </div></div></div>
            </div>

            <div class="table-responsive" id="cp-table">
                <table class="table table-bordered profit-table mb-0">
                    <thead>
                        <tr>
                            <th class="col-fixed month-hdr" rowspan="2">#</th>
                            <th class="col-fixed month-hdr" rowspan="2">Category</th>
                            @foreach($profitMonths as $m)
                                <th colspan="3" class="text-center month-hdr">{{ $m['label'] }}</th>
                            @endforeach
                            <th colspan="4" class="text-center total-hdr">Annual Total</th>
                        </tr>
                        <tr>
                            @foreach($profitMonths as $m)
                                <th style="background:#2c3e50;color:#aed6f1;font-size:10px;" class="text-end">Rev</th>
                                <th style="background:#2c3e50;color:#f9ca24;font-size:10px;" class="text-end">COGS</th>
                                <th style="background:#2c3e50;color:#6bcb77;font-size:10px;" class="text-end">Profit</th>
                            @endforeach
                            <th style="background:#c0392b;color:#aed6f1;font-size:10px;" class="text-end">Revenue</th>
                            <th style="background:#c0392b;color:#f9ca24;font-size:10px;" class="text-end">COGS</th>
                            <th style="background:#c0392b;color:#6bcb77;font-size:10px;" class="text-end">Profit</th>
                            <th style="background:#c0392b;color:#fff;font-size:10px;"    class="text-end">Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($categoryReport as $row)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td><strong>{{ $row['name'] }}</strong></td>
                        @foreach($profitMonths as $m)
                            @php $mon = $row['months'][$m['num']]; @endphp
                            <td class="text-end {{ $mon['revenue'] > 0 ? '' : 'text-muted' }}">
                                {{ $mon['revenue'] > 0 ? number_format($mon['revenue'], 0) : '—' }}
                            </td>
                            <td class="text-end {{ $mon['cogs'] > 0 ? 'text-warning' : 'text-muted' }}">
                                {{ $mon['cogs'] > 0 ? number_format($mon['cogs'], 0) : '—' }}
                            </td>
                            <td class="text-end {{ $mon['profit'] > 0 ? 'text-profit' : ($mon['profit'] < 0 ? 'text-loss' : 'text-muted') }}">
                                {{ $mon['revenue'] > 0 ? number_format($mon['profit'], 0) : '—' }}
                            </td>
                        @endforeach
                        @php
                            $tRev  = $row['total']['revenue'];
                            $tCogs = $row['total']['cogs'];
                            $tProf = $row['total']['profit'];
                            $tMarg = $tRev > 0 ? round(($tProf / $tRev) * 100, 1) : 0;
                        @endphp
                        <td class="text-end fw-bold text-success">{{ number_format($tRev,  0) }}</td>
                        <td class="text-end fw-bold text-warning">{{ number_format($tCogs, 0) }}</td>
                        <td class="text-end fw-bold {{ $tProf >= 0 ? 'text-primary' : 'text-danger' }}">{{ number_format($tProf, 0) }}</td>
                        <td class="text-end fw-bold {{ $tMarg >= 0 ? 'text-primary' : 'text-danger' }}">{{ $tMarg }}%</td>
                    </tr>
                    @empty
                    <tr><td colspan="99" class="text-center text-muted py-4">No sales data found for {{ $profitYear }}.</td></tr>
                    @endforelse
                    </tbody>
                    @if(count($categoryReport))
                    <tfoot>
                        <tr class="grand-row">
                            <td colspan="2" class="text-end">GRAND TOTAL</td>
                            @foreach($profitMonths as $m)
                                @php $gm = $grandMonthly[$m['num']]; @endphp
                                <td class="text-end">{{ $gm['revenue'] > 0 ? number_format($gm['revenue'], 0) : '—' }}</td>
                                <td class="text-end text-warning">{{ $gm['cogs'] > 0 ? number_format($gm['cogs'], 0) : '—' }}</td>
                                <td class="text-end {{ $gm['profit'] >= 0 ? 'text-profit' : 'text-loss' }}">
                                    {{ $gm['profit'] != 0 ? number_format($gm['profit'], 0) : '—' }}
                                </td>
                            @endforeach
                            <td class="text-end text-success">{{ number_format($gRev,  0) }}</td>
                            <td class="text-end text-warning">{{ number_format($gCogs, 0) }}</td>
                            <td class="text-end {{ $gProf >= 0 ? 'text-primary' : 'text-danger' }}">{{ number_format($gProf, 0) }}</td>
                            <td class="text-end {{ $gMarg >= 0 ? 'text-primary' : 'text-danger' }}">{{ $gMarg }}%</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>

    </div>{{-- end tab-content --}}
</div>{{-- end tabs --}}

<script>
function exportPDF(tableId, title, period) {
    const el = document.getElementById(tableId);
    if (!el) return;
    const clone = el.cloneNode(true);
    clone.querySelectorAll('.no-print').forEach(e => e.remove());
    clone.querySelectorAll('.badge').forEach(b => b.replaceWith(document.createTextNode(b.textContent.trim())));
    clone.querySelectorAll('a').forEach(a => {
        const span = document.createElement('span');
        span.textContent = a.textContent.trim();
        a.replaceWith(span);
    });
    const html = `<!DOCTYPE html><html><head><meta charset="utf-8"><title>${title}</title>
    <style>body{font-family:Arial,sans-serif;font-size:11px;margin:20px}
    h2{font-size:14px;margin-bottom:4px}p{font-size:10px;color:#555;margin:0 0 10px}
    table{width:100%;border-collapse:collapse}
    th{background:#1a1a2e;color:#fff;padding:5px 7px;text-align:left}
    td{padding:4px 7px;border-bottom:0.5px solid #ddd}
    tr:nth-child(even) td{background:#f9f9f9}
    tfoot td{background:#f0f0f0;font-weight:bold}
    .text-end{text-align:right}.fw-bold{font-weight:bold}
    .text-profit{color:#28a745;font-weight:600}.text-loss{color:#dc3545;font-weight:600}
    </style></head><body><h2>${title}</h2><p>${period}</p>
    ${clone.innerHTML}
    <script>window.onload=function(){window.print();}<\/script>
    </body></html>`;
    const win = window.open('', '_blank', 'width=1200,height=700');
    win.document.write(html);
    win.document.close();
}

$(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
});
</script>
@endsection