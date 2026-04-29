<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_no', 10)->unique();
            $table->unsignedBigInteger('vendor_id');
            $table->date('po_date');
            $table->date('expected_delivery_date')->nullable();
            $table->string('ref_no')->nullable();
            $table->text('remarks')->nullable();
            $table->enum('status', [
                'draft',
                'ordered',
                'received',
                'partial',      // partially received
                'cancelled',
                'returned',
            ])->default('draft');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('vendor_id')->references('id')->on('chart_of_accounts')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};