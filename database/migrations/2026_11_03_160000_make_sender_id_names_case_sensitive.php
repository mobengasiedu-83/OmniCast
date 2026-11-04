<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SMS sender IDs keep the case the client typed, so "OmniCast" and
     * "omnicast" are two different brands. The connection collation compares
     * them as equal, which would both hide the second request behind the unique
     * index and make the uniqueness rule reject it, so the column is moved to a
     * binary collation. SQLite already compares text case-sensitively.
     */
    public function up(): void
    {
        $this->collate('utf8mb4_bin');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->collate((string) config('database.connections.mysql.collation', 'utf8mb4_unicode_ci'));
    }

    /**
     * Apply a collation to `sender_ids.name`, skipping drivers that do not
     * support per-column collations.
     */
    protected function collate(string $collation): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $column = collect(Schema::getColumns('sender_ids'))->firstWhere('name', 'name');
        $current = is_array($column) ? ($column['collation'] ?? null) : null;

        if (strcasecmp((string) $current, $collation) === 0) {
            return;
        }

        Schema::table('sender_ids', function (Blueprint $table) use ($collation): void {
            $table->string('name', 120)->collation($collation)->change();
        });
    }
};
