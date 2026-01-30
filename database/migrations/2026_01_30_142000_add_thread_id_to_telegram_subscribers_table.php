<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('telegram_subscribers', function (Blueprint $table) {
            $table->string('thread_id')->nullable()->after('chat_id');
            // Rimuoviamo il vincolo unique solo sul chat_id per permettere iscrizioni a topic diversi dello stesso gruppo
            $table->dropUnique(['chat_id']);
            $table->unique(['chat_id', 'thread_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_subscribers', function (Blueprint $table) {
            $table->dropUnique(['chat_id', 'thread_id']);
            $table->dropColumn('thread_id');
            $table->unique('chat_id');
        });
    }
};
