<h1>Admin sign in</h1>
<?php if ($error !== ''): ?><p class="notice notice-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
<form method="post" action="/admin/login">
    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
    <div class="field">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" autocomplete="username" maxlength="64" required>
    </div>
    <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" maxlength="256" required>
    </div>
    <button type="submit">Continue</button>
</form>
