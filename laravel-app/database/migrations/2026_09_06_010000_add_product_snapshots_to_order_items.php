<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('product_name')->nullable()->after('product_id');
            $table->string('product_sku')->nullable()->after('product_name');
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Order-item snapshots contain historical data and cannot be removed automatically.'
        );
    }
};
