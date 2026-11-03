<?php

namespace App\Models;

use App\Events\TugasDipublikasikan;
use App\Support\AdminDashboardCache;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;

class Assignment extends Model
{
    use HasFactory;

    public const STATUSES = ['draft', 'published'];

    protected $fillable = [
        'course_id',
        'created_by',
        'title',
        'instructions',
        'file_path',
        'original_name',
        'file_size',
        'mime_type',
        'due_at',
        'max_score',
        'allow_late',
        'status',
    ];

    protected $attributes = [
        'status'     => 'draft',
        'max_score'  => 100,
        'allow_late' => true,
    ];

    /**
     * Bersihkan berkas fisik saat tugas dihapus.
     */
    protected static function booted(): void
    {
        // Tugas baru terbit atau berubah dari draft menjadi published.
        static::saved(function (Assignment $assignment) {
            if ($assignment->status === 'published'
                && ($assignment->wasRecentlyCreated || $assignment->wasChanged('status'))) {
                TugasDipublikasikan::dispatch($assignment);
            }
        });

        // Invalidasi cache ketika tugas dibuat.
        static::created(function (Assignment $assignment) {
            AdminDashboardCache::forget();
        });

        // Invalidasi cache ketika status tugas berubah.
        static::updated(function (Assignment $assignment) {
            if ($assignment->wasChanged('status')) {
                AdminDashboardCache::forget();
            }
        });

        static::deleted(function (Assignment $assignment) {
            AdminDashboardCache::forget();

            $disk = Storage::disk('local');

            if ($assignment->file_path) {
                $disk->delete($assignment->file_path);
            }

            $disk->deleteDirectory("submissions/{$assignment->id}");
        });
    }

    protected function casts(): array
    {
        return [
            'due_at'     => 'datetime',
            'allow_late' => 'boolean',
            'max_score'  => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function grades(): HasManyThrough
    {
        return $this->hasManyThrough(Grade::class, Submission::class);
    }

    public function acceptsSubmissionsAt(CarbonInterface $at): bool
    {
        return $this->due_at === null
            || $at->lte($this->due_at)
            || $this->allow_late;
    }

    public function isLateAt(CarbonInterface $at): bool
    {
        return $this->due_at !== null && $at->gt($this->due_at);
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->file_path
            ? route('tugas.lampiran', $this)
            : null;
    }

    public function getFileSizeHumanAttribute(): ?string
    {
        return FileSize::format($this->file_size);
    }

    /** Mahasiswa tidak melihat tugas draft; admin dan dosen melihat semuanya. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return in_array($user->role, ['admin', 'dosen'], true)
            ? $query
            : $query->where('status', '!=', 'draft');
    }
}