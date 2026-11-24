
document.addEventListener('DOMContentLoaded', function () {
    // ==========================================
    // 1. Penyimpanan pilihan mahasiswa
    // ==========================================
    const picker = document.getElementById('mk-student-picker');
    const enrollForm = document.getElementById('mk-enroll-form');
    const searchForm = document.getElementById('mk-student-search-form');

    if (picker && enrollForm && searchForm) {
        const storageKey = 'selected-students-' + picker.dataset.courseId;

        let selectedIds = new Set(
            JSON.parse(sessionStorage.getItem(storageKey) || '[]').map(String)
        );

        const oldSelectedIds = JSON.parse(
            picker.dataset.oldSelected || '[]'
        );

        oldSelectedIds.forEach(id => selectedIds.add(id));

        function saveSelectedIds() {
            sessionStorage.setItem(
                storageKey,
                JSON.stringify([...selectedIds])
            );
        }

        picker.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            checkbox.checked = selectedIds.has(checkbox.value);

            checkbox.addEventListener('change', function () {
                if (this.checked) {
                    selectedIds.add(this.value);
                } else {
                    selectedIds.delete(this.value);
                }

                saveSelectedIds();
            });
        });

        searchForm.addEventListener('submit', saveSelectedIds);

        enrollForm.addEventListener('submit', function () {
            picker.querySelectorAll('input[type="checkbox"]:checked')
                .forEach(checkbox => selectedIds.add(checkbox.value));

            selectedIds.forEach(id => {
                const hiddenInput = document.createElement('input');

                hiddenInput.type = 'hidden';
                hiddenInput.name = 'student_ids[]';
                hiddenInput.value = id;

                enrollForm.appendChild(hiddenInput);
            });

            sessionStorage.removeItem(storageKey);
        });
    }

    // ==========================================
    // 2. Pencarian otomatis mahasiswa
    // ==========================================
    const searchInput = document.getElementById('mk-student-filter');
    const studentSearchForm = document.getElementById('mk-student-search-form');

    if (searchInput && studentSearchForm) {
        let searchTimeout;

        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);

            searchTimeout = setTimeout(function () {
                studentSearchForm.requestSubmit();
            }, 400);
        });
    }
});
