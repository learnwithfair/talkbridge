<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('message_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['sent', 'delivered', 'seen'])->default('sent');
            $table->timestamps();
            $table->unique(['message_id', 'user_id']);
            $table->index(['message_id', 'user_id'], 'idx_tb_msg_status');
        });
    }
    public function down(): void { Schema::dropIfExists('message_statuses'); }
};
