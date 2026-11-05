<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rates are quoted to four decimals (GHS 0.0350 per SMS part) and a broadcast
 * is billed one message, call or email at a time. Money remembered to two
 * decimals would round every single-part message up to 0.04, so the wallet, its
 * ledger, the campaign holds and the campaign totals all keep four decimals.
 */
return new class extends Migration
{
    /**
     * The money columns that are debited a billable unit at a time.
     *
     * @var array<string, array<string, int>>
     */
    protected array $columns = [
        'wallets' => ['balance' => 14, 'reserved_balance' => 14, 'low_balance_threshold' => 12],
        'wallet_transactions' => ['amount' => 14, 'balance_after' => 14],
        'billing_reservations' => ['amount' => 14, 'captured_amount' => 14, 'released_amount' => 14],
        'campaigns' => ['estimated_cost' => 14, 'reserved_amount' => 14, 'actual_cost' => 14],
        'campaign_versions' => ['estimated_cost' => 14],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->rescale(4);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->rescale(2);
    }

    /**
     * Move every money column to the given number of decimal places.
     */
    protected function rescale(int $scale): void
    {
        foreach ($this->columns as $table => $columns) {
            Schema::table($table, function (Blueprint $table) use ($columns, $scale): void {
                foreach ($columns as $column => $precision) {
                    $table->decimal($column, $precision, $scale)->default(0)->change();
                }
            });
        }
    }
};
