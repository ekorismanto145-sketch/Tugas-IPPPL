<?php
require_once 'auth.php';
require_login();
$message = '';
$role = current_role();
$is_admin_user = is_admin();
$table = $is_admin_user ? 'pemilik' : 'karyawan';
$id_field = $is_admin_user ? 'id_pemilik' : 'id_karyawan';
$id = (int) $_SESSION['user_id'];
$stmt = mysqli_prepare($koneksi, "SELECT nama, username, foto_profil FROM {$table} WHERE {$id_field} = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$profile = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: ['nama' => $_SESSION['nama'] ?? '', 'username' => '', 'foto_profil' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $photo_path = $profile['foto_profil'] ?? '';
    if (!empty($_FILES['foto_profil']['name'])) {
        $allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime_type = mime_content_type($_FILES['foto_profil']['tmp_name']);
        if ($_FILES['foto_profil']['error'] !== UPLOAD_ERR_OK || !isset($allowed_types[$mime_type]) || $_FILES['foto_profil']['size'] > 2 * 1024 * 1024) {
            $message = 'Foto harus JPG, PNG, atau WEBP dengan ukuran maksimal 2 MB.';
        } else {
            $upload_dir = __DIR__ . '/uploads/profiles';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $filename = $table . '_' . $id . '_' . bin2hex(random_bytes(6)) . '.' . $allowed_types[$mime_type];
            if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $upload_dir . '/' . $filename)) $photo_path = 'uploads/profiles/' . $filename;
            else $message = 'Foto profil gagal diunggah.';
        }
    }
    if ($message === '' && ($nama === '' || $username === '')) {
        $message = 'Nama dan username wajib diisi.';
    } elseif ($message === '') {
        $stmt = mysqli_prepare($koneksi, "UPDATE {$table} SET nama = ?, username = ?, foto_profil = ? WHERE {$id_field} = ?");
        mysqli_stmt_bind_param($stmt, 'sssi', $nama, $username, $photo_path, $id);
        if (mysqli_stmt_execute($stmt)) { $_SESSION['nama'] = $nama; $profile = ['nama' => $nama, 'username' => $username, 'foto_profil' => $photo_path]; $message = 'Profil berhasil diperbarui.'; }
        else $message = 'Profil gagal diperbarui. Username mungkin sudah digunakan.';
    }
}
require 'app_layout.php'; app_start('Profil', 'profile');
?>
<section class="surface mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"><p class="text-sm font-bold uppercase tracking-widest text-cyan-700">Pengaturan akun</p><h1 class="mt-2 text-3xl font-black">Profil <?= e($role); ?></h1><p class="muted mt-2 text-sm">Ganti foto, nama tampilan, dan username akun Anda.</p><?php if ($message): ?><div class="mt-5 rounded-lg bg-emerald-100 p-3 text-sm font-bold text-emerald-700"><?= e($message); ?></div><?php endif; ?><form method="POST" enctype="multipart/form-data" class="mt-6 space-y-4"><div class="flex items-center gap-4"><?php if (!empty($profile['foto_profil'])): ?><img src="<?= e($profile['foto_profil']); ?>" alt="Foto profil" class="h-20 w-20 rounded-full object-cover ring-4 ring-cyan-100"><?php else: ?><div class="flex h-20 w-20 items-center justify-center rounded-full bg-cyan-100 text-2xl font-black text-cyan-700"><?= e(strtoupper(substr($profile['nama'], 0, 1))); ?></div><?php endif; ?><div class="flex-1"><label class="mb-1 block text-sm font-bold">Foto profil</label><input type="file" name="foto_profil" accept="image/jpeg,image/png,image/webp" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></div></div><div><label class="mb-1 block text-sm font-bold">Nama</label><input name="nama" value="<?= e($profile['nama']); ?>" required class="w-full rounded-lg border border-slate-200 px-3 py-3"></div><div><label class="mb-1 block text-sm font-bold">Username</label><input name="username" value="<?= e($profile['username']); ?>" required class="w-full rounded-lg border border-slate-200 px-3 py-3"></div><button class="w-full rounded-lg bg-cyan-500 py-3 font-black text-slate-950 hover:bg-cyan-400">Simpan perubahan</button></form></section>
<?php app_end(); ?>
