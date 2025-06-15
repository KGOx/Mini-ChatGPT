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
        Schema::table('users', function (Blueprint $table) {
            $table->text('custom_instructions')->nullable()->after('email'); // "À propos de vous"
            $table->text('custom_response_style')->nullable()->after('custom_instructions'); // "Comment répondre"
            $table->boolean('enable_custom_instructions')->default(true)->after('custom_response_style');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['custom_instructions', 'custom_response_style', 'enable_custom_instructions']);
        });
    }
};
