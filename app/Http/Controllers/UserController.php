<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                // Dikelompokkan dalam closure agar OR tidak merusak filter role.
                $query->where(function ($sub) use ($request) {
                    $sub->where('name', 'like', '%' . $request->q . '%')
                        ->orWhere('email', 'like', '%' . $request->q . '%')
                        ->orWhere('nim_nip', 'like', '%' . $request->q . '%');
                });
            })
            ->when($request->filled('role'), fn ($query) =>
                $query->where('role', $request->role))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('Users.index', compact('users'));      // ← diubah
    }

    public function create()
    {
        return view('Users.create');
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();

        // role sengaja tidak ada di $fillable (mencegah mass assignment),
        // jadi diisi eksplisit di sini. Hanya admin yang sampai ke method ini.
        $user = new User(Arr::except($data, 'role'));
        $user->role = $data['role'];
        $user->save();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function show(User $user)
    {
        $user->loadCount(['taughtCourses', 'courses']);

        return view('Users.show', compact('user'));         // ← diubah
    }

    public function edit(User $user)
    {
        return view('Users.edit', compact('user'));         // ← diubah
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->fill(Arr::except($data, 'role'));

        if (isset($data['role'])) {
            $user->role = $data['role'];
        }

        $user->save();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        // Admin tidak boleh menghapus akunnya sendiri.
        if ($user->is(auth()->user())) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        // submissions.user_id, materials.uploaded_by, assignments.created_by, dan
        // courses.lecturer_id TIDAK cascade (restrict). Pengguna yang masih punya
        // data tidak dihapus, sehingga tidak ada berkas pengumpulan atau materi
        // yang jadi yatim, dan tidak muncul error 500.
        try {
            $user->delete();
        } catch (QueryException $e) {
            // 23000 = pelanggaran integritas. Error lain dilempar ulang.
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            return redirect()
                ->route('admin.users.index')
                ->with('error', 'Pengguna tidak dapat dihapus karena masih memiliki data terkait (mata kuliah, materi, tugas, atau pengumpulan).');
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Pengguna berhasil dihapus.');
    }
}