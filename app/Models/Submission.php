<?php

namespace App\Models;

use App\Support\AdminDashboardCache;
use App\Support\FileSize;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Submission extends Model
{
    use HasFactory;

    /**
     * Path berkas yang dihapus setelah baris terhapus.
     */
    protected array $filesToDelete = [];

    protected $fillable = [
        'assignment_id',
        'user_id',
        'file_path',
        'original_name',
        'file_size',
        'note',
        'submitted_at',
        'is_late',
    ];

    /**
     * Mengatur invalidasi cache dan penghapusan berkas fisik.
     */
    protected static function booted(): void
    {
        // Submission baru dibuat.
        static::created(function (Submission $submission) {
            AdminDashboardCache::forget();
        });

        // Waktu pengumpulan berubah, misalnya saat mahasiswa mengumpulkan ulang.
        static::updated(function (Submission $submission) {
            if ($submission->wasChanged('submitted_at')) {
                AdminDashboardCache::forget();
            }
        });

        static::deleting(function (Submission $submission) {
            $submission->filesToDelete = $submission->revisions()
                ->pluck('file_path')
                ->push($submission->file_path)
                ->filter()
                ->unique()
                ->values()
                ->all();
        });

        static::deleted(function (Submission $submission) {
            AdminDashboardCache::forget();

            if ($submission->filesToDelete !== []) {
                Storage::disk('local')->delete($submission->filesToDelete);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'is_late' => 'boolean',
        ];
    }

    /** Ukuran berkas terbaru dalam teks yang terbaca manusia ("850 KB"). */
    public function getFileSizeHumanAttribute(): ?string
    {
        return FileSize::format($this->file_size);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Alias untuk kompatibilitas dengan resource/controller API.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function grade(): HasOne
    {
        return $this->hasOne(Grade::class);
    }

    // Riwayat pengumpulan: satu baris per versi (v1, v2, ...), termasuk versi terbaru.
    public function revisions(): HasMany
    {
        return $this->hasMany(SubmissionRevision::class);
    }
}