<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__) . '/inc/config.php';

$db = ps_db();

// Target pincodes across UP, Rajasthan, and Bihar
$targets = [
    // Uttar Pradesh (State ID 38)
    ['state_id' => 38, 'pincode' => '226001', 'count' => 12, 'label' => 'Lucknow, UP'],
    ['state_id' => 38, 'pincode' => '221001', 'count' => 8,  'label' => 'Varanasi, UP'],
    ['state_id' => 38, 'pincode' => '282001', 'count' => 15, 'label' => 'Agra, UP'],
    ['state_id' => 38, 'pincode' => '208001', 'count' => 7,  'label' => 'Kanpur, UP'],
    ['state_id' => 38, 'pincode' => '201301', 'count' => 22, 'label' => 'Noida, UP'],

    // Rajasthan (State ID 33)
    ['state_id' => 33, 'pincode' => '302001', 'count' => 23, 'label' => 'Jaipur, RJ'],
    ['state_id' => 33, 'pincode' => '342001', 'count' => 12, 'label' => 'Jodhpur, RJ'],
    ['state_id' => 33, 'pincode' => '313001', 'count' => 7,  'label' => 'Udaipur, RJ'],
    ['state_id' => 33, 'pincode' => '305001', 'count' => 9,  'label' => 'Ajmer, RJ'],
    ['state_id' => 33, 'pincode' => '324001', 'count' => 14, 'label' => 'Kota, RJ'],

    // Bihar (State ID 5)
    ['state_id' => 5,  'pincode' => '800001', 'count' => 24, 'label' => 'Patna, BH'],
    ['state_id' => 5,  'pincode' => '823001', 'count' => 8,  'label' => 'Gaya, BH'],
    ['state_id' => 5,  'pincode' => '842001', 'count' => 12, 'label' => 'Muzaffarpur, BH'],
    ['state_id' => 5,  'pincode' => '812001', 'count' => 7,  'label' => 'Bhagalpur, BH'],
    ['state_id' => 5,  'pincode' => '846001', 'count' => 11, 'label' => 'Darbhanga, BH'],
];

$sampleMessages = [
    'verbal' => [
        'Persistent catcalling and inappropriate remarks near the bus terminal in the evening.',
        'Group of men making loud, derogatory comments near the market exit.',
        'Verbal harassment while walking back from college around 5:30 PM.',
        'Aggressive shouting and lewd gestures from passing vehicle on main road.',
        'Inappropriate remarks and teasing near the coaching institute gate.',
    ],
    'stalking' => [
        'Was followed for two blocks from the metro station to residential street.',
        'A person on a two-wheeler kept slowing down and following along the sidewalk.',
        'Noticed someone tailing me near the park entrance after sunset.',
        'Repeatedly followed while returning home from library in the late evening.',
    ],
    'transport' => [
        'Overcrowded shared auto where co-passenger repeatedly pushed against me intentionally.',
        'Harassment while waiting at the bus stop; no proper lighting or police presence.',
        'Misbehavior inside the public bus during rush hour evening commute.',
        'Conductor and passengers failed to intervene when passenger misbehaved near rear seats.',
    ],
    'workplace' => [
        'Inappropriate personal remarks and unsolicited touch by senior staff during office hours.',
        'Hostile environment and intimidation in the office elevator and pantry.',
        'Persistent unwanted personal calls and comments by a co-worker.',
    ],
    'touching' => [
        'Unwanted physical contact in the crowded shopping lane near festival stalls.',
        'Intentional groping while crossing the busy railway overbridge.',
        'Crowded market area where men deliberately bumped into shoppers repeatedly.',
    ],
    'threats' => [
        'Threatened with retaliation after objecting to lewd remarks near taxi stand.',
        'Intimidation and blocking pathway while trying to enter commercial complex.',
        'Verbal threats when telling bystanders to stop taking unauthorized photos.',
    ],
    'online' => [
        'Received unsolicited harassment and abusive messages across social media.',
        'Targeted harassment campaign in community forum group.',
    ],
    'other' => [
        'Very poorly lit stretch with loitering groups making it unsafe to pass after 8 PM.',
        'Lack of CCTV and deserted corridor leading to parking lot feeling unsafe.',
        'Broken streetlights on main stretch; auto drivers quoting exorbitant fares and passing comments.',
    ]
];

$venueNames = [
    'City Bus Station Complex', 'Central Market Road', 'Metro Station Gate 2',
    'Main Bazaar Crossing', 'Railway Station Road', 'Commercial Plaza',
    'University Road Junction', 'Public Park Perimeter', 'Auto Stand Crossing',
    'Shopping Mall Lane', 'Civil Lines Crossing', 'Court Road Junction'
];

$insertIncident = $db->prepare("
    INSERT INTO hm_incidents (
        id, category, place_type, state_id, city_id, post_office_id, cell_x, cell_y,
        incident_date, incident_hour, incident_minute,
        venue_encrypted, message_encrypted, share_message,
        status, submitted_at, approved_at, receipt_hash, duplicate_hash, delete_after
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?,
        ?, ?, ?,
        'approved', ?, ?, ?, ?, ?
    )
");

$totalInserted = 0;
$cellsInserted = [];
$dailyCounts = [];
$now = time();

$catKeys = array_keys(INCIDENT_CATEGORIES);
$placeKeys = ['public', 'transport', 'office', 'cafe', 'landmark', 'other'];

foreach ($targets as $target) {
    $stateId = $target['state_id'];
    $pin = $target['pincode'];
    $count = $target['count'];
    $label = $target['label'];

    $poStmt = $db->prepare("
        SELECT * FROM hm_post_offices 
        WHERE state_id = ? AND pincode = ? AND latitude IS NOT NULL AND longitude IS NOT NULL 
        LIMIT 1
    ");
    $poStmt->execute([$stateId, $pin]);
    $po = $poStmt->fetch(PDO::FETCH_ASSOC);

    if (!$po) {
        echo "⚠️ Post office not found for PIN {$pin} in {$label}\n";
        continue;
    }

    $lat = (float)$po['latitude'];
    $lng = (float)$po['longitude'];
    $cellX = (int)floor($lng * 4);
    $cellY = (int)floor($lat * 4);
    $poId = (int)$po['id'];
    $cityId = !empty($po['city_id']) ? (int)$po['city_id'] : null;

    for ($i = 0; $i < $count; $i++) {
        $id = bin2hex(random_bytes(16));
        $cat = $catKeys[array_rand($catKeys)];
        $place = $placeKeys[array_rand($placeKeys)];

        // Dates within past 5 to 45 days
        $daysAgo = random_int(5, 45);
        $date = (new DateTimeImmutable("-{$daysAgo} days", new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
        $hour = random_int(7, 23);
        $minute = random_int(0, 59);

        // Approval: 52 to 96 hours ago (so > 48h publication delay)
        $approvedHoursAgo = random_int(52, 96);
        $submittedHoursAgo = $approvedHoursAgo + random_int(4, 24);
        $submittedAt = gmdate('Y-m-d H:i:s', $now - $submittedHoursAgo * 3600);
        $approvedAt = gmdate('Y-m-d H:i:s', $now - $approvedHoursAgo * 3600);
        $deleteAfter = gmdate('Y-m-d H:i:s', $now + 400 * 86400);

        // Share message ~75% of the time so drawer has rich cards
        $share = (random_int(1, 10) <= 7) ? 1 : 0;
        $msgList = $sampleMessages[$cat] ?? $sampleMessages['other'];
        $rawMessage = $msgList[array_rand($msgList)];
        $encryptedMessage = ps_encrypt_string($rawMessage);

        $venueName = $venueNames[array_rand($venueNames)];
        $encryptedVenue = ps_encrypt_venue(['name' => $venueName, 'website' => '']);

        $receipt = ps_next_receipt($db);
        $receiptHash = hash('sha256', strtolower($receipt));
        $dupHash = hash('sha256', $id . $i . random_bytes(4));

        $insertIncident->execute([
            $id, $cat, $place, $stateId, $cityId, $poId, $cellX, $cellY,
            $date, $hour, $minute,
            $encryptedVenue, $encryptedMessage, $share,
            $submittedAt, $approvedAt, $receiptHash, $dupHash, $deleteAfter
        ]);

        $totalInserted++;
        $dailyCounts[$date] = ($dailyCounts[$date] ?? 0) + 1;
    }

    $cellsInserted["{$cellX},{$cellY}"] = ($cellsInserted["{$cellX},{$cellY}"] ?? 0) + $count;
    echo "✓ Seeded {$count} reports for {$label} (PIN: {$pin}, PO: {$po['office_name']}, Cell: {$cellX}, {$cellY})\n";
}

// Update hm_site_daily
$dailyStmt = $db->prepare("INSERT INTO hm_site_daily (day, received_reports) VALUES (?, ?) ON DUPLICATE KEY UPDATE received_reports = received_reports + ?");
foreach ($dailyCounts as $day => $cnt) {
    $dailyStmt->execute([$day, $cnt, $cnt]);
}

echo "\n============================================\n";
echo "Successfully seeded {$totalInserted} dummy reports across " . count($cellsInserted) . " separate PINs/cells in UP, Rajasthan, and Bihar!\n";
echo "============================================\n";
