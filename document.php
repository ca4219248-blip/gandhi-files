<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$slug = query('slug');
$doc  = null;
$error = null;

try {
    if ($slug !== '') {
        $result = $db->select(
            'pra_documents',
            'select=*&slug=eq.' . rawurlencode($slug) . '&status=eq.published&limit=1'
        );
        $doc = $result['rows'][0] ?? null;
    }
} catch (SupabaseException $e) {
    $error = 'The archive could not be loaded right now. Please try again in a moment.';
}

if ($doc === null && $error === null) {
    http_response_code(404);
    $pageTitle = 'Record not found';
    require __DIR__ . '/partials/header.php';
    echo '<div class="empty"><h1>Record not found</h1><p>This record is not published, or the link is wrong.</p>'
        . '<p><a href="index.php">Back to the archive</a></p></div>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

if ($error !== null) {
    http_response_code(503);
    $pageTitle = 'Archive unavailable';
    require __DIR__ . '/partials/header.php';
    echo '<div class="alert alert-error">' . e($error) . '</div>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

/** @var array<string, mixed> $doc */
$categoryName = '';
$categorySlug = '';

try {
    if (!empty($doc['category_id'])) {
        $catResult = $db->select(
            'pra_categories',
            'select=name,slug&id=eq.' . rawurlencode((string) $doc['category_id']) . '&limit=1'
        );
        if (count($catResult['rows']) > 0) {
            $categoryName = (string) $catResult['rows'][0]['name'];
            $categorySlug = (string) $catResult['rows'][0]['slug'];
        }
    }

    $replies = $db->select(
        'pra_published_replies',
        'select=name,organisation,message,created_at&document_id=eq.' . rawurlencode((string) $doc['id']) . '&order=created_at.asc'
    )['rows'];
} catch (SupabaseException $e) {
    $replies = [];
}

$fileUrl = null;
$fileLabel = '';
if (!empty($doc['file_path'])) {
    try {
        $fileUrl = $db->signedUrl((string) $doc['file_path'], (int) $config['signed_url_ttl']);
        if ($fileUrl === null) {
            $fileUrl = $db->publicUrl((string) $doc['file_path']);
        }
        $fileLabel = (string) ($doc['file_name'] ?? 'document.pdf');
    } catch (SupabaseException $e) {
        $fileUrl = null;
    }
}
if ($fileUrl === null && !empty($doc['external_url'])) {
    $fileUrl = (string) $doc['external_url'];
    $fileLabel = 'Open the document';
}

$pageTitle = (string) $doc['title'];
require __DIR__ . '/partials/header.php';
?>
<article class="record-page">
  <p class="crumb"><a href="index.php">Archive</a> / <?= e($categoryName !== '' ? $categoryName : 'Record') ?></p>

  <header class="record-head">
    <h1><?= e((string) $doc['title']) ?></h1>
    <p class="record-meta">
      <?php if ($categoryName !== '') : ?>
        <a class="chip" href="index.php?category=<?= e(rawurlencode($categorySlug)) ?>"><?= e($categoryName) ?></a>
      <?php endif; ?>
      <span class="chip chip-quiet"><?= e(source_label((string) $doc['source_type'])) ?></span>
      <?php if (!empty($doc['doc_date'])) : ?>
        <time datetime="<?= e((string) $doc['doc_date']) ?>">Document dated <?= e(format_date((string) $doc['doc_date'])) ?></time>
      <?php endif; ?>
      <span class="updated">Added <?= e(format_date((string) ($doc['published_at'] ?? $doc['created_at']))) ?></span>
    </p>
  </header>

  <section class="panel">
    <h2>What this record is</h2>
    <p class="summary"><?= nl2br(e((string) $doc['summary'])) ?></p>
    <?php if (!empty($doc['body'])) : ?>
      <div class="body-text"><?= nl2br(e((string) $doc['body'])) ?></div>
    <?php endif; ?>
  </section>

  <section class="panel panel-source">
    <h2>Source</h2>
    <dl class="source-grid">
      <dt>Source type</dt>
      <dd><?= e(source_label((string) $doc['source_type'])) ?></dd>

      <dt>Document title</dt>
      <dd><?= e((string) $doc['source_title']) ?></dd>

      <?php if (!empty($doc['source_publisher'])) : ?>
        <dt>Issued by</dt>
        <dd><?= e((string) $doc['source_publisher']) ?></dd>
      <?php endif; ?>

      <?php if (!empty($doc['source_date'])) : ?>
        <dt>Source date</dt>
        <dd><?= e(format_date((string) $doc['source_date'])) ?></dd>
      <?php endif; ?>

      <?php if (!empty($doc['source_url'])) : ?>
        <dt>Original link</dt>
        <dd><a href="<?= e((string) $doc['source_url']) ?>" target="_blank" rel="noopener nofollow"><?= e((string) $doc['source_url']) ?></a></dd>
      <?php endif; ?>
    </dl>
    <p class="verify-note">Please read the original document before drawing any conclusion. This page only indexes it.</p>
  </section>

  <?php if ($fileUrl !== null) : ?>
    <section class="panel">
      <h2>Document</h2>
      <p class="file-line">
        <a class="button" href="<?= e($fileUrl) ?>" target="_blank" rel="noopener">Open <?= e($fileLabel) ?></a>
        <?php if (!empty($doc['file_size'])) : ?>
          <span class="muted"><?= e(human_size((int) $doc['file_size'])) ?> &middot; PDF</span>
        <?php endif; ?>
      </p>
      <?php if (strpos((string) $fileUrl, 'application/pdf') !== false || strtolower(substr((string) ($doc['file_mime'] ?? ''), -3)) === 'pdf' || !empty($doc['file_path'])) : ?>
        <div class="viewer">
          <iframe src="<?= e($fileUrl) ?>" title="PDF viewer" loading="lazy"></iframe>
        </div>
        <p class="muted small">Preview may not load in some browsers. Use the button above to open the file.</p>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <section class="panel panel-reply">
    <h2>Right of reply</h2>
    <p>
      If you are named in this record, or you represent an organisation that is, you can
      send a reply, a correction or a clarification. Replies that stay on the document are
      published on this page, unedited apart from length.
    </p>
    <p><a class="button button-quiet" href="right-of-reply.php?document=<?= e(rawurlencode((string) $doc['slug'])) ?>">Send a reply</a></p>

    <?php if ($replies !== []) : ?>
      <div class="replies">
        <?php foreach ($replies as $reply) : ?>
          <blockquote>
            <p><?= nl2br(e((string) $reply['message'])) ?></p>
            <footer>
              <?= e((string) $reply['name']) ?>
              <?php if (!empty($reply['organisation'])) : ?>
                , <?= e((string) $reply['organisation']) ?>
              <?php endif; ?>
              <span class="muted">&middot; <?= e(format_date((string) $reply['created_at'])) ?></span>
            </footer>
          </blockquote>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <p class="disclaimer">
    This archive republishes public documents with attribution. It does not assert that any
    person is guilty of an offence. A criminal allegation is established only by a court.
    Read the <a href="about.php">editorial policy</a>.
  </p>
</article>
<?php require __DIR__ . '/partials/footer.php'; ?>
