<?php

use App\Enums\SenderIdChannel;
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
        if (! Schema::hasColumn('sender_ids', 'channel')) {
            Schema::table('sender_ids', function (Blueprint $table) {
                $table->string('channel', 20)
                    ->default(SenderIdChannel::Sms->value)
                    ->after('client_id')
                    ->index();
            });
        }

        /**
         * MySQL keeps the foreign key on `client_id` satisfied through the old
         * unique index, so a dedicated index has to exist before it is dropped.
         */
        if (! Schema::hasIndex('sender_ids', 'sender_ids_client_id_index')) {
            Schema::table('sender_ids', function (Blueprint $table) {
                $table->index('client_id', 'sender_ids_client_id_index');
            });
        }

        if (Schema::hasIndex('sender_ids', ['client_id', 'name'], 'unique')) {
            Schema::table('sender_ids', function (Blueprint $table) {
                $table->dropUnique(['client_id', 'name']);
            });
        }

        Schema::table('sender_ids', function (Blueprint $table) {
            $table->string('name', 120)->change();
            $table->string('country', 2)->nullable()->default('GH')->change();
        });

        if (! Schema::hasIndex('sender_ids', ['client_id', 'channel', 'name'], 'unique')) {
            Schema::table('sender_ids', function (Blueprint $table) {
                $table->unique(['client_id', 'channel', 'name']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sender_ids', function (Blueprint $table) {
            $table->dropUnique(['client_id', 'channel', 'name']);
        });

        Schema::table('sender_ids', function (Blueprint $table) {
            $table->string('name', 11)->change();
            $table->string('country', 2)->default('GH')->nullable(false)->change();
            $table->unique(['client_id', 'name']);
        });

        if (Schema::hasIndex('sender_ids', 'sender_ids_client_id_index')) {
            Schema::table('sender_ids', function (Blueprint $table) {
                $table->dropIndex('sender_ids_client_id_index');
            });
        }

        Schema::table('sender_ids', function (Blueprint $table) {
            $table->dropColumn('channel');
        });
    }
};
