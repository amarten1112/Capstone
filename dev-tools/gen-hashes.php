<?php
/**
 * dev-tools/gen-hashes.php
 * Generates verified bcrypt hashes for seed_data.sql test passwords.
 *
 * USAGE: http://localhost/Capstone/dev-tools/gen-hashes.php
 * DELETE or block this file before any real-user launch.
 */

// Block access on production (non-localhost)
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!in_array($host, ['localhost', '127.0.0.1'], true)) {
    http_response_code(403);
    die('This tool is only accessible on localhost.');
}

$passwords = [
    'Admin'    => 'Admin1234!',
    'Vendor'   => 'Vendor1234!',
    'Customer' => 'Customer1234!',
];

$results = [];
foreach ($passwords as $label => $plain) {
    $hash = password_hash($plain, PASSWORD_BCRYPT);
    $results[] = [
        'label'   => $label,
        'plain'   => $plain,
        'hash'    => $hash,
        'valid'   => password_verify($plain, $hash),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hash Generator — Dev Tool</title>
    <style>
        body { font-family: monospace; max-width: 900px; margin: 40px auto; padding: 0 20px; background: #f8f9fa; }
        h1   { font-size: 1.4rem; margin-bottom: 4px; }
        p.warn { color: #856404; background: #fff3cd; border: 1px solid #ffc107; padding: 10px 14px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; background: #fff; margin-top: 20px; }
        th, td { text-align: left; padding: 10px 14px; border-bottom: 1px solid #dee2e6; }
        th { background: #343a40; color: #fff; }
        .hash { font-size: 0.85rem; word-break: break-all; }
        .ok   { color: #198754; font-weight: bold; }
        .fail { color: #dc3545; font-weight: bold; }
        pre  { background: #212529; color: #f8f9fa; padding: 16px; border-radius: 4px; overflow-x: auto; font-size: 0.82rem; line-height: 1.6; }
    </style>
</head>
<body>

<h1>Seed Data Hash Generator</h1>
<p class="warn">Dev tool — localhost only. Do not deploy to production.</p>

<table>
    <thead>
        <tr><th>Role</th><th>Password</th><th>bcrypt Hash</th><th>Verified</th></tr>
    </thead>
    <tbody>
        <?php foreach ($results as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['label']) ?></td>
            <td><?= htmlspecialchars($r['plain']) ?></td>
            <td class="hash"><?= htmlspecialchars($r['hash']) ?></td>
            <td class="<?= $r['valid'] ? 'ok' : 'fail' ?>"><?= $r['valid'] ? 'OK' : 'FAIL' ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h2 style="margin-top:32px; font-size:1.1rem;">Ready-to-paste SQL snippets</h2>
<pre>
-- Admin (password: <?= htmlspecialchars($results[0]['plain']) ?>)
'<?= htmlspecialchars($results[0]['hash']) ?>'

-- Vendors (password: <?= htmlspecialchars($results[1]['plain']) ?>)
'<?= htmlspecialchars($results[1]['hash']) ?>'

-- Customers (password: <?= htmlspecialchars($results[2]['plain']) ?>)
'<?= htmlspecialchars($results[2]['hash']) ?>'
</pre>

</body>
</html>
