<h1>Two-factor authentication</h1>
<?php if ($error !== ''): ?><p class="notice notice-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
<form method="post" action="/admin/two-factor">
    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
    <div class="field">
        <label for="code">Authenticator or recovery code</label>
        <input id="code" name="code" type="text" autocomplete="one-time-code" maxlength="19" required>
    </div>
    <button type="submit">Verify</button>
</form>
