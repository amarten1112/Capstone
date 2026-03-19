<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$page_title = 'About Us';
include 'includes/header.php';
?>

<!-- Hero -->
<div class="text-center mb-5">
    <h1 class="display-5 fw-bold">About Virginia Market Square</h1>
    <p class="lead col-md-8 mx-auto">
        A community-driven farmers market connecting local producers with residents of Virginia, Minnesota and the surrounding Iron Range.
    </p>
</div>

<!-- Mission -->
<section class="mb-5">
    <div class="row align-items-center">
        <div class="col-md-6 mb-4 mb-md-0">
            <h2>Our Mission</h2>
            <p>Virginia Market Square exists to strengthen our local food system by making it easy for residents to buy directly from the farmers, bakers, and makers who live and work within 50 miles of Virginia, MN.</p>
            <p>We believe that knowing where your food comes from — and who grew it — matters. Every purchase at the market supports a local family and keeps money circulating in our community.</p>
        </div>
        <div class="col-md-6">
            <div class="row g-3">
                <div class="col-6">
                    <div class="card text-center border-success h-100">
                        <div class="card-body">
                            <div style="font-size:2rem">🌱</div>
                            <h6 class="mt-2">Organic &amp; Sustainable</h6>
                            <p class="small text-muted mb-0">Eco-friendly growing and production practices</p>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card text-center border-success h-100">
                        <div class="card-body">
                            <div style="font-size:2rem">📍</div>
                            <h6 class="mt-2">Truly Local</h6>
                            <p class="small text-muted mb-0">All vendors within 50 miles of Virginia, MN</p>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card text-center border-success h-100">
                        <div class="card-body">
                            <div style="font-size:2rem">🤝</div>
                            <h6 class="mt-2">Community First</h6>
                            <p class="small text-muted mb-0">Building relationships between neighbors</p>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card text-center border-success h-100">
                        <div class="card-body">
                            <div style="font-size:2rem">🏡</div>
                            <h6 class="mt-2">Fresh &amp; Quality</h6>
                            <p class="small text-muted mb-0">Farm-to-table products you can trust</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Market Info -->
<section class="mb-5 section-highlight">
    <h2 class="mb-4">Visit the Market</h2>
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="info-card">
                <h5>📅 Season</h5>
                <p class="mb-0">June through October<br>Every <strong>Thursday</strong></p>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="info-card">
                <h5>🕐 Hours</h5>
                <p class="mb-0"><strong>2:30 PM – 6:00 PM</strong></p>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="info-card">
                <h5>📍 Location</h5>
                <p class="mb-0">111 South 9th Avenue W<br>Virginia, MN 55792</p>
            </div>
        </div>
    </div>
</section>

<!-- Get Involved -->
<section class="cta-section">
    <h2 class="mb-3">Get Involved</h2>
    <p class="lead mb-4">Whether you're a shopper, a vendor, or just curious — there's a place for you at Virginia Market Square.</p>
    <div class="d-flex gap-3 justify-content-center flex-wrap">
        <a href="vendors.php"     class="btn btn-warning btn-lg">Browse Vendors</a>
        <a href="vendor-apply.php" class="btn btn-light btn-lg">Become a Vendor</a>
        <a href="contact.php"     class="btn btn-outline-light btn-lg">Contact Us</a>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
