<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // The categories HR starts with. "code" is what each staff record stores in sys_staff.employment_type, so it never
    // changes (the five older ones keep the values already in the data); "name" is what people read and can be edited.
    private const STARTING_LIST = [
        // code, name, staff number prefix, gets a system login, has an end date
        ['Permanent', 'Permanent & Pensionable', '', true, false],
        ['Contract', 'Contract', 'CT', true, true],
        ['Casual', 'Casual', 'C', false, true],
        ['Intern', 'Internship', 'I', true, true],
        ['Temporary', 'Temporary', 'T', true, true],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('employment_categories')) {
            Schema::create('employment_categories', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name', 100)->unique();
                $table->boolean('active')->default(true);
                $table->string('staff_number_prefix', 10)->default('');
                $table->boolean('gets_login')->default(true);
                $table->boolean('has_end_date')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        $now = now();
        foreach (self::STARTING_LIST as $index => [$code, $name, $prefix, $getsLogin, $hasEndDate]) {
            DB::table('employment_categories')->insertOrIgnore([
                'code' => $code,
                'name' => $name,
                'active' => true,
                'staff_number_prefix' => $prefix,
                'gets_login' => $getsLogin,
                'has_end_date' => $hasEndDate,
                'sort_order' => ($index + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_categories');
    }
};
