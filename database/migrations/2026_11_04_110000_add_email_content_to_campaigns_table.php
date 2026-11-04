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
     * Email campaigns carry their own content: the subject line, the body and the
     * format it was authored in. The columns are nullable so the SMS and voice
     * campaigns that already exist are untouched.
     */
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('email_subject')->nullable()->after('sms_message');
            $table->longText('email_body')->nullable()->after('email_subject');
            $table->string('email_format', 16)->default(EmailFormat::Html->value)->after('email_body');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['email_subject', 'email_body', 'email_format']);
        });
    }
};
