@extends('layouts.app')

@section('title', 'Sale Invoices')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">

      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif
      @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
      @endif

      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title">All Sale Invoices</h2>
        @can('sale_invoices.create')
        <a href="{{ route('sale_invoices.create') }}" class="btn btn-primary">
          <i class="fas fa-plus"></i> Sale Invoice
        </a>
        @endcan
      </header>

      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-hover" id="siTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Invoice #</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Type</th>
                <th>PI Ref</th>
                <th>Net Amount</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($invoices as $invoice)
              @php
                $subTotal = $invoice->items->sum(fn($item) =>
                    ($item->sale_price - ($item->sale_price * ($item->discount ?? 0) / 100)) * $item->quantity
                );
                $netAmount = $subTotal - ($invoice->discount ?? 0);
              @endphp
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td><strong>SI-{{ $invoice->invoice_no }}</strong></td>
                <td>{{ \Carbon\Carbon::parse($invoice->date)->format('d-M-Y') }}</td>
                <td>{{ $invoice->account->name ?? '—' }}</td>
                <td>
                  <span class="badge {{ $invoice->type === 'credit' ? 'bg-warning text-dark' : 'bg-success' }}">
                    {{ ucfirst($invoice->type) }}
                  </span>
                </td>
                <td>
                  @if($invoice->purchaseInvoice)
                    <span class="badge bg-info text-dark">
                      PI-{{ $invoice->purchaseInvoice->invoice_no }}
                    </span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td><strong>{{ number_format($netAmount, 2) }}</strong></td>
                <td>
                  @can('sale_invoices.edit')
                  <a href="{{ route('sale_invoices.edit', $invoice->id) }}"
                     class="text-primary me-1" title="Edit">
                    <i class="fas fa-edit"></i>
                  </a>
                  @endcan
                  @can('sale_invoices.print')
                  <a href="{{ route('sale_invoices.print', $invoice->id) }}"
                     target="_blank" class="text-success me-1" title="Print">
                    <i class="fas fa-print"></i>
                  </a>
                  @endcan
                  @can('sale_invoices.delete')
                  <form action="{{ route('sale_invoices.destroy', $invoice->id) }}"
                        method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-link p-0 text-danger"
                            onclick="return confirm('Delete this invoice?')" title="Delete">
                      <i class="fas fa-trash"></i>
                    </button>
                  </form>
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

<script>
$(document).ready(function () {
    $('#siTable').DataTable({
        pageLength: 50,
        order: [[0, 'desc']],
    });
});
</script>
@endsection