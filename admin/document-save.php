<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$auth->requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

csrf_check();

/** Length that works with or without mbstring. */
function pra_len(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

/** Reject anything that is not really a PDF. */
function pra_is_pdf(string $path): bool
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return false;
    }
    $head = fread($handle, 5);
    fclose($handle);
    if (!is_string($head) || strncmp($head, '%PDF-', 5) !== 0) {
        return false;
    }
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($path);
        if (is_string($mime) && $mime !== '' && stripos($mime, 'pdf') === false) {
            return false;
        }
    }
    return true;
}

function pra_valid_url(string $url): bool
{
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return $scheme === 'http' || $scheme === 'https';
}

$id       = post('id');
$isEdit   = $id !== '';
$errors   = [];
$existing = null;

if ($isEdit) {
    try {
        $found = $db->select('pra_documents', 'select=*&id=eq.' . rawurlencode($id) . '&limit=1', true);
        $existing = $found['rows'][0] ?? null;
    } catch (SupabaseException $e) {
        $existing = null;
    }
    if ($existing === null) {
        flash('error', 'That record no longer exists.');
        redirect('index.php');
    }
}

$title           = post('title');
$slugInput       = post('slug');
$summary         = post('summary');
$body            = post('body');
$categoryId      = post('category_id');
$docDate         = post('doc_date');
$status          = post('status');
$sourceType      = post('source_type');
$sourceTitle     = post('source_title');
$sourcePublisher = post('source_publisher');
$sourceUrl       = post('source_url');
$sourceDate      = post('source_date');
$externalUrl     = post('external_url');

if (!in_array($status, ['draft', 'published', 'unpublished'], true)) {
    $status = 'draft';
}
if (!in_array($sourceType, source_types(), true)) {
    $sourceType = 'other';
}

// ---- validation ----------------------------------------------------------
if (pra_len($title) < 4) {
    $errors[] = 'The title needs at least 4 characters.';
}
if (pra_len($summary) < 20) {
    $errors[] = 'The summary needs at least 20 characters.';
}
if (pra_len($sourceTitle) < 3) {
    $errors[] = 'The source title is required — every record must say what document it comes from.';
}
if ($sourceUrl !== '' && !pra_valid_url($sourceUrl)) {
    $errors[] = 'The original link must be a full http or https URL.';
}
if ($externalUrl !== '' && !pra_valid_url($externalUrl)) {
    $errors[] = 'The external document link must be a full http or https URL.';
}
foreach (['doc_date' => $docDate, 'source_date' => $sourceDate] as $label => $value) {
    if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
        $errors[] = 'Dates must be entered as YYYY-MM-DD.';
        break;
    }
}

// ---- optional PDF upload -------------------------------------------------
$upload      = isset($_FILES['pdf']) && is_array($_FILES['pdf']) ? $_FILES['pdf'] : null;
$hasUpload   = $upload !== null && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
$newFile     = null;

if ($hasUpload && $errors === []) {
    $errorCode = (int) $upload['error'];
    if ($errorCode !== UPLOAD_ERR_OK) {
        $errors[] = 'The PDF upload failed (error ' . $errorCode . '). Please try again.';
    } elseif ((int) $upload['size'] > (int) $config['max_upload_bytes']) {
        $errors[] = 'The PDF is larger than ' . human_size((int) $config['max_upload_bytes']) . '.';
    } elseif (!is_uploaded_file((string) $upload['tmp_name'])) {
        $errors[] = 'The upload could not be verified.';
    } elseif (!pra_is_pdf((string) $upload['tmp_name'])) {
        $errors[] = 'That file is not a valid PDF.';
    }
}

if ($errors === [] && $hasUpload) {
    $contents = file_get_contents((string) $upload['tmp_name']);
    if ($contents === false) {
        $errors[] = 'The uploaded file could not be read.';
    } else {
        $objectPath = 'documents/' . gmdate('Y/m') . '/' . bin2hex(random_bytes(10)) . '.pdf';
        try {
            $db->uploadObject($objectPath, $contents, 'application/pdf');
            $newFile = [
                'path' => $objectPath,
                'name' => basename((string) $upload['name']),
                'size' => (int) $upload['size'],
            ];
        } catch (SupabaseException $e) {
            $errors[] = 'The PDF could not be stored: ' . $e->getMessage();
        }
    }
}

// ---- a record needs a source and something to read ----------------------
$existingFile = $isEdit && !empty($existing['file_path']) ? (string) $existing['file_path'] : '';
$fileAfter    = $newFile !== null ? $newFile['path'] : $existingFile;
$hasSource    = $sourceUrl !== '' || $fileAfter !== '' || $externalUrl !== '';

if ($errors === [] && !$hasSource) {
    $errors[] = 'Attach a PDF, or give the original link, or give an external document link. A record without a source cannot be saved.';
}
if ($errors === [] && $fileAfter === '' && $externalUrl === '') {
    $errors[] = 'A record needs a readable document: upload a PDF or give an external document link.';
}

// ---- unique slug ---------------------------------------------------------
$slug = '';
if ($errors === []) {
    $base   = $slugInput !== '' ? slugify($slugInput) : slugify($title);
    $slug   = $base;
    $suffix = 2;
    try {
        while (true) {
            $query = 'select=id&slug=eq.' . rawurlencode($slug) . '&limit=1';
            if ($isEdit) {
                $query .= '&id=neq.' . rawurlencode($id);
            }
            if ($db->select('pra_documents', $query, true)['rows'] === []) {
                break;
            }
            $slug = $base . '-' . $suffix;
            $suffix++;
            if ($suffix > 50) {
                $slug = $base . '-' . bin2hex(random_bytes(3));
                break;
            }
        }
    } catch (SupabaseException $e) {
        $errors[] = 'Could not check the URL slug: ' . $e->getMessage();
    }
}

// ---- persist -------------------------------------------------------------
if ($errors === []) {
    $row = [
        'title'            => $title,
        'slug'             => $slug,
        'summary'          => $summary,
        'body'             => $body !== '' ? $body : null,
        'category_id'      => $categoryId !== '' ? $categoryId : null,
        'doc_date'         => $docDate !== '' ? $docDate : null,
        'source_type'      => $sourceType,
        'source_title'     => $sourceTitle,
        'source_publisher' => $sourcePublisher !== '' ? $sourcePublisher : null,
        'source_url'       => $sourceUrl !== '' ? $sourceUrl : null,
        'source_date'      => $sourceDate !== '' ? $sourceDate : null,
        'external_url'     => $externalUrl !== '' ? $externalUrl : null,
        'status'           => $status,
    ];

    if ($newFile !== null) {
        $row['file_path'] = $newFile['path'];
        $row['file_name'] = $newFile['name'];
        $row['file_size'] = $newFile['size'];
        $row['file_mime'] = 'application/pdf';
    }

    if ($status === 'published' && ($isEdit ? ($existing['published_at'] ?? null) === null : true)) {
        $row['published_at'] = gmdate('Y-m-d H:i:sP');
    }

    try {
        if ($isEdit) {
            $db->update('pra_documents', 'id=eq.' . rawurlencode($id), $row);
            if ($newFile !== null && $existingFile !== '') {
                try {
                    $db->removeObject($existingFile);
                } catch (SupabaseException $e) {
                    // the new file is saved; a stale object is not worth failing over
                }
            }
            flash('success', 'Record updated.');
        } else {
            $db->insert('pra_documents', $row);
            flash('success', 'Record created.');
        }
        redirect('index.php');
    } catch (SupabaseException $e) {
        if ($newFile !== null) {
            try {
                $db->removeObject($newFile['path']);
            } catch (SupabaseException $ignored) {
            }
        }
        $errors[] = 'Saving failed: ' . $e->getMessage();
    }
}

// ---- error page ----------------------------------------------------------
$pageTitle = 'Could not save';
require __DIR__ . '/partials/header.php';
?>
<h1>Could not save the record</h1>
<div class="alert alert-error">
  <ul>
    <?php foreach ($errors as $message) : ?>
      <li><?= e($message) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<p>
  <a class="button" href="document-edit.php<?= $isEdit ? '?id=' . e(rawurlencode($id)) : '' ?>">Back to the form</a>
  <a class="button button-quiet" href="index.php">Records</a>
</p>
<?php require __DIR__ . '/partials/footer.php'; ?>
