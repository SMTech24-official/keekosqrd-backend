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
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Foreign key for user
            $table->unsignedBigInteger('chat_title_id'); // Foreign key for chatTitle
            $table->text('query'); // User's message
            // $table->text('message'); // User's message
            $table->text('response')->nullable(); // AI response
            $table->integer('thumbs_up')->default(0); // Default value 0
            $table->integer('thumbs_down')->default(0); // Default value 0
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('chat_title_id')->references('id')->on('chat_titles')->onDelete('cascade');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
