<?php
/**
 * scripts/demo_seed.php — CLI demo data seeder
 *
 * Populates categories, media, products, customers, orders, sales, and stock
 * for the Default Organization (org_id=1) using the product images in
 * uploads/products/.
 *
 * Usage:
 *   php scripts/demo_seed.php           # insert demo data (skips if present)
 *   php scripts/demo_seed.php --clean   # remove demo data then re-insert
 */

if (php_sapi_name() !== 'cli') {
    die("This script is CLI only.\n");
}

define('CONFIG_ROOT', realpath(__DIR__ . '/..'));

// ── Load .env (mirrors includes/config.php) ────────────────────────────────────
$cfg = [];
$env_path = CONFIG_ROOT . '/.env';
if (file_exists($env_path)) {
    foreach (file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if (!$line || $line[0] === '#' || $line[0] === ';') {
            continue;
        }
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $cfg[trim($k)] = trim($v, " \t\"'");
        }
    }
}

try {
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8',
            $cfg['DB_HOST'] ?? 'localhost',
            $cfg['DB_NAME'] ?? 'inventory'
        ),
        $cfg['DB_USER'] ?? 'webuser',
        $cfg['DB_PASS'] ?? '',
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "DB connection failed: {$e->getMessage()}\n");
    exit(1);
}

$ORG_ID = 1;
$clean  = in_array('--clean', $argv ?? []);

// ── Helpers ────────────────────────────────────────────────────────────────────
function say(string $msg): void
{
    echo "\n\033[1;34m$msg\033[0m\n";
}

function ok(string $label, int $id = 0): void
{
    echo "  \033[32m✓\033[0m " . $label . ($id ? " \033[2m(id=$id)\033[0m" : '') . "\n";
}

function ids_placeholder(array $ids): string
{
    return implode(',', array_fill(0, count($ids), '?'));
}

// ── Demo data identifiers (used for both --clean and insertion) ────────────────
$DEMO_SKUS = [
    'MCU-NANO-V3', 'MCU-UNO-R3', 'SBC-RPI3B', 'SBC-RPIZ2W',
    'PLT-POTHOS-4', 'PLT-SPIDER-6', 'CAB-USBC-1M', 'CAB-JMP-MM40',
    'SEN-DHT22', 'SEN-PIRSR501', 'TL-IRON-60W', 'TL-MULTIMTR',
];
$DEMO_CATS = [
    'Microcontrollers', 'Single Board Computers', 'Plants & Living Goods',
    'Cables & Connectors', 'Sensors & Modules', 'Tools & Equipment',
];
$DEMO_CUSTOMERS = [
    'Riverside Community Center', 'Greenfield Makerspace',
    'Tech For Good Initiative', 'Oak Street Youth Club',
    'Harbor Nonprofit Network', 'Valley Learning Co-op',
    'Sunrise Community Garden', 'Lakeside Arts Collective',
];
$DEMO_MEDIA = ['nano.jpg', 'pi3.jpg', 'pothos.jpg', 'no_image.jpg', 'no-image.png'];

// ── Optional clean ─────────────────────────────────────────────────────────────
if ($clean) {
    say('Cleaning demo data...');

    // Collect product IDs
    $ph = ids_placeholder($DEMO_SKUS);
    $stmt = $pdo->prepare("SELECT id FROM products WHERE sku IN ($ph) AND org_id = ?");
    $stmt->execute([...$DEMO_SKUS, $ORG_ID]);
    $pids = array_column($stmt->fetchAll(), 'id');

    // Collect customer IDs
    $ph = ids_placeholder($DEMO_CUSTOMERS);
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE name IN ($ph) AND org_id = ?");
    $stmt->execute([...$DEMO_CUSTOMERS, $ORG_ID]);
    $cids = array_column($stmt->fetchAll(), 'id');

    // Delete sales tied to demo products or demo customers' orders
    if ($pids) {
        $ph = ids_placeholder($pids);
        $pdo->prepare("DELETE FROM sales WHERE product_id IN ($ph)")->execute($pids);
    }
    if ($cids) {
        $ph = ids_placeholder($cids);
        $pdo->prepare("DELETE FROM orders WHERE customer_id IN ($ph)")->execute($cids);
    }

    // Delete stock tied to demo products
    if ($pids) {
        $ph = ids_placeholder($pids);
        $pdo->prepare("DELETE FROM stock WHERE product_id IN ($ph)")->execute($pids);
    }

    // Delete demo customers, products, categories, media
    if ($cids) {
        $ph = ids_placeholder($cids);
        $pdo->prepare("DELETE FROM customers WHERE id IN ($ph)")->execute($cids);
    }
    $ph = ids_placeholder($DEMO_SKUS);
    $pdo->prepare("DELETE FROM products WHERE sku IN ($ph) AND org_id = ?")->execute([...$DEMO_SKUS, $ORG_ID]);

    $ph = ids_placeholder($DEMO_CATS);
    $pdo->prepare("DELETE FROM categories WHERE name IN ($ph) AND org_id = ?")->execute([...$DEMO_CATS, $ORG_ID]);

    $ph = ids_placeholder($DEMO_MEDIA);
    $pdo->prepare("DELETE FROM media WHERE file_name IN ($ph) AND org_id = ?")->execute([...$DEMO_MEDIA, $ORG_ID]);

    echo "  Done.\n";
}

// ── Guard: skip if already seeded ──────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku = 'MCU-NANO-V3' AND org_id = ?");
$stmt->execute([$ORG_ID]);
if ((int) $stmt->fetchColumn() > 0) {
    echo "Demo data already present. Run with --clean to re-seed.\n";
    exit(0);
}

// ── 1. Categories ──────────────────────────────────────────────────────────────
say('1/7  Categories');
$cat_ids = [];
$ins = $pdo->prepare("INSERT INTO categories (org_id, name) VALUES (?, ?)");
foreach ($DEMO_CATS as $name) {
    $ins->execute([$ORG_ID, $name]);
    $cat_ids[$name] = (int) $pdo->lastInsertId();
    ok($name, $cat_ids[$name]);
}

// ── 2. Media ───────────────────────────────────────────────────────────────────
say('2/7  Media');
$media_defs = [
    ['nano.jpg',     'image/jpeg'],
    ['pi3.jpg',      'image/jpeg'],
    ['pothos.jpg',   'image/jpeg'],
    ['no_image.jpg', 'image/jpeg'],
    ['no-image.png', 'image/png'],
];
$media_ids = [];
$ins = $pdo->prepare("INSERT INTO media (org_id, file_name, file_type) VALUES (?, ?, ?)");
foreach ($media_defs as [$file, $type]) {
    $ins->execute([$ORG_ID, $file, $type]);
    $media_ids[$file] = (int) $pdo->lastInsertId();
    ok($file, $media_ids[$file]);
}
$fallback_media = $media_ids['no_image.jpg'];

// ── 3. Products ────────────────────────────────────────────────────────────────
say('3/7  Products');
// [name, description, sku, location, qty, low_stock, buy_price, sale_price, category, image_file]
$product_defs = [
    [
        'Arduino Nano v3',
        'ATmega328-based compact microcontroller. 14 digital I/O pins, 8 analog inputs, Mini-USB connector. Breadboard-friendly.',
        'MCU-NANO-V3', 'A-01', 25, 8, 8.00, 14.99, 'Microcontrollers', 'nano.jpg',
    ],
    [
        'Arduino Uno R3',
        'Classic ATmega328P development board. Ideal for prototyping and education. Compatible with most Arduino shields.',
        'MCU-UNO-R3', 'A-02', 12, 5, 22.00, 34.99, 'Microcontrollers', 'no_image.jpg',
    ],
    [
        'Raspberry Pi 3 Model B',
        '64-bit quad-core 1.2 GHz SoC, 1 GB RAM, onboard Wi-Fi and Bluetooth 4.1, 4x USB, HDMI, CSI camera port.',
        'SBC-RPI3B', 'A-03', 8, 3, 35.00, 49.99, 'Single Board Computers', 'pi3.jpg',
    ],
    [
        'Raspberry Pi Zero 2 W',
        'Compact 1 GHz quad-core Cortex-A53, 512 MB RAM, Wi-Fi, Bluetooth 4.2, micro-USB, camera connector.',
        'SBC-RPIZ2W', 'A-04', 15, 5, 15.00, 24.99, 'Single Board Computers', 'no_image.jpg',
    ],
    [
        'Pothos — 4" Pot',
        'Easy-care trailing vine. Tolerates low light and irregular watering. Ships in a 4-inch nursery pot.',
        'PLT-POTHOS-4', 'C-01', 20, 6, 3.00, 8.99, 'Plants & Living Goods', 'pothos.jpg',
    ],
    [
        'Spider Plant — 6" Pot',
        'Air-purifying classic. Produces cascading offshoots. Hardy, fast-growing, and non-toxic to pets.',
        'PLT-SPIDER-6', 'C-02', 10, 4, 5.00, 12.99, 'Plants & Living Goods', 'no_image.jpg',
    ],
    [
        'USB-C to USB-A Cable 1m',
        'Braided nylon jacket, 3 A charging, USB 2.0 data. Compatible with Raspberry Pi 4/5 and most smartphones.',
        'CAB-USBC-1M', 'B-01', 50, 15, 2.00, 7.99, 'Cables & Connectors', 'no_image.jpg',
    ],
    [
        'Jumper Wire Set M/M 40pc',
        '40-piece male-to-male, 20 cm breadboard jumper wires in assorted colours. Essential prototyping accessory.',
        'CAB-JMP-MM40', 'B-02', 30, 10, 3.00, 8.99, 'Cables & Connectors', 'no_image.jpg',
    ],
    [
        'DHT22 Temp/Humidity Sensor',
        'High-accuracy digital sensor. Measures temperature (+-0.5 C) and humidity (+-2-5% RH). Single-wire interface.',
        'SEN-DHT22', 'B-03', 24, 8, 5.00, 12.99, 'Sensors & Modules', 'no_image.jpg',
    ],
    [
        'HC-SR501 PIR Motion Sensor',
        'Adjustable sensitivity and delay. 3-7 m detection range, 120 degree cone, 5 V TTL output.',
        'SEN-PIRSR501', 'B-04', 18, 6, 4.00, 9.99, 'Sensors & Modules', 'no_image.jpg',
    ],
    [
        'Soldering Iron 60W',
        '60 W pencil iron with ceramic element and replaceable conical tip. On/off switch, grounded plug.',
        'TL-IRON-60W', 'D-01', 5, 2, 18.00, 34.99, 'Tools & Equipment', 'no_image.jpg',
    ],
    [
        'Digital Multimeter',
        'Auto-ranging. Measures AC/DC voltage, current, resistance, continuity, and diode. Includes test leads and battery.',
        'TL-MULTIMTR', 'D-02', 7, 2, 12.00, 24.99, 'Tools & Equipment', 'no_image.jpg',
    ],
];

$prod_ids = [];
$ins = $pdo->prepare(
    'INSERT INTO products
       (org_id, name, description, sku, location, quantity, low_stock_threshold,
        buy_price, sale_price, category_id, media_id, date)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
);
foreach ($product_defs as [$name, $desc, $sku, $loc, $qty, $low, $buy, $sale, $cat, $img]) {
    $ins->execute([
        $ORG_ID, $name, $desc, $sku, $loc, $qty, $low, $buy, $sale,
        $cat_ids[$cat],
        $media_ids[$img] ?? $fallback_media,
    ]);
    $prod_ids[$sku] = (int) $pdo->lastInsertId();
    ok("$name [$sku]", $prod_ids[$sku]);
}

// ── 4. Stock movements ─────────────────────────────────────────────────────────
say('4/7  Stock movements (initial receipts)');
// qty = current products.quantity + units sold in the orders below
$stock_defs = [
    'MCU-NANO-V3'  => [32, '2026-01-10', 'Opening stock — batch purchase Jan 2026'],
    'MCU-UNO-R3'   => [14, '2026-01-10', 'Opening stock — batch purchase Jan 2026'],
    'SBC-RPI3B'    => [10, '2026-01-15', 'Opening stock — distributor delivery'],
    'SBC-RPIZ2W'   => [16, '2026-01-15', 'Opening stock — distributor delivery'],
    'PLT-POTHOS-4' => [24, '2026-02-01', 'Spring stock — local nursery'],
    'PLT-SPIDER-6' => [13, '2026-02-01', 'Spring stock — local nursery'],
    'CAB-USBC-1M'  => [55, '2026-01-08', 'Bulk order — accessories run'],
    'CAB-JMP-MM40' => [37, '2026-01-08', 'Bulk order — accessories run'],
    'SEN-DHT22'    => [31, '2026-01-20', 'Opening stock — sensor kit'],
    'SEN-PIRSR501' => [20, '2026-01-20', 'Opening stock — sensor kit'],
    'TL-IRON-60W'  => [ 6, '2026-01-05', 'Opening stock — tools'],
    'TL-MULTIMTR'  => [ 8, '2026-01-05', 'Opening stock — tools'],
];
$ins = $pdo->prepare(
    'INSERT INTO stock (org_id, product_id, quantity, comments, date) VALUES (?, ?, ?, ?, ?)'
);
foreach ($stock_defs as $sku => [$qty, $date, $comment]) {
    $ins->execute([$ORG_ID, $prod_ids[$sku], $qty, $comment, $date]);
    ok("$sku — $qty units on $date", (int) $pdo->lastInsertId());
}

// ── 5. Customers ───────────────────────────────────────────────────────────────
say('5/7  Customers');
// [name, address, city, region, postcode, telephone, email, paymethod]
$customer_defs = [
    ['Riverside Community Center', '142 River Road',       'Millbrook',  'ON', 'K0L 2W0', '613-555-0181', 'programs@rivercc.org',   'invoice'],
    ['Greenfield Makerspace',      '88 Industrial Way',    'Kingston',   'ON', 'K7L 4R9', '613-555-0247', 'hello@greenfieldmake.ca', 'card'],
    ['Tech For Good Initiative',   '310 Innovation Drive', 'Ottawa',     'ON', 'K2H 7B3', '613-555-0392', 'admin@techforgood.ca',    'invoice'],
    ['Oak Street Youth Club',      '55 Oak Street',        'Napanee',    'ON', 'K7R 1A4', '613-555-0114', 'info@oakstreetclub.org',  'cash'],
    ['Harbor Nonprofit Network',   '200 Harbor View Blvd', 'Belleville', 'ON', 'K8N 3E5', '613-555-0465', 'hub@harbornetwork.ca',    'invoice'],
    ['Valley Learning Co-op',      '7 Cooperative Lane',   'Picton',     'ON', 'K0K 2T0', '613-555-0503', 'learn@valleycoop.ca',     'card'],
    ['Sunrise Community Garden',   '901 Sunrise Path',     'Perth',      'ON', 'K7H 2M6', '613-555-0629', 'garden@sunrisecg.org',    'cash'],
    ['Lakeside Arts Collective',   '15 Lakeshore Cres',    'Gananoque',  'ON', 'K7G 1B2', '613-555-0734', 'arts@lakesidecollect.ca', 'card'],
];
$cust_ids = [];
$ins = $pdo->prepare(
    'INSERT INTO customers (org_id, name, address, city, region, postcode, telephone, email, paymethod)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
foreach ($customer_defs as [$name, $addr, $city, $region, $post, $tel, $email, $pay]) {
    $ins->execute([$ORG_ID, $name, $addr, $city, $region, $post, $tel, $email, $pay]);
    $cust_ids[$name] = (int) $pdo->lastInsertId();
    ok($name, $cust_ids[$name]);
}

// ── 6. Orders ─────────────────────────────────────────────────────────────────
say('6/7  Orders');
// [customer_name, status, paymethod, date, notes]
$order_defs = [
    ['Riverside Community Center', 'fulfilled',  'invoice', '2026-03-15', 'Recurring quarterly order. Deliver to main entrance.'],
    ['Greenfield Makerspace',      'fulfilled',  'card',    '2026-03-28', 'Workshop supplies for spring cohort.'],
    ['Tech For Good Initiative',   'shipped',    'invoice', '2026-04-10', 'Net-30 terms. Contact finance dept for remittance.'],
    ['Oak Street Youth Club',      'processing', 'cash',    '2026-04-22', 'Paid in person — receipt #4228.'],
    ['Harbor Nonprofit Network',   'pending',    'invoice', '2026-05-05', 'Awaiting PO approval from board.'],
    ['Valley Learning Co-op',      'fulfilled',  'card',    '2026-04-30', 'Spring curriculum kit. Extra packaging requested.'],
];
$order_ids = [];
$ins = $pdo->prepare(
    'INSERT INTO orders (org_id, customer, customer_id, status, paymethod, date, notes)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
foreach ($order_defs as [$cname, $status, $pay, $date, $notes]) {
    $ins->execute([$ORG_ID, $cname, $cust_ids[$cname], $status, $pay, $date, $notes]);
    $oid = (int) $pdo->lastInsertId();
    $order_ids[] = $oid;
    ok("Order #$oid — $cname [$status]");
}

// ── 7. Sales line items ────────────────────────────────────────────────────────
say('7/7  Sales');
// [order_index, sku, qty, unit_price, date]
// price column stores the line total (qty × unit_price), matching app behaviour.
// Order 5 (Harbor, pending) has no sales — order not yet confirmed.
$sale_defs = [
    // Order 1 — Riverside Community Center (fulfilled 2026-03-15)
    [0, 'MCU-NANO-V3',  3, 14.99, '2026-03-15'],
    [0, 'CAB-USBC-1M',  2,  7.99, '2026-03-15'],
    [0, 'TL-MULTIMTR',  1, 24.99, '2026-03-15'],
    // Order 2 — Greenfield Makerspace (fulfilled 2026-03-28)
    [1, 'SBC-RPI3B',    2, 49.99, '2026-03-28'],
    [1, 'CAB-JMP-MM40', 5,  8.99, '2026-03-28'],
    [1, 'SEN-DHT22',    3, 12.99, '2026-03-28'],
    // Order 3 — Tech For Good Initiative (shipped 2026-04-10)
    [2, 'MCU-NANO-V3',  4, 14.99, '2026-04-10'],
    [2, 'SEN-PIRSR501', 2,  9.99, '2026-04-10'],
    [2, 'TL-IRON-60W',  1, 34.99, '2026-04-10'],
    // Order 4 — Oak Street Youth Club (processing 2026-04-22)
    [3, 'SBC-RPIZ2W',   1, 24.99, '2026-04-22'],
    [3, 'CAB-USBC-1M',  3,  7.99, '2026-04-22'],
    // Order 6 — Valley Learning Co-op (fulfilled 2026-04-30)
    [5, 'MCU-UNO-R3',   2, 34.99, '2026-04-30'],
    [5, 'SEN-DHT22',    4, 12.99, '2026-04-30'],
    [5, 'CAB-JMP-MM40', 2,  8.99, '2026-04-30'],
];
$ins = $pdo->prepare(
    'INSERT INTO sales (org_id, order_id, product_id, qty, price, date) VALUES (?, ?, ?, ?, ?, ?)'
);
foreach ($sale_defs as [$oi, $sku, $qty, $unit, $date]) {
    $total = round($qty * $unit, 2);
    $ins->execute([$ORG_ID, $order_ids[$oi], $prod_ids[$sku], $qty, $total, $date]);
    ok(sprintf('%-14s x%d  $%.2f', $sku, $qty, $total), (int) $pdo->lastInsertId());
}

// ── Summary ────────────────────────────────────────────────────────────────────
say('Done!');
printf(
    "  %d categories  |  %d products  |  %d customers\n  %d orders  |  %d sales lines  |  %d stock movements\n\n",
    count($DEMO_CATS),
    count($product_defs),
    count($customer_defs),
    count($order_defs),
    count($sale_defs),
    count($stock_defs)
);
