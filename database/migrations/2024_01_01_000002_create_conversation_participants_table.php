<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->enum('role', ['super_admin', 'admin', 'member'])->default('member');
            $table->boolean('is_muted')->default(false);
            $table->timestamp('muted_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('left_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->unsignedBigInteger('last_deleted_message_id')->nullable();
            $table->timestamps();
            $table->index(['is_muted', 'muted_until'], 'idx_tb_muted');
            $table->index(['user_id', 'conversation_id'], 'idx_tb_user_conv');
        });
    }
    public function down(): void { Schema::dropIfExists('conversation_participants'); }
};
