<?php

namespace App\Models;

use App\Support\AdminDashboardCache;
use App\Support\FileSize;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'type',
        'file_path',
        'original_name',
        'file_size',
        'mime_type',
        'external_url',
    ];

    protected static function booted(): void
    {
        // Materi baru ditambahkan.
        static::created(function (Material $material) {
            AdminDashboardCache::forget();
        });

        // Materi dihapus.
        static::deleted(function (Material $material) {
            AdminDashboardCache::forget();
        });
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Ukuran berkas materi dalam teks yang terbaca manusia ("2,3 MB").
     * null untuk materi bertipe link (tidak punya berkas).
     */
    public function getFileSizeHumanAttribute(): ?string
    {
        return FileSize::format($this->file_size);
    }

    /**
     * Tautan untuk pengguna:
     *  - type=link : tautan luar apa adanya
     *  - type=file : route unduh (materi.unduh) yang memeriksa hak akses.
     */
    public function getUrlAttribute(): ?string
    {
        if ($this->type === 'link') {
            return $this->external_url;
        }

        return $this->file_path
            ? route('materi.unduh', $this)
            : null;
    }
}