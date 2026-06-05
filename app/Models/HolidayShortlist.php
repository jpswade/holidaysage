<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class HolidayShortlist extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'share_token',
        'sharing_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sharing_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(HolidayShortlistItem::class)->orderBy('position');
    }

    /**
     * Ensure the shortlist has a stable share token (rotated only on demand).
     */
    public function ensureShareToken(): string
    {
        if (is_string($this->share_token) && trim($this->share_token) !== '') {
            return (string) $this->share_token;
        }

        $token = self::generateShareToken();
        $this->forceFill(['share_token' => $token])->save();

        return $token;
    }

    public function rotateShareToken(): string
    {
        $token = self::generateShareToken();
        $this->forceFill(['share_token' => $token])->save();

        return $token;
    }

    public function disableSharing(): void
    {
        $this->forceFill(['sharing_enabled' => false])->save();
    }

    public function enableSharing(): string
    {
        $token = $this->ensureShareToken();
        $this->forceFill(['sharing_enabled' => true])->save();

        return $token;
    }

    private static function generateShareToken(): string
    {
        do {
            $candidate = Str::lower(Str::random(24));
        } while (self::query()->where('share_token', $candidate)->exists());

        return $candidate;
    }
}
