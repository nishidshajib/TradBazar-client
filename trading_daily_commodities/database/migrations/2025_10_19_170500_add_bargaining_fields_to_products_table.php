<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('minimum_price', 10, 2)->nullable()->after('price');
            $table->decimal('maximum_price', 10, 2)->nullable()->after('minimum_price');
            $table->boolean('bargaining_enabled')->default(false)->after('maximum_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['minimum_price', 'maximum_price', 'bargaining_enabled']);
        });
    }
};