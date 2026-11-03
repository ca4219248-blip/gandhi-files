<?php
declare(strict_types=1);

/** @var array<string, mixed> $config */
$pageTitle = $pageTitle ?? 'Archive';
$siteName  = (string) $config['site_name'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> &middot; <?= e($siteName) ?></title>
<meta name="description" content="<?= e((string) $config['site_tagline']) ?>">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site-head">
  <div class="wrap head-inner">
    <a class="brand" href="index.php">
      <span class="brand-mark">PRA</span>
      <span class="brand-text">
        <strong><?= e($siteName) ?></strong>
        <small><?= e((string) $config['site_tagline']) ?></small>
      </span>
    </a>
    <nav class="site-nav">
      <a href="index.php">Archive</a>
      <a href="about.php">About &amp; policy</a>
      <a href="right-of-reply.php">Right of reply</a>
      <a class="nav-admin" href="<?= e(admin_url('index.php', $config)) ?>">Admin</a>
    </nav>
  </div>
</header>
<main class="wrap">
<?php foreach (take_flashes() as $flash) : ?>
  <div class="alert alert-<?= e((string) $flash['type']) ?>"><?= e((string) $flash['message']) ?></div>
<?php endforeach; ?>
