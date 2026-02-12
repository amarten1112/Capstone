<?php
/**
 * index.php - Virginia Market Square Homepage
 * 
 * This is the main homepage for the Farmers Market Square website.
 * It displays:
 * - Hero banner with mission statement
 * - Featured vendors in a responsive card grid
 * - Upcoming market dates
 * - Call-to-action buttons
 * 
 * Database queries:
 * 1. Get featured vendors (verified vendors)
 * 2. Get upcoming market events
 * 
 * The page uses Bootstrap 5 for responsive design and includes reusable
 * header.php and footer.php files for consistent navigation.
 */

// Set page title (used in header.php)
$page_title = "Home";

// Include database configuration and header
include 'includes/config.php';
include 'includes/header.php';

?>

<!-- HERO BANNER SECTION -->
<div class="hero-banner mb-5" style="background: linear-gradient(135deg, #2d5016 0%, #6ba547 100%); 
    color: white; padding: 80px 20px; border-radius: 8px; text-align: center;">
    <h1 class="display-4 fw-bold mb-3">🌱 We Believe in Local!</h1>
    <p class="lead mb-4">
        Supporting local farmers, makers, and producers within 50 miles of Virginia, Minnesota.
    </p>
    <p class="mb-4">
        Organic | Sustainable | Community-Focused
    </p>
    <div class="d-flex gap-2 justify-content-center flex-wrap">
        <a href="vendors.php" class="btn btn-warning btn-lg">Browse Vendors</a>
        <a href="events.php" class="btn btn-light btn-lg">Market Dates</a>
        <a href="vendor-apply.php" class="btn btn-outline-light btn-lg">Become a Vendor</a>
    </div>
</div>

<!-- MARKET SCHEDULE SECTION -->
<section class="mb-5 p-4" style="background-color: #f0f4e8; border-radius: 8px; border-left: 4px solid #2d5016;">
    <h2 class="mb-4"><i class="fas fa-calendar"></i> Market Schedule</h2>
    <div class="row">
        <div class="col-md-6 mb-3">
            <div style="background: white; padding: 20px; border-radius: 8px; border-top: 4px solid #6ba547;">
                <h4 class="text-success">📅 Open for the Season!</h4>
                <p class="mb-2"><strong>June through October</strong></p>
                <p class="mb-0"><strong>Every Thursday</strong><br>2:30 PM - 6:00 PM</p>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div style="background: white; padding: 20px; border-radius: 8px; border-top: 4px solid #d4a574;">
                <h4 class="text-warning">📍 Location</h4>
                <p class="mb-0">
                    111 South 9th Avenue W<br>
                    Virginia, MN 55792
                </p>
            </div>
        </div>
    </div>
</section>

<!-- FEATURED VENDORS SECTION -->
<section class="mb-5">
    <h2 class="mb-4">🌾 Featured Vendors</h2>
    <p class="lead text-muted mb-4">
        Meet some of our wonderful local producers. Visit the market every Thursday or 
        <a href="vendors.php">browse all vendors</a>.
    </p>
    
    <?php
    /**
     * DATABASE QUERY 1: Get Featured Vendors
     * 
     * This query gets a limited number of verified vendors to display on the homepage.
     * In a real scenario, you might have a "featured" column in the database.
     * For now, we're just getting the first 6 verified vendors.
     * 
     * SQL Explanation:
     * - SELECT * FROM vendors: Get all columns from the vendors table
     * - WHERE verified = 1: Only get verified vendors
     * - LIMIT 6: Only get 6 vendors (for featured section)
     */
    
    $sql_featured_vendors = "SELECT * FROM vendors WHERE verified = 1 LIMIT 6";
    $result_vendors = $conn->query($sql_featured_vendors);
    
    // Check if query was successful
    if (!$result_vendors) {
        die("Query failed: " . $conn->error);
    }
    
    // Check if we have any vendors to display
    if ($result_vendors->num_rows > 0) {
        // Start Bootstrap grid
        echo '<div class="row g-4">';
        
        // Loop through each vendor and display as a card
        while ($vendor = $result_vendors->fetch_assoc()) {
            echo '<div class="col-md-6 col-lg-4 mb-3">';
            echo '  <div class="card h-100 shadow-sm" style="border-top: 4px solid #6ba547; transition: transform 0.3s;">';
            
            // Vendor image
            if (!empty($vendor['image_url'])) {
                echo '    <img src="' . htmlspecialchars($vendor['image_url']) . '" class="card-img-top" alt="' . htmlspecialchars($vendor['vendor_name']) . '" style="height: 200px; object-fit: cover;">';
            } else {
                // Placeholder if no image
                echo '    <div class="card-img-top" style="height: 200px; background-color: #e9ecef; display: flex; align-items: center; justify-content: center;">';
                echo '      <span class="text-muted">No image available</span>';
                echo '    </div>';
            }
            
            echo '    <div class="card-body">';
            echo '      <h5 class="card-title">' . htmlspecialchars($vendor['vendor_name']) . '</h5>';
            echo '      <p class="card-text text-muted" style="font-size: 0.9rem;">';
            echo '        <strong>Category:</strong> ' . htmlspecialchars($vendor['category']) . '<br>';
            echo '      </p>';
            echo '      <p class="card-text" style="font-size: 0.95rem;">';
            // Truncate description to 100 characters
            $description = htmlspecialchars($vendor['description']);
            if (strlen($description) > 100) {
                $description = substr($description, 0, 100) . '...';
            }
            echo $description;
            echo '      </p>';
            echo '    </div>';
            echo '    <div class="card-footer bg-transparent">';
            echo '      <a href="vendor-detail.php?vendor_id=' . $vendor['vendor_id'] . '" class="btn btn-sm btn-success">View Details</a>';
            echo '      <a href="contact.php?vendor_id=' . $vendor['vendor_id'] . '" class="btn btn-sm btn-outline-secondary">Contact</a>';
            echo '    </div>';
            echo '  </div>';
            echo '</div>';
        }
        
        echo '</div>'; // End row
    } else {
        // No vendors found
        echo '<div class="alert alert-info" role="alert">';
        echo '  No vendors available yet. Check back soon!';
        echo '</div>';
    }
    ?>
    
    <div class="text-center mt-4">
        <a href="vendors.php" class="btn btn-lg btn-outline-success">View All Vendors →</a>
    </div>
</section>

<!-- UPCOMING EVENTS SECTION -->
<section class="mb-5">
    <h2 class="mb-4">📅 Upcoming Events</h2>
    
    <?php
    /**
     * DATABASE QUERY 2: Get Upcoming Events
     * 
     * This query gets upcoming market events to display on the homepage.
     * 
     * SQL Explanation:
     * - WHERE event_date >= CURDATE(): Only get events on or after today
     * - ORDER BY event_date ASC: Sort by date (earliest first)
     * - LIMIT 4: Only get 4 upcoming events for the homepage
     */
    
    $sql_upcoming_events = "
        SELECT * FROM events 
        WHERE event_date >= CURDATE() 
        ORDER BY event_date ASC 
        LIMIT 4
    ";
    $result_events = $conn->query($sql_upcoming_events);
    
    if (!$result_events) {
        die("Query failed: " . $conn->error);
    }
    
    if ($result_events->num_rows > 0) {
        echo '<div class="row g-3">';
        
        while ($event = $result_events->fetch_assoc()) {
            // Format the date for display
            $event_date = new DateTime($event['event_date']);
            $formatted_date = $event_date->format('M j, Y');
            
            echo '<div class="col-md-6 col-lg-3 mb-3">';
            echo '  <div class="card shadow-sm" style="border-left: 4px solid #d4a574;">';
            echo '    <div class="card-body">';
            echo '      <h5 class="card-title">' . htmlspecialchars($event['event_name']) . '</h5>';
            echo '      <p class="card-text mb-2">';
            echo '        <strong>📅 ' . $formatted_date . '</strong><br>';
            echo '        <strong>🕐 ' . htmlspecialchars($event['event_time']) . '</strong>';
            echo '      </p>';
            echo '      <p class="card-text text-muted small">';
            $desc = htmlspecialchars($event['description']);
            if (strlen($desc) > 80) {
                $desc = substr($desc, 0, 80) . '...';
            }
            echo $desc;
            echo '      </p>';
            echo '    </div>';
            echo '  </div>';
            echo '</div>';
        }
        
        echo '</div>'; // End row
    } else {
        echo '<div class="alert alert-info" role="alert">';
        echo '  No upcoming events scheduled.';
        echo '</div>';
    }
    
    // Important: Close the database connection when done
    // (Optional but good practice)
    // $conn->close();
    ?>
    
    <div class="text-center mt-4">
        <a href="events.php" class="btn btn-lg btn-outline-success">View Full Calendar →</a>
    </div>
</section>

<!-- WHAT WE'RE ABOUT SECTION -->
<section class="mb-5" style="background-color: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
    <div class="row align-items-center">
        <div class="col-md-6 mb-4 mb-md-0">
            <h2 class="mb-3">Why Shop Local?</h2>
            <ul class="list-unstyled">
                <li class="mb-3">
                    <strong style="color: #2d5016;">🌱 Organic & Sustainable</strong><br>
                    <span class="text-muted">Products are grown and made using eco-friendly, sustainable practices.</span>
                </li>
                <li class="mb-3">
                    <strong style="color: #2d5016;">👨‍🌾 Support Local Producers</strong><br>
                    <span class="text-muted">All vendors must be within 50 miles of Virginia, MN. Your money supports your neighbors.</span>
                </li>
                <li class="mb-3">
                    <strong style="color: #2d5016;">🤝 Build Community</strong><br>
                    <span class="text-muted">Meet the people who grow and make your food. Connect with your community.</span>
                </li>
                <li class="mb-3">
                    <strong style="color: #2d5016;">🏡 Fresh & Quality</strong><br>
                    <span class="text-muted">Farm-to-table products that are fresher and higher quality than mass-produced alternatives.</span>
                </li>
            </ul>
            <a href="about.php" class="btn btn-success mt-3">Learn More About Us</a>
        </div>
        <div class="col-md-6">
            <img src="images/farmers-market-hero.jpg" alt="Farmers Market" class="img-fluid rounded" style="max-width: 100%;">
        </div>
    </div>
</section>

<!-- CALL TO ACTION SECTION -->
<section style="background: linear-gradient(135deg, #2d5016 0%, #6ba547 100%); color: white; padding: 60px 20px; border-radius: 8px; text-align: center;">
    <h2 class="mb-3">Ready to Shop Local?</h2>
    <p class="lead mb-4">
        Browse our vendor directory or visit us in person every Thursday!
    </p>
    <div class="d-flex gap-2 justify-content-center flex-wrap">
        <a href="vendors.php" class="btn btn-warning btn-lg">Browse Vendors</a>
        <a href="contact.php" class="btn btn-light btn-lg">Get in Touch</a>
    </div>
</section>

<?php
// Include footer (contains closing HTML tags and scripts)
include 'includes/footer.php';
?>
