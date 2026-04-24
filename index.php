<?php
/**
 * index.php — Virginia Market Square Homepage
 *
 * Sections:
 *   1. Hero banner
 *   2. Market schedule
 *   3. Featured vendors
 *   4. Upcoming events
 *   5. Why Shop Local
 *   6. Call to action
 *
 * Mobile: sections 2–5 are collapsible accordion panels (tap header to expand).
 * Desktop (md+): standard full layout, collapse state is overridden via CSS.
 */

$page_title = 'Home';

require_once 'includes/config.php';
require_once 'includes/auth.php';

// ─── Featured Vendors ────────────────────────────────────────────────────────
$result_vendors = $conn->query(
    "SELECT v.*,
            (SELECT COUNT(*)
             FROM products p
             WHERE p.vendor_id = v.vendor_id
               AND p.is_available = 1
               AND p.stock_quantity > 0) AS product_count
     FROM vendors v
     WHERE v.verified = 1
     ORDER BY v.featured DESC, v.vendor_name ASC
     LIMIT 6"
);
$vendors_list = $result_vendors ? $result_vendors->fetch_all(MYSQLI_ASSOC) : [];

// Primary category per vendor
$cat_map_result = $conn->query(
    "SELECT p.vendor_id, c.category_name, COUNT(*) AS cnt
     FROM products p
     JOIN categories c ON p.category_id = c.category_id
     WHERE p.is_available = 1
     GROUP BY p.vendor_id, c.category_id, c.category_name
     ORDER BY p.vendor_id, cnt DESC"
);
$vendor_categories = [];
while ($row = $cat_map_result->fetch_assoc()) {
    $vid = (int) $row['vendor_id'];
    if (!isset($vendor_categories[$vid])) {
        $vendor_categories[$vid] = $row['category_name'];
    }
}

// ─── Upcoming Events ─────────────────────────────────────────────────────────
$result_events = $conn->query(
    "SELECT * FROM events
     WHERE event_date >= CURDATE()
     ORDER BY event_date ASC
     LIMIT 4"
);
$events_list = $result_events ? $result_events->fetch_all(MYSQLI_ASSOC) : [];

$type_labels = [
    'market_day'    => ['label' => 'Market Day',    'class' => 'bg-success'],
    'special_event' => ['label' => 'Special Event', 'class' => 'bg-primary'],
    'workshop'      => ['label' => 'Workshop',      'class' => 'bg-info text-dark'],
    'promotion'     => ['label' => 'Promotion',     'class' => 'bg-warning text-dark'],
];

include 'includes/header.php';
?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     1. HERO BANNER — always visible
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="hero-banner mb-5">
    <h1 class="display-4 fw-bold mb-3">We Believe in Local!</h1>
    <p class="lead mb-3">
        Supporting local farmers, makers, and producers within 50 miles of Virginia, Minnesota.
    </p>
    <p class="mb-4 fs-6">
        Organic &middot; Sustainable &middot; Community-Focused
    </p>
    <div class="d-flex gap-2 justify-content-center flex-wrap">
        <a href="products.php" class="btn btn-warning btn-lg">Shop Products</a>
        <a href="vendors.php" class="btn btn-light btn-lg">Browse Vendors</a>
        <a href="vendor-apply.php" class="btn btn-outline-light btn-lg">Become a Vendor</a>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     2. MARKET SCHEDULE
     ═══════════════════════════════════════════════════════════════════════ -->
<section class="mb-4 section-highlight">

    <!-- Mobile toggle header -->
    <div class="section-collapse-toggle d-flex d-md-none justify-content-between align-items-center collapsed"
         data-bs-toggle="collapse" data-bs-target="#section-schedule"
         aria-expanded="false" aria-controls="section-schedule">
        <h2 class="mb-0">Market Schedule</h2>
        <span class="collapse-chevron">&#9660;</span>
    </div>

    <!-- Desktop header -->
    <h2 class="mb-4 d-none d-md-block">Market Schedule</h2>

    <!-- Content -->
    <div class="collapse mobile-collapsible" id="section-schedule">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="info-card card-top-accent-green h-100">
                    <h5 class="text-secondary-green mb-3">📅 Open for the Season!</h5>
                    <p class="mb-1"><strong>June through October</strong></p>
                    <p class="mb-0"><strong>Every Thursday</strong><br>2:30 PM – 6:00 PM</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-card card-top-accent-earth h-100">
                    <h5 class="text-accent-earth mb-3">📍 Location</h5>
                    <p class="mb-1">111 South 9th Avenue W</p>
                    <p class="mb-0">Virginia, MN 55792</p>
                </div>
            </div>
        </div>
    </div>

</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     3. FEATURED VENDORS
     ═══════════════════════════════════════════════════════════════════════ -->
<section class="mb-4">

    <!-- Mobile toggle header -->
    <div class="section-collapse-toggle d-flex d-md-none justify-content-between align-items-center collapsed"
         data-bs-toggle="collapse" data-bs-target="#section-vendors"
         aria-expanded="false" aria-controls="section-vendors">
        <h2 class="mb-0">
            Featured Vendors
            <?php if (!empty($vendors_list)): ?>
                <span class="section-count">(<?= count($vendors_list) ?>)</span>
            <?php endif; ?>
        </h2>
        <span class="collapse-chevron">&#9660;</span>
    </div>

    <!-- Desktop header -->
    <div class="d-none d-md-flex justify-content-between align-items-end mb-4">
        <div>
            <h2 class="mb-1">Featured Vendors</h2>
            <p class="text-muted mb-0">
                Meet our local producers — visit the market every Thursday or
                <a href="vendors.php">browse all vendors</a>.
            </p>
        </div>
    </div>

    <!-- Content -->
    <div class="collapse mobile-collapsible" id="section-vendors">

        <?php if (!empty($vendors_list)): ?>
            <div class="row g-4">
                <?php foreach ($vendors_list as $vendor):
                    $vid = (int) $vendor['vendor_id'];
                    $cat_display = $vendor_categories[$vid] ?? $vendor['category_text'] ?? '';
                ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm vendor-card card-top-accent-green">

                            <?php if (!empty($vendor['image_url'])): ?>
                                <a href="vendor-detail.php?vendor_id=<?= $vid ?>">
                                    <img src="<?= htmlspecialchars($vendor['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                                         class="card-img-top vendor-card-image"
                                         alt="<?= htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8') ?>">
                                </a>
                            <?php else: ?>
                                <div class="card-img-top vendor-card-image-placeholder">
                                    <span>No image available</span>
                                </div>
                            <?php endif; ?>

                            <div class="card-body">
                                <h5 class="card-title mb-1">
                                    <a href="vendor-detail.php?vendor_id=<?= $vid ?>"
                                       class="text-decoration-none text-dark">
                                        <?= htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </h5>

                                <?php if ($cat_display !== ''): ?>
                                    <span class="badge bg-success mb-1">
                                        <?= htmlspecialchars($cat_display, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($vendor['featured']): ?>
                                    <span class="badge bg-warning text-dark mb-1">Featured</span>
                                <?php endif; ?>

                                <small class="text-muted d-block mb-2">
                                    <?= (int) $vendor['product_count'] ?> product<?= (int) $vendor['product_count'] !== 1 ? 's' : '' ?>
                                </small>

                                <p class="card-text text-muted small">
                                    <?php
                                    $bio = $vendor['short_bio'] ?? $vendor['description'] ?? '';
                                    $bio = htmlspecialchars($bio, ENT_QUOTES, 'UTF-8');
                                    echo strlen($bio) > 100 ? substr($bio, 0, 100) . '&hellip;' : $bio;
                                    ?>
                                </p>
                            </div>

                            <div class="card-footer bg-transparent">
                                <a href="vendor-detail.php?vendor_id=<?= $vid ?>"
                                   class="btn btn-sm btn-success">View Storefront</a>
                                <a href="contact.php?vendor_id=<?= $vid ?>"
                                   class="btn btn-sm btn-outline-secondary">Contact</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                No vendors available yet. Check back soon!
            </div>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="vendors.php" class="btn btn-lg btn-outline-success">View All Vendors →</a>
        </div>

    </div><!-- /#section-vendors -->

</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     4. UPCOMING EVENTS
     ═══════════════════════════════════════════════════════════════════════ -->
<section class="mb-4">

    <!-- Mobile toggle header -->
    <div class="section-collapse-toggle d-flex d-md-none justify-content-between align-items-center collapsed"
         data-bs-toggle="collapse" data-bs-target="#section-events"
         aria-expanded="false" aria-controls="section-events">
        <h2 class="mb-0">
            Upcoming Events
            <?php if (!empty($events_list)): ?>
                <span class="section-count">(<?= count($events_list) ?>)</span>
            <?php endif; ?>
        </h2>
        <span class="collapse-chevron">&#9660;</span>
    </div>

    <!-- Desktop header -->
    <div class="d-none d-md-flex justify-content-between align-items-end mb-4">
        <h2 class="mb-0">Upcoming Events</h2>
        <a href="events.php" class="btn btn-outline-success btn-sm">View All →</a>
    </div>

    <!-- Content -->
    <div class="collapse mobile-collapsible" id="section-events">

        <?php if (!empty($events_list)): ?>
            <div class="row g-3">
                <?php foreach ($events_list as $event):
                    $event_date = new DateTime($event['event_date']);
                    $type_info  = $type_labels[$event['event_type'] ?? '']
                                  ?? ['label' => 'Event', 'class' => 'bg-secondary'];
                ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="card h-100 shadow-sm card-left-accent-earth">
                            <div class="card-body">
                                <span class="badge <?= $type_info['class'] ?> mb-2">
                                    <?= $type_info['label'] ?>
                                </span>
                                <h6 class="card-title">
                                    <?= htmlspecialchars($event['event_name'], ENT_QUOTES, 'UTF-8') ?>
                                </h6>
                                <p class="card-text mb-1">
                                    <strong>📅 <?= $event_date->format('M j, Y') ?></strong>
                                </p>
                                <?php if (!empty($event['event_time'])): ?>
                                    <p class="card-text text-muted small mb-2">
                                        🕐 <?= htmlspecialchars($event['event_time'], ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($event['description'])): ?>
                                    <p class="card-text text-muted small mb-0">
                                        <?php
                                        $desc = htmlspecialchars($event['description'], ENT_QUOTES, 'UTF-8');
                                        echo strlen($desc) > 80 ? substr($desc, 0, 80) . '&hellip;' : $desc;
                                        ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-3 d-md-none">
                <a href="events.php" class="btn btn-outline-success btn-sm">View All Events →</a>
            </div>

        <?php else: ?>
            <div class="alert alert-info">
                No upcoming events scheduled. Check back soon!
            </div>
        <?php endif; ?>

    </div><!-- /#section-events -->

</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     5. WHY SHOP LOCAL
     ═══════════════════════════════════════════════════════════════════════ -->
<section class="mb-4 about-section">

    <!-- Mobile toggle header -->
    <div class="section-collapse-toggle d-flex d-md-none justify-content-between align-items-center collapsed"
         data-bs-toggle="collapse" data-bs-target="#section-about"
         aria-expanded="false" aria-controls="section-about">
        <h2 class="mb-0">Why Shop Local?</h2>
        <span class="collapse-chevron">&#9660;</span>
    </div>

    <!-- Content -->
    <div class="collapse mobile-collapsible" id="section-about">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <h2 class="mb-3 d-none d-md-block">Why Shop Local?</h2>
                <div class="mb-3">
                    <strong class="text-primary-green">🌱 Organic &amp; Sustainable</strong><br>
                    <span class="text-muted">Products grown and made using eco-friendly, sustainable practices.</span>
                </div>
                <div class="mb-3">
                    <strong class="text-primary-green">👨‍🌾 Support Local Producers</strong><br>
                    <span class="text-muted">All vendors are within 50 miles of Virginia, MN. Your money supports your neighbors.</span>
                </div>
                <div class="mb-3">
                    <strong class="text-primary-green">🤝 Build Community</strong><br>
                    <span class="text-muted">Meet the people who grow and make your food. Connect with your community.</span>
                </div>
                <div class="mb-3">
                    <strong class="text-primary-green">🏡 Fresh &amp; Quality</strong><br>
                    <span class="text-muted">Farm-to-table products that are fresher and higher quality than mass-produced alternatives.</span>
                </div>
                <a href="about.php" class="btn btn-success mt-2">Learn More About Us</a>
            </div>
            <div class="col-lg-6">
                <img src="assets/images/hero/farmers-market-hero.webp"
                     alt="Fresh produce at Virginia Market Square"
                     class="img-fluid rounded shadow-sm"
                     loading="lazy">
            </div>
        </div>
    </div><!-- /#section-about -->

</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     6. CALL TO ACTION — always visible
     ═══════════════════════════════════════════════════════════════════════ -->
<section class="cta-section">
    <h2 class="mb-3">Ready to Shop Local?</h2>
    <p class="lead mb-4">
        Browse our vendor directory, shop products online, or visit us in person every Thursday!
    </p>
    <div class="d-flex gap-2 justify-content-center flex-wrap">
        <a href="products.php" class="btn btn-warning btn-lg">Shop Products</a>
        <a href="vendors.php" class="btn btn-light btn-lg">Browse Vendors</a>
        <a href="contact.php" class="btn btn-outline-light btn-lg">Get in Touch</a>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
