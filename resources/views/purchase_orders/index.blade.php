@extends('layouts.app')

@section('title', 'Purchase Orders')

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
                <h2 class="card-title">
                    {{ request('view_deleted') ? 'Deleted' : 'All' }} Purchase Orders
                </h2>
                <div class="d-flex gap-2">
                    {{-- Status filter --}}
                    <form method="GET" action="{{ route('purchase_orders.index') }}" class="d-flex gap-2 align-items-center">
                        <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                            <option value="all" {{ request('status','all') === 'all' ? 'selected' : '' }}>All Statuses</option>
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </form>

                    @if(request('view_deleted'))
                        <a href="{{ route('purchase_orders.index') }}" class="btn btn-default btn-sm">
                            <i class="fas fa-list"></i> View Active
                        </a>
                    @else
                        <a href="{{ route('purchase_orders.index', ['view_deleted' => 1]) }}" class="btn btn-danger btn-sm">
                            <i class="fas fa-trash-restore"></i> View Deleted
                        </a>
                    @endif

                    <a href="{{ route('purchase_orders.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> New PO
                    </a>
                </div>
            </header>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="poTable">
                        <thead>
                            <tr>
                                <th width="4%">#</th>
                                <th>PO Date</th>
                                <th>PO #</th>
                                <th>Vendor</th>
                                <th>Expected Delivery</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Attachments</th>
                                <th width="10%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $index => $order)
                            @php
                                $total = $order->items->sum(fn($i) => $i->quantity * $i->price);
                            @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ \Carbon\Carbon::parse($order->po_date)->format('d-M-Y') }}</td>
                                <td class="text-primary"><strong>{{ $order->po_no }}</strong></td>
                                <td>{{ $order->vendor->name ?? 'N/A' }}</td>
                                <td>
                                    {{ $order->expected_delivery_date
                                        ? \Carbon\Carbon::parse($order->expected_delivery_date)->format('d-M-Y')
                                        : '—' }}
                                </td>
                                <td>{{ number_format($total, 2) }}</td>
                                <td>
                                    {{-- Inline status badge / quick-change dropdown --}}
                                    <div class="dropdown">
                                        <span class="badge bg-{{ $order->status_badge }} dropdown-toggle"
                                              style="cursor:pointer;font-size:0.85em;"
                                              data-bs-toggle="dropdown">
                                            {{ $order->status_label }}
                                        </span>
                                        <ul class="dropdown-menu dropdown-menu-sm">
                                            @foreach(\App\Models\PurchaseOrder::STATUSES as $val => $lbl)
                                                @if($val !== $order->status)
                                                <li>
                                                    <form method="POST"
                                                          action="{{ route('purchase_orders.status', $order->id) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="{{ $val }}">
                                                        <button type="submit" class="dropdown-item">
                                                            {{ $lbl }}
                                                        </button>
                                                    </form>
                                                </li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    </div>
                                </td>
                                <td>
                                    @if($order->attachments->count())
                                        @foreach($order->attachments as $file)
                                            <a href="{{ asset('storage/' . $file->file_path) }}"
                                               target="_blank" class="me-1" title="{{ $file->original_name }}">
                                                <i class="fas fa-file"></i>
                                            </a>
                                        @endforeach
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($order->trashed())
                                        <form action="{{ route('purchase_orders.restore', $order->id) }}"
                                              method="POST" style="display:inline;">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-link p-0 text-success" title="Restore">
                                                <i class="fas fa-undo"></i> Restore
                                            </button>
                                        </form>
                                    @else
                                        <a href="{{ route('purchase_orders.edit', $order->id) }}"
                                           class="text-primary me-1" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="{{ route('purchase_orders.print', $order->id) }}"
                                           target="_blank" class="text-success me-1" title="Print">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <form action="{{ route('purchase_orders.destroy', $order->id) }}"
                                              method="POST" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-link p-0 text-danger"
                                                    onclick="return confirm('Move this PO to trash?')" title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    @endif
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
    $('#poTable').DataTable({
        pageLength: 50,
        order: [[0, 'desc']],
    });
});
</script>
@endsection