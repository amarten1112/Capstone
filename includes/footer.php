<?php
/**
 * footer.php - Footer (Included on every page)
 *
 * Phase 6, Task 6.9 — Footer improvements:
 *   - Removed bg-dark (CSS handles footer background via --primary-dark)
 *   - 4-column layout: Brand, Hours, Quick Links, Contact
 *   - Quick Links column for easy navigation
 *   - Become a Vendor CTA
 *   - Bottom bar with copyright and tagline
 *
 * Usage: include 'includes/footer.php';
 */
?>
    </div><!-- End of main container -->
</main>

<!-- Footer -->
<footer class="text-white mt-5 py-5">
    <div class="container">
        <div class="row mb-4">

            <!-- Brand & Mission -->
            <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                <h5>🌱 Virginia Market Square</h5>
                <p class="small">Supporting local farmers, bakers, and makers in Virginia, Minnesota and the surrounding Iron Range.</p>
            </div>

            <!-- Market Hours & Location -->
            <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                <h5>Visit Us</h5>
                <p class="small mb-1"><strong>June – October</strong></p>
                <p class="small mb-1">Every Thursday</p>
                <p class="small mb-1">2:30 PM – 6:00 PM</p>
                <p class="small mb-0">
                    111 South 9th Avenue W<br>
                    Virginia, MN 55792
                </p>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                <h5>Quick Links</h5>
                <ul class="list-unstyled small">
                    <li class="mb-1"><a href="<?= $base_url ?>/vendors.php">Browse Vendors</a></li>
                    <li class="mb-1"><a href="<?= $base_url ?>/products.php">Shop Products</a></li>
                    <li class="mb-1"><a href="<?= $base_url ?>/events.php">Market Events</a></li>
                    <li class="mb-1"><a href="<?= $base_url ?>/about.php">About Us</a></li>
                    <li class="mb-1"><a href="<?= $base_url ?>/contact.php">Contact</a></li>
                    <li class="mb-1"><a href="<?= $base_url ?>/vendor-apply.php">Become a Vendor</a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                <h5>Contact</h5>
                <p class="small mb-2">
                    <a href="mailto:virginiamarketsquare@gmail.com">
                        virginiamarketsquare@gmail.com
                    </a>
                </p>
                <p class="small mb-0">
                    <a href="https://www.instagram.com/rusticcedarhomestead/" target="_blank" rel="noopener">
                        Follow us on Instagram
                    </a>
                </p>
            </div>

        </div>

        <hr>

        <!-- Bottom bar -->
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <p class="small mb-0">&copy; <?= date('Y') ?> Virginia Market Square. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <p class="small mb-0 text-white-50">
                    Local &middot; Organic &middot; Sustainable &middot; Within 50 miles of Virginia, MN
                </p>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap Bundle JS (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JavaScript -->
<script src="<?= $base_url ?>/js/script.js"></script>

</body>
</html>
