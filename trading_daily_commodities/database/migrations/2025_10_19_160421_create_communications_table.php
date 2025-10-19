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
        Schema::create('communications', function (Blueprint $table) {
            $table->id(); // MessageID (PK)
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade'); // SenderID (FK from User)
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade'); // ReceiverID (FK from User)
            $table->text('message_text');
            $table->timestamp('timestamp')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
