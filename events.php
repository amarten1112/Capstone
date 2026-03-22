<?php
/**
 * events.php — Market Events Page
 * Virginia Market Square
 *
 * Phase 4, Task 4.5
 *
 * Changes from Phase 2/3 version:
 *   - Added event_type filter dropdown (market_day, special_event, workshop, promotion)
 *   - Uses prepared statement instead of raw query (security improvement)
 *   - Event count in heading
 *   - Filter buttons + type dropdown work together
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

$page_title = 'Events';

// ─── Collect filter inputs ──────────────────────────────────────────────────
$filter     = $_GET['filter'] ?? 'upcoming';   // upcoming | all | past
$event_type = trim($_GET['type'] ?? '');        // market_day | special_event | workshop | promotion

// Whitelist the filter value to prevent injection
if (!in_array($filter, ['upcoming', 'all', 'past'], true)) {
    $filter = 'upcoming';
}

// ─── Allowed event types (for dropdown + validation) ────────────────────────
$type_options = [
    ''              => 'All Types',
    'market_day'    => 'Market Day',
    'special_event' => 'Special Event',
    'workshop'      => 'Workshop',
    'promotion'     => 'Promotion',
];

if (!isset($type_options[$event_type])) {
    $event_type = '';
}

// ─── Build query with prepared statement ────────────────────────────────────
$where  = [];
$params = [];
$types  = '';

// Time filter
if ($filter === 'upcoming') {
    $where[] = 'event_date >= CURDATE()';
} elseif ($filter === 'past') {
    $where[] = 'event_date < CURDATE()';
}
// 'all' = no date filter

// Event type filter
if ($event_type !== '') {
    $where[]  = 'event_type = ?';
    $params[] = $event_type;
    $types   .= 's';
}

$where_clause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Sort: upcoming = chronological, past = reverse chronological, all = chronological
$order = ($filter === 'past') ? 'event_date DESC' : 'event_date ASC';

$sql = "SELECT * FROM events $where_clause ORDER BY $order";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// ─── Helper: build filter URL preserving both filter params ─────────────────
function events_url(array $overrides = []): string {
    global $filter, $event_type;

    $params = [];
    if ($filter !== 'upcoming') $params['filter'] = $filter;
    if ($event_type !== '')     $params['type']   = $event_type;

    foreach ($overrides as $key => $val) {
        if ($val === null || $val === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $val;
        }
    }

    $qs = http_build_query($params);
    return 'events.php' . ($qs !== '' ? '?' . $qs : '');
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Market Events</h1>
    <div class="btn-group">
        <a href="<?= events_url(['filter' => 'upcoming']) ?>"
           class="btn btn-sm <?= $filter === 'upcoming' ? 'btn-success' : 'btn-outline-success' ?>">Upcoming</a>
        <a href="<?= events_url(['filter' => null]) ?>"
           class="btn btn-sm <?= $filter === 'all' ? 'btn-success' : 'btn-outline-success' ?>">All</a>
        <a href="<?= events_url(['filter' => 'past']) ?>"
           class="btn btn-sm <?= $filter === 'past' ? 'btn-success' : 'btn-outline-success' ?>">Past</a>
    </div>
</div>

<!-- Regular market info banner -->
<div class="alert alert-success d-flex gap-3 align-items-start mb-4">
    <span style="font-size:1.5rem">📅</span>
    <div>
        <strong>Regular Market Days</strong> — Every Thursday, June through October<br>
        <span class="text-muted">2:30 PM – 6:00 PM &nbsp;·&nbsp; 111 South 9th Avenue W, Virginia, MN 55792</span>
    </div>
</div>

<!-- Event type filter -->
<form method="GET" action="events.php" class="row g-2 mb-4">
    <!-- Preserve the current time filter -->
    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">

    <div class="col-md-4">
        <select class="form-select" name="type">
            <?php foreach ($type_options as $key => $label): ?>
                <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                    <?= $event_type === $key ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-success">Filter</button>
        <?php if ($event_type !== ''): ?>
            <a href="events.php?filter=<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>"
               class="btn btn-outline-secondary">Clear Type</a>
        <?php endif; ?>
    </div>
    <div class="col-md-5 text-md-end d-flex align-items-center justify-content-md-end">
        <span class="text-muted">
            <?= $result->num_rows ?> event<?= $result->num_rows !== 1 ? 's' : '' ?> found
        </span>
    </div>
</form>

<?php if ($result && $result->num_rows > 0): ?>
    <div class="row g-4">
        <?php while ($event = $result->fetch_assoc()):
            $date = new DateTime($event['event_date']);
            $is_past = $date < new DateTime('today');
            $type_labels = [
                'market_day'    => ['label' => 'Market Day',    'class' => 'bg-success'],
                'special_event' => ['label' => 'Special Event', 'class' => 'bg-primary'],
                'workshop'      => ['label' => 'Workshop',      'class' => 'bg-info text-dark'],
                'promotion'     => ['label' => 'Promotion',     'class' => 'bg-warning text-dark'],
            ];
            $type_info = $type_labels[$event['event_type']] ?? ['label' => 'Event', 'class' => 'bg-secondary'];
        ?>
            <div class="col-md-6">
                <div class="card h-100 shadow-sm <?= $is_past ? 'opacity-75' : '' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="badge <?= $type_info['class'] ?>"><?= $type_info['label'] ?></span>
                            <?php if ($is_past): ?>
                                <span class="badge bg-secondary">Past</span>
                            <?php endif; ?>
                        </div>
                        <h5 class="card-title"><?= htmlspecialchars($event['event_name'], ENT_QUOTES, 'UTF-8') ?></h5>
                        <p class="mb-1"><strong>📅 <?= $date->format('l, F j, Y') ?></strong></p>
                        <?php if (!empty($event['event_time'])): ?>
                            <p class="mb-2 text-muted">🕐 <?= htmlspecialchars($event['event_time'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <?php if (!empty($event['description'])): ?>
                            <p class="card-text"><?= nl2br(htmlspecialchars($event['description'], ENT_QUOTES, 'UTF-8')) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        <?php if ($event_type !== ''): ?>
            No <?= htmlspecialchars(strtolower($type_options[$event_type]), ENT_QUOTES, 'UTF-8') ?> events found.
            <a href="events.php?filter=<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">Show all types</a>.
        <?php elseif ($filter === 'upcoming'): ?>
            No upcoming events scheduled. Check back soon!
        <?php else: ?>
            No events found.
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
