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
        Schema::table('courts', function (Blueprint $table) {
            $table->string('sport_type')->default('Badminton')->after('name');
            $table->enum('court_type', ['indoor', 'outdoor'])->default('indoor')->after('sport_type');
        });
    }

    public function down(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->dropColumn(['sport_type', 'court_type']);
        });
    }
};
