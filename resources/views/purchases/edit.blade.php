@extends('layouts.app')

@section('title', 'Purchase | Edit Invoice')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_invoices.update', $invoice->id) }}" method="POST"
          enctype="multipart/form-data">
      @csrf
      @method('PUT')
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">Edit Purchase Invoice: {{ $invoice->bill_no ?? $invoice->invoice_no }}</h2>
        </header>

        <div class="card-body">
          <div class="row">

            {{-- PO reference (display-only on edit, since items are already saved) --}}
            <div class="col-md-2 mb-3">
              <label>Linked PO #</label>
              <input type="text" class="form-control"
                     value="{{ $invoice->purchaseOrder->po_no ?? '—' }}" disabled>
              <input type="hidden" name="po_id" value="{{ $invoice->po_id }}">
            </div>

            <div class="col-md-2 mb-3">
              <label>Invoice Date</label>
              <input type="date" name="invoice_date" class="form-control"
                     value="{{ $invoice->invoice_date }}" required>
            </div>

            <div class="col-md-2 mb-3">
              <label>Vendor <span class="text-danger">*</span></label>
              <select name="vendor_id" class="form-control select2-js" required>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}"
                    {{ $invoice->vendor_id == $vendor->id ? 'selected' : '' }}>
                    {{ $vendor->name }}
                  </option>
                @endforeach
              </select>
            </div>

            {{-- Drop-ship customer --}}
            <div class="col-md-2 mb-3">
              <label>Drop-ship Customer <small class="text-muted">(optional)</small></label>
              <select name="dropoff_customer_id" class="form-control select2-js">
                <option value="">— None —</option>
                @foreach ($customers as $customer)
                  <option value="{{ $customer->id }}"
                    {{ $invoice->dropoff_customer_id == $customer->id ? 'selected' : '' }}>
                    {{ $customer->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-2 mb-3">
              <label>Bill #</label>
              <input type="text" name="bill_no" class="form-control"
                     value="{{ $invoice->bill_no }}">
            </div>

            <div class="col-md-2 mb-3">
              <label>Ref.</label>
              <input type="text" name="ref_no" class="form-control"
                     value="{{ $invoice->ref_no }}">
            </div>

            <div class="col-md-3 mb-3">
              <label>Attachments <small class="text-muted">(leave blank to keep existing)</small></label>
              <input type="file" name="attachments[]" class="form-control" multiple
                     accept=".pdf,.jpg,.jpeg,.png,.zip">
            </div>

            <div class="col-md-4 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="2">{{ $invoice->remarks }}</textarea>
            </div>

          </div>

          {{-- Existing attachments --}}
          @if($invoice->attachments->count())
          <div class="mb-3">
            <label class="fw-bold">Existing Attachments</label>
            <div class="d-flex flex-wrap gap-2">
              @foreach($invoice->attachments as $file)
              <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank"
                 class="border rounded p-2 text-sm">
                <i class="fas fa-file me-1"></i>{{ $file->original_name ?? basename($file->file_path) }}
              </a>
              @endforeach
            </div>
          </div>
          @endif

          <div class="table-responsive mb-3">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>S.No</th><th>Item</th><th>Variation</th><th>Quantity</th>
                  <th>Unit</th><th>Price</th><th>Amount</th><th>Action</th>
                </tr>
              </thead>
              <tbody id="PurchaseTableBody">
                @foreach($invoice->items as $key => $item)
                <tr>
                  <td class="serial-no">{{ $key + 1 }}</td>
                  <td>
                    <select name="items[{{ $key }}][item_id]" id="item_name{{ $key + 1 }}"
                            class="form-control select2-js product-select"
                            onchange="onItemNameChange(this)">
                      @foreach ($products as $product)
                        <option value="{{ $product->id }}"
                                data-unit-id="{{ $product->measurement_unit }}"
                                {{ $item->item_id == $product->id ? 'selected' : '' }}>
                          {{ $product->name }}
                        </option>
                      @endforeach
                    </select>
                  </td>
                  <td>
                    <select name="items[{{ $key }}][variation_id]" id="variation{{ $key + 1 }}"
                            class="form-control select2-js">
                      <option value="">No Variation</option>
                      @foreach($item->product->variations as $v)
                        <option value="{{ $v->id }}"
                          {{ $item->variation_id == $v->id ? 'selected' : '' }}>
                          {{ $v->sku }}
                        </option>
                      @endforeach
                    </select>
                  </td>
                  <td>
                    <input type="number" name="items[{{ $key }}][quantity]"
                           id="pur_qty{{ $key + 1 }}" class="form-control quantity"
                           value="{{ $item->quantity }}" step="any"
                           onchange="rowTotal({{ $key + 1 }})">
                  </td>
                  <td>
                    <select name="items[{{ $key }}][unit]" class="form-control">
                      @foreach ($units as $unit)
                        <option value="{{ $unit->id }}"
                          {{ $item->unit == $unit->id ? 'selected' : '' }}>
                          {{ $unit->name }}
                        </option>
                      @endforeach
                    </select>
                  </td>
                  <td>
                    <input type="number" name="items[{{ $key }}][price]"
                           id="pur_price{{ $key + 1 }}" class="form-control"
                           value="{{ $item->price }}" step="any"
                           onchange="rowTotal({{ $key + 1 }})">
                  </td>
                  <td>
                    <input type="number" id="amount{{ $key + 1 }}" class="form-control"
                           value="{{ $item->quantity * $item->price }}" disabled>
                  </td>
                  <td>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
                      <i class="fas fa-times"></i>
                    </button>
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
            <button type="button" class="btn btn-outline-primary" onclick="addNewRow()">
              <i class="fas fa-plus"></i> Add Item
            </button>
          </div>

          <div class="row mb-3">
            <div class="col-md-2">
              <label>Total Amount</label>
              <input type="text" id="totalAmount" class="form-control"
                     value="{{ $invoice->total_amount }}" disabled>
              <input type="hidden" name="total_amount" id="total_amount_show"
                     value="{{ $invoice->total_amount }}">
            </div>
            <div class="col-md-2">
              <label>Total Quantity</label>
              <input type="text" id="total_quantity" class="form-control"
                     value="{{ $invoice->total_quantity }}" disabled>
              <input type="hidden" name="total_quantity" id="total_quantity_show"
                     value="{{ $invoice->total_quantity }}">
            </div>
            <div class="col text-end mt-3">
              <h4>Net Amount: <strong class="text-danger">PKR
                <span id="netTotal">{{ number_format($invoice->net_amount, 2) }}</span>
              </strong></h4>
              <input type="hidden" name="net_amount" id="net_amount"
                     value="{{ $invoice->net_amount }}">
            </div>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-primary">Update Invoice</button>
          <a href="{{ route('purchase_invoices.index') }}" class="btn btn-default">Cancel</a>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
var products = @json($products);
var units    = @json($units);
var index    = {{ count($invoice->items) + 1 }};

$(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
    tableTotal();
});

function updateSerialNumbers() {
    $('.serial-no').each((i, el) => $(el).text(i + 1));
}

function removeRow(btn) {
    $(btn).closest('tr').remove();
    updateSerialNumbers();
    tableTotal();
}

function addNewRow() {
    let rowIndex = index - 1;
    let newRow = `
      <tr>
        <td class="serial-no"></td>
        <td>
          <select name="items[${rowIndex}][item_id]" id="item_name${index}"
                  class="form-control select2-js product-select"
                  onchange="onItemNameChange(this)">
            <option value="">Select Item</option>
            ${products.map(p =>
              `<option value="${p.id}" data-unit-id="${p.measurement_unit}">${p.name}</option>`
            ).join('')}
          </select>
        </td>
        <td>
          <select name="items[${rowIndex}][variation_id]" id="variation${index}"
                  class="form-control select2-js">
            <option value="">Select Variation</option>
          </select>
        </td>
        <td>
          <input type="number" name="items[${rowIndex}][quantity]" id="pur_qty${index}"
                 class="form-control quantity" value="0" step="any"
                 onchange="rowTotal(${index})">
        </td>
        <td>
          <select name="items[${rowIndex}][unit]" class="form-control select2-js">
            ${units.map(u => `<option value="${u.id}">${u.name}</option>`).join('')}
          </select>
        </td>
        <td>
          <input type="number" name="items[${rowIndex}][price]" id="pur_price${index}"
                 class="form-control" value="0" step="any" onchange="rowTotal(${index})">
        </td>
        <td>
          <input type="number" id="amount${index}" class="form-control" value="0" disabled>
        </td>
        <td>
          <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
            <i class="fas fa-times"></i>
          </button>
        </td>
      </tr>`;

    $('#PurchaseTableBody').append(newRow);
    $(`#item_name${index}, #variation${index}`).select2({ width: '100%' });
    index++;
    updateSerialNumbers();
}

function rowTotal(row) {
    let qty   = parseFloat($('#pur_qty'   + row).val()) || 0;
    let price = parseFloat($('#pur_price' + row).val()) || 0;
    $('#amount' + row).val((qty * price).toFixed(2));
    tableTotal();
}

function tableTotal() {
    let total = 0, qty = 0;
    $('input[id^="pur_qty"]').each(function () { qty += parseFloat($(this).val()) || 0; });
    $('.quantity').each(function () {
        let row = $(this).closest('tr');
        total  += parseFloat(row.find('input[id^="amount"]').val()) || 0;
    });
    $('#totalAmount').val(total.toFixed(2));
    $('#total_amount_show').val(total.toFixed(2));
    $('#total_quantity').val(qty.toFixed(2));
    $('#total_quantity_show').val(qty.toFixed(2));
    $('#netTotal').text(total.toFixed(2));
    $('#net_amount').val(total.toFixed(2));
}

function onItemNameChange(selectElement) {
    const idMatch = selectElement.id.match(/\d+$/);
    if (!idMatch) return;
    const rowIndex = idMatch[0];

    const unitId = selectElement.options[selectElement.selectedIndex].getAttribute('data-unit-id');
    $(`#unit${rowIndex}`).val(String(unitId)).trigger('change.select2');

    const itemId = selectElement.value;
    const $var   = $(`#variation${rowIndex}`);

    if (itemId) {
        $var.html('<option value="">Loading...</option>').trigger('change.select2');
        fetch(`/product/${itemId}/variations`)
            .then(r => r.json())
            .then(data => {
                $var.html('<option value="">Select Variation</option>');
                if (data.success && data.variation.length > 0) {
                    data.variation.forEach(v => $var.append(`<option value="${v.id}">${v.sku}</option>`));
                } else {
                    $var.html('<option value="">No Variations Found</option>');
                }
                $var.trigger('change.select2');
            })
            .catch(() => $var.html('<option value="">Error loading</option>').trigger('change.select2'));
    } else {
        $var.html('<option value="">Select Variation</option>').trigger('change.select2');
    }
}
</script>
@endsection