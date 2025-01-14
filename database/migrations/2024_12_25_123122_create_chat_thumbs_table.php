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
            Schema::create('chat_thumbs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('chat_id'); // Foreign key for chat
                $table->unsignedBigInteger('user_id'); // Foreign key for user
                $table->enum('thumb_type', ['up', 'down']); // Type of thumb
                $table->timestamps();

                // Foreign key constraints
                $table->foreign('chat_id')->references('id')->on('chats')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

                // Ensure one thumb per user per chat
                $table->unique(['chat_id', 'user_id']);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_thumbs');
    }
};
