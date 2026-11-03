<?php
declare(strict_types=1);

/** @var array<string, mixed> $config */
?>
</main>
<footer class="site-foot">
  <div class="wrap">
    <p class="foot-note">
      <?= e((string) $config['site_name']) ?> publishes public documents with their sources.
      Nothing here is an allegation or an opinion about any person: each record links to the
      original document so a reader can check it. Anyone named in a record has a
      <a href="right-of-reply.php">right of reply</a>.
    </p>
    <p class="foot-meta">
      <a href="about.php">Editorial policy</a>
      <span aria-hidden="true">&middot;</span>
      <a href="right-of-reply.php">Corrections and reply</a>
      <?php if ((string) $config['contact_email'] !== '') : ?>
        <span aria-hidden="true">&middot;</span>
        <a href="mailto:<?= e((string) $config['contact_email']) ?>"><?= e((string) $config['contact_email']) ?></a>
      <?php endif; ?>
    </p>
  </div>
</footer>
</body>
</html>
