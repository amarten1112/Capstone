-- =============================================================================
-- seed_data.sql
-- Virginia Market Square — Test Data / Fixtures
-- Phase 2, Step 4: Populate test database
-- =============================================================================
-- RUN AFTER: database.sql (schema must exist first)
-- HOW TO RUN: phpMyAdmin → farmers_market DB → SQL tab → paste & execute
--
-- TEST CREDENTIALS:
--   Admin:     admin@virginiamn.gov      password: Admin1234!
--   Vendors:   [see emails below]        password: Vendor1234!
--   Customers: [see emails below]        password: Customer1234!
--
-- PASSWORD HASHES: Standard PHP bcrypt ($2y$10$)
--   If password_verify() fails, regenerate hashes in PHP with:
--     echo password_hash('Admin1234!', PASSWORD_DEFAULT);
--   Then replace the hash strings below before re-running.
--
-- DATA THEME: Realistic Iron Range / Northern Minnesota vendors
--   All vendors placed within 50 miles of Virginia, MN 55792
-- =============================================================================

-- Use the correct database
-- BEFORE: USE `farmers_market`;
-- AFTER (commented out — safe to leave in, never executes):
-- USE `farmers_market`; -- Removed: handled by phpMyAdmin context on shared hosting

-- Wrap truncates in a procedure so FK checks stay off in phpMyAdmin
DROP PROCEDURE IF EXISTS `truncate_all_tables`;

DELIMITER $$
CREATE PROCEDURE `truncate_all_tables`()
BEGIN
  SET FOREIGN_KEY_CHECKS = 0;
  TRUNCATE TABLE `vendor_applications`;
  TRUNCATE TABLE `contacts`;
  TRUNCATE TABLE `transactions`;
  TRUNCATE TABLE `order_items`;
  TRUNCATE TABLE `orders`;
  TRUNCATE TABLE `cart`;
  TRUNCATE TABLE `products`;
  TRUNCATE TABLE `categories`;
  TRUNCATE TABLE `customers`;
  TRUNCATE TABLE `vendors`;
  TRUNCATE TABLE `events`;
  TRUNCATE TABLE `users`;
  SET FOREIGN_KEY_CHECKS = 1;
END$$
DELIMITER ;

CALL `truncate_all_tables`();
DROP PROCEDURE IF EXISTS `truncate_all_tables`;


-- =============================================================================
-- 1. USERS  (14 total: 1 admin, 9 vendors, 4 customers)
-- =============================================================================
INSERT INTO `users`
  (`user_id`, `email`, `password_hash`, `full_name`, `user_type`, `created_date`, `is_active`)
VALUES

-- Admin
(1,  'admin@virginiamn.gov',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
     'Market Administrator', 'admin', '2026-01-15 09:00:00', 1),

-- Vendors (password: Vendor1234!)
(2,  'hello@ironrangeroots.com',
     '$2y$10$TKh8H1.PyfSi8Ev0rMFcuOW1ndKxl6Z.9/Gxe3RtC7R3f3Xy2Km7m',
     'Margaret Korhonen', 'vendor', '2026-01-20 10:00:00', 1),

(3,  'orders@northwoodsbakery.com',
     '$2y$10$TKh8H1.PyfSi8Ev0rMFcuOW1ndKxl6Z.9/Gxe3RtC7R3f3Xy2Km7m',
     'Dale Makinen', 'vendor', '2026-01-22 11:00:00', 1),

(4,  'info@birchwoodcreamery.com',
     '$2y$10$TKh8H1.PyfSi8Ev0rMFcuOW1ndKxl6Z.9/Gxe3RtC7R3f3Xy2Km7m',
     'Sonja Halvorsen', 'vendor', '2026-01-25 09:30:00', 1),

(5,  'shop@rangecrafthouse.com',
     '$2y$10$TKh8H1.PyfSi8Ev0rMFcuOW1ndKxl6Z.9/Gxe3RtC7R3f3Xy2Km7m',
     'Art Leppanen', 'vendor', '2026-02-01 14:00:00', 1),

(6,  'fresh@taconiteacres.com',
     '$2y$10$TKh8H1.PyfSi8Ev0rMFcuOW1ndKxl6Z.9/Gxe3RtC7R3f3Xy2Km7m',
     'Eino Virtanen', 'vendor', '2026-02-03 08:00:00', 1),

(7,  'hi@mesabiherbs.com',
     '$2y$10$TKh8H1.PyfSi8Ev0rMFcuOW1ndKxl6Z.9/Gxe3RtC7R3f3Xy2Km7m',
     'Liisa Saarinen', 'vendor', '2026-02-05 10:30:00', 1),

(8,  'contact@bearcountrymeats.com',
     '$2y$10$TKh8H1.PyfSi8Ev0rMFcuOW1ndKxl6Z.9/Gxe3RtC7R3f3Xy2Km7m',
     'Paul Ojala', 'vendor', '2026-02-08 13:00:00', 1),

(9,  'studio@pineknollpottery.com',
     '$2y$10$TKh8H1.PyfSi8Ev0rMFcuOW1ndKxl6Z.9/Gxe3RtC7R3f3Xy2Km7m',
     'Aino Myllymaki', 'vendor', '2026-02-10 09:00:00', 1),

(10, 'hello@hibernatingbearapiary.com',
     '$2y$10$TKh8H1.PyfSi8Ev0rMFcuOW1ndKxl6Z.9/Gxe3RtC7R3f3Xy2Km7m',
     'Rex Kangas', 'vendor', '2026-02-12 11:00:00', 1),

-- Customers (password: Customer1234!)
(11, 'jenna.toivonen@gmail.com',
     '$2y$10$eI2yVLUfMoXjXd/n9RVpL.Yd5Gy7DvzC6VK2DKHh7VdHkX6Z5Z3e',
     'Jenna Toivonen', 'customer', '2026-02-15 16:00:00', 1),

(12, 'mike.anderson@hotmail.com',
     '$2y$10$eI2yVLUfMoXjXd/n9RVpL.Yd5Gy7DvzC6VK2DKHh7VdHkX6Z5Z3e',
     'Mike Anderson', 'customer', '2026-02-18 10:30:00', 1),

(13, 'sara.hill@yahoo.com',
     '$2y$10$eI2yVLUfMoXjXd/n9RVpL.Yd5Gy7DvzC6VK2DKHh7VdHkX6Z5Z3e',
     'Sara Hill', 'customer', '2026-02-20 14:15:00', 1),

(14, 'tom.ranta@gmail.com',
     '$2y$10$eI2yVLUfMoXjXd/n9RVpL.Yd5Gy7DvzC6VK2DKHh7VdHkX6Z5Z3e',
     'Tom Ranta', 'customer', '2026-03-01 09:45:00', 1);


-- =============================================================================
-- 2. VENDORS  (9 vendors, all verified and featured)
-- category_text bridges to existing index.php until Phase 4 JOIN update
-- =============================================================================
INSERT INTO `vendors`
  (`vendor_id`, `user_id`, `vendor_name`, `business_email`, `phone`,
   `description`, `short_bio`, `image_url`, `website_url`,
   `category_text`, `miles_from_va`, `verified`, `featured`, `created_date`)
VALUES

(1, 2, 'Iron Range Roots Farm',
 'hello@ironrangeroots.com', '218-555-0101',
 'A fourth-generation family farm outside of Biwabik, growing certified organic vegetables and fruit since 1982. We believe in honest food grown the old-fashioned way — no shortcuts, no chemicals, just good Minnesota soil and hard work.',
 'Certified organic vegetables and fruit from a fourth-generation Biwabik family farm.',
 'assets/images/vendors/iron-range-roots.jpg',
 'https://ironrangeroots.com',
 'Produce', 12.4, 1, 1, '2026-01-20 10:00:00'),

(2, 3, 'Northwoods Bakery',
 'orders@northwoodsbakery.com', '218-555-0202',
 'Traditional Finnish-American breads and pastries baked fresh every Wednesday and Thursday morning in Eveleth. Our recipes trace back to the Makinen family emigrating from Oulu, Finland in 1910. Try our cardamom pulla.',
 'Finnish-American breads and pastries baked fresh in Eveleth. Est. 1910.',
 'assets/images/vendors/northwoods-bakery.jpg',
 NULL,
 'Baked Goods', 8.2, 1, 1, '2026-01-22 11:00:00'),

(3, 4, 'Birchwood Creamery',
 'info@birchwoodcreamery.com', '218-555-0303',
 'Small-batch artisan cheeses and dairy products from our herd of 22 Jersey cows on our farm near Aurora. All milk is non-homogenized and our animals are pasture-raised year-round.',
 'Artisan cheeses and dairy from pasture-raised Jersey cows near Aurora.',
 'assets/images/vendors/birchwood-creamery.jpg',
 'https://birchwoodcreamery.com',
 'Dairy & Eggs', 18.7, 1, 1, '2026-01-25 09:30:00'),

(4, 5, 'Range Craft House',
 'shop@rangecrafthouse.com', '218-555-0404',
 'Handmade woodwork, iron art, and home goods crafted in a workshop in Gilbert, MN. Art Leppanen spent 20 years working the Iron Range mines before turning to craftsmanship full-time.',
 'Handmade woodwork and iron art from a Gilbert craftsman with Iron Range roots.',
 'assets/images/vendors/range-craft-house.jpg',
 NULL,
 'Handmade Crafts', 6.1, 1, 1, '2026-02-01 14:00:00'),

(5, 6, 'Taconite Acres',
 'fresh@taconiteacres.com', '218-555-0505',
 'Pastured pork, free-range eggs, and seasonal vegetables grown on 80 acres just outside Mountain Iron. We rotate our pigs through the woodlands and raise heritage breed Berkshire and Tamworth crosses for exceptional flavor.',
 'Pastured pork, free-range eggs, and vegetables from 80 acres near Mountain Iron.',
 'assets/images/vendors/taconite-acres.jpg',
 'https://taconiteacres.com',
 'Meat & Poultry', 9.3, 1, 1, '2026-02-03 08:00:00'),

(6, 7, 'Mesabi Herbs & Botanicals',
 'hi@mesabiherbs.com', '218-555-0606',
 'Certified organic medicinal herbs, tinctures, teas, and herbal skincare products grown and handcrafted in Hibbing. Liisa has practiced herbalism for 18 years.',
 'Organic medicinal herbs, teas, and herbal skincare grown and crafted in Hibbing.',
 'assets/images/vendors/mesabi-herbs.jpg',
 'https://mesabiherbs.com',
 'Plants & Herbs', 29.5, 1, 1, '2026-02-05 10:30:00'),

(7, 8, 'Bear Country Meats',
 'contact@bearcountrymeats.com', '218-555-0707',
 'Grass-fed beef and pasture-raised lamb from the Ojala family ranch in Cook, MN. All animals are born and raised on our land, dry-aged on site, and processed at a USDA-inspected facility.',
 'Grass-fed beef and pasture-raised lamb from a family ranch in Cook, MN.',
 'assets/images/vendors/bear-country-meats.jpg',
 NULL,
 'Meat & Poultry', 42.0, 1, 1, '2026-02-08 13:00:00'),

(8, 9, 'Pine Knoll Pottery',
 'studio@pineknollpottery.com', '218-555-0808',
 'Wheel-thrown and hand-built stoneware pottery fired in a wood-burning kiln in Chisholm. Aino Myllymaki studied ceramics at Duluth and has been making functional and sculptural ware on the Range for over a decade.',
 'Wheel-thrown stoneware pottery fired in a wood-burning kiln in Chisholm.',
 'assets/images/vendors/pine-knoll-pottery.jpg',
 'https://pineknollpottery.com',
 'Handmade Crafts', 22.1, 1, 1, '2026-02-10 09:00:00'),

(9, 10, 'Hibernating Bear Apiary',
 'hello@hibernatingbearapiary.com', '218-555-0909',
 'Raw wildflower and clover honey, beeswax candles, and lip balms from 40 hives managed in the forests and meadows around Orr, MN. Rex has kept bees since 2009 and never heat-processes his honey.',
 'Raw wildflower honey, beeswax candles, and lip balms from 40 hives near Orr.',
 'assets/images/vendors/hibernating-bear-apiary.jpg',
 NULL,
 'Honey & Preserves', 47.3, 1, 0, '2026-02-12 11:00:00');


-- =============================================================================
-- 3. CUSTOMERS  (4 test customers linked to user_ids 11-14)
-- =============================================================================
INSERT INTO `customers`
  (`customer_id`, `user_id`, `phone`, `address_line1`, `city`, `state`, `zip`, `created_date`)
VALUES
(1, 11, '218-555-1101', '412 Chestnut St',     'Virginia',     'MN', '55792', '2026-02-15 16:00:00'),
(2, 12, '218-555-1202', '88 Pine Ridge Rd',    'Eveleth',      'MN', '55734', '2026-02-18 10:30:00'),
(3, 13, '218-555-1303', '27 Lakeview Dr',      'Gilbert',      'MN', '55741', '2026-02-20 14:15:00'),
(4, 14, '218-555-1404', '315 Birch Ave N',     'Mountain Iron','MN', '55768', '2026-03-01 09:45:00');


-- =============================================================================
-- 4. CATEGORIES  (7 categories, sort_order controls menu sequence)
-- =============================================================================
INSERT INTO `categories`
  (`category_id`, `category_name`, `slug`, `description`, `sort_order`)
VALUES
(1, 'Produce',          'produce',          'Fresh fruits and vegetables grown locally.',                          1),
(2, 'Baked Goods',      'baked-goods',      'Breads, pastries, cookies, and other baked items.',                  2),
(3, 'Dairy & Eggs',     'dairy-eggs',       'Cheese, milk, butter, yogurt, and farm-fresh eggs.',                 3),
(4, 'Meat & Poultry',   'meat-poultry',     'Grass-fed beef, pastured pork, lamb, and poultry.',                  4),
(5, 'Honey & Preserves','honey-preserves',  'Raw honey, jams, jellies, pickles, and fermented goods.',            5),
(6, 'Plants & Herbs',   'plants-herbs',     'Medicinal herbs, teas, seedlings, potted plants, and botanicals.',   6),
(7, 'Handmade Crafts',  'handmade-crafts',  'Pottery, woodwork, ironwork, candles, and other handmade items.',    7);


-- =============================================================================
-- 5. PRODUCTS  (42 products across all 9 vendors)
-- =============================================================================
INSERT INTO `products`
  (`product_id`, `vendor_id`, `category_id`, `product_name`, `description`,
   `price`, `stock_quantity`, `unit`, `is_available`, `featured`, `created_date`)
VALUES

-- Iron Range Roots Farm  (vendor 1 — Produce)
(1,  1, 1, 'Heirloom Tomato Mix',
 'A colorful mix of Cherokee Purple, Brandywine, and Green Zebra tomatoes. Vine-ripened, picked the morning of market.',
 5.50, 40, 'per lb', 1, 1, '2026-03-01 08:00:00'),

(2,  1, 1, 'Sweet Corn — Dozen',
 'Peaches and Cream and Honey Select varieties. Harvested the morning of market. Sweetest corn north of the Twin Cities.',
 6.00, 60, 'per dozen', 1, 1, '2026-03-01 08:00:00'),

(3,  1, 1, 'Organic Salad Mix',
 'Baby arugula, red oak leaf, spinach, and nasturtium petals. Pre-washed, ready to dress. Certified organic.',
 4.50, 50, 'per 5 oz bag', 1, 0, '2026-03-01 08:00:00'),

(4,  1, 1, 'Hardneck Garlic',
 'Music and Chesnok Red varieties. Cured and ready to store. Grown without pesticides on this farm since 1994.',
 3.00, 80, 'per bulb', 1, 0, '2026-03-01 08:00:00'),

(5,  1, 1, 'Butternut Squash',
 'Large, dense butternut averaging 3-4 lbs each. Perfect for soups, roasting, and storing through winter.',
 4.00, 35, 'each', 1, 0, '2026-03-01 08:00:00'),

-- Northwoods Bakery  (vendor 2 — Baked Goods)
(6,  2, 2, 'Cardamom Pulla Braid',
 'Traditional Finnish sweet bread braided with butter, cardamom, and pearl sugar. Baked fresh Thursday morning. A family recipe since 1912.',
 9.00, 20, 'per loaf', 1, 1, '2026-03-01 09:00:00'),

(7,  2, 2, 'Sourdough Rye Loaf',
 'Dense, tangy sourdough rye using a 40-year-old starter and locally milled dark rye flour. Keeps well for a full week.',
 8.50, 18, 'per loaf', 1, 0, '2026-03-01 09:00:00'),

(8,  2, 2, 'Wild Blueberry Scones — Half Dozen',
 'Buttermilk scones loaded with wild Minnesota blueberries, finished with a light lemon glaze. Best eaten the same day.',
 7.00, 25, 'per half dozen', 1, 1, '2026-03-01 09:00:00'),

(9,  2, 2, 'Rieska Flatbread',
 'Traditional Finnish barley flatbread, soft and slightly dense. Best with butter and smoked fish. Baked in rounds.',
 5.00, 30, 'per round', 1, 0, '2026-03-01 09:00:00'),

(10, 2, 2, 'Cast Iron Cinnamon Roll',
 'Oversized cinnamon roll with cream cheese icing. Baked in cast iron for a caramelized bottom. Worth every penny.',
 4.50, 24, 'each', 1, 0, '2026-03-01 09:00:00'),

-- Birchwood Creamery  (vendor 3 — Dairy & Eggs)
(11, 3, 3, 'Aged Sharp Cheddar',
 'Aged 9 months on the farm from our own Jersey milk. Rich, buttery depth you will not find in grocery store cheddar.',
 12.00, 30, 'per 8 oz block', 1, 1, '2026-03-01 10:00:00'),

(12, 3, 3, 'Fresh Chevre',
 'Soft, tangy fresh goat cheese made twice weekly. Excellent on crackers, in salads, or spread on crusty bread.',
 8.00, 20, 'per 4 oz', 1, 0, '2026-03-01 10:00:00'),

(13, 3, 3, 'Whole Milk Yogurt',
 'European-style whole milk yogurt, thick and creamy with a clean tang. Plain only — let the milk speak for itself.',
 6.50, 25, 'per pint', 1, 0, '2026-03-01 10:00:00'),

(14, 3, 3, 'Farm Fresh Eggs',
 'Eggs from our free-range hens. Fed on pasture, grain, and kitchen scraps. Deep orange yolks with a rich flavor.',
 5.00, 40, 'per dozen', 1, 1, '2026-03-01 10:00:00'),

(15, 3, 3, 'Cultured Butter',
 'Hand-churned cultured butter from our own cream. Available salted and unsalted. Rich, complex flavor.',
 9.00, 22, 'per 8 oz', 1, 0, '2026-03-01 10:00:00'),

-- Range Craft House  (vendor 4 — Handmade Crafts)
(16, 4, 7, 'Hand-Forged Bottle Opener',
 'Forged from reclaimed Iron Range steel. Each one is slightly different. A functional piece of Range history.',
 28.00, 15, 'each', 1, 1, '2026-03-01 11:00:00'),

(17, 4, 7, 'End-Grain Maple Cutting Board',
 '10x14 inch end-grain maple board. Finished with food-safe walnut oil. Comes with a care card. Signed on the back.',
 65.00, 8, 'each', 1, 1, '2026-03-01 11:00:00'),

(18, 4, 7, 'Birch Log Candle Holder',
 'Natural white birch log taper candle holder. Standard two-inch taper fits. Each piece is unique.',
 18.00, 20, 'each', 1, 0, '2026-03-01 11:00:00'),

(19, 4, 7, 'Hand-Forged Leaf Trivet',
 'Hand-forged iron trivet with a pressed aspen leaf design. Protects your table. Looks great on the wall too.',
 42.00, 10, 'each', 1, 0, '2026-03-01 11:00:00'),

-- Taconite Acres  (vendor 5 — Meat & Produce)
(20, 5, 4, 'Berkshire Pork Chops',
 'Bone-in center-cut chops from our heritage Berkshire pigs. Pasture and woodland-raised — the fat marbling is extraordinary.',
 14.00, 25, 'per lb', 1, 1, '2026-03-01 08:30:00'),

(21, 5, 4, 'Maple Breakfast Sausage Links',
 'Mild breakfast links from our own pork, seasoned with sage, thyme, and a little maple. No fillers, no nitrates.',
 10.00, 30, 'per lb', 1, 0, '2026-03-01 08:30:00'),

(22, 5, 3, 'Pastured Eggs — Large',
 'Eggs from hens that follow the pigs through the pasture rotation. Deep orange yolks with rich flavor.',
 4.50, 50, 'per dozen', 1, 0, '2026-03-01 08:30:00'),

(23, 5, 1, 'Red New Potatoes',
 'Small red new potatoes dug fresh this season. Thin skin, creamy texture. Roast whole with olive oil and rosemary.',
 4.00, 45, 'per 2 lb bag', 1, 0, '2026-03-01 08:30:00'),

-- Mesabi Herbs & Botanicals  (vendor 6 — Plants & Herbs)
(24, 6, 6, 'Elderberry Tincture',
 'Concentrated elderberry and echinacea tincture in alcohol base. Cold season staple. 2 oz dropper bottle. Shake before use.',
 22.00, 20, 'per 2 oz bottle', 1, 1, '2026-03-01 12:00:00'),

(25, 6, 6, 'Northern Blend Herbal Tea',
 'Warming blend of locally grown lemon balm, holy basil, rose hips, and dried blueberry. Caffeine-free. Loose leaf.',
 14.00, 30, 'per 2 oz bag', 1, 1, '2026-03-01 12:00:00'),

(26, 6, 6, 'Lavender Facial Toner',
 'Witch hazel, organic lavender hydrosol, and aloe vera. Calming and balancing for all skin types. No synthetic fragrance.',
 16.00, 18, 'per 4 oz bottle', 1, 0, '2026-03-01 12:00:00'),

(27, 6, 6, 'Organic Herb Seedling — 4 inch Pot',
 'Choose from basil, lemon balm, holy basil, peppermint, thyme, or oregano. Certified organic starts from our greenhouse.',
 4.00, 60, 'each', 1, 0, '2026-03-01 12:00:00'),

(28, 6, 6, 'Calendula Healing Salve',
 'Calendula-infused olive oil, beeswax, and vitamin E. For dry hands, chapped lips, and minor cuts.',
 12.00, 25, 'per 2 oz tin', 1, 0, '2026-03-01 12:00:00'),

-- Bear Country Meats  (vendor 7 — Meat)
(29, 7, 4, 'Grass-Fed Ground Beef',
 '80/20 ground beef from our Angus-Hereford crosses. Dry-aged 14 days, ground fresh for market. Frozen in 1 lb vacuum packs.',
 9.00, 40, 'per lb', 1, 1, '2026-03-01 13:00:00'),

(30, 7, 4, 'NY Strip Steak',
 'Boneless New York strip, cut 1.25 inches thick. Dry-aged 21 days. These sell out — arrive early.',
 22.00, 16, 'per lb', 1, 1, '2026-03-01 13:00:00'),

(31, 7, 4, 'Lamb Shoulder Chops',
 'Bone-in shoulder chops from our pasture-raised Katahdin lambs. Excellent braised slow or grilled over hardwood.',
 13.00, 20, 'per lb', 1, 0, '2026-03-01 13:00:00'),

(32, 7, 4, 'Grass-Fed Soup Bones',
 'Marrow-rich knuckle and femur bones for bone broth. Two to three pounds per bag.',
 6.00, 25, 'per bag', 1, 0, '2026-03-01 13:00:00'),

-- Pine Knoll Pottery  (vendor 8 — Handmade Crafts)
(33, 8, 7, 'Stoneware Mug — 12 oz',
 'Wheel-thrown stoneware in our signature pine bark glaze — deep forest green with brown iron spotting. Microwave and dishwasher safe.',
 38.00, 12, 'each', 1, 1, '2026-03-01 14:00:00'),

(34, 8, 7, 'Large Serving Bowl',
 'Hand-built serving bowl in a natural ash glaze. Great for salads, pasta, or fruit. Each one is unique.',
 85.00, 5, 'each', 1, 0, '2026-03-01 14:00:00'),

(35, 8, 7, 'Bud Vase Set — Pair',
 'Matched pair of small wheel-thrown bud vases. Subtle glaze variation makes each set slightly different. A perfect gift.',
 48.00, 8, 'per set', 1, 1, '2026-03-01 14:00:00'),

(36, 8, 7, 'Ceramic Soap Dish',
 'Small hand-built soap dish with drainage ridges. Clean, simple design. Functional and good-looking.',
 22.00, 18, 'each', 1, 0, '2026-03-01 14:00:00'),

-- Hibernating Bear Apiary  (vendor 9 — Honey & Handmade)
(37, 9, 5, 'Raw Wildflower Honey',
 'Unfiltered and unheated wildflower honey from boreal forest hives near Orr. Light to medium amber. Complex flavor.',
 12.00, 35, 'per 8 oz jar', 1, 1, '2026-03-01 15:00:00'),

(38, 9, 5, 'Raw Clover Honey',
 'Classic raw clover honey from agricultural meadow hives. Mild, sweet, perfect for everyday use. Never heat-processed.',
 10.00, 40, 'per 8 oz jar', 1, 0, '2026-03-01 15:00:00'),

(39, 9, 7, 'Beeswax Pillar Candle',
 'Pure beeswax pillar candle, 3x4 inches. Natural honey scent, clean long burn. No added fragrance or dye.',
 16.00, 22, 'each', 1, 1, '2026-03-01 15:00:00'),

(40, 9, 7, 'Beeswax Lip Balm',
 'Beeswax and coconut oil lip balm in a twist tube. Unflavored or peppermint. No petroleum, no synthetic wax.',
 4.00, 50, 'each', 1, 0, '2026-03-01 15:00:00'),

(41, 9, 5, 'Honey & Wild Berry Preserve',
 'Local blueberries and wild raspberries preserved in raw honey. No added sugar, no pectin. Just fruit and honey.',
 11.00, 18, 'per 6 oz jar', 1, 0, '2026-03-01 15:00:00'),

(42, 9, 5, 'Creamed Honey',
 'Spun to a smooth, spreadable consistency. Stays soft at room temperature. Outstanding on toast, oatmeal, or straight off a spoon.',
 13.00, 20, 'per 8 oz jar', 1, 0, '2026-03-01 15:00:00');


-- =============================================================================
-- 6. EVENTS  (8 events for the 2026 market season)
-- event_time stored as VARCHAR to match index.php display format
-- =============================================================================
INSERT INTO `events`
  (`event_id`, `event_name`, `event_date`, `event_time`, `description`, `event_type`, `created_date`)
VALUES

(1, 'Opening Day — 2026 Market Season',
 '2026-06-04', '2:30 PM - 6:00 PM',
 'The first market day of the 2026 season! Welcome back your favorite vendors and meet a few new faces. Free samples at the entrance while supplies last.',
 'market_day', '2026-03-01 10:00:00'),

(2, 'Regular Market Thursday',
 '2026-06-11', '2:30 PM - 6:00 PM',
 'Your weekly Thursday market. Over 20 local vendors with fresh produce, baked goods, meats, dairy, crafts, and more.',
 'market_day', '2026-03-01 10:00:00'),

(3, 'Midsummer Festival Market',
 '2026-06-18', '1:00 PM - 7:00 PM',
 'Extended hours for our Midsummer celebration! Live music from the Mesabi Folk Collective, kids activities, and extra vendors.',
 'special_event', '2026-03-01 10:00:00'),

(4, 'Fermentation & Preservation Workshop',
 '2026-07-09', '4:00 PM - 5:30 PM',
 'Learn lacto-fermentation basics with Liisa Saarinen from Mesabi Herbs. Make your own sauerkraut to take home. $15 materials fee. Limit 20.',
 'workshop', '2026-03-01 10:00:00'),

(5, 'Wild Blueberry Harvest Day',
 '2026-07-23', '2:30 PM - 6:00 PM',
 'Wild blueberry season on the Range! Vendors featuring blueberries in everything from fresh pints to pies to jams. Come early.',
 'special_event', '2026-03-01 10:00:00'),

(6, 'Kids Cooking Demo',
 '2026-08-06', '3:00 PM - 4:00 PM',
 'Hands-on cooking demo for kids ages 6-12 using ingredients straight from the market. Free, no registration needed.',
 'workshop', '2026-03-01 10:00:00'),

(7, 'Fall Harvest Celebration',
 '2026-09-10', '12:00 PM - 6:00 PM',
 'Our biggest market of the year. Extra vendors, live music, a pie contest, and the full bounty of the Minnesota harvest season.',
 'special_event', '2026-03-01 10:00:00'),

(8, 'Final Market of the Season',
 '2026-10-08', '2:30 PM - 6:00 PM',
 'Last market of 2026. Stock up on squash, root vegetables, honey, and everything to carry you through the Minnesota winter.',
 'market_day', '2026-03-01 10:00:00');


-- =============================================================================
-- 7. VENDOR APPLICATIONS  (2 pending — for admin dashboard testing)
-- =============================================================================
INSERT INTO `vendor_applications`
  (`application_id`, `applicant_name`, `applicant_email`, `applicant_phone`,
   `business_name`, `business_description`, `business_category`,
   `miles_from_virginia`, `application_status`, `submitted_date`)
VALUES

(1, 'Helvi Mattson', 'helvi@lakeviewmushrooms.com', '218-555-2201',
 'Lakeview Mushroom Farm',
 'We grow gourmet and medicinal mushrooms (shiitake, oyster, lion''s mane, and chaga) year-round in a converted barn on Vermilion Lake. Looking to bring fresh and dried mushrooms to the Virginia market.',
 'Produce', 31.2, 'pending', '2026-03-01 14:30:00'),

(2, 'Vaino Hamalainen', 'vaino@northernknives.net', '218-555-2202',
 'Northern Steel Knives',
 'I forge custom knives and kitchen tools from reclaimed steel with locally sourced birch and maple handles. Each knife is a working tool built to last a lifetime.',
 'Handmade Crafts', 14.8, 'pending', '2026-03-02 09:15:00');


-- =============================================================================
-- 8. CONTACTS  (3 sample submissions — for admin dashboard testing)
-- =============================================================================
INSERT INTO `contacts`
  (`contact_id`, `name`, `email`, `phone`, `subject`, `message`, `vendor_id`, `status`, `submitted_date`)
VALUES

(1, 'Carol Nieminen', 'carol.n@gmail.com', '218-555-3301',
 'Wholesale inquiry — Birchwood Creamery',
 'Hello, I run a small restaurant in Ely and I am interested in purchasing Birchwood Creamery cheeses in larger quantities for our menu. Could you put me in touch with Sonja or share the best way to reach her?',
 3, 'new', '2026-03-03 10:22:00'),

(2, 'James Hautala', 'jhautala@ironrange.org', NULL,
 'Photography request for market newsletter',
 'I work for the Iron Range Tourism Board and would love to feature Virginia Market Square in our summer newsletter. Could I attend a Thursday market to photograph and speak with vendors?',
 NULL, 'read', '2026-03-04 14:05:00'),

(3, 'Britta Kowalski', 'britta.k@hotmail.com', '218-555-3303',
 'Question about Bear Country Meats quarter share',
 'I saw that Bear Country Meats offers quarter-share beef. I have questions about processing dates, pickup location, and freezer space needed. Can someone reach out?',
 7, 'new', '2026-03-05 08:45:00');


SET FOREIGN_KEY_CHECKS = 1;


-- =============================================================================
-- VERIFICATION QUERY (uncomment and run to check row counts)
-- Expected: users=14, vendors=9, customers=4, categories=7, products=42,
--           events=8, vendor_applications=2, contacts=3
-- =============================================================================
-- SELECT 'users'              AS tbl, COUNT(*) AS rows FROM users
-- UNION SELECT 'vendors',             COUNT(*) FROM vendors
-- UNION SELECT 'customers',           COUNT(*) FROM customers
-- UNION SELECT 'categories',          COUNT(*) FROM categories
-- UNION SELECT 'products',            COUNT(*) FROM products
-- UNION SELECT 'events',              COUNT(*) FROM events
-- UNION SELECT 'vendor_applications', COUNT(*) FROM vendor_applications
-- UNION SELECT 'contacts',            COUNT(*) FROM contacts;

-- END OF SEED DATA
