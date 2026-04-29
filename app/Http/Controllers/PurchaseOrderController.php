<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderAttachment;
use App\Models\Product;
use App\Models\MeasurementUnit;
use App\Models\ChartOfAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PurchaseOrderController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // INDEX
    // ─────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $user  = auth()->user();
        $query = PurchaseOrder::with(['vendor', 'attachments']);

        if ($request->has('view_deleted')) {
            $query->onlyTrashed();
        }

        // Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Non-superadmin sees only their own records
        if (!$user->hasRole('superadmin')) {
            $query->where('created_by', $user->id);
        }

        $orders   = $query->latest()->get();
        $statuses = PurchaseOrder::STATUSES;

        return view('purchase_orders.index', compact('orders', 'statuses'));
    }

    // ─────────────────────────────────────────────────────────────
    // CREATE FORM
    // ─────────────────────────────────────────────────────────────
    public function create()
    {
        $products = Product::with('variations')->orderBy('name')->get();
        $vendors  = ChartOfAccounts::where('account_type', 'vendor')->orderBy('name')->get();
        $units    = MeasurementUnit::all();
        $statuses = PurchaseOrder::STATUSES;

        return view('purchase_orders.create', compact('products', 'vendors', 'units', 'statuses'));
    }

    // ─────────────────────────────────────────────────────────────
    // STORE
    // No accounting entries — PO is for record / approval only.
    // ─────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        Log::info('[PO] Store started', ['user_id' => auth()->id()]);

        $request->validate([
            'po_date'                    => 'required|date',
            'vendor_id'                  => 'required|exists:chart_of_accounts,id',
            'expected_delivery_date'     => 'nullable|date|after_or_equal:po_date',
            'ref_no'                     => 'nullable|string|max:100',
            'remarks'                    => 'nullable|string',
            'status'                     => 'required|in:' . implode(',', array_keys(PurchaseOrder::STATUSES)),
            'attachments.*'              => 'nullable|file|mimes:jpg,jpeg,png,pdf,zip|max:2048',
            'items'                      => 'required|array|min:1',
            'items.*.item_id'            => 'required|exists:products,id',
            'items.*.variation_id'       => 'nullable|exists:product_variations,id',
            'items.*.quantity'           => 'required|numeric|min:0.01',
            'items.*.unit'               => 'required|exists:measurement_units,id',
            'items.*.price'              => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            // ── Auto-generate PO number ───────────────────────────
            $last  = PurchaseOrder::withTrashed()->orderByDesc('id')->first();
            $poNo  = 'PO-' . str_pad($last ? intval(substr($last->po_no, 3)) + 1 : 1, 5, '0', STR_PAD_LEFT);

            $order = PurchaseOrder::create([
                'po_no'                  => $poNo,
                'vendor_id'              => $request->vendor_id,
                'po_date'                => $request->po_date,
                'expected_delivery_date' => $request->expected_delivery_date,
                'ref_no'                 => $request->ref_no,
                'remarks'                => $request->remarks,
                'status'                 => $request->status,
                'created_by'             => auth()->id(),
            ]);

            Log::info('[PO] Header created', ['po_id' => $order->id, 'po_no' => $poNo]);

            // ── Line items ────────────────────────────────────────
            foreach ($request->items as $itemData) {
                $order->items()->create([
                    'item_id'      => $itemData['item_id'],
                    'variation_id' => $itemData['variation_id'] ?? null,
                    'quantity'     => (float) $itemData['quantity'],
                    'unit'         => $itemData['unit'],
                    'price'        => (float) $itemData['price'],
                ]);
            }

            // ── Attachments ───────────────────────────────────────
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('purchase_orders', 'public');
                    $order->attachments()->create([
                        'file_path'     => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'file_type'     => $file->getClientMimeType(),
                    ]);
                }
            }

            DB::commit();
            Log::info('[PO] Stored successfully', ['po_id' => $order->id]);

            return redirect()->route('purchase_orders.index')
                ->with('success', "Purchase Order {$poNo} created successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[PO] Store error', ['message' => $e->getMessage(), 'line' => $e->getLine()]);
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // EDIT FORM
    // ─────────────────────────────────────────────────────────────
    public function edit($id)
    {
        $order    = PurchaseOrder::with(['items.product.variations', 'items.variation', 'attachments'])
                        ->findOrFail($id);
        $vendors  = ChartOfAccounts::where('account_type', 'vendor')->orderBy('name')->get();
        $products = Product::with('variations')->select('id', 'name', 'measurement_unit')->get();
        $units    = MeasurementUnit::all();
        $statuses = PurchaseOrder::STATUSES;

        return view('purchase_orders.edit', compact('order', 'vendors', 'products', 'units', 'statuses'));
    }

    // ─────────────────────────────────────────────────────────────
    // UPDATE
    // ─────────────────────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $request->validate([
            'po_date'                    => 'required|date',
            'vendor_id'                  => 'required|exists:chart_of_accounts,id',
            'expected_delivery_date'     => 'nullable|date|after_or_equal:po_date',
            'ref_no'                     => 'nullable|string|max:100',
            'remarks'                    => 'nullable|string',
            'status'                     => 'required|in:' . implode(',', array_keys(PurchaseOrder::STATUSES)),
            'attachments.*'              => 'nullable|file|mimes:jpg,jpeg,png,pdf,zip|max:2048',
            'items'                      => 'required|array|min:1',
            'items.*.item_id'            => 'required|exists:products,id',
            'items.*.variation_id'       => 'nullable|exists:product_variations,id',
            'items.*.quantity'           => 'required|numeric|min:0.01',
            'items.*.unit'               => 'required|exists:measurement_units,id',
            'items.*.price'              => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $order = PurchaseOrder::with('items')->findOrFail($id);

            $order->update([
                'vendor_id'              => $request->vendor_id,
                'po_date'                => $request->po_date,
                'expected_delivery_date' => $request->expected_delivery_date,
                'ref_no'                 => $request->ref_no,
                'remarks'                => $request->remarks,
                'status'                 => $request->status,
            ]);

            // Replace all line items
            $order->items()->delete();

            foreach ($request->items as $itemData) {
                if (empty($itemData['item_id'])) continue;

                $order->items()->create([
                    'item_id'      => $itemData['item_id'],
                    'variation_id' => $itemData['variation_id'] ?? null,
                    'quantity'     => (float) $itemData['quantity'],
                    'unit'         => $itemData['unit'],
                    'price'        => (float) $itemData['price'],
                ]);
            }

            // Append new attachments (keep existing)
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('purchase_orders', 'public');
                    $order->attachments()->create([
                        'file_path'     => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'file_type'     => $file->getClientMimeType(),
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('purchase_orders.index')
                ->with('success', "Purchase Order {$order->po_no} updated successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[PO] Update error', ['message' => $e->getMessage(), 'line' => $e->getLine()]);
            return back()->withErrors(['error' => 'Failed to update: ' . $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // QUICK STATUS UPDATE  (AJAX — called from index table badge)
    // PATCH /purchase-orders/{id}/status
    // ─────────────────────────────────────────────────────────────
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(PurchaseOrder::STATUSES)),
        ]);

        $order = PurchaseOrder::findOrFail($id);
        $order->update(['status' => $request->status]);

        Log::info('[PO] Status updated', ['po_id' => $id, 'status' => $request->status]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'status'  => $request->status,
                'label'   => PurchaseOrder::STATUSES[$request->status],
                'badge'   => PurchaseOrder::STATUS_BADGES[$request->status],
            ]);
        }

        return back()->with('success', "Status updated to: " . PurchaseOrder::STATUSES[$request->status]);
    }

    // ─────────────────────────────────────────────────────────────
    // DESTROY  (soft delete)
    // ─────────────────────────────────────────────────────────────
    public function destroy($id)
    {
        $order = PurchaseOrder::findOrFail($id);

        DB::beginTransaction();

        try {
            // ← removed $order->items()->delete()
            // Items stay in DB. Only the PO gets a deleted_at timestamp.
            $order->delete();

            DB::commit();
            return redirect()->route('purchase_orders.index')
                ->with('success', 'Purchase Order moved to trash.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[PO] Destroy error', ['message' => $e->getMessage()]);
            return back()->with('error', 'Failed to delete purchase order.');
        }
    }

    // ─────────────────────────────────────────────────────────────
    // RESTORE  (undo soft delete)
    // ─────────────────────────────────────────────────────────────
    public function restore($id)
    {
        $order = PurchaseOrder::onlyTrashed()->findOrFail($id);

        DB::beginTransaction();

        try {
            $order->restore();

            DB::commit();
            return redirect()->back()->with('success', 'Purchase Order restored successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[PO] Restore error', ['message' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Restore failed: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    // DELETE ATTACHMENT
    // DELETE /purchase-orders/attachment/{id}
    // ─────────────────────────────────────────────────────────────
    public function deleteAttachment($id)
    {
        $attachment = PurchaseOrderAttachment::findOrFail($id);

        if (Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return back()->with('success', 'Attachment removed.');
    }

    // ─────────────────────────────────────────────────────────────
    // PRINT  (PDF)
    // ─────────────────────────────────────────────────────────────
    public function print($id)
    {
        $order = PurchaseOrder::with(['vendor', 'items.product', 'items.variation', 'items.measurementUnit'])
                     ->findOrFail($id);

        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('YourApp');
        $pdf->SetAuthor('Your Company');
        $pdf->SetTitle($order->po_no);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        // ── Logo ──────────────────────────────────────────────────
        $logoPath = public_path('assets/img/logo.png');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 15, 12, 35);
        }

        // ── Title ─────────────────────────────────────────────────
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetXY(110, 12);
        $pdf->Cell(85, 10, 'PURCHASE ORDER', 0, 1, 'R');

        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetXY(110, 20);
        $pdf->Cell(85, 5, 'PO #: ' . $order->po_no, 0, 1, 'R');
        $pdf->SetX(110);
        $pdf->Cell(85, 5, 'Date: ' . Carbon::parse($order->po_date)->format('d-M-Y'), 0, 1, 'R');
        if ($order->expected_delivery_date) {
            $pdf->SetX(110);
            $pdf->Cell(85, 5, 'Expected Delivery: ' . Carbon::parse($order->expected_delivery_date)->format('d-M-Y'), 0, 1, 'R');
        }
        $pdf->SetX(110);
        $pdf->Cell(85, 5, 'Status: ' . $order->status_label, 0, 1, 'R');
        $pdf->Ln(5);

        // ── Vendor block ─────────────────────────────────────────
        $vendorHtml = '
        <table width="40%" border="1" cellpadding="3" style="font-size:10px;">
            <tr><td width="40%"><b>Vendor:</b></td><td width="60%">' . e($order->vendor->name ?? 'N/A') . '</td></tr>
            <tr><td><b>Ref:</b></td><td>'   . e($order->ref_no  ?? '-') . '</td></tr>
            <tr><td><b>Remarks:</b></td><td>' . e($order->remarks ?? '-') . '</td></tr>
        </table>';
        $pdf->writeHTML($vendorHtml, true, false, false, false, '');
        $pdf->Ln(5);

        // ── Items table ───────────────────────────────────────────
        $html = '
        <table border="1" cellpadding="5" style="font-size:10px;">
            <thead>
                <tr style="background-color:#f2f2f2;font-weight:bold;text-align:center;">
                    <th width="5%">#</th>
                    <th width="35%">Item Description</th>
                    <th width="18%">Variation</th>
                    <th width="12%">Qty</th>
                    <th width="15%">Price</th>
                    <th width="15%">Total</th>
                </tr>
            </thead>
            <tbody>';

        $totalAmount = 0;
        foreach ($order->items as $i => $item) {
            $lineTotal    = $item->quantity * $item->price;
            $totalAmount += $lineTotal;
            $unitCode     = $item->measurementUnit->shortcode ?? '';

            $html .= '
                <tr>
                    <td width="5%"  style="text-align:center;">' . ($i + 1) . '</td>
                    <td width="35%">' . e($item->product->name ?? '-') . '</td>
                    <td width="18%" style="text-align:center;">' . e($item->variation->sku ?? '-') . '</td>
                    <td width="12%" style="text-align:center;">' . number_format($item->quantity, 2) . ' ' . e($unitCode) . '</td>
                    <td width="15%" style="text-align:right;">'  . number_format($item->price, 2) . '</td>
                    <td width="15%" style="text-align:right;">'  . number_format($lineTotal, 2) . '</td>
                </tr>';
        }

        $html .= '
                <tr style="font-weight:bold;background-color:#fafafa;">
                    <td colspan="5" style="text-align:right;">Total Amount</td>
                    <td style="text-align:right;">' . number_format($totalAmount, 2) . '</td>
                </tr>
            </tbody>
        </table>';

        $pdf->writeHTML($html, true, false, false, false, '');

        // ── Signatures ────────────────────────────────────────────
        $pdf->SetFont('helvetica', '', 10);
        $ySign = $pdf->GetY() + 25;
        if ($ySign > 250) { $pdf->AddPage(); $ySign = 30; }

        $pdf->Line(15, $ySign, 75, $ySign);
        $pdf->SetXY(15, $ySign + 2);
        $pdf->Cell(60, 5, 'Prepared By', 0, 0, 'C');

        $pdf->Line(135, $ySign, 195, $ySign);
        $pdf->SetXY(135, $ySign + 2);
        $pdf->Cell(60, 5, 'Authorized Signature', 0, 0, 'C');

        return $pdf->Output('PO_' . $order->po_no . '.pdf', 'I');
    }
}