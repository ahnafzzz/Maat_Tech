<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 16);
            $table->string('owner_identifier', 64);
            $table->string('attempt_key', 128);
            $table->char('fingerprint', 64);
            $table->json('cart_snapshot')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['owner_type', 'owner_identifier', 'attempt_key'],
                'checkout_attempt_owner_key_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_attempts');
    }
};
