<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$errors   = [];
$sent     = false;
$prefill  = query('document');
$document = null;
$recent   = [];

try {
    if ($prefill !== '') {
        $found = $db->select('pra_documents', 'select=id,title,slug&slug=eq.' . rawurlencode($prefill) . '&status=eq.published&limit=1');
        $document = $found['rows'][0] ?? null;
    }
    $recent = $db->select('pra_documents', 'select=title,slug&status=eq.published&order=published_at.desc&limit=50')['rows'];
} catch (SupabaseException $e) {
    $recent = [];
}

$values = [
    'name'         => post('name'),
    'email'        => post('email'),
    'organisation' => post('organisation'),
    'message'      => post('message'),
    'document_id'  => post('document_id'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Honeypot: real people leave this empty.
    if (post('website') !== '') {
        $sent = true;
    }

    if (!$sent) {
        if (function_exists('mb_strlen') ? mb_strlen($values['name']) < 2 : strlen($values['name']) < 2) {
            $errors[] = 'Please enter your name.';
        }
        if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        $messageLength = function_exists('mb_strlen') ? mb_strlen($values['message']) : strlen($values['message']);
        if ($messageLength < 20) {
            $errors[] = 'Please write at least 20 characters so we can act on your reply.';
        }
        if ($messageLength > 5000) {
            $errors[] = 'Please keep the reply under 5000 characters.';
        }

        if ($errors === []) {
            $row = [
                'name'         => $values['name'],
                'email'        => $values['email'],
                'organisation' => $values['organisation'] !== '' ? $values['organisation'] : null,
                'message'      => $values['message'],
                'status'       => 'received',
            ];
            if ($values['document_id'] !== '') {
                $row['document_id'] = $values['document_id'];
            }

            try {
                $db->insert('pra_reply_requests', $row, false);
                $sent = true;
                $values = ['name' => '', 'email' => '', 'organisation' => '', 'message' => '', 'document_id' => ''];
            } catch (SupabaseException $e) {
                $errors[] = 'The reply could not be saved. Please try again, or email us instead.';
            }
        }
    }
}

$pageTitle = 'Right of reply';
require __DIR__ . '/partials/header.php';
?>
<article class="prose">
  <h1>Right of reply</h1>
  <p>
    If a record in this archive names you, your organisation or someone you represent, use this
    form to send a reply, a correction or a clarification. We publish replies that address the
    document, and we correct any factual error we are shown.
  </p>

  <?php if ($sent) : ?>
    <div class="alert alert-success">
      Thank you. Your reply has been received and will be reviewed. We will use the email address
      you gave us if we need to check anything.
    </div>
  <?php endif; ?>

  <?php foreach ($errors as $message) : ?>
    <div class="alert alert-error"><?= e($message) ?></div>
  <?php endforeach; ?>

  <?php if (!$sent) : ?>
    <form method="post" action="right-of-reply.php" class="stack">
      <?= csrf_field() ?>
      <div class="field">
        <label for="name">Your name <span class="req">*</span></label>
        <input type="text" id="name" name="name" value="<?= e($values['name']) ?>" required maxlength="120">
      </div>

      <div class="field">
        <label for="email">Email <span class="req">*</span></label>
        <input type="email" id="email" name="email" value="<?= e($values['email']) ?>" required maxlength="180">
        <p class="hint">Not published. Used only to verify the reply.</p>
      </div>

      <div class="field">
        <label for="organisation">Organisation (optional)</label>
        <input type="text" id="organisation" name="organisation" value="<?= e($values['organisation']) ?>" maxlength="160">
      </div>

      <div class="field">
        <label for="document_id">Which record?</label>
        <select id="document_id" name="document_id">
          <option value="">Not specific to one record</option>
          <?php foreach ($recent as $item) : ?>
            <option value="<?= e((string) ($item['id'] ?? '')) ?>"
              <?= (($document !== null && (string) $document['id'] === (string) ($item['id'] ?? '')) || $values['document_id'] === (string) ($item['id'] ?? '')) ? 'selected' : '' ?>>
              <?= e((string) $item['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if ($document !== null) : ?>
          <p class="hint">Selected from the record you came from: <?= e((string) $document['title']) ?></p>
        <?php endif; ?>
      </div>

      <div class="field">
        <label for="message">Your reply <span class="req">*</span></label>
        <textarea id="message" name="message" rows="8" required maxlength="5000"><?= e($values['message']) ?></textarea>
        <p class="hint">Point to the specific statement you are replying to, and attach a link if you have one.</p>
      </div>

      <div class="field field-hidden" aria-hidden="true">
        <label for="website">Website</label>
        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
      </div>

      <button type="submit">Send reply</button>
    </form>
  <?php endif; ?>
</article>
<?php require __DIR__ . '/partials/footer.php'; ?>
