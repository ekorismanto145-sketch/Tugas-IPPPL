<?php
require_once 'auth.php';
require_login();

$message = '';
$error_message = '';
$role = current_role();
$is_user_role = is_user();
$id = (int) $_SESSION['user_id'];

// Pastikan kolom foto_profil ada di tabel pelanggan jika belum dibuat
if ($is_user_role) {
    $chk = mysqli_query($koneksi, "SHOW COLUMNS FROM pelanggan LIKE 'foto_profil'");
    if ($chk && mysqli_num_rows($chk) == 0) {
        @mysqli_query($koneksi, "ALTER TABLE pelanggan ADD COLUMN foto_profil VARCHAR(255) NULL AFTER alamat");
    }
}

// Ambil data profil berdasarkan role pengguna
if ($is_user_role) {
    $res = @mysqli_query($koneksi, "SELECT nama, username, no_hp, alamat, foto_profil FROM pelanggan WHERE id_pelanggan = {$id} LIMIT 1");
    if (!$res) {
        $res = @mysqli_query($koneksi, "SELECT nama, username, no_hp, alamat FROM pelanggan WHERE id_pelanggan = {$id} LIMIT 1");
    }
    $profile = $res ? (mysqli_fetch_assoc($res) ?: []) : [];
} else {
    $res = @mysqli_query($koneksi, "SELECT nama, username, no_hp, foto_profil FROM users WHERE id_user = {$id} LIMIT 1");
    $profile = $res ? (mysqli_fetch_assoc($res) ?: []) : [];
}

if (empty($profile)) {
    $profile = [
        'nama' => $_SESSION['nama'] ?? '',
        'username' => '',
        'no_hp' => '',
        'alamat' => '',
        'foto_profil' => ''
    ];
}

// Handling Form Submit Profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $password_baru = $_POST['password_baru'] ?? '';
    
    $photo_path = $profile['foto_profil'] ?? '';

    // Handle Upload Foto Profil
    if (!empty($_FILES['foto_profil']['name'])) {
        $allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $tmp_name = $_FILES['foto_profil']['tmp_name'];
        $mime_type = @mime_content_type($tmp_name);
        
        if ($_FILES['foto_profil']['error'] !== UPLOAD_ERR_OK || !isset($allowed_types[$mime_type]) || $_FILES['foto_profil']['size'] > 2 * 1024 * 1024) {
            $error_message = 'Foto harus format JPG, PNG, atau WEBP dengan ukuran maksimal 2 MB.';
        } else {
            $upload_dir = __DIR__ . '/uploads/profiles';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $prefix = $is_user_role ? 'pelanggan' : 'user';
            $filename = $prefix . '_' . $id . '_' . bin2hex(random_bytes(6)) . '.' . $allowed_types[$mime_type];
            if (move_uploaded_file($tmp_name, $upload_dir . '/' . $filename)) {
                $photo_path = 'uploads/profiles/' . $filename;
            } else {
                $error_message = 'Foto profil gagal diunggah ke server.';
            }
        }
    }

    if (empty($error_message) && ($nama === '' || $username === '')) {
        $error_message = 'Nama lengkap dan username wajib diisi.';
    } elseif (empty($error_message)) {
        // Cek keunikan username (tidak boleh sama dengan akun lain)
        if ($is_user_role) {
            $chk_stmt = mysqli_prepare($koneksi, "SELECT id_pelanggan FROM pelanggan WHERE username = ? AND id_pelanggan != ? LIMIT 1");
        } else {
            $chk_stmt = mysqli_prepare($koneksi, "SELECT id_user FROM users WHERE username = ? AND id_user != ? LIMIT 1");
        }
        
        if ($chk_stmt) {
            mysqli_stmt_bind_param($chk_stmt, 'si', $username, $id);
            mysqli_stmt_execute($chk_stmt);
            $dup = mysqli_fetch_assoc(mysqli_stmt_get_result($chk_stmt));
            mysqli_stmt_close($chk_stmt);
            if ($dup) {
                $error_message = 'Username sudah digunakan oleh akun lain.';
            }
        }
    }

    if (empty($error_message)) {
        if ($is_user_role) {
            $has_foto_col = @mysqli_query($koneksi, "SHOW COLUMNS FROM pelanggan LIKE 'foto_profil'");
            $can_foto = ($has_foto_col && mysqli_num_rows($has_foto_col) > 0);

            if (!empty($password_baru)) {
                $hash = password_hash($password_baru, PASSWORD_BCRYPT);
                if ($can_foto) {
                    $u_stmt = mysqli_prepare($koneksi, "UPDATE pelanggan SET nama = ?, username = ?, no_hp = ?, alamat = ?, foto_profil = ?, password = ? WHERE id_pelanggan = ?");
                    mysqli_stmt_bind_param($u_stmt, 'ssssssi', $nama, $username, $no_hp, $alamat, $photo_path, $hash, $id);
                } else {
                    $u_stmt = mysqli_prepare($koneksi, "UPDATE pelanggan SET nama = ?, username = ?, no_hp = ?, alamat = ?, password = ? WHERE id_pelanggan = ?");
                    mysqli_stmt_bind_param($u_stmt, 'sssssi', $nama, $username, $no_hp, $alamat, $hash, $id);
                }
            } else {
                if ($can_foto) {
                    $u_stmt = mysqli_prepare($koneksi, "UPDATE pelanggan SET nama = ?, username = ?, no_hp = ?, alamat = ?, foto_profil = ? WHERE id_pelanggan = ?");
                    mysqli_stmt_bind_param($u_stmt, 'sssssi', $nama, $username, $no_hp, $alamat, $photo_path, $id);
                } else {
                    $u_stmt = mysqli_prepare($koneksi, "UPDATE pelanggan SET nama = ?, username = ?, no_hp = ?, alamat = ? WHERE id_pelanggan = ?");
                    mysqli_stmt_bind_param($u_stmt, 'ssssi', $nama, $username, $no_hp, $alamat, $id);
                }
            }
        } else {
            if (!empty($password_baru)) {
                $hash = password_hash($password_baru, PASSWORD_BCRYPT);
                $u_stmt = mysqli_prepare($koneksi, "UPDATE users SET nama = ?, username = ?, no_hp = ?, foto_profil = ?, password = ? WHERE id_user = ?");
                mysqli_stmt_bind_param($u_stmt, 'sssssi', $nama, $username, $no_hp, $photo_path, $hash, $id);
            } else {
                $u_stmt = mysqli_prepare($koneksi, "UPDATE users SET nama = ?, username = ?, no_hp = ?, foto_profil = ? WHERE id_user = ?");
                mysqli_stmt_bind_param($u_stmt, 'ssssi', $nama, $username, $no_hp, $photo_path, $id);
            }
        }

        if ($u_stmt && mysqli_stmt_execute($u_stmt)) {
            $_SESSION['nama'] = $nama;
            $profile['nama'] = $nama;
            $profile['username'] = $username;
            $profile['no_hp'] = $no_hp;
            if ($is_user_role) $profile['alamat'] = $alamat;
            $profile['foto_profil'] = $photo_path;
            $message = 'Profil berhasil diperbarui.';
            mysqli_stmt_close($u_stmt);
        } else {
            $error_message = 'Gagal memperbarui data profil.';
        }
    }
}

require 'app_layout.php'; app_start('Kelola Profil', 'profile');
?>

<section class="surface mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-widest text-cyan-600">Pengaturan Akun</p>
            <h1 class="mt-1 text-2xl font-black text-slate-900 sm:text-3xl">Profil <?= e($role); ?></h1>
            <p class="muted mt-1 text-sm">Kelola informasi diri, foto profil, dan kata sandi akun Anda.</p>
        </div>
        <span class="rounded-full bg-cyan-100 px-3 py-1 text-xs font-bold text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200"><?= e($role); ?></span>
    </div>

    <?php if ($message): ?>
        <div class="mt-5 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-sm font-bold text-emerald-700 dark:bg-emerald-950 dark:border-emerald-800 dark:text-emerald-200">
            ✓ <?= e($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="mt-5 rounded-xl bg-red-50 border border-red-200 p-4 text-sm font-bold text-red-700 dark:bg-red-950 dark:border-red-800 dark:text-red-200">
            ⚠️ <?= e($error_message); ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="mt-6 space-y-5">
        <!-- Foto Profil & Upload -->
        <div class="flex flex-col items-center gap-4 rounded-xl border border-slate-100 bg-slate-50 p-4 sm:flex-row dark:border-slate-800 dark:bg-slate-900">
            <?php if (!empty($profile['foto_profil'])): ?>
                <img src="<?= e($profile['foto_profil']); ?>" alt="Foto profil" class="h-20 w-20 rounded-full object-cover ring-4 ring-cyan-400/30">
            <?php else: ?>
                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-cyan-500 text-2xl font-black text-slate-950 ring-4 ring-cyan-400/30">
                    <?= e(strtoupper(substr($profile['nama'] ?? 'U', 0, 1))); ?>
                </div>
            <?php endif; ?>
            
            <div class="flex-1 w-full">
                <label class="mb-1 block text-sm font-bold text-slate-800 dark:text-slate-200">Ganti Foto Profil</label>
                <input type="file" name="foto_profil" accept="image/jpeg,image/png,image/webp" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none focus:border-cyan-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300">
                <p class="mt-1 text-[11px] text-slate-500">Format: JPG, PNG, WEBP (Maksimal 2 MB)</p>
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-bold text-slate-800 dark:text-slate-200">Nama Lengkap</label>
            <input type="text" name="nama" value="<?= e($profile['nama']); ?>" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-cyan-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-bold text-slate-800 dark:text-slate-200">Username</label>
                <input type="text" name="username" value="<?= e($profile['username']); ?>" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-cyan-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
            </div>
            <div>
                <label class="mb-1 block text-sm font-bold text-slate-800 dark:text-slate-200">Nomor HP / WhatsApp</label>
                <input type="text" name="no_hp" value="<?= e($profile['no_hp'] ?? ''); ?>" placeholder="08xxxxxxxxxx" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-cyan-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
            </div>
        </div>

        <?php if ($is_user_role): ?>
            <div>
                <label class="mb-1 block text-sm font-bold text-slate-800 dark:text-slate-200">Alamat Lengkap</label>
                <textarea name="alamat" rows="2" placeholder="Masukkan alamat lengkap penjemputan/pengiriman..." class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-cyan-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"><?= e($profile['alamat'] ?? ''); ?></textarea>
            </div>
        <?php endif; ?>

        <div class="border-t border-slate-200 pt-4 dark:border-slate-800">
            <label class="mb-1 block text-sm font-bold text-slate-800 dark:text-slate-200">Password Baru <span class="text-xs font-normal text-slate-400">(Kosongkan jika tidak ingin mengganti)</span></label>
            <input type="password" name="password_baru" placeholder="••••••••" autocomplete="new-password" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-cyan-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
        </div>

        <button type="submit" class="w-full rounded-xl bg-cyan-500 py-3.5 text-sm font-black text-slate-950 transition hover:bg-cyan-400 shadow-md shadow-cyan-500/20">
            Simpan Perubahan
        </button>
    </form>
</section>

<?php app_end(); ?>
