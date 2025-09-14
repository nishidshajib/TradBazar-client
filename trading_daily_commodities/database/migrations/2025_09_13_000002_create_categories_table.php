<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Seed categories as per project
        DB::table('categories')->insert([
            ['name' => 'handicrafts'],
            ['name' => 'vegetables'],
            ['name' => 'fruits'],
            ['name' => 'traditional clothing'],
            ['name' => 'pottery'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
