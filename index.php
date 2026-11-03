<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$q        = query('q');
$category = query('category');
$page     = max(1, (int) query('page', '1'));
$perPage  = (int) $config['per_page'];
$offset   = ($page - 1) * $perPage;

$filters   = ['status=eq.published'];
$categoryId = null;

try {
    if ($category !== '') {
        $catResult = $db->select('pra_categories', 'select=id,name,slug&slug=eq.' . rawurlencode($category) . '&limit=1');
        if (count($catResult['rows']) > 0) {
            $categoryId = (string) $catResult['rows'][0]['id'];
            $filters[]  = 'category_id=eq.' . rawurlencode($categoryId);
        }
    }

    if ($q !== '') {
        $needle   = rawurlencode('*' . str_replace(['*', ','], ' ', $q) . '*');
        $filters[] = 'or=(title.ilike.' . $needle . ',summary.ilike.' . $needle . ',source_title.ilike.' . $needle . ')';
    }

    $select = 'select=id,title,slug,summary,doc_date,published_at,source_type,source_title,source_url,'
        . 'file_path,external_url,pra_categories(name,slug)'
        . '&' . implode('&', $filters)
        . '&order=published_at.desc.nullslast&limit=' . $perPage . '&offset=' . $offset;

    $result    = $db->select('pra_documents', $select, false, true);
    $documents = $result['rows'];
    $total     = $result['count'] ?? count($documents);
    $categories = $db->select('pra_categories', 'select=id,name,slug&order=name.asc')['rows'];
} catch (SupabaseException $e) {
    http_response_code(503);
    $pageTitle = 'Archive unavailable';
    require __DIR__ . '/partials/header.php';
    echo '<div class="alert alert-error">The archive could not be loaded right now. Please try again in a moment.</div>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

$pageCount = (int) ceil($total / $perPage);
if ($pageCount < 1) {
    $pageCount = 1;
}

$buildUrl = static function (array $overrides) use ($q, $category): string {
    $params = ['q' => $q, 'category' => $category];
    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }
    $params = array_filter($params, static fn ($value) => $value !== '' && $value !== null);
    return 'index.php' . ($params === [] ? '' : '?' . http_build_query($params));
};

$pageTitle = $q !== '' ? 'Search: ' . $q : 'Archive';
require __DIR__ . '/partials/header.php';
?>
<section class="hero">
  <h1>Public documents, with their sources attached</h1>
  <p>
    Every record in this archive links to the original court order, audit report,
    parliamentary record, notification or published report it comes from. No claim is
    made beyond what the document itself says.
  </p>
</section>

<form class="filters" method="get" action="index.php">
  <div class="filter-row">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search titles, summaries and sources" aria-label="Search the archive">
    <select name="category" aria-label="Category">
      <option value="">All categories</option>
      <?php foreach ($categories as $item) : ?>
        <option value="<?= e((string) $item['slug']) ?>" <?= $category === (string) $item['slug'] ? 'selected' : '' ?>>
          <?= e((string) $item['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Search</button>
  </div>
  <?php if ($q !== '' || $category !== '') : ?>
    <p class="filter-clear"><a href="index.php">Clear filters</a></p>
  <?php endif; ?>
</form>

<p class="result-count">
  <?= $total ?> <?= $total === 1 ? 'record' : 'records' ?>
  <?php if ($q !== '') : ?>matching &ldquo;<?= e($q) ?>&rdquo;<?php endif; ?>
</p>

<?php if ($documents === []) : ?>
  <div class="empty">
    <p>Nothing matches this search yet.</p>
    <p>The archive only lists records that carry a source. Try a broader term.</p>
  </div>
<?php else : ?>
  <ol class="record-list">
    <?php foreach ($documents as $doc) : ?>
      <?php
        $catName = isset($doc['pra_categories']['name']) ? (string) $doc['pra_categories']['name'] : '';
        $catSlug = isset($doc['pra_categories']['slug']) ? (string) $doc['pra_categories']['slug'] : '';
        $hasFile = !empty($doc['file_path']) || !empty($doc['external_url']);
      ?>
      <li class="record">
        <div class="record-meta">
          <?php if ($catName !== '') : ?>
            <a class="chip" href="<?= e($buildUrl(['category' => $catSlug, 'page' => '1'])) ?>"><?= e($catName) ?></a>
          <?php endif; ?>
          <span class="chip chip-quiet"><?= e(source_label((string) $doc['source_type'])) ?></span>
          <?php if (!empty($doc['doc_date'])) : ?>
            <time datetime="<?= e((string) $doc['doc_date']) ?>"><?= e(format_date((string) $doc['doc_date'])) ?></time>
          <?php endif; ?>
        </div>
        <h2><a href="document.php?slug=<?= e(rawurlencode((string) $doc['slug'])) ?>"><?= e((string) $doc['title']) ?></a></h2>
        <p class="record-summary"><?= e(excerpt((string) $doc['summary'])) ?></p>
        <p class="record-source">
          <span>Source:</span> <?= e((string) $doc['source_title']) ?>
          <?php if ($hasFile) : ?>
            <span class="dot">&middot;</span> <span class="ok">document attached</span>
          <?php endif; ?>
        </p>
      </li>
    <?php endforeach; ?>
  </ol>

  <?php if ($pageCount > 1) : ?>
    <nav class="pager" aria-label="Pagination">
      <?php if ($page > 1) : ?>
        <a href="<?= e($buildUrl(['page' => (string) ($page - 1)])) ?>">&larr; Newer</a>
      <?php endif; ?>
      <span>Page <?= $page ?> of <?= $pageCount ?></span>
      <?php if ($page < $pageCount) : ?>
        <a href="<?= e($buildUrl(['page' => (string) ($page + 1)])) ?>">Older &rarr;</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
