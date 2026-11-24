<header class="toolbar">
    <h1>Submissions (<?= $e($total) ?>)</h1>
    <form method="post" action="/admin/logout">
        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
        <button type="submit">Sign out</button>
    </form>
</header>
<?php if ($submissions === []): ?>
    <p>No submissions yet.</p>
<?php else: ?>
    <table>
        <thead><tr><th scope="col">Received (UTC)</th><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Notification</th><th scope="col"><span class="visually-hidden">Open</span></th></tr></thead>
        <tbody>
        <?php foreach ($submissions as $stored): ?>
            <tr>
                <td><?= $e(gmdate('Y-m-d H:i', $stored->createdAt)) ?></td>
                <td><?= $e($stored->submission->name) ?></td>
                <td><?= $e($stored->submission->email) ?></td>
                <td><?= $e($stored->notificationStatus) ?></td>
                <td><a href="/admin/submissions/<?= $e($stored->id) ?>">View</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <nav class="pagination" aria-label="Pagination">
        <?php if ($page > 1): ?><a href="/admin?page=<?= $e($page - 1) ?>">Previous</a><?php endif; ?>
        <span>Page <?= $e($page) ?> of <?= $e($lastPage) ?></span>
        <?php if ($page < $lastPage): ?><a href="/admin?page=<?= $e($page + 1) ?>">Next</a><?php endif; ?>
    </nav>
<?php endif; ?>
