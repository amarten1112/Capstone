<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$page_title = 'Events';

$filter = $_GET['filter'] ?? 'upcoming'; // upcoming | all | past

if ($filter === 'past') {
    $sql = "SELECT * FROM events WHERE event_date < CURDATE() ORDER BY event_date DESC";
} elseif ($filter === 'all') {
    $sql = "SELECT * FROM events ORDER BY event_date ASC";
} else {
    $filter = 'upcoming';
    $sql = "SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC";
}

$result = $conn->query($sql);

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Market Events</h1>
    <div class="btn-group">
        <a href="events.php?filter=upcoming" class="btn btn-sm <?= $filter === 'upcoming' ? 'btn-success' : 'btn-outline-success' ?>">Upcoming</a>
        <a href="events.php?filter=all"      class="btn btn-sm <?= $filter === 'all'      ? 'btn-success' : 'btn-outline-success' ?>">All</a>
        <a href="events.php?filter=past"     class="btn btn-sm <?= $filter === 'past'     ? 'btn-success' : 'btn-outline-success' ?>">Past</a>
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
        <?= $filter === 'upcoming' ? 'No upcoming events scheduled. Check back soon!' : 'No events found.' ?>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
