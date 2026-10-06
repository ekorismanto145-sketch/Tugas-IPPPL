<?php
require_once 'app_layout.php';
require_admin();

$message = '';
$error = '';
$active_tab = $_GET['tab'] ?? 'staf';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action 1: Tambah Staf Baru
    if ($action === 'add_staf') {
        $nama = trim($_POST['nama'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] === 'Admin' ? 'Admin' : 'Staff';
        $no_hp = trim($_POST['no_hp'] ?? '');

        if ($nama === '' || $username === '' || $password === '') {
            $error = 'Nama, username, dan password staf wajib diisi!';
        } else {
            $check_stmt = mysqli_prepare($koneksi, "SELECT username FROM users WHERE username = ? UNION SELECT username FROM pelanggan WHERE username = ? LIMIT 1");
            mysqli_stmt_bind_param($check_stmt, 'ss', $username, $username);
            mysqli_stmt_execute($check_stmt);
            if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) > 0) {
                $error = 'Username sudah digunakan, pilih username lain.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins_stmt = mysqli_prepare($koneksi, "INSERT INTO users (nama, username, password, role, no_hp) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($ins_stmt, 'sssss', $nama, $username, $hash, $role, $no_hp);
                if (mysqli_stmt_execute($ins_stmt)) {
                    $message = 'Akun staf baru berhasil ditambahkan!';
                    $active_tab = 'staf';
                } else {
                    $error = 'Gagal menambahkan staf.';
                }
            }
        }
    }

    // Action 2: Tambah Pelanggan Baru
    elseif ($action === 'add_pelanggan') {
        $nama = trim($_POST['nama'] ?? '');
        $no_hp = trim($_POST['no_hp'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($nama === '') {
            $error = 'Nama pelanggan wajib diisi!';
        } else {
            if ($username !== '') {
                $check_stmt = mysqli_prepare($koneksi, "SELECT username FROM users WHERE username = ? UNION SELECT username FROM pelanggan WHERE username = ? LIMIT 1");
                mysqli_stmt_bind_param($check_stmt, 'ss', $username, $username);
                mysqli_stmt_execute($check_stmt);
                if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) > 0) {
                    $error = 'Username pelanggan sudah digunakan.';
                }
            }

            if (!$error) {
                $hash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;
                $user_val = $username !== '' ? $username : null;
                $ins_stmt = mysqli_prepare($koneksi, "INSERT INTO pelanggan (nama, username, password, no_hp, alamat) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($ins_stmt, 'sssss', $nama, $user_val, $hash, $no_hp, $alamat);
                if (mysqli_stmt_execute($ins_stmt)) {
                    $message = 'Data pelanggan berhasil ditambahkan!';
                    $active_tab = 'pelanggan';
                } else {
                    $error = 'Gagal menambahkan data pelanggan.';
                }
            }
        }
    }

    // Action 3: Status Staf (Aktif/Nonaktif)
    elseif ($action === 'status_staf') {
        $id_user = (int) ($_POST['id_user'] ?? 0);
        $status = ($_POST['status'] ?? '') === 'Aktif' ? 'Aktif' : 'Nonaktif';
        if ($id_user > 0) {
            $upd = mysqli_prepare($koneksi, "UPDATE users SET status = ? WHERE id_user = ?");
            mysqli_stmt_bind_param($upd, 'si', $status, $id_user);
            mysqli_stmt_execute($upd);
            $message = 'Status akun staf diperbarui.';
            $active_tab = 'staf';
        }
    }

    // Action 4: Adjust Poin Pelanggan
    elseif ($action === 'adjust_poin') {
        $id_pelanggan = (int) ($_POST['id_pelanggan'] ?? 0);
        $poin = (int) ($_POST['poin'] ?? 0);
        if ($id_pelanggan > 0) {
            $upd = mysqli_prepare($koneksi, "UPDATE pelanggan SET poin_bonus = ? WHERE id_pelanggan = ?");
            mysqli_stmt_bind_param($upd, 'ii', $poin, $id_pelanggan);
            mysqli_stmt_execute($upd);

            // Log poin
            $log = mysqli_prepare($koneksi, "INSERT INTO riwayat_poin (id_pelanggan, tipe, jumlah_poin, keterangan) VALUES (?, 'Masuk', ?, 'Penyesuaian Manual oleh Admin')");
            mysqli_stmt_bind_param($log, 'ii', $id_pelanggan, $poin);
            mysqli_stmt_execute($log);

            $message = 'Jumlah poin pelanggan berhasil disesuaikan.';
            $active_tab = 'pelanggan';
        }
    }
}

// Fetch Staf & Pelanggan
$staf_query = mysqli_query($koneksi, "SELECT id_user, nama, username, role, no_hp, status, created_at FROM users ORDER BY nama ASC");
$pelanggan_query = mysqli_query($koneksi, "SELECT id_pelanggan, nama, username, no_hp, alamat, poin_bonus, created_at FROM pelanggan ORDER BY nama ASC");

app_start('Kelola Staf & Pelanggan', 'employees');
?>

<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <p class="text-xs font-black uppercase tracking-widest text-cyan-600">Admin Management</p>
        <h1 class="mt-1 text-3xl font-black text-slate-900 dark:text-white">Kelola Staf & Pelanggan</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manajemen terpisah untuk akun Staf internal dan Member Pelanggan.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <button onclick="openModal('modal-staf')" class="rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-black text-slate-950 shadow-md transition hover:bg-cyan-400">
            + Tambah Staf Baru
        </button>
        <button onclick="openModal('modal-pelanggan')" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-black text-white shadow-md transition hover:bg-indigo-500">
            + Tambah Pelanggan Baru
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="mt-5 rounded-xl border-l-4 border-emerald-500 bg-emerald-50 p-4 text-sm font-bold text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
        ✅ <?= e($message); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="mt-5 rounded-xl border-l-4 border-rose-500 bg-rose-50 p-4 text-sm font-bold text-rose-800 dark:bg-rose-950/40 dark:text-rose-300">
        ⚠️ <?= e($error); ?>
    </div>
<?php endif; ?>

<!-- TAB NAVIGASI -->
<div class="mt-8 flex border-b border-slate-200 dark:border-slate-700">
    <button onclick="switchTab('staf')" id="btn-tab-staf" class="tab-btn px-6 py-3 font-bold text-sm border-b-2 transition <?= $active_tab === 'staf' ? 'border-cyan-500 text-cyan-600 dark:text-cyan-400 font-black' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400' ?>">
        👨‍💼 Data Staf Internal (<?= mysqli_num_rows($staf_query); ?>)
    </button>
    <button onclick="switchTab('pelanggan')" id="btn-tab-pelanggan" class="tab-btn px-6 py-3 font-bold text-sm border-b-2 transition <?= $active_tab === 'pelanggan' ? 'border-cyan-500 text-cyan-600 dark:text-cyan-400 font-black' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400' ?>">
        👥 Data Pelanggan / Member (<?= mysqli_num_rows($pelanggan_query); ?>)
    </button>
</div>

<!-- SECTION STAF -->
<div id="section-staf" class="mt-6 <?= $active_tab === 'staf' ? '' : 'hidden' ?>">
    <div class="rounded-2xl bg-white p-5 shadow-sm dark:bg-slate-800">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-900 text-xs uppercase tracking-wider text-white dark:bg-slate-950">
                    <tr>
                        <th class="px-5 py-4">Nama Staf</th>
                        <th class="px-5 py-4">Username Login</th>
                        <th class="px-5 py-4">Role Akses</th>
                        <th class="px-5 py-4">Status Akun</th>
                        <th class="px-5 py-4 text-center">Aksi Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    <?php while ($staf = mysqli_fetch_assoc($staf_query)): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                            <td class="px-5 py-4 font-bold text-slate-900 dark:text-white">
                                <?= e($staf['nama']); ?>
                                <div class="text-xs text-slate-400 font-normal"><?= e($staf['no_hp'] ?: '-'); ?></div>
                            </td>
                            <td class="px-5 py-4 font-mono text-cyan-600 font-semibold dark:text-cyan-400"><?= e($staf['username']); ?></td>
                            <td class="px-5 py-4">
                                <span class="rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                    <?= e($staf['role']); ?>
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <span class="rounded-full px-3 py-1 text-xs font-bold <?= $staf['status'] === 'Aktif' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-400'; ?>">
                                    <?= e($staf['status']); ?>
                                </span>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <form method="POST" class="inline-flex items-center gap-2">
                                    <input type="hidden" name="action" value="status_staf">
                                    <input type="hidden" name="id_user" value="<?= (int)$staf['id_user']; ?>">
                                    <select name="status" class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-bold dark:bg-slate-900">
                                        <option value="Aktif" <?= $staf['status'] === 'Aktif' ? 'selected' : ''; ?>>Aktif</option>
                                        <option value="Nonaktif" <?= $staf['status'] === 'Nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                                    </select>
                                    <button class="rounded-lg bg-indigo-600 px-3 py-1 text-xs font-bold text-white hover:bg-indigo-500">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- SECTION PELANGGAN -->
<div id="section-pelanggan" class="mt-6 <?= $active_tab === 'pelanggan' ? '' : 'hidden' ?>">
    <div class="rounded-2xl bg-white p-5 shadow-sm dark:bg-slate-800">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-900 text-xs uppercase tracking-wider text-white dark:bg-slate-950">
                    <tr>
                        <th class="px-5 py-4">Nama Pelanggan</th>
                        <th class="px-5 py-4">Username</th>
                        <th class="px-5 py-4">No. HP</th>
                        <th class="px-5 py-4">Alamat</th>
                        <th class="px-5 py-4 text-center">Poin Bonus</th>
                        <th class="px-5 py-4 text-center">Adjust Poin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    <?php while ($cust = mysqli_fetch_assoc($pelanggan_query)): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                            <td class="px-5 py-4 font-bold text-slate-900 dark:text-white">
                                <span class="inline-block rounded-md bg-cyan-100 px-2 py-0.5 text-xs font-black text-cyan-800 dark:bg-cyan-950 dark:text-cyan-300 mr-1"><?= format_pelanggan_id((int)$cust['id_pelanggan']); ?></span>
                                <?= e($cust['nama']); ?>
                            </td>
                            <td class="px-5 py-4 font-mono text-xs text-indigo-600 dark:text-indigo-400">
                                <?= $cust['username'] ? e($cust['username']) : '<span class="italic text-slate-400">Belum set</span>'; ?>
                            </td>
                            <td class="px-5 py-4 text-xs font-bold">
                                <?php if ($wa = format_wa_phone($cust['no_hp'])): ?>
                                    <a href="https://wa.me/<?= $wa; ?>" target="_blank" class="inline-flex items-center gap-1 rounded-lg bg-emerald-100 px-2 py-1 text-emerald-800 hover:bg-emerald-200 dark:bg-emerald-950 dark:text-emerald-300">
                                        💬 <?= e($cust['no_hp']); ?>
                                    </a>
                                <?php else: ?>
                                    <?= e($cust['no_hp'] ?: '-'); ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-xs"><?= e($cust['alamat'] ?: '-'); ?></td>
                            <td class="px-5 py-4 text-center font-bold text-cyan-600 dark:text-cyan-400">⭐ <?= (int)$cust['poin_bonus']; ?> Poin</td>
                            <td class="px-5 py-4 text-center">
                                <form method="POST" class="inline-flex items-center gap-1">
                                    <input type="hidden" name="action" value="adjust_poin">
                                    <input type="hidden" name="id_pelanggan" value="<?= (int)$cust['id_pelanggan']; ?>">
                                    <input type="number" name="poin" value="<?= (int)$cust['poin_bonus']; ?>" min="0" class="w-20 rounded-lg border border-slate-200 px-2 py-1 text-xs text-center font-bold dark:bg-slate-900">
                                    <button class="rounded-lg bg-cyan-500 px-2.5 py-1 text-xs font-black text-slate-950 hover:bg-cyan-400">Set</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH STAF -->
<div id="modal-staf" class="fixed inset-0 z-50 flex hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-800">
        <div class="flex items-center justify-between border-b pb-3 dark:border-slate-700">
            <h3 class="text-lg font-black text-slate-900 dark:text-white">➕ Tambah Staf Baru</h3>
            <button onclick="closeModal('modal-staf')" class="text-xl text-slate-400 hover:text-slate-600">&times;</button>
        </div>
        <form method="POST" class="mt-4 space-y-4">
            <input type="hidden" name="action" value="add_staf">
            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Nama Lengkap</label>
                <input type="text" name="nama" required class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm dark:bg-slate-900" placeholder="Budi Santoso">
            </div>
            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Username Login</label>
                <input type="text" name="username" required class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm dark:bg-slate-900" placeholder="budi_kasir">
            </div>
            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Password</label>
                <input type="password" name="password" required minlength="6" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm dark:bg-slate-900" placeholder="Minimal 6 karakter">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Role Akses</label>
                    <select name="role" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm dark:bg-slate-900">
                        <option value="Staff">Staff (Kasir / Kurir)</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">No. HP</label>
                    <input type="text" name="no_hp" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm dark:bg-slate-900" placeholder="08xxxxxxxx">
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeModal('modal-staf')" class="rounded-xl bg-slate-200 px-4 py-2 text-sm font-bold text-slate-700 dark:bg-slate-700 dark:text-slate-300">Batal</button>
                <button type="submit" class="rounded-xl bg-cyan-500 px-4 py-2 text-sm font-black text-slate-950 hover:bg-cyan-400">Simpan Staf</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL TAMBAH PELANGGAN -->
<div id="modal-pelanggan" class="fixed inset-0 z-50 flex hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-800">
        <div class="flex items-center justify-between border-b pb-3 dark:border-slate-700">
            <h3 class="text-lg font-black text-slate-900 dark:text-white">➕ Tambah Pelanggan Baru</h3>
            <button onclick="closeModal('modal-pelanggan')" class="text-xl text-slate-400 hover:text-slate-600">&times;</button>
        </div>
        <form method="POST" class="mt-4 space-y-4">
            <input type="hidden" name="action" value="add_pelanggan">
            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Nama Pelanggan</label>
                <input type="text" name="nama" required class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm dark:bg-slate-900" placeholder="Nama Pelanggan">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Username (Opsional)</label>
                    <input type="text" name="username" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm dark:bg-slate-900" placeholder="Untuk login app">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Password (Opsional)</label>
                    <input type="password" name="password" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm dark:bg-slate-900" placeholder="Password login">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">No. HP / WhatsApp</label>
                <input type="text" name="no_hp" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm dark:bg-slate-900" placeholder="08xxxxxxxx">
            </div>
            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-600 dark:text-slate-400">Alamat Lengkap</label>
                <textarea name="alamat" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm dark:bg-slate-900" placeholder="Alamat penjemputan/pengantaran"></textarea>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeModal('modal-pelanggan')" class="rounded-xl bg-slate-200 px-4 py-2 text-sm font-bold text-slate-700 dark:bg-slate-700 dark:text-slate-300">Batal</button>
                <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-black text-white hover:bg-indigo-500">Simpan Pelanggan</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(tab) {
    const secStaf = document.getElementById('section-staf');
    const secPelanggan = document.getElementById('section-pelanggan');
    const btnStaf = document.getElementById('btn-tab-staf');
    const btnPelanggan = document.getElementById('btn-tab-pelanggan');

    if (tab === 'staf') {
        secStaf.classList.remove('hidden');
        secPelanggan.classList.add('hidden');
        btnStaf.className = 'tab-btn px-6 py-3 font-black text-sm border-b-2 border-cyan-500 text-cyan-600 dark:text-cyan-400 transition';
        btnPelanggan.className = 'tab-btn px-6 py-3 font-bold text-sm border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 transition';
    } else {
        secPelanggan.classList.remove('hidden');
        secStaf.classList.add('hidden');
        btnPelanggan.className = 'tab-btn px-6 py-3 font-black text-sm border-b-2 border-cyan-500 text-cyan-600 dark:text-cyan-400 transition';
        btnStaf.className = 'tab-btn px-6 py-3 font-bold text-sm border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 transition';
    }
}
function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
</script>

<?php app_end(); ?>
