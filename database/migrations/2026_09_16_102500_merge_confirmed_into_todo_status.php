<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Confirmed" and "to do" were two names for one state: a requirement that
 * is settled and really going to be built. What was not settled is now
 * called uncertain, on features too, where it used to be "idea".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('requirements')->where('status', '已确认')->update(['status' => '待做']);
        DB::table('requirements')->where('status', '待定')->update(['status' => '不确定']);
        DB::table('features')->where('status', '想法')->update(['status' => '不确定']);
    }

    public function down(): void
    {
        DB::table('requirements')->where('status', '待做')->update(['status' => '已确认']);
        DB::table('requirements')->where('status', '不确定')->update(['status' => '待定']);
        DB::table('features')->where('status', '不确定')->update(['status' => '想法']);
    }
};
