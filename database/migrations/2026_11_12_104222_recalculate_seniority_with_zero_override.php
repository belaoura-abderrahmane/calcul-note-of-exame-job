<?php

use App\Models\Competition;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * القيمة اليدوية 0 لأقدمية الشهادة أصبحت تُعامل كفارغة (حساب تلقائي من التاريخ):
     * نعيد حساب نقاط المترشحين المسجلين.
     */
    public function up(): void
    {
        Competition::query()->each(fn (Competition $competition): int => $competition->recalculate());
    }

    public function down(): void
    {
        //
    }
};
