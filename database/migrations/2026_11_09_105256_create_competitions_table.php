<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->string('title');                       // عنوان المسابقة
            $table->string('institution')->nullable();     // المؤسسة / الإدارة
            $table->string('grade');                       // الرتبة
            $table->string('required_diploma')->nullable();// الشهادة المطلوبة
            $table->string('required_specialty')->nullable(); // التخصصات المطلوبة
            $table->unsignedInteger('positions')->default(1); // عدد المناصب
            $table->date('reference_date')->nullable();    // تاريخ اختتام التسجيلات (لحساب الأقدمية)
            $table->date('exam_date')->nullable();         // تاريخ المسابقة
            $table->string('status')->default('open');     // open / closed
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitions');
    }
};
