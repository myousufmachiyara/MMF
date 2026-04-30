@extends('layouts.app')
@section('title', 'Purchase Reports')
@section('content')
<style>
    @media print { .no-print { display: none !important; } }
    .ref-link { text-decoration: none; font-weight: 500; }
    .ref-link:hover { text-decoration: underline; }
</style>

<div class="tabs">
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'PUR' ? 'active fw-bold' : '' }}"
               href="{{ request()->fullUrlWithQuery(['tab' => 'PUR']) }}">
                Purchase Register
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'PR' ? 'active fw-bold' : '' }}"
               href="{{ request()->fullUrlWithQuery(['tab' => 'PR']) }}">
                Purchase Returns
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'PAY' ? 'active fw-bold' : '' }}"
               href="{{ request()->fullUrlWithQuery(['tab' => 'PAY']) }}">
                Payments to Vendors
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'VWP' ? 'active fw-bold' : '' }}"
               href="{{ request()->fullUrlWithQuery(['tab' => 'VWP']) }}">
                Vendor-wise Summary
            </a>
        </li>
    </ul>

    <div class="tab-content mt-3">

        {{-- ══════════════════════════════════════════════════════
             TAB: PURCHASE REGISTER
        ══════════════════════════════════════════════════════ --}}
        <div id="PUR" class="tab-pane fade {{ $tab === 'PUR' ? 'show active' : '' }}">

            <form method="GET" action="{{ route('reports.purchase') }}" class="no-print">
                <input type="hidden" name="tab" value="PUR">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label>Vendor</label>
                        <select name="vendor_id" class="form-control select2-js">
                            <option value="">-- All Vendors --</option>
                            @foreach($vendors as $v)
                                <option value="{{ $v->id }}"
                                    {{ $vendorId == $v->id ? 'selected' : '' }}>
                                    {{ $v->name }}
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
                                onclick="exportPDF('pur-table','Purchase Register','{{ $dateFrom }} to {{ $dateTo }}')">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                    </div>
                </div>
            </form>

            @php $grandTotal = $invoices->sum(fn($inv) => $inv->items->sum(fn($i) => $i->quantity * $i->price)); @endphp
            <div class="mb-2 text-end no-print">
                <h4>Total Purchase: <span class="text-danger">PKR {{ number_format($grandTotal, 2) }}</span></h4>
            </div>

            <div id="pur-table">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th><th>Date</th><th>PI #</th><th>Bill #</th>
                            <th>Vendor</th><th>PO #</th>
                            <th class="text-end">Amount</th>
                            <th class="no-print text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($invoices as $i => $inv)
                    @php $amt = $inv->items->sum(fn($item) => $item->quantity * $item->price); @endphp
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ \Carbon\Carbon::parse($inv->invoice_date)->format('d-M-Y') }}</td>
                        <td><strong>PI-{{ $inv->invoice_no }}</strong></td>
                        <td>{{ $inv->bill_no ?? '—' }}</td>
                        <td>{{ $inv->vendor->name ?? '—' }}</td>
                        <td>{{ $inv->purchaseOrder->po_no ?? '—' }}</td>
                        <td class="text-end fw-bold">{{ number_format($amt, 2) }}</td>
                        <td class="text-center no-print">
                            <a href="{{ route('purchase_invoices.print', $inv->id) }}"
                               target="_blank" class="btn btn-outline-success btn-sm" title="Print">
                                <i class="fas fa-print"></i>
                            </a>
                            <a href="{{ route('purchase_invoices.edit', $inv->id) }}"
                               class="btn btn-outline-primary btn-sm ms-1" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted">No purchase records found.</td></tr>
                    @endforelse
                    </tbody>
                    @if($invoices->count())
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="6" class="text-end">Grand Total</td>
                            <td class="text-end">{{ number_format($grandTotal, 2) }}</td>
                            <td class="no-print"></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════
             TAB: PURCHASE RETURNS
        ══════════════════════════════════════════════════════ --}}
        <div id="PR" class="tab-pane fade {{ $tab === 'PR' ? 'show active' : '' }}">

            <form method="GET" action="{{ route('reports.purchase') }}" class="no-print">
                <input type="hidden" name="tab" value="PR">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label>Vendor</label>
                        <select name="vendor_id" class="form-control select2-js">
                            <option value="">-- All Vendors --</option>
                            @foreach($vendors as $v)
                                <option value="{{ $v->id }}"
                                    {{ $vendorId == $v->id ? 'selected' : '' }}>
                                    {{ $v->name }}
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
                                onclick="exportPDF('pr-table','Purchase Returns','{{ $dateFrom }} to {{ $dateTo }}')">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                    </div>
                </div>
            </form>

            <div id="pr-table">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th><th>Date</th><th>Reference</th>
                            <th>Vendor</th>
                            <th class="text-end">Amount</th><th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($returns as $i => $ret)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ \Carbon\Carbon::parse($ret->date)->format('d-M-Y') }}</td>
                        <td>{{ $ret->reference }}</td>
                        <td>{{ $ret->vendor_name }}</td>
                        <td class="text-end text-warning fw-bold">{{ number_format($ret->amount, 2) }}</td>
                        <td>{{ $ret->remarks }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted">No purchase return records found.</td></tr>
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
             TAB: PAYMENTS TO VENDORS
        ══════════════════════════════════════════════════════ --}}
        <div id="PAY" class="tab-pane fade {{ $tab === 'PAY' ? 'show active' : '' }}">

            <form method="GET" action="{{ route('reports.purchase') }}" class="no-print">
                <input type="hidden" name="tab" value="PAY">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label>Vendor</label>
                        <select name="vendor_id" class="form-control select2-js">
                            <option value="">-- All Vendors --</option>
                            @foreach($vendors as $v)
                                <option value="{{ $v->id }}"
                                    {{ $vendorId == $v->id ? 'selected' : '' }}>
                                    {{ $v->name }}
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
                                onclick="exportPDF('pay-table','Vendor Payments','{{ $dateFrom }} to {{ $dateTo }}')">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                    </div>
                </div>
            </form>

            <div id="pay-table">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th><th>Date</th><th>Reference</th>
                            <th>Vendor</th><th>Paid From</th>
                            <th class="text-end">Amount</th><th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($payments as $i => $pay)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ \Carbon\Carbon::parse($pay->date)->format('d-M-Y') }}</td>
                        <td>{{ $pay->reference }}</td>
                        <td>{{ $pay->vendor_name }}</td>
                        <td>{{ $pay->cashbank_name }}</td>
                        <td class="text-end text-success fw-bold">{{ number_format($pay->amount, 2) }}</td>
                        <td>{{ $pay->remarks }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted">No payment records found.</td></tr>
                    @endforelse
                    </tbody>
                    @if($payments->count())
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="5" class="text-end">Total Paid</td>
                            <td class="text-end text-success">{{ number_format($totalPaid, 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════
             TAB: VENDOR-WISE SUMMARY
        ══════════════════════════════════════════════════════ --}}
        <div id="VWP" class="tab-pane fade {{ $tab === 'VWP' ? 'show active' : '' }}">

            <form method="GET" action="{{ route('reports.purchase') }}" class="no-print">
                <input type="hidden" name="tab" value="VWP">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label>Vendor</label>
                        <select name="vendor_id" class="form-control select2-js">
                            <option value="">-- All Vendors --</option>
                            @foreach($vendors as $v)
                                <option value="{{ $v->id }}"
                                    {{ $vendorId == $v->id ? 'selected' : '' }}>
                                    {{ $v->name }}
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
                                onclick="exportPDF('vwp-table','Vendor-wise Summary','{{ $dateFrom }} to {{ $dateTo }}')">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                    </div>
                </div>
            </form>

            <div id="vwp-table">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th><th>Vendor</th>
                            <th class="text-end">Purchased</th>
                            <th class="text-end text-warning">Returned</th>
                            <th class="text-end text-success">Paid</th>
                            <th class="text-end">Net Payable</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($vendorSummary as $vs)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td><strong>{{ $vs['name'] }}</strong></td>
                        <td class="text-end">{{ number_format($vs['purchased'], 2) }}</td>
                        <td class="text-end text-warning">{{ number_format($vs['returned'], 2) }}</td>
                        <td class="text-end text-success">{{ number_format($vs['paid'], 2) }}</td>
                        <td class="text-end fw-bold
                            {{ $vs['net_payable'] > 0 ? 'text-danger' : 'text-secondary' }}">
                            {{ number_format($vs['net_payable'], 2) }}
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted">No vendor data found.</td></tr>
                    @endforelse
                    </tbody>
                    @if(count($vendorSummary))
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="2" class="text-end">Grand Total</td>
                            <td class="text-end">{{ number_format(array_sum(array_column($vendorSummary,'purchased')), 2) }}</td>
                            <td class="text-end text-warning">{{ number_format(array_sum(array_column($vendorSummary,'returned')), 2) }}</td>
                            <td class="text-end text-success">{{ number_format(array_sum(array_column($vendorSummary,'paid')), 2) }}</td>
                            <td class="text-end text-danger">{{ number_format(array_sum(array_column($vendorSummary,'net_payable')), 2) }}</td>
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
    clone.querySelectorAll('a').forEach(a => {
        const span = document.createElement('span');
        span.textContent = a.textContent;
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
    .text-end{text-align:right}.fw-bold{font-weight:bold}</style>
    </head><body><h2>${title}</h2><p>${period}</p>
    ${clone.innerHTML}
    <script>window.onload=function(){window.print();}<\/script>
    </body></html>`;
    const win = window.open('', '_blank', 'width=900,height=700');
    win.document.write(html);
    win.document.close();
}

$(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
});
</script>
@endsection