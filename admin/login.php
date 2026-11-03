<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

if ($auth->check()) {
    redirect('index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $error = $auth->attempt(post('email'), post('password'));
    if ($error === null) {
        flash('success', 'Signed in.');
        redirect('index.php');
    }
}

$pageTitle = 'Sign in';
require __DIR__ . '/partials/header.php';
?>
<div class="login-card">
  <h1>Admin sign in</h1>
  <p class="muted">Use the email and password of an account listed in the archive admin allow list.</p>

  <?php if ($error !== null) : ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endif; ?>

  <?php if (!$db->hasServiceKey()) : ?>
    <div class="alert alert-error">
      <code>SUPABASE_SERVICE_KEY</code> is not set in <code>.env</code>. Admin sign in and all
      write operations need it.
    </div>
  <?php endif; ?>

  <form method="post" action="login.php" class="stack">
    <?= csrf_field() ?>
    <div class="field">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= e(post('email')) ?>" required autocomplete="username">
    </div>
    <div class="field">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>
    <button type="submit">Sign in</button>
  </form>
  <p class="muted small"><a href="../index.php">Back to the public archive</a></p>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
