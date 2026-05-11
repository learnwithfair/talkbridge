<?php

namespace RahatulRabbi\TalkBridge\Traits;

use RahatulRabbi\TalkBridge\Models\DeviceToken;

/**
 * HasTalkBridgeFeatures
 *
 * Automatically injected into your User model by:
 *   php artisan talkbridge:install
 *
 * Automatically removed by:
 *   php artisan talkbridge:uninstall
 *
 * The markers below are used for precise injection and removal.
 * Do not edit them manually.
 */
trait HasTalkBridgeFeatures
{
    // =========================================================================
    // Display name — supports single column or composite columns
    // =========================================================================

    /**
     * Get display name.
     * Respects config: 'name' => 'name' or 'name' => ['first_name', 'last_name']
     */
    public function getChatDisplayName(): string
    {
        $nameConfig = config('talkbridge.user_fields.name', 'name');

        if (is_array($nameConfig)) {
            return collect($nameConfig)
                ->map(fn($col) => $col && isset($this->{$col}) ? (string) $this->{$col} : '')
                ->filter()
                ->implode(' ');
        }

        $col = $nameConfig ?: 'name';
        return isset($this->{$col}) ? (string) $this->{$col} : '';
    }

    /**
     * Get avatar URL from configured column.
     */
    public function getChatAvatar(): ?string
    {
        $col = config('talkbridge.user_fields.avatar', 'avatar_path');
        return ($col && isset($this->{$col})) ? (string) $this->{$col} : null;
    }

    /**
     * Get last seen as human-readable diff string.
     */
    public function getChatLastSeen(): ?string
    {
        $col = config('talkbridge.user_fields.last_seen', 'last_seen_at');
        return ($col && isset($this->{$col}) && $this->{$col}) ? $this->{$col}->diffForHumans() : null;
    }

    // =========================================================================
    // Online presence
    // =========================================================================

    public function isOnline(): bool
    {
        $col       = config('talkbridge.user_fields.last_seen', 'last_seen_at');
        $threshold = (int) config('talkbridge.online_threshold_minutes', 2);

        if (! $col || ! isset($this->{$col}) || ! $this->{$col}) {
            return false;
        }

        return $this->{$col}->greaterThan(now()->subMinutes($threshold));
    }

    // =========================================================================
    // Blocking
    // =========================================================================

    public function blockedUsers()
    {
        return $this->belongsToMany(static::class, 'user_blocks', 'user_id', 'blocked_id')
            ->withTimestamps();
    }

    public function blockedByUsers()
    {
        return $this->belongsToMany(static::class, 'user_blocks', 'blocked_id', 'user_id')
            ->withTimestamps();
    }

    public function hasBlocked($user): bool
    {
        if (! $user || ! is_object($user)) return false;
        return $this->blockedUsers()->where('users.id', $user->id)->exists();
    }

    public function isBlockedBy($user): bool
    {
        if (! $user || ! is_object($user)) return false;
        return $this->blockedByUsers()->where('users.id', $user->id)->exists();
    }

    // =========================================================================
    // Restricting
    // =========================================================================

    public function restrictedUsers()
    {
        return $this->belongsToMany(static::class, 'user_restricts', 'user_id', 'restricted_id')
            ->withTimestamps();
    }

    public function restrictedByUsers()
    {
        return $this->belongsToMany(static::class, 'user_restricts', 'restricted_id', 'user_id')
            ->withTimestamps();
    }

    public function hasRestricted($user): bool
    {
        if (! $user || ! is_object($user)) return false;
        return $this->restrictedUsers()->where('restricted_id', $user->id)->exists();
    }

    // =========================================================================
    // Device tokens (push notifications)
    // =========================================================================

    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }
}
