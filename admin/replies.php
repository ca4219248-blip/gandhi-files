<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$auth->requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $id        = post('id');
    $decision  = post('decision');
    $adminNote = post('admin_note');

    if ($id !== '' && in_array($decision, ['published', 'rejected', 'received'], true)) {
        try {
            $db->update('pra_reply_requests', 'id=eq.' . rawurlencode($id), [
                'status'     => $decision,
                'admin_note' => $adminNote !== '' ? $adminNote : null,
            ], true);
            flash('success', 'Reply marked as ' . $decision . '.');
        } catch (SupabaseException $e) {
            flash('error', 'Could not update the reply: ' . $e->getMessage());
        }
    } else {
        flash('error', 'Nothing to update.');
    }

    redirect('replies.php');
}

$filter = query('status', 'received');
if (!in_array($filter, ['received', 'published', 'rejected', 'all'], true)) {
    $filter = 'received';
}

try {
    $query = 'select=id,document_id,name,email,organisation,message,status,admin_note,created_at'
        . ($filter === 'all' ? '' : '&status=eq.' . rawurlencode($filter))
        . '&order=created_at.desc&limit=100';

    $replies = $db->select('pra_reply_requests', $query, true)['rows'];

    $titles = [];
    $documentIds = [];
    foreach ($replies as $reply) {
        if (!empty($reply['document_id'])) {
            $documentIds[] = (string) $reply['document_id'];
        }
    }
    $documentIds = array_values(array_unique($documentIds));
    if ($documentIds !== []) {
        $list = implode(',', array_map('rawurlencode', $documentIds));
        $docs = $db->select('pra_documents', 'select=id,title,slug&id=in.(' . $list . ')', true)['rows'];
        foreach ($docs as $doc) {
            $titles[(string) $doc['id']] = $doc;
        }
    }
    $error = null;
} catch (SupabaseException $e) {
    $replies = [];
    $titles  = [];
    $error   = $e->getMessage();
}

$pageTitle = 'Right of reply queue';
require __DIR__ . '/partials/header.php';
?>
<h1>Right of reply</h1>
<p class="muted">Replies submitted through the public form. Publishing a reply shows it on the record's page, without the email address.</p>

<div class="toolbar">
  <?php foreach (['received' => 'Awaiting review', 'published' => 'Published', 'rejected' => 'Rejected', 'all' => 'All'] as $value => $label) : ?>
    <a class="button <?= $filter === $value ? '' : 'button-quiet' ?>" href="replies.php?status=<?= e($value) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($error !== null) : ?>
  <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($replies === []) : ?>
  <div class="empty"><p>Nothing in this queue.</p></div>
<?php else : ?>
  <?php foreach ($replies as $reply) : ?>
    <?php
      $docId = (string) ($reply['document_id'] ?? '');
      $doc   = $titles[$docId] ?? null;
    ?>
    <article class="record" style="margin-bottom:14px;">
      <div class="record-meta">
        <span class="badge badge-<?= e((string) $reply['status']) ?>"><?= e((string) $reply['status']) ?></span>
        <span class="muted small"><?= e(format_datetime((string) $reply['created_at'])) ?></span>
        <?php if ($doc !== null) : ?>
          <a class="chip" href="../document.php?slug=<?= e(rawurlencode((string) $doc['slug'])) ?>" target="_blank" rel="noopener">
            <?= e((string) $doc['title']) ?>
          </a>
        <?php endif; ?>
      </div>

      <p class="record-summary" style="white-space:pre-wrap;"><?= e((string) $reply['message']) ?></p>

      <p class="record-source">
        <span>From:</span> <?= e((string) $reply['name']) ?>
        <?php if (!empty($reply['organisation'])) : ?> &middot; <?= e((string) $reply['organisation']) ?><?php endif; ?>
        &middot; <a href="mailto:<?= e((string) $reply['email']) ?>"><?= e((string) $reply['email']) ?></a>
      </p>

      <form method="post" action="replies.php" class="inline-form" style="margin-top:12px;">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e((string) $reply['id']) ?>">
        <div class="field">
          <label for="note-<?= e((string) $reply['id']) ?>">Internal note</label>
          <input type="text" id="note-<?= e((string) $reply['id']) ?>" name="admin_note" maxlength="300" value="<?= e((string) ($reply['admin_note'] ?? '')) ?>">
        </div>
        <button type="submit" name="decision" value="published">Publish</button>
        <button type="submit" name="decision" value="rejected" class="button button-danger">Reject</button>
        <button type="submit" name="decision" value="received" class="button button-quiet">Back to queue</button>
      </form>
    </article>
  <?php endforeach; ?>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
