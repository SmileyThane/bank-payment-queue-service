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
        Schema::table('uploads', function (Blueprint $table) {
            $table->decimal('outstanding_amount', 15)->nullable()->change();
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('amount', 15)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->dropColumn('outstanding_amount');
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }

};
