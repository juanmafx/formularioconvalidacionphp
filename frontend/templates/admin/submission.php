<?php
$labels = ['name' => 'Name', 'email' => 'Email', 'phone' => 'Phone', 'address' => 'Current address', 'profession' => 'Profession', 'field_one' => 'Field one', 'field_two' => 'Field two', 'field_three' => 'Field three', 'message' => 'Message'];
$fields = $stored->submission->toArray();
?>
<header class="toolbar">
    <h1>Submission</h1>
    <form method="post" action="/admin/logout">
        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
        <button type="submit">Sign out</button>
    </form>
</header>
<p><a href="/admin">Back to submissions</a></p>
<dl>
    <dt>Received (UTC)</dt><dd><?= $e(gmdate('Y-m-d H:i', $stored->createdAt)) ?></dd>
    <?php foreach ($labels as $field => $label): ?>
        <dt><?= $e($label) ?></dt><dd class="preserve-lines"><?= $e($fields[$field]) ?></dd>
    <?php endforeach; ?>
</dl>
