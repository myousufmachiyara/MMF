@extends('layouts.app')

@section('title', 'Edit Sale Invoice')

@section('content')
<style>
    .select2-container { display: block !important; width: 100% !important; }
    .select2-container--default .select2-selection--single { height: 38px !important; padding: 5px; border: 1px solid #ced4da; }
    #itemTable td { vertical-align: middle; }
    .stock-label { font-size: 11px; font-weight: bold; margin-top: 2px; }
</style>

<div class="row">
  <form action="{{ route('sale_invoices.update', $invoice->id) }}"
        onkeydown="return event.key != 'Enter';" method="POST">
    @csrf
    @method('PUT')

    {{-- Hidden field for linked PI (value set by Blade, cleared by unlink button) --}}
    <input type="hidden" name="purchase_invoice_id" id="pi_hidden"
           value="{{ $invoice->purchase_invoice_id }}">

    {{-- ── Header card ───────────────────────────────────────── --}}
    <div class="col-12 mb-2">
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">Edit Sale Invoice: <strong>SI-{{ $invoice->invoice_no }}</strong></h2>
        </header>
        <div class="card-body">
          <div class="row mb-2 align-items-end">

            <div class="col-md-2">
              <label>Invoice #</label>
              <input type="text" class="form-control"
                     value="{{ $invoice->invoice_no }}" readonly/>
            </div>

            <div class="col-md-2">
              <label>Date <span class="text-danger">*</span></label>
              <input type="date" name="date" class="form-control"
                     value="{{ $invoice->date }}" required />
            </div>

            <div class="col-md-3">
              <label>Customer <span class="text-danger">*</span></label>
              <select name="account_id" class="form-control select2-js" required>
                @foreach($customers as $acc)
                  <option value="{{ $acc->id }}"
                    {{ $invoice->account_id == $acc->id ? 'selected' : '' }}>
                    {{ $acc->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-2">
              <label>Invoice Type <span class="text-danger">*</span></label>
              <select name="type" class="form-control" required>
                <option value="cash"   {{ $invoice->type == 'cash'   ? 'selected' : '' }}>Cash</option>
                <option value="credit" {{ $invoice->type == 'credit' ? 'selected' : '' }}>Credit</option>
              </select>
            </div>

            {{-- Linked PI — visible read-only field with unlink option --}}
            <div class="col-md-3">
              <label>Linked Purchase Invoice</label>
              @if($invoice->purchaseInvoice)
                <div class="input-group">
                  <input type="text" class="form-control" id="pi_display_input" readonly
                         value="PI-{{ $invoice->purchaseInvoice->invoice_no }} — {{ $invoice->purchaseInvoice->vendor->name ?? '' }}"
                         style="background:#fff3cd; border-color:#ffc107; font-weight:600;">
                  <button type="button" class="btn btn-outline-danger" title="Unlink PI"
                          onclick="unlinkPi()">
                    <i class="fas fa-unlink"></i>
                  </button>
                </div>
                <small class="text-muted">Drop-ship invoice link</small>
              @else
                <input type="text" class="form-control"
                       value="— none —" readonly style="background:#f8f9fa;">
                <small class="text-muted">No PI linked</small>
              @endif
            </div>

          </div>
        </div>
      </section>
    </div>

    {{-- ── Items card ─────────────────────────────────────────── --}}
    <div class="col-12">
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">Invoice Items</h2>
        </header>
        <div class="card-body">
          <table class="table table-bordered" id="itemTable">
            <thead>
              <tr class="bg-light">
                <th>Item</th>
                <th width="15%">Variation</th>
                <th width="12%">Price</th>
                <th width="10%">Qty</th>
                <th width="12%">Total</th>
                <th width="5%"></th>
              </tr>
            </thead>
            <tbody>
              @foreach($invoice->items as $i => $item)
              <tr>
                <td>
                  <div class="stock-label"></div>
                  <select name="items[{{ $i }}][product_id]"
                          id="item_name{{ $i }}"
                          class="form-control select2-js product-select"
                          onchange="onItemNameChange(this)" required>
                    <option value="">Select Product</option>
                    @foreach($products as $product)
                      @php
                        $isCurrent    = ($item->product_id == $product->id);
                        $displayStock = $product->real_time_stock + ($isCurrent ? $item->quantity : 0);
                      @endphp
                      <option value="{{ $product->id }}"
                              data-price="{{ $product->selling_price ?? 0 }}"
                              data-stock="{{ $displayStock }}"
                              {{ $isCurrent ? 'selected' : '' }}>
                        {{ $product->name }} (Stock: {{ $displayStock }})
                      </option>
                    @endforeach
                  </select>
                </td>
                <td>
                  <select name="items[{{ $i }}][variation_id]"
                          id="variation{{ $i }}"
                          class="form-control select2-js variation-select">
                    <option value="">Select Variation</option>
                    @if($item->variation_id)
                      <option value="{{ $item->variation_id }}" selected>
                        {{ $item->variation->sku ?? 'Selected Variation' }}
                      </option>
                    @endif
                  </select>
                </td>
                <td>
                  <input type="number" name="items[{{ $i }}][sale_price]"
                         class="form-control sale-price" step="any"
                         value="{{ $item->sale_price }}" required>
                </td>
                <td>
                  <input type="number" name="items[{{ $i }}][quantity]"
                         class="form-control quantity" step="any"
                         value="{{ $item->quantity }}" required>
                  <small class="stock-label-inline"
                         style="font-size:11px; font-weight:bold;"></small>
                </td>
                <td>
                  <input type="number" class="form-control row-total"
                         value="{{ $item->sale_price * $item->quantity }}" readonly>
                </td>
                <td>
                  <button type="button" class="btn btn-danger btn-sm"
                          onclick="removeRow(this)">
                    <i class="fas fa-times"></i>
                  </button>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
          <button type="button" class="btn btn-success btn-sm" onclick="addRow()">
            + Add Item
          </button>

          <hr>

          {{-- Totals + Remarks --}}
          <div class="row mb-2">
            <div class="col-md-4">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="3">{{ $invoice->remarks }}</textarea>
            </div>
            <div class="col-md-2">
              <label><strong>Discount (PKR)</strong></label>
              <input type="number" name="discount" id="discountInput"
                     class="form-control" step="any" value="{{ $invoice->discount }}">
            </div>
            <div class="col-md-6 text-end">
              <label><strong>Net Payable</strong></label>
              <h3 class="text-primary mt-0 mb-1">
                PKR <span id="netAmountText">0.00</span>
              </h3>
              <input type="hidden" name="net_amount" id="netAmountInput"
                     value="{{ $invoice->net_amount }}">
              <label class="text-danger mt-2"><strong>Remaining Balance</strong></label>
              <h4 class="text-danger mt-0">
                PKR <span id="balanceAmountText">0.00</span>
              </h4>
            </div>
          </div>

          <hr>

          {{-- ── Payment section ─────────────────────────────── --}}
          <div class="row p-3 mb-2"
               style="background-color:#e7f3ff; border-radius:5px; border:1px solid #b8daff;">
            <div class="col-md-12 mb-2">
              <h5><i class="fas fa-money-bill-wave me-1"></i> Payment</h5>
              <small class="text-muted">
                Edit the total received amount here to correct any previous entry.
                Set to 0 to remove the payment entirely.
              </small>
            </div>

            <div class="col-md-4">
              <label>Receive In (Cash/Bank)</label>
              <select name="payment_account_id" class="form-control select2-js">
                <option value="">-- No Payment --</option>
                @foreach($paymentAccounts as $pa)
                  <option value="{{ $pa->id }}"
                    {{ $existingPaymentAccountId == $pa->id ? 'selected' : '' }}>
                    {{ $pa->name }}
                  </option>
                @endforeach
              </select>
              <small class="text-muted">Change account if needed</small>
            </div>

            <div class="col-md-4">
              <label>
                <strong>Total Amount Received</strong>
                <span class="text-danger">*</span>
              </label>
              <input type="number" name="amount_received" id="amountReceivedInput"
                     class="form-control form-control-lg" step="any"
                     value="{{ $amountReceived > 0 ? $amountReceived : '' }}"
                     placeholder="0.00">
              <small class="text-muted">
                Previously received:
                <strong class="text-success">
                  PKR {{ number_format($amountReceived, 2) }}
                </strong>
              </small>
            </div>

            <div class="col-md-4 d-flex align-items-center justify-content-end">
              <div class="text-end">
                <label class="text-muted d-block">Balance after this payment:</label>
                <h4 class="text-danger mb-0">
                  PKR <span id="balanceAfterPayment">0.00</span>
                </h4>
              </div>
            </div>
          </div>

        </div>
        <footer class="card-footer text-end">
          <a href="{{ route('sale_invoices.index') }}" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary btn-lg">
            <i class="fas fa-save"></i> Update Invoice
          </button>
        </footer>
      </section>
    </div>

  </form>
</div>

<script>
let rowIndex = {{ $invoice->items->count() }};

$(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });

    // Initialize stock labels and variations for existing rows
    $('#itemTable tbody tr').each(function () {
        const row       = $(this);
        const productId = row.find('.product-select').val();
        const varId     = row.find('.variation-select').val();

        updateStockLabel(row.find('.product-select'));
        calcRowTotal(row);

        if (productId) {
            loadVariations(productId, row.find('.variation-select'), varId);
        }
    });

    $(document).on('input', '.quantity, .sale-price, #discountInput', function () {
        const row = $(this).closest('tr');
        if (row.length && row.closest('tbody').length) {
            if ($(this).hasClass('quantity')) {
                const stock = parseFloat(row.find('.product-select :selected').data('stock')) || 0;
                $(this).css('border-color', parseFloat($(this).val()) > stock ? 'red' : '');
            }
            calcRowTotal(row);
        } else {
            calcTotal();
        }
    });

    $(document).on('input', '#amountReceivedInput', calcTotal);

    calcTotal();
});

// Unlink PI
function unlinkPi() {
    $('#pi_hidden').val('');
    const $inp = $('#pi_display_input');
    $inp.val('— unlinked —').css({ background: '#f8f9fa', borderColor: '#ced4da', fontWeight: 'normal' });
    $inp.closest('.input-group').find('button').prop('disabled', true);
}

function onItemNameChange(selectElement) {
    const $row          = $(selectElement).closest('tr');
    const productId     = selectElement.value;
    const variationSel  = $row.find('.variation-select');

    $row.find('.sale-price').val(
        $(selectElement).find(':selected').data('price') || 0
    );

    updateStockLabel($(selectElement));
    loadVariations(productId, variationSel);
    calcRowTotal($row);
}

function loadVariations(productId, dropdown, selectedId = null) {
    if (!productId) {
        dropdown.html('<option value="">Select Variation</option>').trigger('change.select2');
        return;
    }

    dropdown.html('<option value="">Loading...</option>').trigger('change.select2');

    fetch(`/product/${productId}/variations`)
        .then(res => res.json())
        .then(data => {
            dropdown.html('<option value="">Select Variation</option>');
            const variations = data.variation || data.variations || [];

            variations.forEach(v => {
                const selected = (selectedId == v.id) ? 'selected' : '';
                const label    = [v.sku, v.name].filter(Boolean).join(' - ');
                dropdown.append(`<option value="${v.id}" ${selected}>${label}</option>`);
            });

            if (variations.length === 0) {
                dropdown.html('<option value="">Standard (No Variations)</option>');
            }
            dropdown.trigger('change.select2');
        });
}

function updateStockLabel(selectElement) {
    const row            = selectElement.closest('tr');
    const stockAvailable = parseFloat(selectElement.find(':selected').data('stock')) || 0;
    const label          = row.find('.stock-label');
    if (!selectElement.val()) { label.text(''); return; }
    label.text('Available: ' + stockAvailable);
    label.css('color', stockAvailable <= 0 ? 'red' : (stockAvailable < 5 ? 'orange' : 'green'));
}

function addRow() {
    const idx = rowIndex++;
    const rowHtml = `
        <tr>
            <td>
                <div class="stock-label"></div>
                <select name="items[${idx}][product_id]"
                        class="form-control select2-js product-select"
                        onchange="onItemNameChange(this)" required>
                    <option value="">Select Product</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}"
                                data-price="{{ $product->selling_price ?? 0 }}"
                                data-stock="{{ $product->real_time_stock ?? 0 }}">
                            {{ $product->name }} (Stock: {{ $product->real_time_stock ?? 0 }})
                        </option>
                    @endforeach
                </select>
            </td>
            <td>
                <select name="items[${idx}][variation_id]"
                        class="form-control select2-js variation-select">
                    <option value="">Select Variation</option>
                </select>
            </td>
            <td>
                <input type="number" name="items[${idx}][sale_price]"
                       class="form-control sale-price" step="any" required>
            </td>
            <td>
                <input type="number" name="items[${idx}][quantity]"
                       class="form-control quantity" step="any" required>
                <small class="stock-label-inline" style="font-size:11px;font-weight:bold;"></small>
            </td>
            <td>
                <input type="number" class="form-control row-total" readonly>
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        </tr>`;

    $('#itemTable tbody').append(rowHtml);
    const $newRow = $('#itemTable tbody tr').last();
    $newRow.find('.select2-js').select2({ width: '100%' });
    calcTotal();
}

function removeRow(btn) {
    if ($('#itemTable tbody tr').length > 1) {
        $(btn).closest('tr').remove();
        calcTotal();
    }
}

function calcRowTotal(row) {
    const price = parseFloat(row.find('.sale-price').val()) || 0;
    const qty   = parseFloat(row.find('.quantity').val())   || 0;
    row.find('.row-total').val((price * qty).toFixed(2));
    calcTotal();
}

function calcTotal() {
    let total = 0;
    $('.row-total').each(function () {
        total += parseFloat($(this).val()) || 0;
    });

    const discount  = parseFloat($('#discountInput').val()) || 0;
    const netAmount = Math.max(0, total - discount);
    const received  = parseFloat($('#amountReceivedInput').val()) || 0;
    const balance   = netAmount - received;

    $('#netAmountText').text(netAmount.toLocaleString(undefined, { minimumFractionDigits: 2 }));
    $('#netAmountInput').val(netAmount.toFixed(2));
    $('#balanceAmountText').text(balance.toLocaleString(undefined, { minimumFractionDigits: 2 }));
    $('#balanceAfterPayment').text(balance.toLocaleString(undefined, { minimumFractionDigits: 2 }));
}
</script>
@endsection