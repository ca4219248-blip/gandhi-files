<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$auth->requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

csrf_check();

$id = post('id');
if ($id === '') {
    flash('error', 'No record was selected.');
    redirect('index.php');
}

try {
    $found = $db->select('pra_documents', 'select=id,title,file_path&id=eq.' . rawurlencode($id) . '&limit=1', true);
    $doc   = $found['rows'][0] ?? null;

    if ($doc === null) {
        flash('error', 'That record no longer exists.');
        redirect('index.php');
    }

    $db->delete('pra_documents', 'id=eq.' . rawurlencode($id), true);

    if (!empty($doc['file_path'])) {
        try {
            $db->removeObject((string) $doc['file_path']);
        } catch (SupabaseException $e) {
            // the row is gone; a leftover object is harmless
        }
    }

    flash('success', 'Record deleted.');
} catch (SupabaseException $e) {
    flash('error', 'Delete failed: ' . $e->getMessage());
}

redirect('index.php');
