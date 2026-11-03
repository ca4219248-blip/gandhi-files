<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$pageTitle = 'About and editorial policy';
require __DIR__ . '/partials/header.php';
?>
<article class="prose">
  <h1>About this archive</h1>
  <p>
    <?= e((string) $config['site_name']) ?> is a small, independent index of public documents.
    Each entry points to the original record — a court order, an audit report, a parliamentary
    answer, a gazette notification or a published report — and gives the reader the source so
    they can check it themselves.
  </p>

  <h2>What gets published</h2>
  <ul>
    <li>A record is listed only if the document itself is attached or linked, and its source is stated.</li>
    <li>The summary on a record describes what the document says. It does not add a conclusion.</li>
    <li>Records are written in neutral language. No adjectives about people, no motive, no verdict.</li>
    <li>Drafts stay private. Only published records are visible, and files are served through short lived links.</li>
  </ul>

  <h2>What does not get published</h2>
  <ul>
    <li>Material that is not traceable to a document.</li>
    <li>Private information about individuals — addresses, phone numbers, family details, health, minors.</li>
    <li>Content that is abusive, threatening or aimed at a community.</li>
    <li>Anything whose publication a court has restrained.</li>
  </ul>

  <h2>Right of reply and corrections</h2>
  <p>
    Anyone named in a record can send a reply, a correction or a clarification through the
    <a href="right-of-reply.php">right of reply form</a>. Replies that address the document are
    published on the record's page. If a factual error is shown, the record is corrected and the
    correction is noted. If a document turns out to be withdrawn, superseded or restrained, the
    record is unpublished.
  </p>

  <h2>Disclaimer</h2>
  <p>
    This site republishes documents; it does not investigate, accuse or conclude. Being named in
    a public document is not a finding of guilt. An allegation of a criminal offence is
    established only by a competent court, and every accused person is presumed innocent until
    then. Nothing here is legal advice.
  </p>

  <?php if ((string) $config['contact_email'] !== '') : ?>
    <h2>Contact</h2>
    <p>Corrections and takedown requests: <a href="mailto:<?= e((string) $config['contact_email']) ?>"><?= e((string) $config['contact_email']) ?></a></p>
  <?php endif; ?>
</article>
<?php require __DIR__ . '/partials/footer.php'; ?>
