<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('delivery_failed_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
        });

        // Rows created before delivery tracking existed were issued through a
        // successful request path, so preserve their redeemability.
        DB::table('otp_codes')
            ->whereNull('delivered_at')
            ->update(['delivered_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->dropColumn([
                'delivered_at',
                'delivery_failed_at',
                'superseded_at',
            ]);
        });
    }
};
