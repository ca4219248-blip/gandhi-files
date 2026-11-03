<?php
declare(strict_types=1);

/** @var array<string, mixed> $config */
/** @var Auth $auth */
$pageTitle = $pageTitle ?? 'Admin';
$adminUser = $auth->check() ? $auth->user() : null;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle) ?> &middot; <?= e((string) $config['site_name']) ?> Admin</title>
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/admin.css">
</head>
<body class="admin">
<header class="site-head">
  <div class="wrap head-inner">
    <a class="brand" href="index.php">
      <span class="brand-mark">GF</span>
      <span class="brand-text">
        <strong><?= e((string) $config['site_name']) ?></strong>
        <small>Admin panel</small>
      </span>
    </a>
    <?php if ($adminUser !== null) : ?>
      <nav class="site-nav">
        <a href="index.php">Records</a>
        <a href="document-edit.php">New record</a>
        <a href="categories.php">Categories</a>
        <a href="replies.php">Replies</a>
        <a href="../index.php" target="_blank" rel="noopener">View site</a>
        <span class="who"><?= e($adminUser['email']) ?></span>
        <a class="nav-admin" href="logout.php">Sign out</a>
      </nav>
    <?php endif; ?>
  </div>
</header>
<main class="wrap">
<?php foreach (take_flashes() as $flash) : ?>
  <div class="alert alert-<?= e((string) $flash['type']) ?>"><?= e((string) $flash['message']) ?></div>
<?php endforeach; ?>
