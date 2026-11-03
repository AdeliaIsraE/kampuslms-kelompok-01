<?php

namespace App\Models;

use App\Support\AdminDashboardCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'sks',
        'lecturer_id',
        'status',
    ];

    /** Folder yang dihapus setelah baris terhapus; diisi saat event `deleting`. */
    protected array $directoriesToDelete = [];

    /**
     * Bersihkan berkas fisik saat mata kuliah dihapus.
     */
    protected static function booted(): void
    {
        static::created(function (Course $course) {
            AdminDashboardCache::forget();
        });

        static::updated(function (Course $course) {
            AdminDashboardCache::forget();
        });

        static::deleting(function (Course $course) {
            $course->directoriesToDelete = [
                "materials/{$course->id}",
                "assignments/{$course->id}",
                ...$course->assignments()
                    ->pluck('id')
                    ->map(fn ($id) => "submissions/{$id}")
                    ->all(),
            ];
        });

        static::deleted(function (Course $course) {
            AdminDashboardCache::forget();

            $disk = Storage::disk('local');

            foreach ($course->directoriesToDelete as $directory) {
                $disk->deleteDirectory($directory);
            }
        });
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('enrolled_at')
            ->withTimestamps();
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function isLecturedBy(User $u): bool
    {
        return $u->role === 'dosen' && (int) $this->lecturer_id === (int) $u->id;
    }

    public function hasStudent(User $u): bool
    {
        return $u->role === 'mahasiswa'
            && $this->students()->whereKey($u->id)->exists();
    }

    // Admin atau dosen pengampu
    public function isManagedBy(User $u): bool
    {
        return $u->role === 'admin' || $this->isLecturedBy($u);
    }

    /** dosen: MK yang diajar; mahasiswa: MK AKTIF yang diikuti (draft/archived disembunyikan). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            'admin' => $query,
            'dosen' => $query->where('lecturer_id', $user->id),
            'mahasiswa' => $query
                ->where('status', 'active')
                ->whereHas('students', fn (Builder $q) => $q->whereKey($user->id)),
            default => $query->whereRaw('1 = 0'),
        };
    }
}