<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_two_factor_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('selector', 32)->unique();
            $table->string('code_hash');
            $table->string('session_binding_hash', 64);
            $table->string('credential_version', 64);
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('failed_attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();

            $table->index(['admin_id', 'status']);
            $table->index(['admin_id', 'created_at']);
            $table->index(['status', 'expires_at']);
        });

        // Administrator-row codes cannot be bound to a browser or consumed atomically.
        // Invalidate them during upgrade without changing credentials or 2FA settings.
        DB::table('admins')->update([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_two_factor_challenges');
    }
};
