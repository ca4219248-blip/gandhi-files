<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$auth->requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');

    try {
        if ($action === 'add') {
            $name        = post('name');
            $description = post('description');
            $slug        = slugify(post('slug') !== '' ? post('slug') : $name);

            if (pra_len_safe($name) < 2) {
                flash('error', 'Give the category a name.');
            } else {
                $db->insert('pra_categories', [
                    'name'        => $name,
                    'slug'        => $slug,
                    'description' => $description !== '' ? $description : null,
                ], true);
                flash('success', 'Category added.');
            }
        } elseif ($action === 'delete') {
            $id = post('id');
            if ($id !== '') {
                $db->delete('pra_categories', 'id=eq.' . rawurlencode($id), true);
                flash('success', 'Category deleted. Records that used it now have no category.');
            }
        }
    } catch (SupabaseException $e) {
        flash('error', 'That did not work: ' . $e->getMessage());
    }

    redirect('categories.php');
}

function pra_len_safe(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

try {
    $categories = $db->select('pra_categories', 'select=id,name,slug,description&order=name.asc', true)['rows'];
    $error      = null;
} catch (SupabaseException $e) {
    $categories = [];
    $error      = $e->getMessage();
}

$pageTitle = 'Categories';
require __DIR__ . '/partials/header.php';
?>
<h1>Categories</h1>

<?php if ($error !== null) : ?>
  <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<form class="form-card" method="post" action="categories.php">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="add">
  <div class="inline-form">
    <div class="field">
      <label for="name">Name</label>
      <input type="text" id="name" name="name" required maxlength="80" placeholder="e.g. Court orders">
    </div>
    <div class="field">
      <label for="slug">Slug</label>
      <input type="text" id="slug" name="slug" maxlength="80" placeholder="court-orders">
    </div>
    <div class="field">
      <label for="description">Description</label>
      <input type="text" id="description" name="description" maxlength="200">
    </div>
    <button type="submit">Add category</button>
  </div>
</form>

<h2 class="section-title">Existing categories</h2>

<?php if ($categories === []) : ?>
  <div class="empty"><p>No categories yet.</p></div>
<?php else : ?>
  <table class="data">
    <thead>
      <tr><th>Name</th><th>Slug</th><th>Description</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($categories as $category) : ?>
        <tr>
          <td><strong><?= e((string) $category['name']) ?></strong></td>
          <td class="small"><?= e((string) $category['slug']) ?></td>
          <td class="small"><?= e((string) ($category['description'] ?? '')) ?></td>
          <td class="actions">
            <a class="button button-quiet" href="../index.php?category=<?= e(rawurlencode((string) $category['slug'])) ?>" target="_blank" rel="noopener">View</a>
            <form method="post" action="categories.php" onsubmit="return confirm('Delete this category?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= e((string) $category['id']) ?>">
              <button type="submit" class="button button-danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
