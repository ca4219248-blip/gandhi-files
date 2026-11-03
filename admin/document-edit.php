<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$auth->requireLogin();

$id  = query('id');
$doc = null;
$error = null;

try {
    if ($id !== '') {
        $found = $db->select('pra_documents', 'select=*&id=eq.' . rawurlencode($id) . '&limit=1', true);
        $doc   = $found['rows'][0] ?? null;
        if ($doc === null) {
            flash('error', 'That record does not exist.');
            redirect('index.php');
        }
    }
    $categories = $db->select('pra_categories', 'select=id,name&order=name.asc', true)['rows'];
} catch (SupabaseException $e) {
    $error      = 'Could not load the form data: ' . $e->getMessage();
    $categories = [];
}

$isEdit = $doc !== null;

$pageTitle = $isEdit ? 'Edit record' : 'New record';
require __DIR__ . '/partials/header.php';
?>
<h1><?= $isEdit ? 'Edit record' : 'New record' ?></h1>

<?php if ($error !== null) : ?>
  <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<form class="form-card" method="post" action="document-save.php" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <?php if ($isEdit) : ?>
    <input type="hidden" name="id" value="<?= e((string) $doc['id']) ?>">
  <?php endif; ?>

  <p class="section-title">The record</p>
  <div class="stack">
    <div class="field">
      <label for="title">Title <span class="req">*</span></label>
      <input type="text" id="title" name="title" required maxlength="300" value="<?= e($isEdit ? (string) $doc['title'] : '') ?>">
      <p class="hint">Neutral and descriptive, e.g. &ldquo;CAG report on municipal contracts, 2019&rdquo;.</p>
    </div>

    <div class="field">
      <label for="slug">URL slug</label>
      <input type="text" id="slug" name="slug" maxlength="80" value="<?= e($isEdit ? (string) $doc['slug'] : '') ?>">
      <p class="hint">Leave blank to generate it from the title.</p>
    </div>

    <div class="field">
      <label for="summary">Summary <span class="req">*</span></label>
      <textarea id="summary" name="summary" rows="4" required maxlength="2000"><?= e($isEdit ? (string) $doc['summary'] : '') ?></textarea>
      <p class="hint">What the document says, in your own words, without conclusions about people.</p>
    </div>

    <div class="field">
      <label for="body">Background (optional)</label>
      <textarea id="body" name="body" rows="6" maxlength="20000"><?= e($isEdit ? (string) ($doc['body'] ?? '') : '') ?></textarea>
      <p class="hint">Context, procedural history, or where the document sits in a case. No opinion.</p>
    </div>

    <div class="grid-3">
      <div class="field">
        <label for="category_id">Category</label>
        <select id="category_id" name="category_id">
          <option value="">No category</option>
          <?php foreach ($categories as $category) : ?>
            <option value="<?= e((string) $category['id']) ?>"
              <?= ($isEdit && (string) ($doc['category_id'] ?? '') === (string) $category['id']) ? 'selected' : '' ?>>
              <?= e((string) $category['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="doc_date">Document date</label>
        <input type="date" id="doc_date" name="doc_date" value="<?= e($isEdit ? (string) ($doc['doc_date'] ?? '') : '') ?>">
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <?php foreach (['draft' => 'Draft (private)', 'published' => 'Published', 'unpublished' => 'Unpublished'] as $value => $label) : ?>
            <option value="<?= e($value) ?>" <?= ($isEdit ? (string) $doc['status'] : 'draft') === $value ? 'selected' : '' ?>>
              <?= e($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <p class="section-title">The source — required</p>
  <div class="stack">
    <div class="grid-2">
      <div class="field">
        <label for="source_type">Source type <span class="req">*</span></label>
        <select id="source_type" name="source_type" required>
          <?php foreach (source_types() as $type) : ?>
            <option value="<?= e($type) ?>" <?= ($isEdit && (string) $doc['source_type'] === $type) ? 'selected' : '' ?>>
              <?= e(source_label($type)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="source_date">Source date</label>
        <input type="date" id="source_date" name="source_date" value="<?= e($isEdit ? (string) ($doc['source_date'] ?? '') : '') ?>">
      </div>
    </div>

    <div class="field">
      <label for="source_title">Source title <span class="req">*</span></label>
      <input type="text" id="source_title" name="source_title" required maxlength="400" value="<?= e($isEdit ? (string) $doc['source_title'] : '') ?>">
      <p class="hint">Exact title of the document, with case number, report number or page reference where there is one.</p>
    </div>

    <div class="grid-2">
      <div class="field">
        <label for="source_publisher">Issued by</label>
        <input type="text" id="source_publisher" name="source_publisher" maxlength="200" value="<?= e($isEdit ? (string) ($doc['source_publisher'] ?? '') : '') ?>">
      </div>
      <div class="field">
        <label for="source_url">Original link</label>
        <input type="url" id="source_url" name="source_url" maxlength="500" value="<?= e($isEdit ? (string) ($doc['source_url'] ?? '') : '') ?>">
      </div>
    </div>
  </div>

  <p class="section-title">The file</p>
  <div class="stack">
    <?php if ($isEdit && !empty($doc['file_path'])) : ?>
      <div class="existing-file">
        Current file: <strong><?= e((string) ($doc['file_name'] ?? 'document.pdf')) ?></strong>
        <?php if (!empty($doc['file_size'])) : ?>
          (<?= e(human_size((int) $doc['file_size'])) ?>)
        <?php endif; ?>
        — uploading a new PDF replaces it.
      </div>
    <?php endif; ?>

    <div class="field">
      <label for="pdf">PDF file (max 25 MB)</label>
      <input type="file" id="pdf" name="pdf" accept="application/pdf,.pdf">
    </div>

    <div class="field">
      <label for="external_url">Or an external link to the document</label>
      <input type="url" id="external_url" name="external_url" maxlength="500" value="<?= e($isEdit ? (string) ($doc['external_url'] ?? '') : '') ?>">
      <p class="hint">Use this when the document already lives on an official site and you do not want to host a copy.</p>
    </div>
  </div>

  <div class="form-actions">
    <button type="submit"><?= $isEdit ? 'Save changes' : 'Create record' ?></button>
    <a class="button button-quiet" href="index.php">Cancel</a>
  </div>
</form>
<?php require __DIR__ . '/partials/footer.php'; ?>
