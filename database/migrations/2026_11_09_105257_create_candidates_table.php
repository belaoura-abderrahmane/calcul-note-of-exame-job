<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();

            // المعلومات الشخصية
            $table->string('registration_number')->nullable(); // رقم التسجيل
            $table->string('last_name');
            $table->string('first_name');
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();

            // الشهادة
            $table->string('diploma')->nullable();
            $table->string('specialty')->nullable();
            $table->string('university')->nullable();
            $table->date('diploma_date')->nullable();

            // معطيات التنقيط
            $table->decimal('specialty_match', 4, 2)->default(0);   // مطابقة التخصص (0-6)
            $table->decimal('average', 5, 2)->nullable();            // المعدل العام
            $table->string('master_mention')->nullable();            // تقدير الماستر
            $table->boolean('is_grande_ecole')->default(false);      // خريج مدرسة عليا
            $table->boolean('is_major')->default(false);             // الأول على الدفعة
            $table->unsignedInteger('trainings_count')->default(0);  // عدد التكوينات التكميلية
            $table->text('trainings_details')->nullable();
            $table->unsignedInteger('research_count')->default(0);   // عدد الأعمال/البحوث
            $table->text('research_details')->nullable();
            $table->json('experiences')->nullable();                 // [{type, years, months}]
            $table->decimal('seniority_years_override', 5, 2)->nullable(); // أقدمية الشهادة يدويا
            $table->decimal('interview', 4, 2)->default(0);          // المقابلة (0-3)

            // النقاط المحسوبة
            $table->decimal('score_specialty', 5, 2)->default(0);
            $table->decimal('score_path', 5, 2)->default(0);
            $table->decimal('score_training', 5, 2)->default(0);
            $table->decimal('score_research', 5, 2)->default(0);
            $table->decimal('score_experience', 5, 2)->default(0);
            $table->decimal('score_seniority', 5, 2)->default(0);
            $table->decimal('score_interview', 5, 2)->default(0);
            $table->decimal('total', 5, 2)->default(0)->index();

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
