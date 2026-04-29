<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderAttachment extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'file_path',
        'original_name',
        'file_type',
    ];

    public function order()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }
}