<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->string('account_type')->default('business')->after('name');
        });

        Schema::table('client_registrations', function (Blueprint $table): void {
            $table->string('account_type')->default('business')->after('id');
            $table->string('company_name')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('client_registrations')
            ->whereNull('company_name')
            ->update(['company_name' => DB::raw('contact_name')]);

        Schema::table('client_registrations', function (Blueprint $table): void {
            $table->string('company_name')->nullable(false)->change();
            $table->dropColumn('account_type');
        });

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn('account_type');
        });
    }
};
