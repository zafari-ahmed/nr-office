<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const KEYS = ['projects.view_all', 'rotation.view'];

    /**
     * Staff can now see all projects and the site rotation. If Admin already saved Staff's access rights,
     * add these two to what was saved (new installs get them from Access::DEFAULTS).
     */
    public function up(): void
    {
        $row = DB::table('settings')->where('key', 'access.staff')->first();
        if (! $row) {
            return;
        }
        $keys = array_values(array_unique(array_merge(array_filter(explode(',', (string) $row->value)), self::KEYS)));
        DB::table('settings')->where('key', 'access.staff')->update(['value' => implode(',', $keys), 'updated_at' => now()]);
        Cache::forget('settings.all');
    }

    public function down(): void
    {
        $row = DB::table('settings')->where('key', 'access.staff')->first();
        if (! $row) {
            return;
        }
        $keys = array_diff(array_filter(explode(',', (string) $row->value)), self::KEYS);
        DB::table('settings')->where('key', 'access.staff')->update(['value' => implode(',', $keys), 'updated_at' => now()]);
        Cache::forget('settings.all');
    }
};
