<?php
/**
 * Bolakausa – Complete Database Seed Script
 * Clears all data and inserts 1+ week of realistic B2B wholesale demo data.
 * Access: http://localhost/bolakausa/seed.php
 * REMOVE THIS FILE after seeding on production.
 */

// Safety key – pass ?key=seed2024 in URL to run
if (($_GET['key'] ?? '') !== 'seed2024') {
    die('<h2>Seed protection active.</h2><p>Pass <code>?key=seed2024</code> to run the seeder.</p>');
}

date_default_timezone_set('America/New_York');
define('BASE_URL', '/bolakausa/');

require_once 'config/database.php';
require_once 'includes/auth_helper.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$log = [];
function logStep($msg) {
    global $log;
    $log[] = $msg;
    echo "✔ $msg<br>\n";
    flush();
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Bolakausa Seeder</title>";
echo "<style>body{font-family:monospace;padding:2rem;background:#0f172a;color:#e2e8f0;line-height:1.8}h2{color:#10b981}br{display:block;}</style></head><body>";
echo "<h2>🌱 Bolakausa Database Seeder</h2><p style='color:#94a3b8'>Clearing old data and inserting fresh demo data...</p><hr style='border-color:#1e293b;margin:1rem 0'>\n";

// ─────────────────────────────────────────────
// STEP 1 – TRUNCATE ALL TABLES (safe order)
// ─────────────────────────────────────────────
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$tables = [
    'system_logs','notifications','chats','wallet_topups','wallet_transactions',
    'order_item_picks','order_status_history','order_items','orders',
    'inventory_lots','product_price_tiers','product_variants','product_images',
    'discounts','coupons','products','categories','user_addresses','locations',
    'promotions','settings','users'
];
foreach ($tables as $t) {
    $pdo->exec("TRUNCATE TABLE `$t`");
}
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
logStep("Truncated all tables");

// ─────────────────────────────────────────────
// STEP 2 – SETTINGS
// ─────────────────────────────────────────────
$settings = [
    ['company_name',       'Bolakausa Wholesale Foods'],
    ['company_email',      'support@bolakausa.com'],
    ['company_phone',      '+1 (800) 555-3663'],
    ['company_address',    '120 Fulton Street, New York, NY 10038'],
    ['company_logo_url',   ''],
    ['system_timezone',    'America/New_York'],
    ['tax_on_shipping',    '0'],
    ['min_order_amount',   '150'],
    ['pay_later_limit',    '5000'],
    ['stripe_enabled',     '1'],
    ['bank_name',          'Chase Bank'],
    ['bank_account',       '****4892'],
    ['bank_routing',       '021000021'],
    ['bank_instructions',  'Wire transfer to Chase Bank. Include your order number as reference.'],
    ['rejection_charge_percent', '5'],
    ['email_notifications', '1'],
    ['smtp_host',          'smtp.mailtrap.io'],
    ['smtp_port',          '587'],
    ['smtp_user',          'demo@bolakausa.com'],
    ['smtp_pass',          'demo_pass'],
    ['invoice_footer_text','Thank you for your business! Payment terms: Net 30 days.'],
];
$stmtS = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
foreach ($settings as $s) $stmtS->execute($s);
logStep("Inserted " . count($settings) . " settings");

// ─────────────────────────────────────────────
// STEP 3 – LOCATIONS (US Delivery Zones)
// ─────────────────────────────────────────────
$locations = [
    ['New York, NY',        8.875, 25.00, 0.45],
    ['Los Angeles, CA',     10.25, 45.00, 0.60],
    ['Chicago, IL',         10.00, 38.00, 0.55],
    ['Houston, TX',         8.25,  42.00, 0.52],
    ['Philadelphia, PA',    8.00,  28.00, 0.48],
    ['Phoenix, AZ',         8.60,  50.00, 0.65],
    ['San Antonio, TX',     8.25,  44.00, 0.54],
    ['San Diego, CA',       10.25, 46.00, 0.62],
    ['Dallas, TX',          8.25,  41.00, 0.52],
    ['Miami, FL',           7.00,  40.00, 0.50],
    ['Atlanta, GA',         4.00,  35.00, 0.48],
    ['Seattle, WA',         10.10, 52.00, 0.68],
    ['Boston, MA',          6.25,  30.00, 0.47],
    ['Denver, CO',          0.00,  47.00, 0.60],
    ['Portland, OR',        0.00,  50.00, 0.65],
];
$stmtL = $pdo->prepare("INSERT INTO locations (name, tax_percent, base_delivery_charge, per_unit_weight_charge) VALUES (?,?,?,?)");
foreach ($locations as $l) $stmtL->execute($l);
logStep("Inserted " . count($locations) . " locations");

// ─────────────────────────────────────────────
// STEP 4 – CATEGORIES
// ─────────────────────────────────────────────
$categories = [
    ['Fresh Produce',         'Fresh fruits and vegetables sourced daily'],
    ['Dairy & Eggs',          'Pasteurized dairy products and cage-free eggs'],
    ['Meat & Poultry',        'USDA-certified fresh and frozen meats'],
    ['Seafood',               'Fresh and frozen seafood selections'],
    ['Bakery & Bread',        'Artisan breads, rolls, and baked goods'],
    ['Beverages',             'Juices, water, sodas, and specialty drinks'],
    ['Pantry & Dry Goods',    'Canned goods, grains, pasta, and sauces'],
    ['Frozen Foods',          'Frozen vegetables, entrees, and desserts'],
    ['Snacks & Confectionery','Chips, nuts, candies, and protein bars'],
    ['Cleaning & Janitorial', 'Cleaning supplies and janitorial products'],
];
$stmtC = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?,?)");
foreach ($categories as $c) $stmtC->execute($c);
logStep("Inserted " . count($categories) . " categories");

// ─────────────────────────────────────────────
// STEP 5 – PRODUCTS (30 products)
// ─────────────────────────────────────────────
$products_data = [
    // [category_id, name, description, base_price, stock_qty, min_qty, max_qty, weight, is_featured]
    [1, 'Roma Tomatoes Case',       'Premium roma tomatoes, 25 lb case. Ideal for sauces and salads.',                          18.99,  240, 1, 50,  25.00, 1],
    [1, 'Russet Potatoes 50lb',     'Idaho russet potatoes, 50 lb sack. Great for fries and baked potatoes.',                   22.50,  180, 1, 30,  50.00, 1],
    [1, 'Yellow Onions 50lb',       'Sweet yellow onions, 50 lb sack. Essential kitchen staple.',                               17.99,  200, 1, 40,  50.00, 0],
    [1, 'Green Bell Peppers Case',  'Fresh green bell peppers, 25 lb case.',                                                    28.50,  120, 1, 25,  25.00, 0],
    [1, 'Romaine Lettuce 24ct',     '24-count case of crisp romaine hearts.',                                                   36.00,   80, 1, 20,   8.50, 1],
    [2, 'Whole Milk 1gal x12',      'Grade A whole milk, 12-pack gallon jugs. Pasteurized and homogenized.',                    54.00,  150, 1, 20,  96.00, 1],
    [2, 'Large Eggs 30 Dozen',      'Grade A large eggs, 30-dozen case. Cage-free certified.',                                  89.99,   90, 1, 10,  37.50, 1],
    [2, 'Shredded Cheddar 5lb',     'Mild cheddar cheese, pre-shredded, 5 lb bag.',                                            24.99,  160, 1, 30,   5.00, 0],
    [2, 'Unsalted Butter 36lb',     'USDA Grade AA unsalted butter, 36 lb case (36 x 1 lb).',                                  98.00,   75, 1, 10,  36.00, 0],
    [3, 'Chicken Breast 40lb',      'Boneless skinless chicken breast, IQF, 40 lb case.',                                      98.00,  110, 1, 15,  40.00, 1],
    [3, 'Ground Beef 80/20 30lb',   '80/20 fresh ground beef, 30 lb case. Perfect for burgers and meat sauces.',               94.50,   95, 1, 12,  30.00, 1],
    [3, 'Pork Loin Boneless 25lb',  'Boneless pork loin roast, 25 lb case.',                                                   72.00,   70, 1, 10,  25.00, 0],
    [3, 'Turkey Sliced Deli 10lb',  'Sliced oven-roasted turkey breast, 10 lb bulk pack.',                                     38.50,  100, 1, 20,  10.00, 0],
    [4, 'Atlantic Salmon Fillet 20lb','Fresh Atlantic salmon fillets, skin-on, 20 lb case.',                                   145.00,  45, 1,  8,  20.00, 1],
    [4, 'Shrimp 21/25 25lb',        'Gulf shrimp, peeled & deveined, 21/25 count, 25 lb case.',                               118.00,  55, 1, 10,  25.00, 0],
    [5, 'Sourdough Loaves 12ct',    'Artisan sourdough bread, 12-count case. Baked fresh daily.',                               48.00,   65, 1, 15,  18.00, 1],
    [5, 'Hamburger Buns 12doz',     'Classic hamburger buns, 12 dozen per case.',                                               36.00,   90, 1, 20,  22.00, 0],
    [6, 'Orange Juice 1gal x6',     'Not-from-concentrate orange juice, 6 x 1 gallon. Fresh squeezed quality.',               42.00,  120, 1, 24,  48.00, 0],
    [6, 'Bottled Water 500ml x96',  'Pure spring water, 96 x 500ml bottles per pallet case.',                                  28.00,  200, 1, 50,  96.00, 1],
    [6, 'Coffee Beans 5lb Bag',     'Single-origin Colombian coffee, whole bean, 5 lb bag.',                                   45.00,  140, 1, 30,   5.00, 1],
    [7, 'All-Purpose Flour 50lb',   'Enriched bleached all-purpose flour, 50 lb sack.',                                        24.00,  300, 1, 60,  50.00, 0],
    [7, 'Long Grain White Rice 50lb','Premium long grain white rice, 50 lb sack.',                                             28.50,  280, 1, 60,  50.00, 1],
    [7, 'Canola Oil 1gal x6',       'Pure canola oil, 6 x 1-gallon jugs. Neutral flavor, high smoke point.',                  38.00,  160, 1, 30,  48.00, 0],
    [7, 'Pasta Penne 20lb Case',    'Dried penne pasta, 20 lb case (20 x 1 lb bags).',                                        22.00,  210, 1, 40,  20.00, 0],
    [8, 'Frozen Broccoli Florets 20lb','IQF broccoli florets, 20 lb case. No added preservatives.',                           30.00,  130, 1, 25,  20.00, 0],
    [8, 'Frozen French Fries 30lb', 'Straight-cut frozen french fries, 30 lb case. Restaurant quality.',                      42.00,  175, 1, 30,  30.00, 1],
    [9, 'Tortilla Chips 3lb x6',    'Restaurant-style tortilla chips, 6 x 3 lb bags.',                                        36.00,  190, 1, 40,  18.00, 0],
    [9, 'Mixed Nuts 5lb',           'Premium roasted mixed nuts, 5 lb bulk bag. No peanuts.',                                  52.00,   85, 1, 20,   5.00, 1],
    [10,'Dish Soap Commercial 1gal x4','Concentrated commercial dish soap, 4 x 1 gallon.',                                    44.00,  210, 1, 50,  32.00, 0],
    [10,'Paper Towels 30 Roll Case','2-ply paper towels, 30 rolls per case.',                                                   52.00,  120, 1, 20,  30.00, 0],
];
$stmtP = $pdo->prepare("INSERT INTO products (category_id,name,description,base_price,stock_qty,min_order_qty,max_order_qty,weight,is_featured) VALUES (?,?,?,?,?,?,?,?,?)");
foreach ($products_data as $p) $stmtP->execute($p);
logStep("Inserted " . count($products_data) . " products");

// ─────────────────────────────────────────────
// STEP 6 – PRODUCT VARIANTS (for select products)
// ─────────────────────────────────────────────
$variants = [
    // [product_id, type, value, price_modifier, stock_qty]
    [10, 'Weight Option', '20lb Half Case',  -52.00, 80],   // Chicken Breast half case
    [10, 'Weight Option', '60lb Bulk Case',   25.00, 40],
    [11, 'Fat Content',   '90/10 Lean',       12.00, 60],   // Ground beef leaner option
    [14, 'Cut Style',     'Skin-off Fillets',  8.00, 25],   // Salmon skin-off
    [20, 'Grind',         'Pre-Ground 5lb',    0.00, 90],   // Coffee pre-ground
    [20, 'Grind',         'Espresso Fine 5lb', 2.00, 60],
    [27, 'Flavor',        'Ranch Flavored',    2.00, 80],   // Tortilla chips
    [27, 'Flavor',        'Lime & Chili',      2.00, 70],
];
$stmtV = $pdo->prepare("INSERT INTO product_variants (product_id,variant_type,variant_value,price_modifier,stock_qty) VALUES (?,?,?,?,?)");
foreach ($variants as $v) $stmtV->execute($v);
logStep("Inserted " . count($variants) . " product variants");

// ─────────────────────────────────────────────
// STEP 7 – PRICE TIERS (bulk pricing)
// ─────────────────────────────────────────────
$tiers = [
    // [product_id, min_qty, unit_price]
    // Roma Tomatoes
    [1,  5,  17.50], [1,  10, 16.75], [1, 20, 15.99],
    // Russet Potatoes
    [2,  3,  21.00], [2,  6,  19.50], [2, 12, 18.25],
    // Chicken Breast
    [10, 2,  94.00], [10, 4,  88.00], [10, 8, 82.50],
    // Ground Beef
    [11, 2,  90.00], [11, 4,  85.00],
    // Whole Milk
    [6,  3,  51.00], [6,  6,  48.50], [6, 12, 46.00],
    // Large Eggs
    [7,  2,  86.00], [7,  5,  82.00],
    // Rice
    [22, 5,  26.50], [22, 10, 24.99],
    // Bottled Water
    [19, 5,  25.00], [19, 10, 22.50], [19, 20, 20.00],
    // Flour
    [21, 5,  22.00], [21, 10, 20.50],
];
$stmtT = $pdo->prepare("INSERT INTO product_price_tiers (product_id,min_qty,unit_price) VALUES (?,?,?)");
foreach ($tiers as $t) $stmtT->execute($t);
logStep("Inserted " . count($tiers) . " price tiers");

// ─────────────────────────────────────────────
// STEP 8 – USERS (all roles)
// ─────────────────────────────────────────────
$pass = password_hash('Password123!', PASSWORD_DEFAULT);

$users_data = [
    // [username, email, password, role, status, full_name, phone, location_id]
    ['admin',       'admin@bolakausa.com',         $pass, 'admin',          'active', 'Alex Rivera',         '+1-212-555-0101', 1],
    ['manager1',    'manager@bolakausa.com',        $pass, 'manager',        'active', 'Sam Torres',          '+1-212-555-0102', 1],
    ['editor1',     'editor@bolakausa.com',         $pass, 'editor',         'active', 'Jamie Lee',           '+1-212-555-0103', 1],
    ['warehouse1',  'warehouse@bolakausa.com',      $pass, 'warehouse',      'active', 'Chris Park',          '+1-212-555-0104', 1],
    ['viewer1',     'viewer@bolakausa.com',         $pass, 'viewer',         'active', 'Morgan Hayes',        '+1-212-555-0105', 1],
    // Wholesale customers (active)
    ['freshdeli_ny',    'freshdeli@example.com',    $pass, 'wholesale_user', 'active', 'Fresh Deli NYC LLC',  '+1-212-555-0201', 1],
    ['sunshine_la',     'sunshine@example.com',     $pass, 'wholesale_user', 'active', 'Sunshine Bistro LA',  '+1-310-555-0202', 2],
    ['lakeside_chicago','lakeside@example.com',     $pass, 'wholesale_user', 'active', 'Lakeside Grill Chicago','+1-312-555-0203', 3],
    ['texasbest',       'texasbest@example.com',    $pass, 'wholesale_user', 'active', 'Texas Best Catering', '+1-713-555-0204', 4],
    ['miamifresh',      'miamifresh@example.com',   $pass, 'wholesale_user', 'active', 'Miami Fresh Foods',   '+1-305-555-0205', 10],
    // Executive (VIP wholesale)
    ['vegasprime',      'vegasprime@example.com',   $pass, 'executive',      'active', 'Vegas Prime Steakhouse','+1-702-555-0301', 6],
    ['bostonelite',     'bostonelite@example.com',  $pass, 'executive',      'active', 'Boston Elite Catering','+1-617-555-0302', 13],
    // Pending accounts
    ['newretail_atl',   'newretail@example.com',    $pass, 'wholesale_user', 'pending','Atlanta Retail Co',   '+1-404-555-0401', 11],
    ['seattlecafe',     'seattlecafe@example.com',  $pass, 'wholesale_user', 'pending','Seattle Cafe Group',  '+1-206-555-0402', 12],
];
$stmtU = $pdo->prepare("INSERT INTO users (username,email,password,role,status,full_name,phone,location_id) VALUES (?,?,?,?,?,?,?,?)");
foreach ($users_data as $u) $stmtU->execute($u);
logStep("Inserted " . count($users_data) . " users");

// Get user IDs
$uids = [];
$rows = $pdo->query("SELECT id, username FROM users")->fetchAll();
foreach ($rows as $r) $uids[$r['username']] = $r['id'];

// ─────────────────────────────────────────────
// STEP 9 – USER ADDRESSES
// ─────────────────────────────────────────────
$addresses = [
    [$uids['freshdeli_ny'],    '45 Broadway, Suite 1200',    'New York',   1, 1],
    [$uids['freshdeli_ny'],    '88 Wall Street, Lower Level', 'New York',   1, 0],
    [$uids['sunshine_la'],     '2840 Sunset Blvd',            'Los Angeles',2, 1],
    [$uids['lakeside_chicago'],'742 N Michigan Ave',          'Chicago',    3, 1],
    [$uids['texasbest'],       '5500 Main St, Suite 400',     'Houston',    4, 1],
    [$uids['texasbest'],       '12200 Westheimer Rd',         'Houston',    4, 0],
    [$uids['miamifresh'],      '1800 Biscayne Blvd',          'Miami',      10, 1],
    [$uids['vegasprime'],      '3700 Las Vegas Blvd S',       'Las Vegas',  6, 1],
    [$uids['bostonelite'],     '200 Faneuil Hall Marketplace', 'Boston',    13, 1],
    [$uids['bostonelite'],     '90 Tremont St',               'Boston',     13, 0],
    [$uids['newretail_atl'],   '230 Peachtree St NW',         'Atlanta',    11, 1],
];
$stmtA = $pdo->prepare("INSERT INTO user_addresses (user_id,address_line,city,location_id,is_default) VALUES (?,?,?,?,?)");
foreach ($addresses as $a) $stmtA->execute($a);
logStep("Inserted " . count($addresses) . " user addresses");

// ─────────────────────────────────────────────
// STEP 10 – COUPONS & DISCOUNTS
// ─────────────────────────────────────────────
$coupons = [
    ['WELCOME20',  'percentage', 20.00, 200.00, 150.00, 100, 2, '2026-01-01', '2027-12-31', 'all'],
    ['SAVE50',     'fixed',      50.00, 500.00, 50.00,  50,  8, '2026-01-01', '2027-06-30', 'all'],
    ['VIP15',      'percentage', 15.00, 300.00, 200.00, 30,  3, '2026-01-01', '2027-12-31', 'executive'],
    ['BULK100',    'fixed',      100.00,1000.00,100.00, 20,  1, '2026-01-01', '2027-12-31', 'all'],
    ['SUMMER10',   'percentage', 10.00, 150.00, 80.00,  200, 45,'2026-06-01', '2026-09-30', 'all'],
    ['FREESHIP',   'fixed',      35.00, 300.00, 35.00,  75,  12,'2026-05-01', '2026-12-31', 'all'],
    ['NEWMEMBER',  'percentage', 25.00, 250.00, 100.00, 20,  0, '2026-01-01', '2027-12-31', 'all'],
];
$stmtCp = $pdo->prepare("INSERT INTO coupons (code,type,value,min_spend,max_discount,usage_limit,used_count,start_date,end_date,target_wholesalers,is_active) VALUES (?,?,?,?,?,?,?,?,?,'all',1)");
// Re-insert with target_wholesalers correctly
$stmtCp2 = $pdo->prepare("INSERT INTO coupons (code,type,value,min_spend,max_discount,usage_limit,used_count,start_date,end_date,target_wholesalers,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,1)");
foreach ($coupons as $cp) {
    $stmtCp2->execute($cp);
}
logStep("Inserted " . count($coupons) . " coupons");

// Active Discounts
$discounts = [
    // [name, type, product_id, percent, amount, target, start, end]
    ['Summer Produce Sale',   'product_specific', 1,  12.00, 0.00, 'all',       '2026-06-01', '2026-09-30'],
    ['Chicken Bulk Deal',     'product_specific', 10, 8.00,  0.00, 'all',       '2026-06-01', '2026-12-31'],
    ['Executive 10% Off All', 'global',           null,10.00, 0.00, 'executive', '2026-01-01', '2027-12-31'],
    ['Milk Deal',             'product_specific', 6,  5.00,  0.00, 'all',       '2026-06-01', '2026-12-31'],
    ['Salmon Premium Offer',  'product_specific', 14, 10.00, 0.00, 'executive', '2026-06-01', '2027-06-30'],
];
$stmtD = $pdo->prepare("INSERT INTO discounts (name,discount_type,product_id,percent,amount,target_wholesalers,start_date,end_date,is_active) VALUES (?,?,?,?,?,?,?,?,1)");
foreach ($discounts as $d) $stmtD->execute($d);
logStep("Inserted " . count($discounts) . " discounts");

// ─────────────────────────────────────────────
// STEP 11 – INVENTORY LOTS
// ─────────────────────────────────────────────
$now_date = date('Y-m-d');
$lots = [
    [1,  'LOT-TOM-001',  date('Y-m-d', strtotime('+14 days')), 'A-01', 500, 240, 'active'],
    [2,  'LOT-POT-001',  date('Y-m-d', strtotime('+60 days')), 'A-02', 400, 180, 'active'],
    [3,  'LOT-ONI-001',  date('Y-m-d', strtotime('+45 days')), 'A-03', 400, 200, 'active'],
    [6,  'LOT-MLK-001',  date('Y-m-d', strtotime('+7 days')),  'B-01', 200, 150, 'active'],
    [7,  'LOT-EGG-001',  date('Y-m-d', strtotime('+14 days')), 'B-02', 120, 90,  'active'],
    [10, 'LOT-CHK-001',  date('Y-m-d', strtotime('+30 days')), 'C-01', 200, 110, 'active'],
    [10, 'LOT-CHK-002',  date('Y-m-d', strtotime('+25 days')), 'C-01', 100, 0,   'active'],
    [11, 'LOT-GRB-001',  date('Y-m-d', strtotime('+10 days')), 'C-02', 150, 95,  'active'],
    [14, 'LOT-SAL-001',  date('Y-m-d', strtotime('+5 days')),  'D-01', 80,  45,  'active'],
    [19, 'LOT-WAT-001',  date('Y-m-d', strtotime('+180 days')),'E-01', 500, 200, 'active'],
    [20, 'LOT-COF-001',  date('Y-m-d', strtotime('+365 days')),'E-02', 200, 140, 'active'],
    [22, 'LOT-RIC-001',  date('Y-m-d', strtotime('+365 days')),'F-01', 600, 280, 'active'],
    [21, 'LOT-FLR-001',  date('Y-m-d', strtotime('+365 days')),'F-02', 600, 300, 'active'],
    [26, 'LOT-FRY-001',  date('Y-m-d', strtotime('+90 days')), 'G-01', 300, 175, 'active'],
    [5,  'LOT-ROM-001',  date('Y-m-d', strtotime('+5 days')),  'A-05', 3,   3,   'active'],   // low stock
    [30, 'LOT-PTW-001',  date('Y-m-d', strtotime('+180 days')),'H-01', 200, 120, 'active'],
];
$stmtIL = $pdo->prepare("INSERT INTO inventory_lots (product_id,lot_number,expiry_date,shelf_location,qty_received,qty_remaining,status) VALUES (?,?,?,?,?,?,?)");
foreach ($lots as $lt) $stmtIL->execute($lt);
logStep("Inserted " . count($lots) . " inventory lots");

// ─────────────────────────────────────────────
// STEP 12 – WALLET CREDITS (seed balances)
// ─────────────────────────────────────────────
$wallet_users = [
    [$uids['freshdeli_ny'],  2500.00, 'credit', 'Wallet funded – initial balance'],
    [$uids['sunshine_la'],   1800.00, 'credit', 'Wallet funded – initial balance'],
    [$uids['lakeside_chicago'],1200.00,'credit', 'Wallet funded – initial balance'],
    [$uids['texasbest'],     3000.00, 'credit', 'Wallet funded – initial balance'],
    [$uids['miamifresh'],    950.00,  'credit', 'Wallet funded – initial balance'],
    [$uids['vegasprime'],    5000.00, 'credit', 'Wallet funded – initial balance'],
    [$uids['bostonelite'],   4200.00, 'credit', 'Wallet funded – initial balance'],
];
$stmtW = $pdo->prepare("INSERT INTO wallet_transactions (user_id,amount,type,description,created_at) VALUES (?,?,?,?,NOW())");
foreach ($wallet_users as $w) $stmtW->execute($w);
logStep("Inserted " . count($wallet_users) . " wallet seed credits");

// Pending top-up requests
$topups = [
    [$uids['freshdeli_ny'],  500.00,  'Bank Transfer', 'REF-FD-20240612', 'pending'],
    [$uids['sunshine_la'],   750.00,  'Bank Transfer', 'REF-SB-20240613', 'approved'],
    [$uids['lakeside_chicago'],300.00,'Bank Transfer', 'REF-LG-20240614', 'pending'],
    [$uids['texasbest'],     1000.00, 'Bank Transfer', 'REF-TB-20240615', 'approved'],
];
$stmtTU = $pdo->prepare("INSERT INTO wallet_topups (user_id,amount,payment_method,transaction_id,status,created_at) VALUES (?,?,?,?,?,NOW())");
foreach ($topups as $tu) $stmtTU->execute($tu);
logStep("Inserted " . count($topups) . " wallet top-up requests");

// ─────────────────────────────────────────────
// HELPER: place an order
// ─────────────────────────────────────────────
function seedOrder($pdo, $user_id, $items, $payment_method, $payment_status, $fulfillment_status,
                   $address, $created_offset_days, $coupon_id = null) {
    $subtotal    = 0;
    $location_id = 1; // New York default

    // Get location from user
    $locRow = $pdo->prepare("SELECT location_id FROM users WHERE id = ?");
    $locRow->execute([$user_id]);
    $location_id = (int)($locRow->fetchColumn() ?: 1);

    $locData = $pdo->prepare("SELECT tax_percent, base_delivery_charge FROM locations WHERE id = ?");
    $locData->execute([$location_id]);
    $loc = $locData->fetch();

    foreach ($items as $item) {
        $subtotal += $item['price'] * $item['qty'];
    }

    $tax_rate = (float)($loc['tax_percent'] ?? 8.875) / 100;
    $shipping  = (float)($loc['base_delivery_charge'] ?? 25.00);
    $tax       = round($subtotal * $tax_rate, 2);
    $coupon_disc = 0;
    if ($coupon_id) {
        $cp = $pdo->prepare("SELECT * FROM coupons WHERE id = ?");
        $cp->execute([$coupon_id]);
        $cpRow = $cp->fetch();
        if ($cpRow) {
            if ($cpRow['type'] === 'percentage') {
                $coupon_disc = min(round($subtotal * $cpRow['value'] / 100, 2), (float)$cpRow['max_discount']);
            } else {
                $coupon_disc = (float)$cpRow['value'];
            }
        }
    }
    $total = $subtotal + $tax + $shipping - $coupon_disc;

    $created_at = date('Y-m-d H:i:s', strtotime("-{$created_offset_days} days"));

    $status_map = [
        'Unpaid'   => 'Pending Payment',
        'Paid'     => $fulfillment_status,
    ];
    $order_status = $status_map[$payment_status] ?? $fulfillment_status;

    $stmtO = $pdo->prepare("INSERT INTO orders
        (user_id, status, payment_status, fulfillment_status, total_amount, tax_amount, shipping_amount,
         discount_amount, coupon_id, payment_method, delivery_address, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmtO->execute([
        $user_id, $order_status, $payment_status, $fulfillment_status,
        round($total, 2), $tax, $shipping, $coupon_disc, $coupon_id,
        $payment_method, $address, $created_at, $created_at
    ]);
    $order_id = $pdo->lastInsertId();

    $stmtOI = $pdo->prepare("INSERT INTO order_items (order_id,product_id,variant_id,qty,price_at_purchase) VALUES (?,?,?,?,?)");
    foreach ($items as $item) {
        $stmtOI->execute([$order_id, $item['pid'], $item['vid'] ?? null, $item['qty'], $item['price']]);
    }

    // Mark stock deducted for paid orders
    if ($payment_status === 'Paid') {
        $pdo->prepare("UPDATE orders SET stock_deducted=1 WHERE id=?")->execute([$order_id]);
    }

    // Add status history
    $stmtSH = $pdo->prepare("INSERT INTO order_status_history (order_id,status,changed_by,notes,created_at) VALUES (?,?,?,?,?)");
    $stmtSH->execute([$order_id, $order_status, $user_id, 'Initial seed status', $created_at]);

    return $order_id;
}

// Get product prices
$priceMap = [];
$pRows = $pdo->query("SELECT id, base_price FROM products")->fetchAll();
foreach ($pRows as $r) $priceMap[$r['id']] = (float)$r['base_price'];

// ─────────────────────────────────────────────
// STEP 13 – ORDERS (~28 orders over 10 days)
// ─────────────────────────────────────────────
$order_ids = [];

// freshdeli_ny – 6 orders
$order_ids[] = seedOrder($pdo, $uids['freshdeli_ny'],
    [['pid'=>10,'qty'=>2,'price'=>$priceMap[10]],['pid'=>1,'qty'=>5,'price'=>17.50],['pid'=>6,'qty'=>3,'price'=>$priceMap[6]]],
    'Bank Transfer','Paid','Delivered','45 Broadway, Suite 1200, New York, NY',10,1);

$order_ids[] = seedOrder($pdo, $uids['freshdeli_ny'],
    [['pid'=>22,'qty'=>4,'price'=>26.50],['pid'=>21,'qty'=>2,'price'=>$priceMap[21]],['pid'=>19,'qty'=>5,'price'=>25.00]],
    'Wallet','Paid','Shipped','45 Broadway, Suite 1200, New York, NY',6);

$order_ids[] = seedOrder($pdo, $uids['freshdeli_ny'],
    [['pid'=>7,'qty'=>2,'price'=>$priceMap[7]],['pid'=>11,'qty'=>1,'price'=>$priceMap[11]]],
    'Wallet','Paid','Ready to Ship','45 Broadway, Suite 1200, New York, NY',4);

$order_ids[] = seedOrder($pdo, $uids['freshdeli_ny'],
    [['pid'=>5,'qty'=>3,'price'=>$priceMap[5]],['pid'=>16,'qty'=>2,'price'=>$priceMap[16]]],
    'COD','Unpaid','Pending','45 Broadway, Suite 1200, New York, NY',2);

$order_ids[] = seedOrder($pdo, $uids['freshdeli_ny'],
    [['pid'=>14,'qty'=>1,'price'=>$priceMap[14]],['pid'=>8,'qty'=>2,'price'=>$priceMap[8]]],
    'Bank Transfer','Paid','Processing','88 Wall Street, Lower Level, New York, NY',1,2);

$order_ids[] = seedOrder($pdo, $uids['freshdeli_ny'],
    [['pid'=>20,'qty'=>3,'price'=>$priceMap[20]],['pid'=>27,'qty'=>2,'price'=>$priceMap[27]]],
    'Stripe','Paid','Confirmed','45 Broadway, Suite 1200, New York, NY',0);

// sunshine_la – 4 orders
$order_ids[] = seedOrder($pdo, $uids['sunshine_la'],
    [['pid'=>10,'qty'=>3,'price'=>94.00],['pid'=>13,'qty'=>2,'price'=>$priceMap[13]],['pid'=>26,'qty'=>4,'price'=>$priceMap[26]]],
    'Pay Later','Paid','Delivered','2840 Sunset Blvd, Los Angeles, CA',9);

$order_ids[] = seedOrder($pdo, $uids['sunshine_la'],
    [['pid'=>15,'qty'=>2,'price'=>$priceMap[15]],['pid'=>14,'qty'=>1,'price'=>$priceMap[14]]],
    'Stripe','Paid','Shipped','2840 Sunset Blvd, Los Angeles, CA',5);

$order_ids[] = seedOrder($pdo, $uids['sunshine_la'],
    [['pid'=>3,'qty'=>3,'price'=>$priceMap[3]],['pid'=>4,'qty'=>2,'price'=>$priceMap[4]]],
    'Wallet','Paid','Processing','2840 Sunset Blvd, Los Angeles, CA',3);

$order_ids[] = seedOrder($pdo, $uids['sunshine_la'],
    [['pid'=>29,'qty'=>5,'price'=>$priceMap[29]],['pid'=>30,'qty'=>3,'price'=>$priceMap[30]]],
    'COD','Unpaid','Pending','2840 Sunset Blvd, Los Angeles, CA',1);

// lakeside_chicago – 3 orders
$order_ids[] = seedOrder($pdo, $uids['lakeside_chicago'],
    [['pid'=>11,'qty'=>2,'price'=>90.00],['pid'=>12,'qty'=>1,'price'=>$priceMap[12]],['pid'=>25,'qty'=>3,'price'=>$priceMap[25]]],
    'Bank Transfer','Paid','Delivered','742 N Michigan Ave, Chicago, IL',8);

$order_ids[] = seedOrder($pdo, $uids['lakeside_chicago'],
    [['pid'=>22,'qty'=>5,'price'=>26.50],['pid'=>23,'qty'=>3,'price'=>$priceMap[23]],['pid'=>24,'qty'=>4,'price'=>$priceMap[24]]],
    'Wallet','Paid','Ready to Ship','742 N Michigan Ave, Chicago, IL',3,4);

$order_ids[] = seedOrder($pdo, $uids['lakeside_chicago'],
    [['pid'=>6,'qty'=>4,'price'=>51.00],['pid'=>8,'qty'=>2,'price'=>$priceMap[8]]],
    'COD','Unpaid','Pending','742 N Michigan Ave, Chicago, IL',1);

// texasbest – 4 orders
$order_ids[] = seedOrder($pdo, $uids['texasbest'],
    [['pid'=>10,'qty'=>4,'price'=>88.00],['pid'=>11,'qty'=>3,'price'=>85.00],['pid'=>13,'qty'=>2,'price'=>$priceMap[13]]],
    'Pay Later','Paid','Delivered','5500 Main St, Suite 400, Houston, TX',10);

$order_ids[] = seedOrder($pdo, $uids['texasbest'],
    [['pid'=>26,'qty'=>5,'price'=>$priceMap[26]],['pid'=>15,'qty'=>2,'price'=>$priceMap[15]]],
    'Bank Transfer','Paid','Shipped','5500 Main St, Suite 400, Houston, TX',6);

$order_ids[] = seedOrder($pdo, $uids['texasbest'],
    [['pid'=>21,'qty'=>3,'price'=>$priceMap[21]],['pid'=>22,'qty'=>5,'price'=>26.50],['pid'=>23,'qty'=>2,'price'=>$priceMap[23]]],
    'Stripe','Paid','Processing','12200 Westheimer Rd, Houston, TX',2);

$order_ids[] = seedOrder($pdo, $uids['texasbest'],
    [['pid'=>7,'qty'=>2,'price'=>86.00],['pid'=>9,'qty'=>1,'price'=>$priceMap[9]]],
    'Bank Transfer','Unpaid','Pending','5500 Main St, Suite 400, Houston, TX',0);

// miamifresh – 3 orders
$order_ids[] = seedOrder($pdo, $uids['miamifresh'],
    [['pid'=>14,'qty'=>2,'price'=>$priceMap[14]],['pid'=>15,'qty'=>2,'price'=>$priceMap[15]]],
    'Stripe','Paid','Delivered','1800 Biscayne Blvd, Miami, FL',7);

$order_ids[] = seedOrder($pdo, $uids['miamifresh'],
    [['pid'=>1,'qty'=>8,'price'=>16.75],['pid'=>4,'qty'=>3,'price'=>$priceMap[4]],['pid'=>5,'qty'=>2,'price'=>$priceMap[5]]],
    'Wallet','Paid','Ready to Ship','1800 Biscayne Blvd, Miami, FL',2);

// Cancelled & Rejected orders
$order_ids[] = seedOrder($pdo, $uids['miamifresh'],
    [['pid'=>10,'qty'=>1,'price'=>$priceMap[10]],['pid'=>11,'qty'=>1,'price'=>$priceMap[11]]],
    'Bank Transfer','Unpaid','Cancelled','1800 Biscayne Blvd, Miami, FL',5);

// vegasprime (executive) – 4 orders
$order_ids[] = seedOrder($pdo, $uids['vegasprime'],
    [['pid'=>14,'qty'=>3,'price'=>130.50],['pid'=>10,'qty'=>5,'price'=>88.00],['pid'=>11,'qty'=>3,'price'=>85.00]],
    'Wallet','Paid','Delivered','3700 Las Vegas Blvd S, Las Vegas, NV',9,3);

$order_ids[] = seedOrder($pdo, $uids['vegasprime'],
    [['pid'=>9,'qty'=>2,'price'=>$priceMap[9]],['pid'=>12,'qty'=>2,'price'=>$priceMap[12]],['pid'=>28,'qty'=>3,'price'=>$priceMap[28]]],
    'Pay Later','Paid','Shipped','3700 Las Vegas Blvd S, Las Vegas, NV',5);

$order_ids[] = seedOrder($pdo, $uids['vegasprime'],
    [['pid'=>22,'qty'=>8,'price'=>24.99],['pid'=>23,'qty'=>4,'price'=>$priceMap[23]],['pid'=>24,'qty'=>6,'price'=>$priceMap[24]]],
    'Stripe','Paid','Processing','3700 Las Vegas Blvd S, Las Vegas, NV',2);

$order_ids[] = seedOrder($pdo, $uids['vegasprime'],
    [['pid'=>6,'qty'=>6,'price'=>48.50],['pid'=>7,'qty'=>3,'price'=>86.00]],
    'Wallet','Unpaid','Pending','3700 Las Vegas Blvd S, Las Vegas, NV',0);

// bostonelite (executive) – 3 orders
$order_ids[] = seedOrder($pdo, $uids['bostonelite'],
    [['pid'=>14,'qty'=>2,'price'=>130.50],['pid'=>15,'qty'=>2,'price'=>$priceMap[15]],['pid'=>10,'qty'=>2,'price'=>88.00]],
    'Pay Later','Paid','Delivered','200 Faneuil Hall Marketplace, Boston, MA',8,3);

$order_ids[] = seedOrder($pdo, $uids['bostonelite'],
    [['pid'=>20,'qty'=>5,'price'=>$priceMap[20]],['pid'=>28,'qty'=>4,'price'=>$priceMap[28]]],
    'Wallet','Paid','Ready to Ship','200 Faneuil Hall Marketplace, Boston, MA',3);

// Rejected order with refund
$rej_oid = seedOrder($pdo, $uids['sunshine_la'],
    [['pid'=>10,'qty'=>2,'price'=>$priceMap[10]]],
    'Stripe','Paid','Rejected','2840 Sunset Blvd, Los Angeles, CA',4);
$pdo->prepare("UPDATE orders SET status='Rejected', refund_approved=0 WHERE id=?")->execute([$rej_oid]);
$order_ids[] = $rej_oid;

logStep("Inserted " . count($order_ids) . " orders across 7 customers");

// ─────────────────────────────────────────────
// STEP 14 – WALLET DEBITS for paid wallet orders
// ─────────────────────────────────────────────
$walletOrders = $pdo->query("SELECT id, user_id, total_amount FROM orders WHERE payment_method='Wallet' AND payment_status='Paid'")->fetchAll();
$stmtWD = $pdo->prepare("INSERT INTO wallet_transactions (user_id,amount,type,description,created_at) VALUES (?,?,?,?,NOW())");
foreach ($walletOrders as $wo) {
    $stmtWD->execute([$wo['user_id'], $wo['total_amount'], 'debit', "Order #ORD-{$wo['id']} payment"]);
}
logStep("Inserted " . count($walletOrders) . " wallet debits for wallet-paid orders");

// ─────────────────────────────────────────────
// STEP 15 – NOTIFICATIONS
// ─────────────────────────────────────────────
$notifs = [];
foreach ($uids as $uname => $uid) {
    $role = '';
    foreach ($users_data as $ud) {
        if ($ud[0] === $uname) { $role = $ud[3]; break; }
    }
    if ($role === 'admin') {
        $notifs[] = [$uid, 'New Order Received', 'Order #ORD-001 from Fresh Deli NYC needs review.', 0];
        $notifs[] = [$uid, 'Wallet Top-Up Request', 'freshdeli_ny requested a $500 wallet top-up.', 0];
        $notifs[] = [$uid, 'Low Stock Alert', 'Romaine Lettuce 24ct is running low (3 units remaining).', 0];
        $notifs[] = [$uid, 'New User Registration', 'Atlanta Retail Co is pending account approval.', 0];
        $notifs[] = [$uid, 'Payment Verification Needed', 'Bank transfer from Lakeside Grill requires verification.', 1];
    } elseif ($role === 'manager') {
        $notifs[] = [$uid, '5 Orders Need Attention', '5 orders are pending fulfillment update.', 0];
        $notifs[] = [$uid, 'Stock Inbound Alert', 'New inventory lot LOT-CHK-002 ready for receiving.', 1];
    } elseif (in_array($role, ['wholesale_user', 'executive'])) {
        $notifs[] = [$uid, 'Order Shipped!', 'Your recent order has been dispatched. Tracking info available.', 0];
        $notifs[] = [$uid, 'Exclusive Offer', 'Use code SUMMER10 for 10% off your next order.', 1];
    } elseif ($role === 'warehouse') {
        $notifs[] = [$uid, 'New Fulfillment Queue', '3 orders are ready to pick and pack.', 0];
    }
}
$stmtN = $pdo->prepare("INSERT INTO notifications (user_id,title,message,is_read,created_at) VALUES (?,?,?,?,NOW())");
foreach ($notifs as $n) $stmtN->execute($n);
logStep("Inserted " . count($notifs) . " notifications");

// ─────────────────────────────────────────────
// STEP 16 – CHAT MESSAGES
// ─────────────────────────────────────────────
$chats = [
    [$uids['freshdeli_ny'], 'wholesale_user', 'Hi, I need to update the delivery address for Order #ORD-001. Can you help?', 0],
    [$uids['admin'],        'admin',          'Hi! Sure, I can update that for you. What address would you like to change it to?', 1],
    [$uids['freshdeli_ny'], 'wholesale_user', 'Please change it to 88 Wall Street, Lower Level, New York. Thank you!', 0],
    [$uids['admin'],        'admin',          "Updated! You'll receive a confirmation email shortly.", 1],
    [$uids['sunshine_la'],  'wholesale_user', 'Do you have an ETA for my salmon order? Order #ORD-008.', 0],
    [$uids['admin'],        'admin',          'Your order is currently in transit and expected to arrive tomorrow by 3 PM.', 1],
    [$uids['vegasprime'],   'wholesale_user', 'Can I increase my Pay Later credit limit? We are expanding.', 0],
    [$uids['texasbest'],    'wholesale_user', 'I noticed a price discrepancy on my last invoice. Please review.', 0],
];
$stmtCh = $pdo->prepare("INSERT INTO chats (user_id,sender_role,message,is_read,created_at) VALUES (?,?,?,?,NOW())");
foreach ($chats as $ch) $stmtCh->execute($ch);
logStep("Inserted " . count($chats) . " chat messages");

// ─────────────────────────────────────────────
// STEP 17 – PROMOTIONS (banner messages)
// ─────────────────────────────────────────────
$promos = [
    ['🎉 Summer Sale: Use SUMMER10 for 10% off all orders above $150. Limited time!', '2026-06-01', '2026-09-30', 'all'],
    ['🚚 Free Shipping: Use FREESHIP on orders above $300 through December 2026.', '2026-06-01', '2026-12-31', 'all'],
    ['⭐ VIP Exclusive: Executive members get an automatic 10% discount on every order!', '2026-01-01', '2027-12-31', 'executive'],
    ['📦 Bulk Deals: Buy 10+ cases of Chicken Breast or Ground Beef and save 8-12%!', '2026-06-01', '2026-12-31', 'all'],
    ['🌿 New Arrivals: Check out our expanded Organic Produce lineup in the catalog.', '2026-06-15', '2026-08-31', 'all'],
];
$stmtPr = $pdo->prepare("INSERT INTO promotions (message,start_date,end_date,target_wholesalers,is_active) VALUES (?,?,?,?,1)");
foreach ($promos as $pr) $stmtPr->execute($pr);
logStep("Inserted " . count($promos) . " promotions");

// ─────────────────────────────────────────────
// STEP 18 – SYSTEM LOGS (audit trail)
// ─────────────────────────────────────────────
$syslogs = [
    [$uids['admin'],   'order_status_update', 'Pending', 'Processing'],
    [$uids['admin'],   'user_approved',       null, 'freshdeli_ny activated'],
    [$uids['manager1'],'stock_inbound',       null, 'LOT-CHK-001: 200 units received'],
    [$uids['admin'],   'coupon_created',      null, 'Coupon SUMMER10 created'],
    [$uids['admin'],   'payment_verified',    'Unpaid', 'Paid - Bank Transfer verified'],
    [$uids['manager1'],'price_updated',       '94.50', '88.00 - Bulk discount applied'],
    [$uids['admin'],   'settings_updated',    null, 'Tax rate updated for New York'],
];
$stmtSL = $pdo->prepare("INSERT INTO system_logs (user_id,action_type,old_value,new_value) VALUES (?,?,?,?)");
foreach ($syslogs as $sl) $stmtSL->execute($sl);
logStep("Inserted " . count($syslogs) . " system log entries");

// ─────────────────────────────────────────────
// DONE
// ─────────────────────────────────────────────
echo "<hr style='border-color:#1e293b;margin:1.5rem 0'>\n";
echo "<h2>✅ Seed Complete!</h2>\n";
echo "<p style='color:#94a3b8;margin-bottom:1.5rem'>All demo data has been inserted successfully.</p>\n";

echo "<div style='background:#1e293b;border-radius:12px;padding:1.5rem;max-width:600px'>";
echo "<h3 style='color:#10b981;margin-bottom:1rem;font-family:sans-serif'>Login Credentials (all use Password123!)</h3>";
$creds = [
    ['Admin',         'admin@bolakausa.com',       '/bolakausa/admin'],
    ['Manager',       'manager@bolakausa.com',     '/bolakausa/manager'],
    ['Editor',        'editor@bolakausa.com',       '/bolakausa/admin/products'],
    ['Warehouse',     'warehouse@bolakausa.com',   '/bolakausa/warehouse'],
    ['Viewer',        'viewer@bolakausa.com',       '/bolakausa/viewer'],
    ['Wholesale (NY)','freshdeli@example.com',      '/bolakausa/home'],
    ['Wholesale (LA)','sunshine@example.com',       '/bolakausa/home'],
    ['Executive',     'vegasprime@example.com',     '/bolakausa/home'],
    ['Pending User',  'newretail@example.com',      '/bolakausa/home'],
];
foreach ($creds as $cr) {
    echo "<div style='display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;border-bottom:1px solid #334155;gap:1rem'>";
    echo "<span style='color:#6366f1;font-weight:700;min-width:120px'>{$cr[0]}</span>";
    echo "<span style='color:#e2e8f0;flex:1'>{$cr[1]}</span>";
    echo "<a href='{$cr[2]}' style='color:#10b981;text-decoration:none;font-size:0.8rem'>→ Go</a>";
    echo "</div>\n";
}
echo "<p style='color:#64748b;font-size:0.8rem;margin-top:1rem'>Password for all accounts: <strong style='color:#f59e0b'>Password123!</strong></p>";
echo "</div>";
echo "<br><p style='color:#f43f5e'>⚠ DELETE seed.php from your server after use!</p>";
echo "</body></html>";
