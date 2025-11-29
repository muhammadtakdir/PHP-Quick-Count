<?php
/**
 * Users Management - Quick Count System
 */

require_once '../config/config.php';
requireAdmin();

$pageTitle = 'Kelola Pengguna';
$conn = getConnection();

// Handle actions
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Delete
if ($action == 'delete' && $id > 0) {
    if ($id == $_SESSION['user_id']) {
        setFlash('error', 'Tidak dapat menghapus akun sendiri!');
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            setFlash('success', 'Pengguna berhasil dihapus!');
        } else {
            setFlash('error', 'Gagal menghapus pengguna!');
        }
    }
    header('Location: users.php');
    exit;
}

// Save (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $username = sanitize($_POST['username']);
    $nama_lengkap = sanitize($_POST['nama_lengkap']);
    $email = sanitize($_POST['email'] ?? '');
    $telepon = sanitize($_POST['telepon'] ?? '');
    $role = sanitize($_POST['role']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $password = $_POST['password'] ?? '';
    
    // Handle foto
    $foto = null;
    if (!empty($_FILES['foto']['name'])) {
        $result = uploadImage($_FILES['foto'], FOTO_USER_PATH, 300);
        if ($result['success']) {
            $foto = $result['filename'];
        }
    }
    
    if ($id > 0) {
        // Update
        $sql = "UPDATE users SET username = ?, nama_lengkap = ?, email = ?, telepon = ?, role = ?, is_active = ?";
        $params = [$username, $nama_lengkap, $email, $telepon, $role, $is_active];
        $types = "sssssi";
        
        if (!empty($password)) {
            $sql .= ", password = ?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
            $types .= "s";
        }
        if ($foto) {
            $sql .= ", foto = ?";
            $params[] = $foto;
            $types .= "s";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $id;
        $types .= "i";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $message = 'Data pengguna berhasil diupdate!';
    } else {
        // Insert
        if (empty($password)) {
            setFlash('error', 'Password wajib diisi untuk pengguna baru!');
            header('Location: users.php');
            exit;
        }
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $foto = $foto ?? 'default.png';
        
        $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, telepon, role, foto, is_active) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssi", $username, $passwordHash, $nama_lengkap, $email, $telepon, $role, $foto, $is_active);
        $message = 'Pengguna berhasil ditambahkan!';
    }
    
    if ($stmt->execute()) {
        setFlash('success', $message);
    } else {
        setFlash('error', 'Gagal menyimpan data! Username mungkin sudah digunakan.');
    }
    header('Location: users.php');
    exit;
}

// Get all users
$result = $conn->query("SELECT * FROM users ORDER BY role, nama_lengkap");
$usersList = $result->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1>Kelola Pengguna</h1>
        <p>Manajemen akun pengguna sistem</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#formModal">
        <i class="bi bi-plus-lg me-2"></i>Tambah Pengguna
    </button>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-hover" id="dataTable">
            <thead>
                <tr>
                    <th width="50">No</th>
                    <th width="60">Foto</th>
                    <th>Nama</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th width="100">Role</th>
                    <th width="80">Status</th>
                    <th width="150">Login Terakhir</th>
                    <th width="100">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usersList as $i => $row): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <img src="../uploads/users/<?= htmlspecialchars($row['foto']) ?>" 
                             class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;"
                             onerror="this.src='../assets/img/default.png'">
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($row['nama_lengkap']) ?></strong>
                        <?php if ($row['telepon']): ?>
                        <br><small class="text-muted"><i class="bi bi-phone"></i> <?= htmlspecialchars($row['telepon']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td>
                        <?php 
                        $roleColors = ['admin' => 'danger', 'operator' => 'primary', 'viewer' => 'secondary'];
                        ?>
                        <span class="badge bg-<?= $roleColors[$row['role']] ?? 'secondary' ?>">
                            <?= ucfirst($row['role']) ?>
                        </span>
                    </td>
                    <td>
                        <?= $row['is_active'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>' ?>
                    </td>
                    <td>
                        <?= $row['last_login'] ? date('d/m/Y H:i', strtotime($row['last_login'])) : '-' ?>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="editData(<?= htmlspecialchars(json_encode($row)) ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php if ($row['id'] != $_SESSION['user_id']): ?>
                        <button class="btn btn-sm btn-danger" onclick="confirmDelete('users.php?action=delete&id=<?= $row['id'] ?>', '<?= addslashes($row['nama_lengkap']) ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="users.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="formId">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah Pengguna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" id="formUsername" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password <span class="text-danger" id="pwdRequired">*</span></label>
                            <input type="password" name="password" id="formPassword" class="form-control">
                            <small class="text-muted" id="pwdHint">Kosongkan jika tidak ingin mengubah password</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama_lengkap" id="formNama" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="formEmail" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Telepon</label>
                            <input type="text" name="telepon" id="formTelepon" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="role" id="formRole" class="form-select" required>
                                <option value="admin">Admin (Akses Penuh + Konfigurasi)</option>
                                <option value="operator" selected>Operator (Input & Edit Data)</option>
                                <option value="viewer">Viewer (Hanya Lihat)</option>
                            </select>
                            <small class="text-muted" id="roleHint">
                                <strong>Admin:</strong> Semua fitur + Konfigurasi & Kelola User<br>
                                <strong>Operator:</strong> Input/edit data suara & master data<br>
                                <strong>Viewer:</strong> Hanya melihat data & grafik
                            </small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Foto</label>
                            <input type="file" name="foto" class="form-control" accept="image/*">
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="formActive" class="form-check-input" checked>
                        <label class="form-check-label" for="formActive">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$additionalJS = <<<JS
<script>
$('#dataTable').DataTable();

function editData(data) {
    $('#modalTitle').text('Edit Pengguna');
    $('#formId').val(data.id);
    $('#formUsername').val(data.username);
    $('#formNama').val(data.nama_lengkap);
    $('#formEmail').val(data.email);
    $('#formTelepon').val(data.telepon);
    $('#formRole').val(data.role);
    $('#formActive').prop('checked', data.is_active == 1);
    $('#formPassword').removeAttr('required');
    $('#pwdRequired').hide();
    $('#pwdHint').show();
    $('#formModal').modal('show');
}

$('#formModal').on('hidden.bs.modal', function() {
    $('#modalTitle').text('Tambah Pengguna');
    $(this).find('form')[0].reset();
    $('#formPassword').attr('required', 'required');
    $('#pwdRequired').show();
    $('#pwdHint').hide();
});
</script>
JS;

include '../includes/footer.php';
?>
