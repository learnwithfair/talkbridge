<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TalkBridge — Smart Users Table Migration
 *
 * Reads config/talkbridge.php and adds ONLY the columns that
 * do not already exist in the users table.
 *
 * Columns controlled by config:
 *   - name / first_name + last_name  (user_fields.name)
 *   - avatar_path                    (user_fields.avatar)
 *   - last_seen_at                   (user_fields.last_seen)
 *   - is_active                      (user_fields.is_active)
 *
 * This migration is intentionally idempotent — it will never
 * drop or modify a column that already exists.
 */
return new class extends Migration
{
    /**
     * Columns this migration may add — tracked for clean rollback.
     */
    protected array $addedColumns = [];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // ------------------------------------------------------------------
            // name field(s)
            // Reads: talkbridge.user_fields.name
            // Supports single column ('name') or array (['first_name','last_name'])
            // ------------------------------------------------------------------
            $nameConfig = config('talkbridge.user_fields.name', 'name');

            if (is_array($nameConfig)) {
                // Composite name: add each column if missing
                foreach ($nameConfig as $col) {
                    if ($col && ! Schema::hasColumn('users', $col)) {
                        $table->string($col)->nullable()->after('id');
                    }
                }
            } else {
                // Single name column — only add if it's not the standard 'name'
                // (standard 'name' is created by Laravel's default user migration)
                if ($nameConfig && $nameConfig !== 'name' && ! Schema::hasColumn('users', $nameConfig)) {
                    $table->string($nameConfig)->nullable()->after('id');
                }
            }

            // ------------------------------------------------------------------
            // avatar field
            // Reads: talkbridge.user_fields.avatar
            // ------------------------------------------------------------------
            $avatarCol = config('talkbridge.user_fields.avatar');

            if ($avatarCol && ! Schema::hasColumn('users', $avatarCol)) {
                $table->string($avatarCol)->nullable()->after('email');
            }

            // ------------------------------------------------------------------
            // last_seen field
            // Reads: talkbridge.user_fields.last_seen
            // ------------------------------------------------------------------
            $lastSeenCol = config('talkbridge.user_fields.last_seen', 'last_seen_at');

            if ($lastSeenCol && ! Schema::hasColumn('users', $lastSeenCol)) {
                $table->timestamp($lastSeenCol)->nullable()->after('remember_token');
            }

            // ------------------------------------------------------------------
            // is_active field
            // Reads: talkbridge.user_fields.is_active
            // ------------------------------------------------------------------
            $activeCol = config('talkbridge.user_fields.is_active');

            if ($activeCol && ! Schema::hasColumn('users', $activeCol)) {
                $table->boolean($activeCol)->default(true)->after('remember_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $toDrop = [];

            // name
            $nameConfig = config('talkbridge.user_fields.name', 'name');
            if (is_array($nameConfig)) {
                foreach ($nameConfig as $col) {
                    if ($col && Schema::hasColumn('users', $col)) {
                        $toDrop[] = $col;
                    }
                }
            } else {
                if ($nameConfig && $nameConfig !== 'name' && Schema::hasColumn('users', $nameConfig)) {
                    $toDrop[] = $nameConfig;
                }
            }

            // avatar
            $avatarCol = config('talkbridge.user_fields.avatar');
            if ($avatarCol && Schema::hasColumn('users', $avatarCol)) {
                $toDrop[] = $avatarCol;
            }

            // last_seen
            $lastSeenCol = config('talkbridge.user_fields.last_seen', 'last_seen_at');
            if ($lastSeenCol && Schema::hasColumn('users', $lastSeenCol)) {
                $toDrop[] = $lastSeenCol;
            }

            // is_active
            $activeCol = config('talkbridge.user_fields.is_active');
            if ($activeCol && Schema::hasColumn('users', $activeCol)) {
                $toDrop[] = $activeCol;
            }

            $toDrop = array_unique($toDrop);

            if (! empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
