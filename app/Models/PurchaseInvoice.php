<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_no',
        'vendor_id',
        'po_id',
        'dropoff_customer_id',
        'invoice_date',
        'bill_no',
        'ref_no',
        'remarks',
        'created_by',
    ];

    // ── Relationships ────────────────────────────────────────────

    public function items()
    {
        return $this->hasMany(PurchaseInvoiceItem::class, 'purchase_invoice_id');
    }

    public function vendor()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'vendor_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments()
    {
        return $this->hasMany(PurchaseInvoiceAttachment::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function dropoffCustomer()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'dropoff_customer_id');
    }

    /**
     * The sale invoice raised to bill this drop-ship PI to the customer.
     * Null means not billed yet → dashboard alert.
     */
    public function saleInvoice()
    {
        return $this->hasOne(SaleInvoice::class, 'purchase_invoice_id');
    }

    // ── Helpers ──────────────────────────────────────────────────

    /**
     * True when this PI was drop-shipped to a customer but no
     * sale invoice has been raised yet.
     */
    public function isDropshipUnbilled(): bool
    {
        if (!$this->dropoff_customer_id) return false;
        return !$this->saleInvoice()->exists();
    }
}