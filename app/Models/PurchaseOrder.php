<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    // ── Status constants — use these everywhere instead of raw strings ──
    const STATUS_DRAFT     = 'draft';
    const STATUS_ORDERED   = 'ordered';
    const STATUS_RECEIVED  = 'received';
    const STATUS_PARTIAL   = 'partial';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_RETURNED  = 'returned';

    // Canonical list used by validation and the view
    const STATUSES = [
        self::STATUS_DRAFT     => 'Draft',
        self::STATUS_ORDERED   => 'Ordered',
        self::STATUS_RECEIVED  => 'Received',
        self::STATUS_PARTIAL   => 'Partially Received',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_RETURNED  => 'Returned',
    ];

    // Badge colours for the index table
    const STATUS_BADGES = [
        self::STATUS_DRAFT     => 'secondary',
        self::STATUS_ORDERED   => 'primary',
        self::STATUS_RECEIVED  => 'success',
        self::STATUS_PARTIAL   => 'warning',
        self::STATUS_CANCELLED => 'danger',
        self::STATUS_RETURNED  => 'dark',
    ];

    protected $fillable = [
        'po_no',
        'vendor_id',
        'po_date',
        'expected_delivery_date',
        'ref_no',
        'remarks',
        'status',
        'created_by',
    ];

    // ── Relationships ────────────────────────────────────────────
    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
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
        return $this->hasMany(PurchaseOrderAttachment::class, 'purchase_order_id');
    }

    // ── Helpers ──────────────────────────────────────────────────
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusBadgeAttribute(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'secondary';
    }

    /** Can this PO still be edited / cancelled? */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_ORDERED]);
    }
}