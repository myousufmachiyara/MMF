<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChartOfAccounts extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'shoa_id',
        'name',
        'account_code',
        'account_type',
        'receivables',
        'payables',
        'credit_limit',
        'credit_days',      // ← NEW
        'opening_date',
        'remarks',
        'address',
        'contact_no',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'receivables'  => 'decimal:2',
        'payables'     => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'credit_days'  => 'integer',
        'opening_date' => 'date',
    ];

    public function subHeadOfAccount()
    {
        return $this->belongsTo(SubHeadOfAccounts::class, 'shoa_id', 'id');
    }

    public function purchaseInvoices()
    {
        return $this->hasMany(PurchaseInvoice::class, 'vendor_id');
    }

    // ── Sales invoices raised against this customer ───────────────────
    public function salesInvoices()
    {
        return $this->hasMany(SalesInvoice::class, 'customer_id');
    }
}