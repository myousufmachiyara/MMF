@extends('layouts.app')

@section('title', 'Purchase Orders | New')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_orders.store') }}" method="POST"
          enctype="multipart/form-data" onkeydown="return event.key != 'Enter';">
      @csrf
      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">New Purchase Order</h2>
        </header>

        <div class="card-body">
          <div class="row">

            <div class="col-md-2 mb-3">
              <label>PO Date <span class="text-danger">*</span></label>
              <input type="date" name="po_date" class="form-control"
                     value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-2 mb-3">
              <label>Vendor <span class="text-danger">*</span></label>
              <select name="vendor_id" class="form-control select2-js" required>
                <option value="">Select Vendor</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-2 mb-3">
              <label>Expected Delivery</label>
              <input type="date" name="expected_delivery_date" class="form-control">
            </div>

            <div class="col-md-2 mb-3">
              <label>Ref.</label>
              <input type="text" name="ref_no" class="form-control">
            </div>

            <div class="col-md-2 mb-3">
              <label>Status <span class="text-danger">*</span></label>
              <select name="status" class="form-control select2-js" required>
                @foreach($statuses as $value => $label)
                  <option value="{{ $value }}" {{ $value === 'draft' ? 'selected' : '' }}>
                    {{ $label }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label>Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple
                     accept=".pdf,.jpg,.jpeg,.png,.zip">
            </div>

            <div class="col-md-4 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="2"></textarea>
            </div>

          </div>

          {{-- ── Items table ─────────────────────────────────── --}}
          <div class="table-responsive mb-3">
            <table class="table table-bordered" id="poTable">
              <thead>
                <tr>
                  <th>S.No</th>
                  <th>Item</th>
                  <th>Variation</th>
                  <th>Quantity</th>
                  <th>Unit</th>
                  <th>Price</th>
                  <th>Amount</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="poTableBody">
                <tr>
                  <td class="serial-no">1</td>
                  <td>
                    <select name="items[0][item_id]" id="item_name1"
                            class="form-control select2-js product-select"
                            onchange="onItemNameChange(this)">
                      <option value="">Select Item</option>
                      @foreach ($products as $product)
                        <option value="{{ $product->id }}"
                                data-unit-id="{{ $product->measurement_unit }}">
                          {{ $product->name }}
                        </option>
                      @endforeach
                    </select>
                  </td>
                  <td>
                    <select name="items[0][variation_id]" id="variation1"
                            class="form-control select2-js">
                      <option value="">Select Variation</option>
                    </select>
                  </td>
                  <td>
                    <input type="number" name="items[0][quantity]" id="pur_qty1"
                           class="form-control quantity" value="0" step="any"
                           onchange="rowTotal(1)">
                  </td>
                  <td>
                    <select name="items[0][unit]" id="unit1" class="form-control" required>
                      <option value="">-- Select --</option>
                      @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->shortcode }})</option>
                      @endforeach
                    </select>
                  </td>
                  <td>
                    <input type="number" name="items[0][price]" id="pur_price1"
                           class="form-control" value="0" step="any" onchange="rowTotal(1)">
                  </td>
                  <td>
                    <input type="number" id="amount1" class="form-control" value="0"
                           step="any" disabled>
                  </td>
                  <td>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
                      <i class="fas fa-times"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
            <button type="button" class="btn btn-outline-primary" onclick="addNewRow()">
              <i class="fas fa-plus"></i> Add Item
            </button>
          </div>

          {{-- ── Totals ──────────────────────────────────────── --}}
          <div class="row mb-3">
            <div class="col-md-2">
              <label>Total Amount</label>
              <input type="text" id="totalAmount" class="form-control" disabled>
              <input type="hidden" name="total_amount" id="total_amount_show">
            </div>
            <div class="col-md-2">
              <label>Total Quantity</label>
              <input type="text" id="total_quantity" class="form-control" disabled>
              <input type="hidden" name="total_quantity" id="total_quantity_show">
            </div>
          </div>
          <div class="row">
            <div class="col text-end">
              <h4>Net Amount: <strong class="text-danger">PKR <span id="netTotal">0.00</span></strong></h4>
              <input type="hidden" name="net_amount" id="net_amount">
            </div>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success">
            <i class="fas fa-save"></i> Save Purchase Order
          </button>
          <a href="{{ route('purchase_orders.index') }}" class="btn btn-default">Cancel</a>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
var products = @json($products);
var index = 2;

$(document).ready(function () {
    $('.select2-js').select2({ width: '100%', dropdownAutoWidth: true });
    updateSerialNumbers();
});

function updateSerialNumbers() {
    $('#poTableBody tr').each(function (i) {
        $(this).find('.serial-no').text(i + 1);
    });
}

function removeRow(button) {
    let rows = $('#poTableBody tr').length;
    if (rows > 1) {
        $(button).closest('tr').remove();
        tableTotal();
        updateSerialNumbers();
    }
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
            <select name="items[${rowIndex}][unit]" id="unit${index}"
                    class="form-control" required>
              <option value="">-- Select --</option>
              @foreach ($units as $unit)
                <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->shortcode }})</option>
              @endforeach
            </select>
          </td>
          <td>
            <input type="number" name="items[${rowIndex}][price]" id="pur_price${index}"
                   class="form-control" value="0" step="any" onchange="rowTotal(${index})">
          </td>
          <td>
            <input type="number" id="amount${index}" class="form-control"
                   value="0" step="any" disabled>
          </td>
          <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
              <i class="fas fa-times"></i>
            </button>
          </td>
        </tr>`;

    $('#poTableBody').append(newRow);
    $(`#item_name${index}`).select2({ width: '100%' });
    $(`#variation${index}`).select2({ width: '100%' });
    $(`#unit${index}`).select2({ width: '100%' });
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
    $('#poTableBody tr').each(function () {
        total += parseFloat($(this).find('input[id^="amount"]').val()) || 0;
        qty   += parseFloat($(this).find('input.quantity').val())       || 0;
    });
    $('#totalAmount').val(total.toFixed(2));
    $('#total_amount_show').val(total.toFixed(2));
    $('#total_quantity').val(qty.toFixed(2));
    $('#total_quantity_show').val(qty.toFixed(2));
    $('#netTotal').text(total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','));
    $('#net_amount').val(total.toFixed(2));
}

function onItemNameChange(selectElement) {
    const idMatch = selectElement.id.match(/\d+$/);
    if (!idMatch) return;
    const rowIndex = idMatch[0];

    // Auto-select unit
    const unitId = selectElement.options[selectElement.selectedIndex].getAttribute('data-unit-id');
    $(`#unit${rowIndex}`).val(String(unitId)).trigger('change.select2');

    // Load variations
    const itemId          = selectElement.value;
    const variationSelect = $(`#variation${rowIndex}`);

    if (itemId) {
        variationSelect.html('<option value="">Loading...</option>').trigger('change.select2');
        fetch(`/product/${itemId}/variations`)
            .then(r => r.json())
            .then(data => {
                variationSelect.html('<option value="">Select Variation</option>');
                if (data.success && data.variation.length > 0) {
                    data.variation.forEach(v => {
                        variationSelect.append(`<option value="${v.id}">${v.sku}</option>`);
                    });
                } else {
                    variationSelect.html('<option value="">No Variations</option>');
                }
                variationSelect.trigger('change.select2');
            })
            .catch(() => {
                variationSelect.html('<option value="">Error loading</option>').trigger('change.select2');
            });
    } else {
        variationSelect.html('<option value="">Select Variation</option>').trigger('change.select2');
    }
}
</script>
@endsection