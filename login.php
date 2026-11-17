<?php
require 'config.php';

if (isset($_GET['logout'])) {
    session_destroy();
    session_start();
    flash('Anda telah logout.');
    header('Location: login.php'); exit;
}

$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = trim($_POST['identitas'] ?? '');
    $s = db()->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
    $s->execute([$id, $id]);
    $user = $s->fetch();
    if ($user && password_verify($_POST['password'] ?? '', $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['nama']    = $user['nama'];
        header('Location: bookings.php'); exit;
    }
    $err = 'Username/email atau password salah.';
}

// Render the login form
//pppppp
header_html('Login');
?>
<h1>Login</h1>
<?php if ($err): ?><p class="error"><?= e($err) ?></p><?php endif; ?>
<form method="post" action="login.php" class="card">
  <input type="hidden" name="csrf" value="<?= csrf() ?>" />
  <label>Username / Email <input type="text" name="identitas" required="required" /></label>
  <label>Password <input type="password" name="password" required="required" /></label>
  <button type="submit">Login</button>
  <p>Belum punya akun? <a href="register.php">Registrasi</a></p>
</form>
<?php footer_html(); ?>
