<?php

namespace App\Http\Controllers;

use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\ChartOfAccounts;
use App\Models\Product;
use App\Models\MeasurementUnit;
use App\Models\ProductVariation;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SaleInvoiceController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // Account resolvers
    // ─────────────────────────────────────────────────────────────
    private function salesRevenueAccount(): ChartOfAccounts
    {
        $account = ChartOfAccounts::where('account_code', '401001')->first();
        if (!$account) throw new \Exception('Sales Revenue account (401001) not found.');
        return $account;
    }

    private function inventoryAccount(): ChartOfAccounts
    {
        $account = ChartOfAccounts::where('account_code', '104001')->first();
        if (!$account) throw new \Exception('Inventory account (104001) not found.');
        return $account;
    }

    private function cogsAccount(): ?ChartOfAccounts
    {
        return ChartOfAccounts::where('account_code', '501001')->first();
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: unbilled drop-ship PIs for a customer
    // GET /sale-invoices/dropship-invoices/{customerId}
    // ─────────────────────────────────────────────────────────────
    public function getDropshipInvoices(int $customerId)
    {
        $invoices = PurchaseInvoice::with([
                        'vendor',
                        'items.product',
                        'items.variation',
                        'items.measurementUnit',
                    ])
                    ->where('dropoff_customer_id', $customerId)
                    ->whereDoesntHave('saleInvoice')
                    ->get()
                    ->map(fn($pi) => [
                        'id'           => $pi->id,
                        'invoice_no'   => $pi->invoice_no,
                        'invoice_date' => $pi->invoice_date,
                        'vendor_name'  => $pi->vendor->name ?? '',
                        'items'        => $pi->items->map(fn($item) => [
                            'product_id'     => $item->item_id,
                            'product_name'   => $item->product->name ?? '',
                            'variation_id'   => $item->variation_id,
                            'variation_sku'  => $item->variation->sku ?? '',
                            'quantity'       => $item->quantity,
                            'unit_shortcode' => $item->measurementUnit->shortcode ?? '',
                        ]),
                    ]);

        return response()->json(['success' => true, 'invoices' => $invoices]);
    }

    // ─────────────────────────────────────────────────────────────
    // Stock calculation
    // ─────────────────────────────────────────────────────────────
    private function buildProductsWithStock(): \Illuminate\Support\Collection
    {
        return Product::with('variations')->orderBy('name')->get()
            ->map(function ($product) {
                $purchased = (float) DB::table('purchase_invoice_items')
                    ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
                    ->where('purchase_invoice_items.item_id', $product->id)
                    ->whereNull('purchase_invoices.deleted_at')
                    ->sum('purchase_invoice_items.quantity');

                $sold = (float) DB::table('sale_invoice_items')
                    ->join('sale_invoices', 'sale_invoice_items.sale_invoice_id', '=', 'sale_invoices.id')
                    ->where('sale_invoice_items.product_id', $product->id)
                    ->whereNull('sale_invoices.deleted_at')
                    ->sum('sale_invoice_items.quantity');

                $purchaseReturned = (float) DB::table('purchase_return_items')
                    ->where('item_id', $product->id)->sum('quantity');

                $saleReturned = (float) DB::table('sale_return_items')
                    ->where('product_id', $product->id)->sum('qty');

                $product->real_time_stock = ($purchased + $saleReturned) - ($sold + $purchaseReturned);

                foreach ($product->variations as $v) {
                    $vPurchased = (float) DB::table('purchase_invoice_items')
                        ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
                        ->where('purchase_invoice_items.item_id', $product->id)
                        ->where('purchase_invoice_items.variation_id', $v->id)
                        ->whereNull('purchase_invoices.deleted_at')
                        ->sum('purchase_invoice_items.quantity');

                    $vSold = (float) DB::table('sale_invoice_items')
                        ->join('sale_invoices', 'sale_invoice_items.sale_invoice_id', '=', 'sale_invoices.id')
                        ->where('sale_invoice_items.product_id', $product->id)
                        ->where('sale_invoice_items.variation_id', $v->id)
                        ->whereNull('sale_invoices.deleted_at')
                        ->sum('sale_invoice_items.quantity');

                    $vPurchaseReturned = (float) DB::table('purchase_return_items')
                        ->where('item_id', $product->id)->where('variation_id', $v->id)->sum('quantity');

                    $vSaleReturned = (float) DB::table('sale_return_items')
                        ->where('product_id', $product->id)->where('variation_id', $v->id)->sum('qty');

                    $v->current_stock = ($vPurchased + $vSaleReturned) - ($vSold + $vPurchaseReturned);
                }

                return $product;
            });
    }

    // ─────────────────────────────────────────────────────────────
    // COGS
    // ─────────────────────────────────────────────────────────────
    private function calculateCogs(array $items): float
    {
        $totalCost = 0.0;
        foreach ($items as $item) {
            $query = PurchaseInvoiceItem::where('item_id', $item['product_id']);
            if (!empty($item['variation_id'])) {
                $query->where('variation_id', $item['variation_id']);
            }
            $latestPurchase = $query->latest('id')->first();
            if ($latestPurchase) {
                $totalCost += (float) $latestPurchase->price * (float) $item['quantity'];
            }
        }
        return $totalCost;
    }

    // ─────────────────────────────────────────────────────────────
    // INDEX
    // ─────────────────────────────────────────────────────────────
    public function index()
    {
        $invoices = SaleInvoice::with('items.product', 'account', 'purchaseInvoice')
                       ->latest()->get();
        return view('sales.index', compact('invoices'));
    }

    // ─────────────────────────────────────────────────────────────
    // CREATE FORM
    // ─────────────────────────────────────────────────────────────
    public function create()
    {
        $products        = $this->buildProductsWithStock();
        $customers       = ChartOfAccounts::where('account_type', 'customer')->orderBy('name')->get();
        $paymentAccounts = ChartOfAccounts::whereIn('account_type', ['cash', 'bank'])->orderBy('name')->get();
        $units           = MeasurementUnit::all();

        return view('sales.create', compact('products', 'customers', 'paymentAccounts', 'units'));
    }

    // ─────────────────────────────────────────────────────────────
    // STORE
    // ─────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date'                 => 'required|date',
            'account_id'           => 'required|exists:chart_of_accounts,id',
            'type'                 => 'required|in:cash,credit',
            'discount'             => 'nullable|numeric|min:0',
            'remarks'              => 'nullable|string',
            'purchase_invoice_id'  => 'nullable|exists:purchase_invoices,id',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.variation_id' => 'nullable|exists:product_variations,id',
            'items.*.sale_price'   => 'required|numeric|min:0',
            'items.*.quantity'     => 'required|numeric|min:1',
            'payment_account_id'   => 'nullable|exists:chart_of_accounts,id',
            'amount_received'      => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $last      = SaleInvoice::withTrashed()->orderByDesc('id')->first();
            $invoiceNo = str_pad($last ? intval($last->invoice_no) + 1 : 1, 6, '0', STR_PAD_LEFT);

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $subtotal += $item['sale_price'] * $item['quantity'];
            }
            $netTotal = $subtotal - ($validated['discount'] ?? 0);

            $invoice = SaleInvoice::create([
                'invoice_no'          => $invoiceNo,
                'date'                => $validated['date'],
                'account_id'          => $validated['account_id'],
                'type'                => $validated['type'],
                'discount'            => $validated['discount'] ?? 0,
                'net_amount'          => $netTotal,
                'remarks'             => $validated['remarks'] ?? null,
                'purchase_invoice_id' => $request->purchase_invoice_id ?: null,
                'created_by'          => Auth::id(),
            ]);

            foreach ($validated['items'] as $item) {
                SaleInvoiceItem::create([
                    'sale_invoice_id' => $invoice->id,
                    'product_id'      => $item['product_id'],
                    'variation_id'    => $item['variation_id'] ?? null,
                    'sale_price'      => $item['sale_price'],
                    'quantity'        => $item['quantity'],
                    'discount'        => 0,
                ]);
            }

            // Entry A: DR Customer / CR Sales Revenue
            $salesAccount = $this->salesRevenueAccount();
            Voucher::create([
                'voucher_type' => 'journal',
                'date'         => $validated['date'],
                'ac_dr_sid'    => $validated['account_id'],
                'ac_cr_sid'    => $salesAccount->id,
                'amount'       => $netTotal,
                'reference'    => 'SI-' . $invoice->id,
                'remarks'      => "Sale Invoice #{$invoiceNo} — revenue",
            ]);

            // Entry B: DR COGS / CR Inventory
            $cogsAccount = $this->cogsAccount();
            if ($cogsAccount) {
                $totalCost = $this->calculateCogs($validated['items']);
                if ($totalCost > 0) {
                    Voucher::create([
                        'voucher_type' => 'journal',
                        'date'         => $validated['date'],
                        'ac_dr_sid'    => $cogsAccount->id,
                        'ac_cr_sid'    => $this->inventoryAccount()->id,
                        'amount'       => $totalCost,
                        'reference'    => 'SI-' . $invoice->id,
                        'remarks'      => "Sale Invoice #{$invoiceNo} — COGS",
                    ]);
                }
            }

            // Entry C: DR Cash/Bank / CR Customer (optional)
            $amountReceived = (float) ($request->amount_received ?? 0);
            if ($request->filled('payment_account_id') && $amountReceived > 0) {
                Voucher::create([
                    'voucher_type' => 'receipt',
                    'date'         => $validated['date'],
                    'ac_dr_sid'    => $validated['payment_account_id'],
                    'ac_cr_sid'    => $validated['account_id'],
                    'amount'       => $amountReceived,
                    'reference'    => 'SI-' . $invoice->id,
                    'remarks'      => "Payment received — Invoice #{$invoiceNo}",
                ]);
            }

            DB::commit();
            Log::info('[SI] Stored', ['invoice_id' => $invoice->id]);
            return redirect()->route('sale_invoices.index')
                ->with('success', 'Sale Invoice created successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[SI] Store failed', ['error' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Error saving invoice: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    // EDIT FORM
    // ─────────────────────────────────────────────────────────────
    public function edit($id)
    {
        $invoice = SaleInvoice::with([
                       'items.product',
                       'items.variation',
                       'account',
                       'purchaseInvoice.vendor',
                   ])->findOrFail($id);

        $products = $this->buildProductsWithStock();

        // Fetch existing receipt voucher so we can pre-fill account + amount
        $existingPaymentVoucher   = Voucher::where('reference', 'SI-' . $id)
                                        ->where('voucher_type', 'receipt')->first();
        $amountReceived           = (float) ($existingPaymentVoucher?->amount ?? 0);
        $existingPaymentAccountId = $existingPaymentVoucher?->ac_dr_sid ?? null;

        return view('sales.edit', [
            'invoice'                  => $invoice,
            'products'                 => $products,
            'customers'                => ChartOfAccounts::where('account_type', 'customer')->orderBy('name')->get(),
            'paymentAccounts'          => ChartOfAccounts::whereIn('account_type', ['cash', 'bank'])->orderBy('name')->get(),
            'amountReceived'           => $amountReceived,
            'existingPaymentAccountId' => $existingPaymentAccountId,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // UPDATE
    // amount_received = TOTAL received (replaces previous, not additive)
    // ─────────────────────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'date'                 => 'required|date',
            'account_id'           => 'required|exists:chart_of_accounts,id',
            'type'                 => 'required|in:cash,credit',
            'discount'             => 'nullable|numeric|min:0',
            'remarks'              => 'nullable|string',
            'purchase_invoice_id'  => 'nullable|exists:purchase_invoices,id',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.variation_id' => 'nullable|exists:product_variations,id',
            'items.*.sale_price'   => 'required|numeric|min:0',
            'items.*.quantity'     => 'required|numeric|min:0.01',
            'payment_account_id'   => 'nullable|exists:chart_of_accounts,id',
            'amount_received'      => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $invoice   = SaleInvoice::with('items')->findOrFail($id);
            $invoiceNo = $invoice->invoice_no;
            $reference = 'SI-' . $invoice->id;

            // Recalculate totals
            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $subtotal += $item['sale_price'] * $item['quantity'];
            }
            $netTotal = $subtotal - ($validated['discount'] ?? 0);

            $invoice->update([
                'date'                => $validated['date'],
                'account_id'          => $validated['account_id'],
                'type'                => $validated['type'],
                'discount'            => $validated['discount'] ?? 0,
                'net_amount'          => $netTotal,
                'remarks'             => $validated['remarks'] ?? null,
                'purchase_invoice_id' => $request->purchase_invoice_id ?: null,
            ]);

            // Replace items
            $invoice->items()->delete();
            foreach ($validated['items'] as $item) {
                SaleInvoiceItem::create([
                    'sale_invoice_id' => $invoice->id,
                    'product_id'      => $item['product_id'],
                    'variation_id'    => $item['variation_id'] ?? null,
                    'sale_price'      => $item['sale_price'],
                    'quantity'        => $item['quantity'],
                    'discount'        => 0,
                ]);
            }

            // Replace journal vouchers
            Voucher::where('reference', $reference)->where('voucher_type', 'journal')->delete();

            $salesAccount = $this->salesRevenueAccount();
            Voucher::create([
                'voucher_type' => 'journal',
                'date'         => $validated['date'],
                'ac_dr_sid'    => $validated['account_id'],
                'ac_cr_sid'    => $salesAccount->id,
                'amount'       => $netTotal,
                'reference'    => $reference,
                'remarks'      => "Updated: Sale Invoice #{$invoiceNo} — revenue",
            ]);

            $cogsAccount = $this->cogsAccount();
            if ($cogsAccount) {
                $totalCost = $this->calculateCogs($validated['items']);
                if ($totalCost > 0) {
                    Voucher::create([
                        'voucher_type' => 'journal',
                        'date'         => $validated['date'],
                        'ac_dr_sid'    => $cogsAccount->id,
                        'ac_cr_sid'    => $this->inventoryAccount()->id,
                        'amount'       => $totalCost,
                        'reference'    => $reference,
                        'remarks'      => "Updated: Sale Invoice #{$invoiceNo} — COGS",
                    ]);
                }
            }

            // Update receipt voucher
            // amount_received here = the TOTAL received amount (correctable)
            $paymentVoucher = Voucher::where('reference', $reference)
                                  ->where('voucher_type', 'receipt')->first();

            $amountReceived = (float) ($request->amount_received ?? 0);
            $hasAccount     = $request->filled('payment_account_id');
            $hasAmount      = $amountReceived > 0;

            if ($hasAccount && $hasAmount) {
                $receiptData = [
                    'date'      => $validated['date'],
                    'ac_dr_sid' => $validated['payment_account_id'],
                    'ac_cr_sid' => $validated['account_id'],
                    'amount'    => $amountReceived,
                    'remarks'   => "Payment — Invoice #{$invoiceNo}",
                    'reference' => $reference,
                ];
                $paymentVoucher
                    ? $paymentVoucher->update($receiptData)
                    : Voucher::create(array_merge(['voucher_type' => 'receipt'], $receiptData));
            } elseif ($paymentVoucher) {
                // Amount set to 0 or account cleared → remove payment
                $paymentVoucher->delete();
            }

            DB::commit();
            Log::info('[SI] Updated', ['invoice_id' => $invoice->id]);
            return redirect()->route('sale_invoices.index')
                ->with('success', 'Invoice updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[SI] Update error', ['error' => $e->getMessage()]);
            return back()->with('error', 'Update failed: ' . $e->getMessage())->withInput();
        }
    }

    // ─────────────────────────────────────────────────────────────
    // DESTROY
    // ─────────────────────────────────────────────────────────────
    public function destroy($id)
    {
        $invoice = SaleInvoice::findOrFail($id);
        DB::beginTransaction();
        try {
            Voucher::where('reference', 'SI-' . $invoice->id)->delete();
            $invoice->items()->delete();
            $invoice->delete();
            DB::commit();
            return redirect()->route('sale_invoices.index')->with('success', 'Sale Invoice deleted.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to delete: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    // PRINT (PDF)
    // ─────────────────────────────────────────────────────────────
    public function print($id)
    {
        $invoice        = SaleInvoice::with([
                              'account',
                              'items.product.measurementUnit',
                              'items.variation',
                              'purchaseInvoice',
                          ])->findOrFail($id);
        $amountReceived = Voucher::where('reference', 'SI-' . $id)
                              ->where('voucher_type', 'receipt')->sum('amount');

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Your Company');
        $pdf->SetTitle('SALE-' . $invoice->invoice_no);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        $logoPath = public_path('assets/img/logo.png');
        if (file_exists($logoPath)) $pdf->Image($logoPath, 15, 12, 35);

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetXY(110, 12);
        $pdf->Cell(85, 10, 'SALE INVOICE', 0, 1, 'R');

        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetXY(110, 20);
        $pdf->Cell(85, 5, 'Invoice #: ' . $invoice->invoice_no, 0, 1, 'R');
        $pdf->SetX(110);
        $pdf->Cell(85, 5, 'Date: ' . Carbon::parse($invoice->date)->format('d-M-Y'), 0, 1, 'R');
        $pdf->SetX(110);
        $pdf->Cell(85, 5, 'Customer: ' . ($invoice->account->name ?? '-'), 0, 1, 'R');
        if ($invoice->purchaseInvoice) {
            $pdf->SetX(110);
            $pdf->Cell(85, 5, 'PI Ref: PI-' . $invoice->purchaseInvoice->invoice_no, 0, 1, 'R');
        }
        $pdf->Ln(10);

        $html = '
        <table border="1" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="font-weight:bold;background-color:#f5f5f5;">
                <th width="5%">#</th>
                <th width="33%">Item Name</th>
                <th width="18%">Variation</th>
                <th width="14%">Qty</th>
                <th width="15%">Price</th>
                <th width="15%">Total</th>
            </tr>';

        $count = $totalQty = $subTotal = 0;
        foreach ($invoice->items as $item) {
            $count++;
            $lineTotal = $item->sale_price * $item->quantity;
            $unitCode  = $item->product->measurementUnit->shortcode ?? '';
            $html .= '
            <tr>
                <td>' . $count . '</td>
                <td style="text-align:left">' . e($item->product->name ?? '-') . '</td>
                <td>' . e($item->variation->sku ?? '-') . '</td>
                <td>' . number_format($item->quantity, 2) . ' ' . e($unitCode) . '</td>
                <td>' . number_format($item->sale_price, 2) . '</td>
                <td>' . number_format($lineTotal, 2) . '</td>
            </tr>';
            $totalQty += $item->quantity;
            $subTotal += $lineTotal;
        }

        $invoiceDiscount = $invoice->discount ?? 0;
        $netTotal        = $subTotal - $invoiceDiscount;
        $balanceDue      = $netTotal - $amountReceived;

        $html .= '
        <tr>
            <td colspan="3" align="right" bgcolor="#f5f5f5"><b>Total Qty</b></td>
            <td><b>' . number_format($totalQty, 2) . '</b></td>
            <td align="right" bgcolor="#f5f5f5"><b>Sub Total</b></td>
            <td align="right"><b>' . number_format($subTotal, 2) . '</b></td>
        </tr>';

        if ($invoiceDiscount > 0) {
            $html .= '
        <tr>
            <td colspan="5" align="right">Less: Discount</td>
            <td align="right">' . number_format($invoiceDiscount, 2) . '</td>
        </tr>';
        }

        $html .= '
        <tr style="background-color:#f5f5f5;">
            <td colspan="5" align="right"><b>Net Payable</b></td>
            <td align="right"><b>' . number_format($netTotal, 2) . '</b></td>
        </tr>
        <tr>
            <td colspan="5" align="right">Amount Received</td>
            <td align="right" style="color:green;">' . number_format($amountReceived, 2) . '</td>
        </tr>
        <tr style="background-color:#eeeeee;">
            <td colspan="5" align="right"><b>Remaining Balance</b></td>
            <td align="right" style="color:red;"><b>' . number_format($balanceDue, 2) . '</b></td>
        </tr>
        </table>';

        $pdf->writeHTML($html, true, false, false, false, '');

        if (!empty($invoice->remarks)) {
            $pdf->Ln(2);
            $pdf->writeHTML('<p style="font-size:9px;"><b>Remarks:</b> ' . nl2br(e($invoice->remarks)) . '</p>',
                true, false, false, false, '');
        }

        if ($pdf->GetY() > 240) $pdf->AddPage();
        $pdf->Ln(30);
        $y = $pdf->GetY();
        $w = 60;
        $pdf->Line(20, $y, 20 + $w, $y);
        $pdf->Line(130, $y, 130 + $w, $y);
        $pdf->SetY($y + 2);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetX(20);  $pdf->Cell($w, 5, 'Customer Signature',  0, 0, 'C');
        $pdf->SetX(130); $pdf->Cell($w, 5, 'Authorized Signature', 0, 0, 'C');

        return $pdf->Output('Invoice_' . $invoice->invoice_no . '.pdf', 'I');
    }
}