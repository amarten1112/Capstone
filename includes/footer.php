<?php
/**
 * footer.php - Footer (Included on every page)
 * 
 * This file contains the closing of the main container and footer HTML.
 * Include this at the bottom of the page body on every public page.
 * 
 * Usage: include 'includes/footer.php';
 */
?>
    </div><!-- End of main container -->
</main>

<!-- Footer -->
<footer class="bg-dark text-white mt-5 py-5">
    <div class="container">
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <h5>Virginia Market Square</h5>
                <p>Supporting local farmers and makers in Virginia, Minnesota.</p>
            </div>
            <div class="col-md-4 mb-3">
                <h5>Hours</h5>
                <p>
                    <strong>June - October</strong><br>
                    Thursdays 2:30 - 6:00pm<br>
                    111 South 9th Avenue W<br>
                    Virginia, MN 55792
                </p>
            </div>
            <div class="col-md-4 mb-3">
                <h5>Contact</h5>
                <p>
                    Email: <a href="mailto:virginiamarketsquare@gmail.com" class="text-warning">virginiamarketsquare@gmail.com</a><br>
                    <br>
                    <a href="https://www.instagram.com/rusticcedarhomestead/" class="text-warning" target="_blank">Follow us on Instagram</a>
                </p>
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-md-12 text-center">
                <p>&copy; 2026 Virginia Market Square. All rights reserved.</p>
                <p class="text-muted small">Supporting local, organic, sustainable businesses within 50 miles of Virginia, MN</p>
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
