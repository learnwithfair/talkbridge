<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

// =============================================================================
// Internal disk helper
// =============================================================================

if (! function_exists('_talkbridge_disk')) {
    /**
     * Return the configured Storage disk instance.
     *
     * @internal
     */
    function _talkbridge_disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('talkbridge.uploads.disk', 'public'));
    }
}

// =============================================================================
// File upload / delete helpers
// =============================================================================

if (! function_exists('talkbridge_upload_file')) {
    /**
     * Upload a file to the configured disk (local public, S3, GCS, etc.).
     *
     * Works identically for every Laravel filesystem driver:
     *   - public  → storage/app/public/{folder}/{file}
     *   - s3      → s3://{bucket}/{folder}/{file}
     *   - gcs     → gcs://{bucket}/{folder}/{file}
     *   - etc.
     *
     * Returns a full public URL on success, null on failure.
     *
     * @param  UploadedFile  $file
     * @param  string        $folder      Relative folder path, e.g. "uploads/messages"
     * @param  string|null   $customName  File name without extension
     * @return string|null   Full public URL or null on failure
     */
    function talkbridge_upload_file(UploadedFile $file, string $folder, ?string $customName = null): ?string
    {
        try {
            $disk = config('talkbridge.uploads.disk', 'public');

            $fileName = $customName
                ? $customName . '.' . $file->getClientOriginalExtension()
                : time() . '_' . \Illuminate\Support\Str::slug(
                pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
            ) . '.' . $file->getClientOriginalExtension();

            // Cloud disks (S3, GCS, R2 etc.) need explicit public visibility
            $visibility = _talkbridge_disk_is_cloud($disk) ? 'public' : null;

            // Always return the RELATIVE storage path.
            // Use talkbridge_file_url($path) to get the full public URL.
            // Storing relative paths keeps the DB portable across disks and domains.

            if ($visibility) {
                $path = Storage::disk($disk)->putFileAs($folder, $file, $fileName);
                return Storage::disk($disk)->url($path) ?? 'error';
            } else {
                return $file->storeAs($folder, $fileName, $disk);
            }

        } catch (\Throwable $e) {
            Log::error('talkbridge_upload_file failed', [
                'disk'  => config('talkbridge.uploads.disk', 'public'),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

}

if (! function_exists('talkbridge_delete_file')) {
    /**
     * Delete a file from the configured disk.
     *
     * Accepts either a full URL or a relative storage path.
     *
     * @param  string|null  $filePath  Full URL or relative path
     * @return bool
     */
    function talkbridge_delete_file(?string $filePath): bool
    {
        if (! $filePath) {
            return false;
        }

        try {
            $disk         = config('talkbridge.uploads.disk', 'public');
            $relativePath = _talkbridge_url_to_path($disk, $filePath);

            return Storage::disk($disk)->delete($relativePath);

        } catch (\Throwable $e) {
            Log::error('talkbridge_delete_file failed', [
                'path'  => $filePath,
                'disk'  => config('talkbridge.uploads.disk', 'public'),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

if (! function_exists('talkbridge_delete_files')) {
    /**
     * Delete multiple files from the configured disk.
     *
     * @param  string[]  $paths  Full URLs or relative paths
     * @return array{deleted: string[], failed: string[]}
     */
    function talkbridge_delete_files(array $paths): array
    {
        $deleted = [];
        $failed  = [];

        foreach ($paths as $path) {
            talkbridge_delete_file($path) ? $deleted[] = $path : $failed[] = $path;
        }

        return ['deleted' => $deleted, 'failed' => $failed];
    }
}

if (! function_exists('talkbridge_file_url')) {
    /**
     * Get the full public URL for a stored file path.
     *
     * Useful when you have a relative path and need the URL.
     *
     * @param  string  $path  Relative storage path
     * @return string
     */
    function talkbridge_file_url(string $path): string
    {
        if ($path === '' || $path === null) {
            return '';
        }

        // Already a full URL — return as-is
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $disk = config('talkbridge.uploads.disk', 'public');
        return _talkbridge_file_url($disk, $path);
    }
}

if (! function_exists('talkbridge_file_type')) {
    /**
     * Detect file type category from file extension.
     *
     * Returns one of: 'image', 'video', 'audio', 'file'
     *
     * @param  string  $path  File path or name with extension
     * @return string
     */
    function talkbridge_file_type(string $path): string
    {
        $ext   = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $types = config('talkbridge.uploads.allowed_types', []);

        foreach ($types as $type => $extensions) {
            if (in_array($ext, $extensions, true)) {
                return $type;
            }
        }

        return 'file';
    }
}

// =============================================================================
// Internal disk utilities
// =============================================================================

if (! function_exists('_talkbridge_disk_is_cloud')) {
    /**
     * Determine if a disk driver is a cloud / remote filesystem.
     * Cloud disks require explicit visibility when uploading public files.
     *
     * @internal
     */
    function _talkbridge_disk_is_cloud(string $diskName): bool
    {
        $driver = config("filesystems.disks.{$diskName}.driver", '');

        return in_array($driver, ['s3', 'gcs', 'r2', 'do_spaces', 'azure'], true)
        || str_contains(strtolower($driver), 'cloud');
    }
}

if (! function_exists('_talkbridge_file_url')) {
    /**
     * Generate the correct public URL for a stored file, regardless of disk driver.
     *
     *  - local public disk   → Storage::url($path)
     *  - S3 / GCS / cloud    → Storage::disk($disk)->url($path)
     *  - custom URL prefix   → reads filesystems.disks.{disk}.url from config
     *
     * @internal
     */
    function _talkbridge_file_url(string $diskName, string $path): string
    {
        try {
            // All disk drivers support url() in Laravel — use it uniformly.
            return Storage::disk($diskName)->url($path);
        } catch (\Throwable $e) {
            // Fallback: build URL manually from config
            $baseUrl = config("filesystems.disks.{$diskName}.url", config('app.url'));
            return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        }
    }
}

if (! function_exists('_talkbridge_url_to_path')) {
    /**
     * Convert a full URL back to a relative storage path for deletion.
     *
     * If the value is already a relative path, returns it unchanged.
     *
     * @internal
     */
    function _talkbridge_url_to_path(string $diskName, string $urlOrPath): string
    {
        // Already a relative path (no scheme)
        if (! str_contains($urlOrPath, '://') && ! str_starts_with($urlOrPath, 'http')) {
            return $urlOrPath;
        }

        try {
            // Try to derive relative path by stripping the disk base URL
            $diskUrl = Storage::disk($diskName)->url('');
            $baseUrl = rtrim($diskUrl, '/');

            if ($baseUrl && str_starts_with($urlOrPath, $baseUrl)) {
                return ltrim(substr($urlOrPath, strlen($baseUrl)), '/');
            }
        } catch (\Throwable) {
            // ignore
        }

        // Fallback: strip app URL prefix
        $appUrl = rtrim(config('app.url', ''), '/');
        if ($appUrl && str_starts_with($urlOrPath, $appUrl)) {
            return ltrim(substr($urlOrPath, strlen($appUrl)), '/');
        }

        return $urlOrPath;
    }
}

// =============================================================================
// User helpers — all null-safe
// =============================================================================

if (! function_exists('talkbridge_user_name')) {
    /**
     * Resolve display name from a user model instance.
     *
     * Null-safe: returns empty string if $user is null or not an object.
     *
     * Supports:
     *   'name' => 'name'                           single column
     *   'name' => ['first_name', 'last_name']      composite
     *   'name' => ['f_name', 'm_name', 'l_name']   three parts
     */
    function talkbridge_user_name($user): string
    {
        if ($user === null || ! is_object($user)) {
            return '';
        }

        if (method_exists($user, 'getChatDisplayName')) {
            return (string) $user->getChatDisplayName();
        }

        $nameConfig = config('talkbridge.user_fields.name', 'name');

        if (is_array($nameConfig)) {
            return collect($nameConfig)
                ->map(fn($col) => $col && isset($user->{$col}) ? (string) $user->{$col} : '')
                ->filter()
                ->implode(' ');
        }

        $col = $nameConfig ?: 'name';
        return isset($user->{$col}) ? (string) $user->{$col} : '';
    }
}

if (! function_exists('talkbridge_user_avatar')) {
    /**
     * Resolve avatar URL from a user model instance.
     *
     * If the stored value is a relative path (not starting with http),
     * it is converted to a full URL using the configured disk.
     *
     * Null-safe: returns null if $user is null or not an object.
     */
    function talkbridge_user_avatar($user): ?string
    {
        if ($user === null || ! is_object($user)) {
            return null;
        }

        if (method_exists($user, 'getChatAvatar')) {
            return $user->getChatAvatar();
        }

        $col = config('talkbridge.user_fields.avatar', 'avatar_path');

        if (! $col || ! isset($user->{$col}) || ! $user->{$col}) {
            return null;
        }

        $value = (string) $user->{$col};

        // Already a full URL — return as-is
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        // Relative path — convert using configured disk
        return talkbridge_file_url($value);
    }
}

if (! function_exists('talkbridge_user_online')) {
    /**
     * Check if a user is currently online.
     *
     * Null-safe: returns false if $user is null.
     */
    function talkbridge_user_online($user): bool
    {
        if ($user === null || ! is_object($user)) {
            return false;
        }

        if (method_exists($user, 'isOnline')) {
            return (bool) $user->isOnline();
        }

        $col       = config('talkbridge.user_fields.last_seen', 'last_seen_at');
        $threshold = (int) config('talkbridge.online_threshold_minutes', 2);

        if (! $col || ! isset($user->{$col}) || ! $user->{$col}) {
            return false;
        }

        return $user->{$col}->greaterThan(now()->subMinutes($threshold));
    }
}

if (! function_exists('talkbridge_user_last_seen')) {
    /**
     * Get human-readable last seen string.
     *
     * Null-safe: returns null if $user is null.
     */
    function talkbridge_user_last_seen($user): ?string
    {
        if ($user === null || ! is_object($user)) {
            return null;
        }

        if (method_exists($user, 'getChatLastSeen')) {
            return $user->getChatLastSeen();
        }

        $col = config('talkbridge.user_fields.last_seen', 'last_seen_at');

        if (! $col || ! isset($user->{$col}) || ! $user->{$col}) {
            return null;
        }

        return $user->{$col}->diffForHumans();
    }
}
