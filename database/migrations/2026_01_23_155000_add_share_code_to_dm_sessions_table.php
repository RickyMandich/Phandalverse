<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('dm_sessions', function (Blueprint $table) {
            $table->string('share_code', 10)->nullable()->unique()->index()->after('name');
        });

        // Populate existing sessions
        $sessions = \App\Models\DmSession::whereNull('share_code')->get();
        foreach ($sessions as $session) {
            $session->update(['share_code' => \App\Models\DmSession::generateUniqueCode()]);
        }
    }

    public function down(): void
    {
        Schema::table('dm_sessions', function (Blueprint $table) {
            $table->dropColumn('share_code');
        });
    }
};
