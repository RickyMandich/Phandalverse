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
        Schema::create('statistics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45); // Supports both IPv4 and IPv6
            $table->text('url');
            $table->string('http_method', 10);
            $table->text('user_agent')->nullable();
            $table->text('referrer')->nullable();
            $table->integer('response_status')->nullable();
            $table->float('response_time')->nullable(); // in milliseconds
            $table->timestamp('created_at')->useCurrent();

            // Foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            // Indexes for performance
            $table->index('user_id');
            $table->index('ip_address');
            $table->index('created_at');
            $table->index('http_method');
            $table->index(['user_id', 'ip_address', 'created_at']); // Composite index for grouped queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statistics');
    }
};
