<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$auth->requireLogin();

$q       = query('q');
$status  = query('status');
$page    = max(1, (int) query('page', '1'));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$filters = [];
if ($status !== '' && in_array($status, ['draft', 'published', 'unpublished'], true)) {
    $filters[] = 'status=eq.' . rawurlencode($status);
}
if ($q !== '') {
    $needle    = rawurlencode('*' . str_replace(['*', ','], ' ', $q) . '*');
    $filters[] = 'or=(title.ilike.' . $needle . ',source_title.ilike.' . $needle . ')';
}

$error     = null;
$documents = [];
$total     = 0;
$counts    = ['draft' => 0, 'published' => 0, 'unpublished' => 0, 'replies' => 0];

try {
    $select = 'select=id,title,slug,status,source_type,published_at,updated_at,file_path,external_url'
        . ($filters === [] ? '' : '&' . implode('&', $filters))
        . '&order=updated_at.desc&limit=' . $perPage . '&offset=' . $offset;

    $result    = $db->select('pra_documents', $select, true, true);
    $documents = $result['rows'];
    $total     = $result['count'] ?? count($documents);

    foreach (['draft', 'published', 'unpublished'] as $state) {
        $counts[$state] = (int) ($db->select('pra_documents', 'select=id&status=eq.' . $state, true, true)['count'] ?? 0);
    }
    $counts['replies'] = (int) ($db->select('pra_reply_requests', 'select=id&status=eq.received', true, true)['count'] ?? 0);
} catch (SupabaseException $e) {
    $error = 'Could not load records: ' . $e->getMessage();
}

$pageCount = max(1, (int) ceil($total / $perPage));

$pageTitle = 'Records';
require __DIR__ . '/partials/header.php';
?>
<h1>Records</h1>

<?php if (!$db->hasServiceKey()) : ?>
  <div class="alert alert-error">
    <code>SUPABASE_SERVICE_KEY</code> is missing from <code>.env</code>. The admin panel cannot
    read drafts or save anything without it.
  </div>
<?php endif; ?>

<?php if ($error !== null) : ?>
  <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="stats">
  <div class="stat"><span>Published</span><strong><?= (int) $counts['published'] ?></strong></div>
  <div class="stat"><span>Drafts</span><strong><?= (int) $counts['draft'] ?></strong></div>
  <div class="stat"><span>Unpublished</span><strong><?= (int) $counts['unpublished'] ?></strong></div>
  <div class="stat"><span>New replies</span><strong><?= (int) $counts['replies'] ?></strong></div>
</div>

<form class="toolbar" method="get" action="index.php">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by title or source">
  <select name="status">
    <option value="">All statuses</option>
    <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
    <option value="unpublished" <?= $status === 'unpublished' ? 'selected' : '' ?>>Unpublished</option>
  </select>
  <button type="submit">Filter</button>
  <a class="button" href="document-edit.php">New record</a>
</form>

<?php if ($documents === []) : ?>
  <div class="empty">
    <p>No records yet.</p>
    <p><a href="document-edit.php">Add the first record</a> — it needs a source and a PDF or a link.</p>
  </div>
<?php else : ?>
  <table class="data">
    <thead>
      <tr>
        <th>Title</th>
        <th>Status</th>
        <th>Source type</th>
        <th>Updated</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($documents as $doc) : ?>
        <tr>
          <td>
            <strong><?= e((string) $doc['title']) ?></strong>
            <div class="muted small"><?= e((string) $doc['slug']) ?></div>
          </td>
          <td><span class="badge badge-<?= e((string) $doc['status']) ?>"><?= e((string) $doc['status']) ?></span></td>
          <td class="small"><?= e(source_label((string) $doc['source_type'])) ?></td>
          <td class="small"><?= e(format_date((string) $doc['updated_at'])) ?></td>
          <td class="actions">
            <a class="button button-quiet" href="document-edit.php?id=<?= e(rawurlencode((string) $doc['id'])) ?>">Edit</a>
            <?php if ((string) $doc['status'] === 'published') : ?>
              <a class="button button-quiet" href="../document.php?slug=<?= e(rawurlencode((string) $doc['slug'])) ?>" target="_blank" rel="noopener">View</a>
            <?php endif; ?>
            <form method="post" action="document-delete.php" onsubmit="return confirm('Delete this record and its PDF permanently?');">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= e((string) $doc['id']) ?>">
              <button type="submit" class="button button-danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($pageCount > 1) : ?>
    <nav class="pager">
      <?php if ($page > 1) : ?>
        <a href="index.php?page=<?= $page - 1 ?>&amp;q=<?= e(rawurlencode($q)) ?>&amp;status=<?= e(rawurlencode($status)) ?>">&larr; Previous</a>
      <?php endif; ?>
      <span>Page <?= $page ?> of <?= $pageCount ?></span>
      <?php if ($page < $pageCount) : ?>
        <a href="index.php?page=<?= $page + 1 ?>&amp;q=<?= e(rawurlencode($q)) ?>&amp;status=<?= e(rawurlencode($status)) ?>">Next &rarr;</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
