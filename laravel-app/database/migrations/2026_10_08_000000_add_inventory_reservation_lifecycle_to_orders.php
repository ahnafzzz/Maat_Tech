<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('confirmed_at')->nullable()->after('placed_at');
            $table->timestamp('expires_at')->nullable()->after('confirmed_at')->index();
            $table->timestamp('cancelled_at')->nullable()->after('expires_at');
            $table->timestamp('stock_released_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['confirmed_at', 'expires_at', 'cancelled_at', 'stock_released_at']);
        });
    }
};
