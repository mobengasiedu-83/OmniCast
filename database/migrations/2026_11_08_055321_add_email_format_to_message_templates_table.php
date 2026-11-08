<?php

use App\Enums\EmailFormat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An email template remembers whether it was written as markup or plain
     * text, so the campaign built from it keeps the same format instead of
     * guessing. The column defaults to HTML because that is how every existing
     * email template was authored.
     */
    public function up(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->string('email_format', 16)->default(EmailFormat::Html->value)->after('body');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropColumn('email_format');
        });
    }
};
