@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

{{-- ── Date header ─────────────────────────────────────────────── --}}
<div class="mb-3">
    <h2 class="text-dark mb-0"><strong id="currentDate"></strong></h2>
</div>

{{-- ── Drop-ship alert ─────────────────────────────────────────── --}}
@if($unbilledDropships->count())
<div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
    <div class="d-flex align-items-start gap-2">
        <i class="fas fa-exclamation-triangle fa-lg mt-1"></i>
        <div class="flex-fill">
            <strong>
                {{ $unbilledDropships->count() }}
                Drop-ship Purchase Invoice{{ $unbilledDropships->count() > 1 ? 's' : '' }}
                Pending Sale Invoice
            </strong>
            <p class="mb-2 mt-1 text-muted" style="font-size:0.9em;">
                These purchase invoices were delivered directly to a customer
                but no sale invoice has been created yet.
            </p>
            <ul class="mb-0" style="font-size:0.9em;">
                @foreach($unbilledDropships as $pi)
                <li class="mb-1">
                    <strong>PI-{{ $pi->invoice_no }}</strong>
                    &mdash;
                    <span class="text-muted">{{ $pi->vendor->name ?? '—' }}</span>
                    &rarr;
                    <span class="text-primary fw-semibold">
                        {{ $pi->dropoffCustomer->name ?? '—' }}
                    </span>
                    <span class="text-muted ms-1" style="font-size:0.85em;">
                        ({{ \Carbon\Carbon::parse($pi->invoice_date)->format('d M Y') }})
                    </span>
                    <a href="{{ route('sale_invoices.create') }}"
                       class="btn btn-sm btn-outline-warning ms-2 py-0">
                        Create Sale Invoice
                    </a>
                </li>
                @endforeach
            </ul>
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- ── Stat cards ──────────────────────────────────────────────── --}}
<div class="row mb-3">

    <div class="col-12 col-md-3 mb-2">
        <section class="card card-featured-left card-featured-success h-100">
            <div class="card-body">
                <p class="text-muted mb-1" style="font-size:0.85em;">TODAY'S SALES</p>
                <h3 class="text-success mb-0">
                    <strong>PKR {{ number_format($todaySales, 0) }}</strong>
                </h3>
                @can('sale_invoices.index')
                <div class="mt-2">
                    <a href="{{ route('sale_invoices.index') }}"
                       class="text-success text-uppercase" style="font-size:0.8em;">
                        View Invoices &rarr;
                    </a>
                </div>
                @endcan
            </div>
        </section>
    </div>

    <div class="col-12 col-md-3 mb-2">
        <section class="card card-featured-left card-featured-primary h-100">
            <div class="card-body">
                <p class="text-muted mb-1" style="font-size:0.85em;">THIS MONTH'S SALES</p>
                <h3 class="text-primary mb-0">
                    <strong>PKR {{ number_format($monthSales, 0) }}</strong>
                </h3>
                @can('reports.sales')
                <div class="mt-2">
                    <a href="{{ route('reports.sale') }}"
                       class="text-primary text-uppercase" style="font-size:0.8em;">
                        Sales Report &rarr;
                    </a>
                </div>
                @endcan
            </div>
        </section>
    </div>

    <div class="col-12 col-md-3 mb-2">
        <section class="card card-featured-left card-featured-danger h-100">
            <div class="card-body">
                <p class="text-muted mb-1" style="font-size:0.85em;">TOTAL RECEIVABLES</p>
                <h3 class="text-danger mb-0">
                    <strong>PKR {{ number_format($totalReceivables, 0) }}</strong>
                </h3>
                @can('reports.accounts')
                <div class="mt-2">
                    <a href="{{ route('reports.accounts') }}"
                       class="text-danger text-uppercase" style="font-size:0.8em;">
                        Accounts Report &rarr;
                    </a>
                </div>
                @endcan
            </div>
        </section>
    </div>

    <div class="col-12 col-md-3 mb-2">
        <section class="card card-featured-left card-featured-tertiary h-100">
            <div class="card-body">
                <p class="text-muted mb-1" style="font-size:0.85em;">TOTAL PAYABLES</p>
                <h3 class="mb-0" style="color:#6f42c1;">
                    <strong>PKR {{ number_format($totalPayables, 0) }}</strong>
                </h3>
                @can('reports.accounts')
                <div class="mt-2">
                    <a href="{{ route('reports.accounts') }}"
                       class="text-uppercase" style="font-size:0.8em;color:#6f42c1;">
                        Accounts Report &rarr;
                    </a>
                </div>
                @endcan
            </div>
        </section>
    </div>

</div>

{{-- ── Pending POs ─────────────────────────────────────────────── --}}
@if($pendingPOs->count())
@can('purchase_orders.index')
<div class="row mb-3">
    <div class="col-12">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title mb-0">
                    <i class="fas fa-clipboard-list text-warning me-1"></i>
                    Pending Purchase Orders
                    <span class="badge bg-warning text-dark ms-1">{{ $pendingPOs->count() }}</span>
                </h2>
                <a href="{{ route('purchase_orders.index') }}"
                   class="btn btn-sm btn-outline-secondary">View All</a>
            </header>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>PO #</th>
                                <th>Date</th>
                                <th>Vendor</th>
                                <th>Expected Delivery</th>
                                <th>Status</th>
                                <th width="80">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingPOs as $po)
                            <tr>
                                <td><strong>{{ $po->po_no }}</strong></td>
                                <td>{{ \Carbon\Carbon::parse($po->po_date)->format('d M Y') }}</td>
                                <td>{{ $po->vendor->name ?? '—' }}</td>
                                <td>
                                    @if($po->expected_delivery_date)
                                        @php
                                            $due    = \Carbon\Carbon::parse($po->expected_delivery_date);
                                            $overdue = $due->isPast();
                                        @endphp
                                        <span class="{{ $overdue ? 'text-danger fw-semibold' : '' }}">
                                            {{ $due->format('d M Y') }}
                                            @if($overdue) <i class="fas fa-exclamation-circle"></i> @endif
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $po->status_badge }}">
                                        {{ $po->status_label }}
                                    </span>
                                </td>
                                <td>
                                    @can('purchase_invoices.create')
                                    <a href="{{ route('purchase_invoices.create') }}?po={{ $po->po_no }}"
                                       class="btn btn-xs btn-outline-primary" title="Create Invoice">
                                        <i class="fas fa-file-invoice"></i>
                                    </a>
                                    @endcan
                                    @can('purchase_orders.edit')
                                    <a href="{{ route('purchase_orders.edit', $po->id) }}"
                                       class="btn btn-xs btn-outline-secondary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    @endcan
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endcan
@endif

{{-- ── Recent activity ─────────────────────────────────────────── --}}
<div class="row">

    {{-- Recent Sale Invoices --}}
    @can('sale_invoices.index')
    <div class="col-12 col-md-6 mb-3">
        <section class="card h-100">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title mb-0">Recent Sale Invoices</h2>
                <a href="{{ route('sale_invoices.index') }}"
                   class="btn btn-sm btn-outline-secondary">View All</a>
            </header>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>SI #</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentSaleInvoices as $si)
                            <tr>
                                <td>
                                    <a href="{{ route('sale_invoices.print', $si->id) }}"
                                       target="_blank" class="text-primary">
                                        SI-{{ $si->invoice_no }}
                                    </a>
                                </td>
                                <td>{{ \Carbon\Carbon::parse($si->date)->format('d M Y') }}</td>
                                <td>{{ $si->account->name ?? '—' }}</td>
                                <td>
                                    <span class="badge {{ $si->type === 'cash' ? 'bg-success' : 'bg-warning text-dark' }}">
                                        {{ ucfirst($si->type) }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">No sale invoices yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
    @endcan

    {{-- Recent Purchase Invoices --}}
    @can('purchase_invoices.index')
    <div class="col-12 col-md-6 mb-3">
        <section class="card h-100">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title mb-0">Recent Purchase Invoices</h2>
                <a href="{{ route('purchase_invoices.index') }}"
                   class="btn btn-sm btn-outline-secondary">View All</a>
            </header>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>PI #</th>
                                <th>Date</th>
                                <th>Vendor</th>
                                <th>Drop-ship?</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentPurchaseInvoices as $pi)
                            <tr>
                                <td>
                                    <a href="{{ route('purchase_invoices.print', $pi->id) }}"
                                       target="_blank" class="text-primary">
                                        PI-{{ $pi->invoice_no }}
                                    </a>
                                </td>
                                <td>{{ \Carbon\Carbon::parse($pi->invoice_date)->format('d M Y') }}</td>
                                <td>{{ $pi->vendor->name ?? '—' }}</td>
                                <td>
                                    @if($pi->dropoff_customer_id)
                                        @if($pi->saleInvoice)
                                            <span class="badge bg-success" title="Billed">
                                                <i class="fas fa-check"></i> Billed
                                            </span>
                                        @else
                                            <span class="badge bg-warning text-dark" title="Pending sale invoice">
                                                <i class="fas fa-clock"></i> Pending SI
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">No purchase invoices yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
    @endcan

</div>

<script>
$(document).ready(function () {
    function getDaySuffix(day) {
        if (day >= 11 && day <= 13) return day + 'th';
        switch (day % 10) {
            case 1: return day + 'st';
            case 2: return day + 'nd';
            case 3: return day + 'rd';
            default: return day + 'th';
        }
    }

    const now   = new Date();
    const day   = getDaySuffix(now.getDate());
    const month = now.toLocaleString('en-GB', { month: 'long' });
    const weekday = now.toLocaleString('en-GB', { weekday: 'long' });

    document.getElementById('currentDate').innerText =
        `${weekday}, ${day} ${month} ${now.getFullYear()}`;
});
</script>

@endsection