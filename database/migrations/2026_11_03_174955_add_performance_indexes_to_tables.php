<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index tambahan minggu 11. Tiap index dicek dulu agar tidak dobel
     * dengan index yang sudah ada (mis. dari foreign key atau unique).
     */
    private array $indexes = [
        // AssignmentController@index: where course_id [+ status = published untuk
        // mahasiswa] order by due_at. Satu index melayani filter dan pengurutan.
        ['assignments', ['course_id', 'status', 'due_at'], 'assignments_course_status_due_idx'],

        // MaterialController@index: materials where course_id order by created_at desc
        // (latest()). Tanpa ini, urutan dikerjakan filesort.
        ['materials', ['course_id', 'created_at'], 'materials_course_created_idx'],

        // Dashboard dosen dan Course::visibleTo() untuk dosen:
        // where lecturer_id = ? [and status = 'active'].
        ['courses', ['lecturer_id', 'status'], 'courses_lecturer_status_idx'],

        // Course::visibleTo() untuk mahasiswa: cari mata kuliah milik user_id
        // lewat tabel pivot. Unique (course_id, user_id) tidak membantu pencarian
        // dari sisi user_id karena kolom pertamanya course_id.
        ['course_user', ['user_id', 'course_id'], 'course_user_user_course_idx'],

        // CourseController@lecturers dan daftar mahasiswa tersedia:
        // users where role = ? order by name.
        ['users', ['role', 'name'], 'users_role_name_idx'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$tabel, $kolom, $nama]) {
            if (Schema::hasIndex($tabel, $kolom)) {
                continue;
            }

            Schema::table($tabel, fn (Blueprint $t) => $t->index($kolom, $nama));
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as [$tabel, , $nama]) {
            if (Schema::hasIndex($tabel, $nama)) {
                Schema::table($tabel, fn (Blueprint $t) => $t->dropIndex($nama));
            }
        }
    }
};