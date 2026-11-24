<?php
$singleLineFields = [
    'name' => ['Name', 'text', 'name', true, 100],
    'address' => ['Current address', 'text', 'street-address', false, 200],
    'phone' => ['Phone', 'tel', 'tel', true, 32],
    'email' => ['Email', 'email', 'email', true, 254],
];
$multiLineFields = [
    'profession' => ['Profession', 500],
    'field_one' => ['Field one', 1000],
    'field_two' => ['Field two', 1000],
    'field_three' => ['Field three', 1000],
    'message' => ['Message', 5000],
];
?>
<h1>Contact</h1>
<?php if ($sent): ?>
    <p class="notice notice-success" role="status">Thank you. Your message was received.</p>
<?php else: ?>
    <?php if ($errors !== []): ?>
        <p class="notice notice-error" role="alert">Please correct the highlighted fields.</p>
    <?php endif; ?>
    <form method="post" action="/" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
        <div class="trap" aria-hidden="true">
            <label>Leave this empty <input type="text" name="<?= $e($honeypotField) ?>" tabindex="-1" autocomplete="off"></label>
        </div>
        <?php foreach ($singleLineFields as $field => [$label, $type, $autocomplete, $required, $limit]): ?>
            <div class="field<?= isset($errors[$field]) ? ' field-invalid' : '' ?>">
                <label for="<?= $e($field) ?>"><?= $e($label) ?><?= $required ? ' *' : '' ?></label>
                <input id="<?= $e($field) ?>" name="<?= $e($field) ?>" type="<?= $e($type) ?>" autocomplete="<?= $e($autocomplete) ?>" maxlength="<?= $e($limit) ?>" value="<?= $e($values[$field] ?? '') ?>"<?= $required ? ' required' : '' ?>>
                <?php if (isset($errors[$field])): ?><p class="field-error"><?= $e($errors[$field]) ?></p><?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php foreach ($multiLineFields as $field => [$label, $limit]): ?>
            <div class="field<?= isset($errors[$field]) ? ' field-invalid' : '' ?>">
                <label for="<?= $e($field) ?>"><?= $e($label) ?></label>
                <textarea id="<?= $e($field) ?>" name="<?= $e($field) ?>" maxlength="<?= $e($limit) ?>" rows="3"><?= $e($values[$field] ?? '') ?></textarea>
                <?php if (isset($errors[$field])): ?><p class="field-error"><?= $e($errors[$field]) ?></p><?php endif; ?>
            </div>
        <?php endforeach; ?>
        <button type="submit">Send</button>
    </form>
<?php endif; ?>
