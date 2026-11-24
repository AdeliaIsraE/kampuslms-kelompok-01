<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const PESAN_GAGAL = 'NIM/NIP, email, atau kata sandi salah.';

    public function test_login_berhasil_dengan_email(): void
    {
        $mahasiswa = User::factory()->mahasiswa()->create();

        $this->post(route('login.store'), [
            'login' => $mahasiswa->email,
            'password' => 'password',
        ])->assertRedirect(route('mahasiswa.dashboard'));

        $this->assertAuthenticatedAs($mahasiswa);
    }

    public function test_login_berhasil_dengan_nim_atau_nip(): void
    {
        $dosen = User::factory()->dosen()->create();

        $this->post(route('login.store'), [
            'login' => $dosen->nim_nip,
            'password' => 'password',
        ])->assertRedirect(route('dosen.dashboard'));

        $this->assertAuthenticatedAs($dosen);
    }

    public function test_login_gagal_jika_password_salah(): void
    {
        $mahasiswa = User::factory()->mahasiswa()->create();

        $this->post(route('login.store'), [
            'login' => $mahasiswa->email,
            'password' => 'bukan-password-yang-benar',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_pesan_gagal_login_tidak_membedakan_email_salah_dan_password_salah(): void
    {
        $mahasiswa = User::factory()->mahasiswa()->create();

        $this->post(route('login.store'), [
            'login' => $mahasiswa->email,
            'password' => 'password-salah',
        ])->assertSessionHasErrors(['login' => self::PESAN_GAGAL]);

        $this->post(route('login.store'), [
            'login' => 'tidak-terdaftar@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors(['login' => self::PESAN_GAGAL]);
    }

    public function test_tamu_yang_membuka_dashboard_diarahkan_ke_halaman_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_peran_yang_salah_ditolak_di_halaman_admin(): void
    {
        $mahasiswa = User::factory()->mahasiswa()->create();

        $this->actingAs($mahasiswa)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_logout_mengakhiri_sesi(): void
    {
        $mahasiswa = User::factory()->mahasiswa()->create();

        $this->actingAs($mahasiswa)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}