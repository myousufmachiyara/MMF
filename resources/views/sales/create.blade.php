@extends('layouts.app')

@section('title', 'Create Sale Invoice')

@section('content')
<style>
    .select2-container--default .select2-selection--single { height: 38px !important; padding: 5px; border: 1px solid #ced4da; }
    .select2-container { display: block !important; width: 100% !important; }
    #itemTable th { background: #f8f9fa; }
    #itemTable td { vertical-align: middle; }
    .stock-exceeded { border-color: red !important; }
    .stock-ok       { border-color: #28a745 !important; }
</style>

<div class="row">
    <form action="{{ route('sale_invoices.store') }}" onkeydown="return event.key != 'Enter';" method="POST">
        @csrf

        {{-- Hidden: carries PI id when it's a drop-ship invoice --}}
        <input type="hidden" name="purchase_invoice_id" id="purchase_invoice_id_input" value="">

        <div class="col-12 mb-2">
            <section class="card">
                <header class="card-header">
                    <h2 class="card-title">Create Sale Invoice</h2>
                    @if ($errors->any())
                        <div class="alert alert-danger mt-2">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                            </ul>
                        </div>
                    @endif
                </header>
                <div class="card-body">
                    <div class="row mb-2 align-items-end">

                        <div class="col-md-2">
                            <label>Invoice #</label>
                            <input type="text" class="form-control" readonly placeholder="Auto"/>
                        </div>

                        <div class="col-md-2">
                            <label>Date <span class="text-danger">*</span></label>
                            <input type="date" name="date" class="form-control"
                                   value="{{ date('Y-m-d') }}" required />
                        </div>

                        <div class="col-md-3">
                            <label>Customer <span class="text-danger">*</span></label>
                            <select name="account_id" id="customer_select"
                                    class="form-control select2-js" required>
                                <option value="">Select Customer</option>
                                @foreach($customers as $account)
                                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label>Invoice Type <span class="text-danger">*</span></label>
                            <select name="type" id="invoice_type" class="form-control" required>
                                <option value="cash">Cash</option>
                                <option value="credit">Credit</option>
                            </select>
                        </div>

                        {{-- Drop-ship PI selector — appears when customer has unbilled PIs --}}
                        <div class="col-md-3" id="dropship_panel" style="display:none;">
                            <label>
                                Link Drop-ship Purchase Invoice
                                <small class="text-muted">(optional)</small>
                            </label>
                            <select id="dropship_pi_select" class="form-control select2-js">
                                <option value="">— none —</option>
                            </select>
                            <small class="text-danger fw-semibold" id="dropship_hint"></small>
                        </div>

                    </div>

                    {{-- Visible PI link badge — shown after PI is selected --}}
                    <div id="linked_pi_row" style="display:none;" class="mt-2">
                        <div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-0">
                            <i class="fas fa-link"></i>
                            <strong>Linked Purchase Invoice:</strong>
                            <span id="linked_pi_text" class="ms-1"></span>
                            <button type="button" class="btn btn-sm btn-outline-danger ms-auto"
                                    onclick="clearLinkedPi()">
                                <i class="fas fa-unlink"></i> Unlink
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-12">
            <section class="card">
                <header class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="card-title">Invoice Items</h2>
                    <span id="dropship_label" class="badge bg-warning text-dark"
                          style="display:none; font-size:0.85em;">
                        🚚 Drop-ship Invoice
                    </span>
                </header>
                <div class="card-body">
                    <table class="table table-bordered" id="itemTable">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th width="15%">Variation</th>
                                <th width="12%">Sale Price <span class="text-danger">*</span></th>
                                <th width="10%">Qty</th>
                                <th width="12%">Total</th>
                                <th width="50px"></th>
                            </tr>
                        </thead>
                        <tbody id="itemTableBody">
                            <tr>
                                <td>
                                    <select name="items[0][product_id]" id="item_name0"
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
                                    <select name="items[0][variation_id]" id="variation0"
                                            class="form-control select2-js variation-select">
                                        <option value="">Select Variation</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="items[0][sale_price]"
                                           class="form-control sale-price" step="any" required>
                                </td>
                                <td>
                                    <input type="number" name="items[0][quantity]"
                                           class="form-control quantity" step="any" required>
                                    <small class="text-muted stock-hint"></small>
                                </td>
                                <td>
                                    <input type="number" name="items[0][total]"
                                           class="form-control row-total" readonly>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm"
                                            onclick="removeRow(this)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-success btn-sm" onclick="addRow()">
                        + Add Item
                    </button>

                    <hr>
                    <div class="row mb-2">
                        <div class="col-md-4">
                            <label>Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-2">
                            <label><strong>Discount (PKR)</strong></label>
                            <input type="number" name="discount" id="discountInput"
                                   class="form-control" step="any" value="0">
                        </div>
                        <div class="col-md-6 text-end">
                            <label><strong>Total Bill</strong></label>
                            <h4 class="text-primary mt-0">PKR <span id="netAmountText">0.00</span></h4>
                            <input type="hidden" name="net_amount" id="netAmountInput">
                        </div>
                    </div>

                    <hr>
                    <div class="row mb-2">
                        <div class="col-md-4">
                            <label><strong>Receive Payment To:</strong></label>
                            <select name="payment_account_id" class="form-control select2-js">
                                <option value="">No Payment (Credit Sale)</option>
                                @foreach($paymentAccounts as $pAc)
                                    <option value="{{ $pAc->id }}">{{ $pAc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Amount Received</label>
                            <input type="number" name="amount_received" id="amountReceived"
                                   class="form-control" step="any" value="0">
                        </div>
                        <div class="col-md-5 text-end">
                            <label>Remaining Balance</label>
                            <h4 class="text-danger mt-0">PKR <span id="balanceAmountText">0.00</span></h4>
                        </div>
                    </div>
                </div>
                <footer class="card-footer text-end">
                    <a href="{{ route('sale_invoices.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Invoice</button>
                </footer>
            </section>
        </div>
    </form>
</div>

<script>
var allProducts = @json($products);
var rowIndex    = 1;

$(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });

    $(document).on('input', '.sale-price, .quantity', function () {
        calcRowTotal($(this).closest('tr'));
    });
    $(document).on('input', '#amountReceived, #discountInput', calcTotal);
    $(document).on('change', '#invoice_type', function () {
        if ($(this).val() === 'cash') $('#amountReceived').val($('#netAmountInput').val());
        else $('#amountReceived').val(0);
        calcTotal();
    });
    $(document).on('input', '.quantity', function () {
        validateStock($(this).closest('tr'));
    });

    // When customer changes, check for unbilled drop-ship PIs
    $('#customer_select').on('change', function () {
        const customerId = $(this).val();
        resetDropship();
        if (!customerId) return;

        fetch(`/sale-invoices/dropship-invoices/${customerId}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.invoices.length) return;

                const $sel = $('#dropship_pi_select');
                $sel.html('<option value="">— none —</option>');
                data.invoices.forEach(pi => {
                    $sel.append(
                        `<option value="${pi.id}" data-items='${JSON.stringify(pi.items)}'
                                 data-label="PI-${pi.invoice_no} | ${pi.vendor_name} | ${pi.invoice_date}">
                            PI-${pi.invoice_no} | ${pi.vendor_name} | ${pi.invoice_date}
                         </option>`
                    );
                });

                $('#dropship_panel').show();
                $('#dropship_hint').text(
                    `${data.invoices.length} unbilled drop-ship PI(s) found for this customer.`
                );
                $sel.trigger('change.select2');
            });
    });

    // When a PI is selected, pre-fill items and show visible badge
    $('#dropship_pi_select').on('change', function () {
        const piId     = $(this).val();
        const selected = $(this).find('option:selected');

        if (!piId) {
            clearLinkedPi();
            return;
        }

        let items;
        try { items = JSON.parse(selected.attr('data-items')); }
        catch(e) { return; }

        // Set hidden field
        $('#purchase_invoice_id_input').val(piId);

        // Show visible PI indicator
        $('#linked_pi_text').text(selected.attr('data-label') || selected.text());
        $('#linked_pi_row').show();
        $('#dropship_label').show();

        // Rebuild items table
        $('#itemTableBody').empty();
        rowIndex = 0;
        items.forEach((item, i) => buildDropshipRow(item, i));
        calcTotal();
    });
});

// Clear linked PI and reset to normal row
function clearLinkedPi() {
    $('#purchase_invoice_id_input').val('');
    $('#linked_pi_row').hide();
    $('#linked_pi_text').text('');
    $('#dropship_label').hide();
    $('#dropship_pi_select').val('').trigger('change.select2');
    $('#itemTableBody').html(blankRow(0));
    rowIndex = 1;
    $(`#item_name0, #variation0`).select2({ width: '100%' });
    calcTotal();
}

function resetDropship() {
    $('#purchase_invoice_id_input').val('');
    $('#linked_pi_row').hide();
    $('#linked_pi_text').text('');
    $('#dropship_label').hide();
    $('#dropship_panel').hide();
    $('#dropship_hint').text('');
    $('#dropship_pi_select').html('<option value="">— none —</option>');
    $('#itemTableBody').html(blankRow(0));
    rowIndex = 1;
    $(`#item_name0, #variation0`).select2({ width: '100%' });
    calcTotal();
}

function buildDropshipRow(item, idx) {
    const productOptions = allProducts.map(p =>
        `<option value="${p.id}"
            data-price="${p.selling_price ?? 0}"
            data-stock="${p.real_time_stock ?? 0}"
            ${p.id == item.product_id ? 'selected' : ''}>
            ${p.name} (Stock: ${p.real_time_stock ?? 0})
         </option>`
    ).join('');

    const variationOption = item.variation_id
        ? `<option value="${item.variation_id}" selected>${item.variation_sku}</option>`
        : '';

    const row = `
        <tr>
            <td>
                <small class="text-warning fw-semibold d-block">🚚 Drop-ship</small>
                <select name="items[${idx}][product_id]" id="item_name${idx}"
                        class="form-control select2-js product-select"
                        onchange="onItemNameChange(this)" required>
                    <option value="">Select Product</option>
                    ${productOptions}
                </select>
            </td>
            <td>
                <select name="items[${idx}][variation_id]" id="variation${idx}"
                        class="form-control select2-js variation-select">
                    <option value="">No Variation</option>
                    ${variationOption}
                </select>
            </td>
            <td>
                <input type="number" name="items[${idx}][sale_price]"
                       class="form-control sale-price" step="any"
                       placeholder="Set price" required>
            </td>
            <td>
                <input type="number" name="items[${idx}][quantity]"
                       class="form-control quantity" value="${item.quantity}"
                       step="any" required>
                <small class="text-muted stock-hint"></small>
            </td>
            <td>
                <input type="number" name="items[${idx}][total]"
                       class="form-control row-total" readonly>
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        </tr>`;

    $('#itemTableBody').append(row);
    $(`#item_name${idx}`).select2({ width: '100%' });
    $(`#variation${idx}`).select2({ width: '100%' });
    rowIndex = idx + 1;
}

function blankRow(idx) {
    const productOptions = allProducts.map(p =>
        `<option value="${p.id}"
            data-price="${p.selling_price ?? 0}"
            data-stock="${p.real_time_stock ?? 0}">
            ${p.name} (Stock: ${p.real_time_stock ?? 0})
         </option>`
    ).join('');

    return `
        <tr>
            <td>
                <select name="items[${idx}][product_id]" id="item_name${idx}"
                        class="form-control select2-js product-select"
                        onchange="onItemNameChange(this)" required>
                    <option value="">Select Product</option>
                    ${productOptions}
                </select>
            </td>
            <td>
                <select name="items[${idx}][variation_id]" id="variation${idx}"
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
                <small class="text-muted stock-hint"></small>
            </td>
            <td>
                <input type="number" name="items[${idx}][total]"
                       class="form-control row-total" readonly>
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        </tr>`;
}

function addRow() {
    const idx = rowIndex++;
    $('#itemTableBody').append(blankRow(idx));
    $(`#item_name${idx}, #variation${idx}`).select2({ width: '100%' });
}

function getAvailableStock($row) {
    const $selVar = $row.find('.variation-select option:selected');
    const vStock  = parseFloat($selVar.data('stock'));
    if ($selVar.val() && !isNaN(vStock)) return vStock;
    const pStock  = parseFloat($row.find('.product-select option:selected').data('stock'));
    return isNaN(pStock) ? 0 : pStock;
}

function validateStock($row) {
    const $qty  = $row.find('.quantity');
    const $hint = $row.find('.stock-hint');
    const qty   = parseFloat($qty.val()) || 0;
    const stock = getAvailableStock($row);
    if (qty > stock) {
        $qty.addClass('stock-exceeded').removeClass('stock-ok');
        $hint.text('⚠ Only ' + stock + ' available').css('color', 'red');
    } else {
        $qty.addClass('stock-ok').removeClass('stock-exceeded');
        $hint.text('In stock: ' + stock).css('color', '#28a745');
    }
}

function onItemNameChange(selectElement) {
    const $row    = $(selectElement).closest('tr');
    const itemId  = selectElement.value;
    const idMatch = selectElement.id.match(/\d+$/);
    if (!idMatch) return;
    const rowNum = idMatch[0];

    $row.find('.sale-price').val(
        $(selectElement.options[selectElement.selectedIndex]).data('price') || 0
    );

    const $var = $(`#variation${rowNum}`);
    if (!itemId) {
        $var.html('<option value="">Select Variation</option>').trigger('change.select2');
        $row.find('.stock-hint').text('');
        $row.find('.quantity').removeClass('stock-exceeded stock-ok');
        calcRowTotal($row);
        return;
    }

    $var.html('<option value="">Loading...</option>').trigger('change.select2');
    fetch(`/product/${itemId}/variations`)
        .then(r => r.json())
        .then(data => {
            const variations = data.variation || data.variations || [];
            if (variations.length > 0) {
                let html = '<option value="">Select Variation</option>';
                variations.forEach(v => {
                    const vStock = v.stock_quantity ?? v.current_stock ?? 0;
                    const label  = [v.sku, v.name].filter(Boolean).join(' ');
                    html += `<option value="${v.id}" data-stock="${vStock}">${label} (Stock: ${vStock})</option>`;
                });
                $var.html(html);
            } else {
                $var.html('<option value="">Standard (No Variations)</option>');
            }
            $var.trigger('change.select2');
            validateStock($row);
        })
        .catch(() => $var.html('<option value="">Error loading</option>').trigger('change.select2'));

    calcRowTotal($row);
}

function removeRow(btn) {
    if ($('#itemTableBody tr').length > 1) {
        $(btn).closest('tr').remove();
        calcTotal();
    }
}

function calcRowTotal($row) {
    const price = parseFloat($row.find('.sale-price').val()) || 0;
    const qty   = parseFloat($row.find('.quantity').val())   || 0;
    $row.find('.row-total').val((price * qty).toFixed(2));
    validateStock($row);
    calcTotal();
}

function calcTotal() {
    let total = 0;
    $('.row-total').each(function () { total += parseFloat($(this).val()) || 0; });
    const discount  = parseFloat($('#discountInput').val()) || 0;
    const netAmount = Math.max(0, total - discount);
    $('#netAmountText').text(netAmount.toLocaleString(undefined, { minimumFractionDigits: 2 }));
    $('#netAmountInput').val(netAmount.toFixed(2));
    const received = parseFloat($('#amountReceived').val()) || 0;
    $('#balanceAmountText').text(
        (netAmount - received).toLocaleString(undefined, { minimumFractionDigits: 2 })
    );
}
</script>
@endsection