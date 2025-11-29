<?php
/**
 * User Profile - Quick Count System
 */

require_once '../config/config.php';
requireLogin();

$pageTitle = 'Profil Saya';
$conn = getConnection();
$user = getCurrentUser();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = sanitize($_POST['nama_lengkap']);
    $email = sanitize($_POST['email'] ?? '');
    $telepon = sanitize($_POST['telepon'] ?? '');
    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    
    // Handle foto
    $foto = null;
    if (!empty($_FILES['foto']['name'])) {
        $result = uploadImage($_FILES['foto'], FOTO_USER_PATH, 300);
        if ($result['success']) {
            $foto = $result['filename'];
        }
    }
    
    // Update profile
    $sql = "UPDATE users SET nama_lengkap = ?, email = ?, telepon = ?";
    $params = [$nama_lengkap, $email, $telepon];
    $types = "sss";
    
    // Change password if provided
    if (!empty($password_baru)) {
        if (empty($password_lama) || !password_verify($password_lama, $user['password'])) {
            setFlash('error', 'Password lama tidak sesuai!');
            header('Location: profile.php');
            exit;
        }
        $sql .= ", password = ?";
        $params[] = password_hash($password_baru, PASSWORD_DEFAULT);
        $types .= "s";
    }
    
    if ($foto) {
        $sql .= ", foto = ?";
        $params[] = $foto;
        $types .= "s";
    }
    
    $sql .= " WHERE id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= "i";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        $_SESSION['user_name'] = $nama_lengkap;
        if ($foto) $_SESSION['user_foto'] = $foto;
        setFlash('success', 'Profil berhasil diupdate!');
    } else {
        setFlash('error', 'Gagal mengupdate profil!');
    }
    
    header('Location: profile.php');
    exit;
}

include '../includes/header.php';
?>

<div class="page-header">
    <h1>Profil Saya</h1>
    <p>Kelola informasi akun Anda</p>
</div>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-body text-center">
                <img src="../uploads/users/<?= htmlspecialchars($user['foto'] ?? 'default.png') ?>" 
                     class="rounded-circle mb-3 shadow" 
                     style="width: 150px; height: 150px; object-fit: cover;"
                     onerror="this.src='../assets/img/default.png'">
                <h4><?= htmlspecialchars($user['nama_lengkap']) ?></h4>
                <p class="text-muted">@<?= htmlspecialchars($user['username']) ?></p>
                <span class="badge bg-<?= $user['role'] == 'admin' ? 'danger' : ($user['role'] == 'operator' ? 'primary' : 'secondary') ?> mb-3">
                    <?= ucfirst($user['role']) ?>
                </span>
                
                <hr>
                
                <div class="text-start">
                    <?php if ($user['email']): ?>
                    <p class="mb-2">
                        <i class="bi bi-envelope me-2 text-muted"></i>
                        <?= htmlspecialchars($user['email']) ?>
                    </p>
                    <?php endif; ?>
                    <?php if ($user['telepon']): ?>
                    <p class="mb-2">
                        <i class="bi bi-phone me-2 text-muted"></i>
                        <?= htmlspecialchars($user['telepon']) ?>
                    </p>
                    <?php endif; ?>
                    <p class="mb-2">
                        <i class="bi bi-clock me-2 text-muted"></i>
                        Login terakhir: <?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : '-' ?>
                    </p>
                    <p class="mb-0">
                        <i class="bi bi-calendar me-2 text-muted"></i>
                        Terdaftar: <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-pencil me-2"></i>Edit Profil
            </div>
            <div class="card-body">
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" readonly>
                        <small class="text-muted">Username tidak dapat diubah</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama_lengkap" class="form-control" 
                               value="<?= htmlspecialchars($user['nama_lengkap']) ?>" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Telepon</label>
                            <input type="text" name="telepon" class="form-control" 
                                   value="<?= htmlspecialchars($user['telepon'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Foto Profil</label>
                        <input type="file" name="foto" class="form-control" accept="image/*">
                    </div>
                    
                    <hr>
                    
                    <h6 class="mb-3">Ubah Password</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password Lama</label>
                            <input type="password" name="password_lama" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password Baru</label>
                            <input type="password" name="password_baru" class="form-control">
                        </div>
                    </div>
                    <small class="text-muted">Kosongkan jika tidak ingin mengubah password</small>
                    
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
