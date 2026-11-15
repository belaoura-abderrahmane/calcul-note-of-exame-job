<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * معايير الفصل بين المتساوين (الوضعية العائلية، الإعاقة، ذوو الحقوق) والإقصاء بسبب الغياب عن المقابلة.
     */
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->string('family_status', 20)->nullable()->after('gender');
            $table->boolean('is_disabled')->default(false)->after('family_status');
            $table->boolean('is_martyr_rights')->default(false)->after('is_disabled');
            $table->boolean('interview_absent')->default(false)->after('interview_details');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn(['family_status', 'is_disabled', 'is_martyr_rights', 'interview_absent']);
        });
    }
};
