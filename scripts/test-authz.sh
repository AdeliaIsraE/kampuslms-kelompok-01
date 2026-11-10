#!/usr/bin/env bash
# KampusLMS — pengujian regresi otorisasi berdasarkan data seeder.
# Jalankan dari root proyek setelah database di-seed dan `php artisan serve` aktif.
#
# Wajib diisi dengan ID AKTUAL dari database:
# ADMIN_ID DOSEN_ID MAHASISWA_ID
# COURSE_ID OTHER_COURSE_ID MATERIAL_ID ASSIGNMENT_ID OTHER_ASSIGNMENT_ID
# DRAFT_ASSIGNMENT_ID OTHER_SUBMISSION_ID FOREIGN_SUBMISSION_ID USER_TARGET_ID
#
# Ketentuan relasi data:
# - COURSE_ID diampu oleh DOSEN_ID, berstatus active, dan diikuti MAHASISWA_ID.
# - OTHER_COURSE_ID diampu dosen lain dan tidak diikuti MAHASISWA_ID.
# - ASSIGNMENT_ID adalah tugas published pada COURSE_ID.
# - OTHER_ASSIGNMENT_ID adalah tugas published pada OTHER_COURSE_ID.
# - DRAFT_ASSIGNMENT_ID adalah tugas draft pada mata kuliah yang diikuti MAHASISWA_ID.
# - OTHER_SUBMISSION_ID adalah submission mahasiswa lain untuk ASSIGNMENT_ID.
# - FOREIGN_SUBMISSION_ID adalah submission pada mata kuliah yang tidak diampu DOSEN_ID.
# - USER_TARGET_ID adalah ID pengguna lain, bukan akun admin yang sedang login.
#
# Seeder membuat relasi secara acak. Periksa relasi di database sebelum menjalankan.
# File submission tidak dibuat oleh seeder; pengujian unduh di bawah hanya menguji
# penolakan akses, bukan keberhasilan transfer file.
#
# Catatan keamanan: /dev-login/{id} tersedia pada environment local. Jangan gunakan
# endpoint tersebut sebagai mekanisme autentikasi produksi dan hapus/nonaktifkan
# sebelum aplikasi dipasang pada environment selain lokal.

set -uo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8000}"
ADMIN_NIM_NIP="${ADMIN_NIM_NIP:-198001012005011001}"
DOSEN_NIM_NIP="${DOSEN_NIM_NIP:-198505052010011002}"
MAHASISWA_NIM_NIP="${MAHASISWA_NIM_NIP:-10241999}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@kampuslms.test}"
DOSEN_EMAIL="${DOSEN_EMAIL:-dosen@kampuslms.test}"
MAHASISWA_EMAIL="${MAHASISWA_EMAIL:-mahasiswa@kampuslms.test}"
SEED_PASSWORD="${SEED_PASSWORD:-${DEMO_PASSWORD:-password}}"

OUT_DIR="${TMPDIR:-/tmp}/kampuslms-authz-$$"
mkdir -p "$OUT_DIR"
trap 'rm -rf "$OUT_DIR"' EXIT

required=(
  ADMIN_ID DOSEN_ID MAHASISWA_ID COURSE_ID OTHER_COURSE_ID MATERIAL_ID
  ASSIGNMENT_ID OTHER_ASSIGNMENT_ID DRAFT_ASSIGNMENT_ID
  OTHER_SUBMISSION_ID FOREIGN_SUBMISSION_ID USER_TARGET_ID
)
missing=()
for variable in "${required[@]}"; do
  [[ -n "${!variable:-}" ]] || missing+=("$variable")
done

if ((${#missing[@]})); then
  printf 'GALAT: isi ID berikut dari database hasil seeder:\n  %s\n' "${missing[*]}" >&2
  printf 'Jalankan ulang dengan format: ADMIN_ID=... DOSEN_ID=... ./scripts/test-authz.sh\n' >&2
  exit 2
fi

for command in curl grep sed head; do
  command -v "$command" >/dev/null || {
    printf 'GALAT: perintah %s tidak ditemukan.\n' "$command" >&2
    exit 2
  }
done

declare -A TOKEN COOKIE CSRF
PASS=0
FAIL=0
FAILURES=()

# Login API memakai akun demo yang dibuat DatabaseSeeder.
login_api() {
  local key="$1" email="$2" body="$OUT_DIR/${key}-login.json" code
  code=$(curl -sS -o "$body" -w '%{http_code}' \
    -H 'Accept: application/json' \
    -H 'Content-Type: application/json' \
    -X POST "$BASE_URL/api/v1/auth/login" \
    --data "{\"email\":\"$email\",\"password\":\"$SEED_PASSWORD\",\"device_name\":\"uji-otorisasi-$key\"}") || code=000

  if [[ "$code" != 200 ]]; then
    printf 'GALAT: login API akun %s menghasilkan HTTP %s. Periksa server, kata sandi, dan throttle.\n' "$key" "$code" >&2
    exit 2
  fi

  TOKEN["$key"]=$(sed -n 's/.*"token":"\([^"]*\)".*/\1/p' "$body")
  [[ -n "${TOKEN[$key]}" ]] || {
    printf 'GALAT: token API untuk akun %s tidak ditemukan.\n' "$key" >&2
    exit 2
  }
}

# Login web memakai form login asli: NIM/NIP + kata sandi + token CSRF.
login_web() {
  local key="$1" nim_nip="$2" jar="$OUT_DIR/${key}.cookies"
  local page="$OUT_DIR/${key}.html" token code

  code=$(curl -sS -c "$jar" -b "$jar" -o "$page" -w '%{http_code}' "$BASE_URL/") || code=000
  [[ "$code" == 200 ]] || {
    printf 'GALAT: membuka halaman login untuk %s menghasilkan HTTP %s.\n' "$key" "$code" >&2
    exit 2
  }

  token=$(sed -n 's/.*name="_token"[^>]*value="\([^"]*\)".*/\1/p' "$page" | head -n1)
  if [[ -z "$token" ]]; then
    printf 'GALAT: token CSRF tidak ditemukan pada form login. Periksa resources/views/welcome.blade.php.\n' >&2
    exit 2
  fi

  code=$(curl -sS -L -c "$jar" -b "$jar" -o /dev/null -w '%{http_code}' \
    -H "Referer: $BASE_URL/" \
    --data-urlencode "_token=$token" \
    --data-urlencode "nim_nip=$nim_nip" \
    --data-urlencode "password=$SEED_PASSWORD" \
    "$BASE_URL/login") || code=000

  if [[ "$code" != 200 ]]; then
    printf 'GALAT: login web akun %s berakhir dengan HTTP %s. Periksa NIM/NIP dan kata sandi.\n' "$key" "$code" >&2
    exit 2
  fi

  COOKIE["$key"]="$jar"
  CSRF["$key"]="$token"
}

laporkan() {
  local label="$1" diharapkan="$2" aktual="$3"
  if [[ "$aktual" == "$diharapkan" ]]; then
    printf 'LULUS  %-70s diharapkan=%s aktual=%s\n' "$label" "$diharapkan" "$aktual"
    PASS=$((PASS + 1))
  else
    printf 'GAGAL  %-70s diharapkan=%s aktual=%s\n' "$label" "$diharapkan" "$aktual"
    FAIL=$((FAIL + 1))
    FAILURES+=("$label: diharapkan $diharapkan, aktual $aktual")
  fi
}

uji_web() {
  local label="$1" key="$2" method="$3" path="$4" expected="$5" data="${6:-}" code
  if [[ -n "$data" ]]; then
    code=$(curl -sS -o "$OUT_DIR/respons-web" -w '%{http_code}' \
      -b "${COOKIE[$key]}" -H "Referer: $BASE_URL$path" \
      -X "$method" --data "$data" "$BASE_URL$path") || code=000
  else
    code=$(curl -sS -o "$OUT_DIR/respons-web" -w '%{http_code}' \
      -b "${COOKIE[$key]}" -X "$method" "$BASE_URL$path") || code=000
  fi
  laporkan "$label" "$expected" "$code"
}

uji_api() {
  local label="$1" key="$2" method="$3" path="$4" expected="$5"
  local data="${6:-}" file="${7:-}" code args=()

  args=(-sS -o "$OUT_DIR/respons-api" -w '%{http_code}'
        -H 'Accept: application/json'
        -H "Authorization: Bearer ${TOKEN[$key]}")

  if [[ -n "$file" ]]; then
    args+=(-F "file=@$file")
  elif [[ -n "$data" ]]; then
    args+=(-H 'Content-Type: application/json' --data "$data")
  fi

  case "$method" in
    GET)    code=$(curl "${args[@]}" "$BASE_URL$path") || code=000 ;;
    POST)   code=$(curl "${args[@]}" -X POST "$BASE_URL$path") || code=000 ;;
    PUT)    code=$(curl "${args[@]}" -X PUT "$BASE_URL$path") || code=000 ;;
    PATCH)  code=$(curl "${args[@]}" -X PATCH "$BASE_URL$path") || code=000 ;;
    DELETE) code=$(curl "${args[@]}" -X DELETE "$BASE_URL$path") || code=000 ;;
    *) printf 'GALAT INTERNAL: metode HTTP tidak didukung: %s\n' "$method" >&2; exit 2 ;;
  esac

  laporkan "$label" "$expected" "$code"
}

# Login seluruh tiga akun demo di API dan web.
login_api admin "$ADMIN_EMAIL"
login_api dosen "$DOSEN_EMAIL"
login_api mahasiswa "$MAHASISWA_EMAIL"

login_web admin "$ADMIN_NIM_NIP"
login_web dosen "$DOSEN_NIM_NIP"
login_web mahasiswa "$MAHASISWA_NIM_NIP"

echo
echo '=== WEB: akses halaman berdasarkan peran ==='
uji_web 'Halaman daftar pengguna — admin' admin GET '/admin/users' 200
uji_web 'Halaman daftar pengguna — dosen ditolak' dosen GET '/admin/users' 403
uji_web 'Halaman daftar pengguna — mahasiswa ditolak' mahasiswa GET '/admin/users' 403
uji_web 'Detail mata kuliah milik dosen — dosen pengampu' dosen GET "/dosen/mata-kuliah/$COURSE_ID" 200
uji_web 'Detail mata kuliah dosen lain — dosen bukan pengampu [RISIKO TINGGI]' dosen GET "/dosen/mata-kuliah/$OTHER_COURSE_ID" 403
uji_web 'Detail mata kuliah — mahasiswa yang terdaftar' mahasiswa GET "/mahasiswa/mata-kuliah/$COURSE_ID" 200
uji_web 'Detail mata kuliah yang tidak diikuti mahasiswa [RISIKO TINGGI]' mahasiswa GET "/mahasiswa/mata-kuliah/$OTHER_COURSE_ID" 403
uji_web 'Unduh submission mahasiswa lain — mahasiswa [RISIKO TINGGI]' mahasiswa GET "/pengumpulan/$OTHER_SUBMISSION_ID/unduh" 403
uji_web 'Unduh submission di luar mata kuliah yang diampu — dosen [RISIKO TINGGI]' dosen GET "/pengumpulan/$FOREIGN_SUBMISSION_ID/unduh" 403
uji_web 'Halaman edit pengguna — admin' admin GET "/admin/users/$USER_TARGET_ID/edit" 200
uji_web 'Halaman edit pengguna — dosen ditolak' dosen GET "/admin/users/$USER_TARGET_ID/edit" 403
uji_web 'Halaman edit pengguna — mahasiswa ditolak' mahasiswa GET "/admin/users/$USER_TARGET_ID/edit" 403

echo
echo '=== WEB: percobaan pengubahan role ==='
uji_web 'Dosen mencoba mengubah role pengguna menjadi admin [RISIKO TINGGI]' dosen PATCH \
  "/admin/users/$USER_TARGET_ID" 403 \
  "_token=${CSRF[dosen]}&name=Pengguna%20Uji&email=pengguna-uji%40example.invalid&nim_nip=UJIOTORISASI&role=admin"

uji_web 'Mahasiswa mencoba mengubah role pengguna menjadi admin [RISIKO TINGGI]' mahasiswa PATCH \
  "/admin/users/$USER_TARGET_ID" 403 \
  "_token=${CSRF[mahasiswa]}&name=Pengguna%20Uji&email=pengguna-uji%40example.invalid&nim_nip=UJIOTORISASI&role=admin"

# UpdateUserRequest mengharuskan role admin ketika admin mengedit akunnya sendiri.
# Validasi gagal akan redirect (302); sesudahnya API /me harus tetap menunjukkan admin.
uji_web 'Admin mencoba menurunkan role akunnya sendiri — validasi menolak' admin PATCH \
  "/admin/users/$ADMIN_ID" 302 \
  "_token=${CSRF[admin]}&name=Admin%20KampusLMS&email=admin%40kampuslms.test&nim_nip=$ADMIN_NIM_NIP&role=mahasiswa"

code=$(curl -sS -o "$OUT_DIR/admin-me.json" -w '%{http_code}' \
  -H 'Accept: application/json' -H "Authorization: Bearer ${TOKEN[admin]}" \
  "$BASE_URL/api/v1/me") || code=000
laporkan 'API /me tetap dapat diakses admin setelah percobaan perubahan role' 200 "$code"
if grep -Eq '"role"[[:space:]]*:[[:space:]]*"admin"' "$OUT_DIR/admin-me.json"; then
  laporkan 'Role admin tidak berubah setelah percobaan menurunkan role [RISIKO TINGGI]' admin admin
else
  laporkan 'Role admin tidak berubah setelah percobaan menurunkan role [RISIKO TINGGI]' admin bukan-admin
fi

echo
echo '=== API: autentikasi, mata kuliah, tugas, materi, dan submission ==='
code=$(curl -sS -o "$OUT_DIR/tanpa-token.json" -w '%{http_code}' \
  -H 'Accept: application/json' "$BASE_URL/api/v1/me") || code=000
laporkan 'GET /api/v1/me tanpa token' 401 "$code"

uji_api 'Detail mata kuliah — admin' admin GET "/api/v1/courses/$COURSE_ID" 200
uji_api 'Detail mata kuliah — dosen pengampu' dosen GET "/api/v1/courses/$COURSE_ID" 200
uji_api 'Detail mata kuliah dosen lain — dosen bukan pengampu [RISIKO TINGGI]' dosen GET "/api/v1/courses/$OTHER_COURSE_ID" 403
uji_api 'Detail mata kuliah — mahasiswa terdaftar' mahasiswa GET "/api/v1/courses/$COURSE_ID" 200
uji_api 'Detail mata kuliah yang tidak diikuti — mahasiswa [RISIKO TINGGI]' mahasiswa GET "/api/v1/courses/$OTHER_COURSE_ID" 403

uji_api 'Daftar tugas mata kuliah — admin' admin GET "/api/v1/courses/$COURSE_ID/assignments" 200
uji_api 'Daftar tugas mata kuliah — dosen pengampu' dosen GET "/api/v1/courses/$COURSE_ID/assignments" 200
uji_api 'Daftar tugas mata kuliah — mahasiswa terdaftar' mahasiswa GET "/api/v1/courses/$COURSE_ID/assignments" 200
uji_api 'Daftar tugas mata kuliah yang tidak diikuti — mahasiswa [RISIKO TINGGI]' mahasiswa GET "/api/v1/courses/$OTHER_COURSE_ID/assignments" 403

uji_api 'Daftar materi mata kuliah — admin' admin GET "/api/v1/courses/$COURSE_ID/materials" 200
uji_api 'Daftar materi mata kuliah — dosen pengampu' dosen GET "/api/v1/courses/$COURSE_ID/materials" 200
uji_api 'Daftar materi mata kuliah — mahasiswa terdaftar' mahasiswa GET "/api/v1/courses/$COURSE_ID/materials" 200
uji_api 'Daftar materi mata kuliah yang tidak diikuti — mahasiswa [RISIKO TINGGI]' mahasiswa GET "/api/v1/courses/$OTHER_COURSE_ID/materials" 403

uji_api 'Daftar submission tugas — admin' admin GET "/api/v1/assignments/$ASSIGNMENT_ID/submissions" 200
uji_api 'Daftar submission tugas — dosen pengampu' dosen GET "/api/v1/assignments/$ASSIGNMENT_ID/submissions" 200
uji_api 'Daftar submission tugas — mahasiswa terdaftar' mahasiswa GET "/api/v1/assignments/$ASSIGNMENT_ID/submissions" 200
uji_api 'Daftar submission tugas pada mata kuliah lain — dosen [RISIKO TINGGI]' dosen GET "/api/v1/assignments/$OTHER_ASSIGNMENT_ID/submissions" 403
uji_api 'Daftar submission tugas pada mata kuliah yang tidak diikuti — mahasiswa [RISIKO TINGGI]' mahasiswa GET "/api/v1/assignments/$OTHER_ASSIGNMENT_ID/submissions" 403

# Pastikan daftar submission mahasiswa tidak membocorkan submission mahasiswa lain.
code=$(curl -sS -o "$OUT_DIR/submission-mahasiswa.json" -w '%{http_code}' \
  -H 'Accept: application/json' -H "Authorization: Bearer ${TOKEN[mahasiswa]}" \
  "$BASE_URL/api/v1/assignments/$ASSIGNMENT_ID/submissions") || code=000
laporkan 'Status daftar submission milik mahasiswa' 200 "$code"
if grep -Eq "\"id\"[[:space:]]*:[[:space:]]*$OTHER_SUBMISSION_ID([,}])" "$OUT_DIR/submission-mahasiswa.json"; then
  laporkan 'Daftar submission tidak boleh memuat ID submission mahasiswa lain [RISIKO TINGGI]' tidak-bocor bocor
else
  laporkan 'Daftar submission tidak boleh memuat ID submission mahasiswa lain [RISIKO TINGGI]' tidak-bocor tidak-bocor
fi

# Endpoint menerima multipart field "file". File uji kecil dipakai untuk memastikan
# penolakan berasal dari otorisasi, bukan karena tidak adanya field file.
printf 'berkas uji otorisasi\n' > "$OUT_DIR/berkas-uji.txt"
uji_api 'Mahasiswa tidak boleh mengumpulkan tugas mata kuliah yang tidak diikuti [RISIKO TINGGI]' \
  mahasiswa POST "/api/v1/assignments/$OTHER_ASSIGNMENT_ID/submissions" 403 '' "$OUT_DIR/berkas-uji.txt"
uji_api 'Mahasiswa tidak boleh mengumpulkan tugas berstatus draft [RISIKO TINGGI]' \
  mahasiswa POST "/api/v1/assignments/$DRAFT_ASSIGNMENT_ID/submissions" 403 '' "$OUT_DIR/berkas-uji.txt"

uji_api 'Dosen bukan pengampu tidak boleh memberi nilai [RISIKO TINGGI]' dosen PUT \
  "/api/v1/submissions/$FOREIGN_SUBMISSION_ID/grade" 403 '{"score":90,"feedback":"Uji otorisasi"}'
uji_api 'Mahasiswa tidak boleh memberi atau mengubah nilai' mahasiswa PUT \
  "/api/v1/submissions/$FOREIGN_SUBMISSION_ID/grade" 403 '{"score":90,"feedback":"Uji otorisasi"}'

echo
echo '=== RINGKASAN HASIL ==='
printf 'LULUS=%d GAGAL=%d\n' "$PASS" "$FAIL"
if ((FAIL > 0)); then
  printf 'Pemeriksaan yang gagal:\n'
  printf ' - %s\n' "${FAILURES[@]}"
  exit 1
fi

echo 'Semua pemeriksaan yang dikonfigurasi lulus.'