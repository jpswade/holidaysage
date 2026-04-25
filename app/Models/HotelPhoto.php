<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class HotelPhoto extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CACHED = 'cached';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'hotel_id',
        'position',
        'external_url',
        'external_url_hash',
        'file_path',
        'status',
        'mime_type',
        'file_size',
        'width',
        'height',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function isCached(): bool
    {
        return $this->status === self::STATUS_CACHED
            && $this->file_path
            && Storage::disk('public')->exists($this->file_path);
    }

    public function publicUrl(): ?string
    {
        if (! $this->isCached() || $this->file_path === null) {
            return null;
        }

        return Storage::disk('public')->url($this->file_path);
    }
}
