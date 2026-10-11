<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__) . '/inc/config.php';

$db = ps_db();

// 1. Get all states
$states = $db->query("SELECT id, name, code FROM hm_states ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($states) . " states.\n";

$sampleMessages = [
    'verbal' => [
        'Persistent catcalling and inappropriate remarks near the bus terminal in the evening.',
        'Group of men making loud, derogatory comments near the market exit.',
        'Verbal harassment while walking back from college around 5:30 PM.',
        'Aggressive shouting and lewd gestures from passing vehicle on main road.',
    ],
    'stalking' => [
        'Was followed for two blocks from the metro station to residential street.',
        'A person on a two-wheeler kept slowing down and following along the sidewalk.',
        'Noticed someone tailing me near the park entrance after sunset.',
    ],
    'transport' => [
        'Overcrowded shared auto where co-passenger repeatedly pushed against me intentionally.',
        'Harassment while waiting at the bus stop; no proper lighting or police presence.',
        'Misbehavior inside the public bus during rush hour evening commute.',
    ],
    'workplace' => [
        'Inappropriate personal remarks and unsolicited touch by senior staff during office hours.',
        'Hostile environment and intimidation in the office elevator and pantry.',
    ],
    'touching' => [
        'Unwanted physical contact in the crowded shopping lane near festival stalls.',
        'Intentional groping while crossing the busy railway overbridge.',
    ],
    'threats' => [
        'Threatened with retaliation after objecting to lewd remarks near taxi stand.',
        'Intimidation and blocking pathway while trying to enter commercial complex.',
    ],
    'online' => [
        'Received unsolicited harassment and abusive messages across social media.',
        'Targeted harassment campaign in community forum group.',
    ],
    'other' => [
        'Very poorly lit stretch with loitering groups making it unsafe to pass after 8 PM.',
        'Lack of CCTV and deserted corridor leading to parking lot feeling unsafe.',
    ]
];

$venueNames = [
    'City Bus Station Complex', 'Central Market Road', 'Metro Station Gate 2',
    'Main Bazaar Crossing', 'Railway Station Road', 'Commercial Plaza',
    'University Road Junction', 'Public Park Perimeter', 'Auto Stand Crossing'
];

// Tier counts:
// High hubs (Level 3: >= 30 reports) -> Delhi (East)
// Medium hubs (Level 2: 20-29 reports) -> Maharashtra (Mumbai), Karnataka (Bengaluru)
// Moderate hubs (Level 1: 10-19 reports) -> West Bengal, Tamil Nadu, Telangana, Gujarat, Rajasthan, Uttar Pradesh, Kerala
// Base (Level 0: 5-8 reports) -> All other states
function getTargetCount(string $stateName): int {
    if ($stateName === 'Delhi') return 32; // Level 3 (Deep Crimson)
    if (in_array($stateName, ['Maharashtra', 'Karnataka'], true)) return 22; // Level 2 (Dark Coral)
    if (in_array($stateName, ['West Bengal', 'Tamil Nadu', 'Telangana', 'Gujarat', 'Rajasthan', 'Uttar Pradesh', 'Kerala'], true)) return 12; // Level 1 (Coral Red)
    return random_int(6, 8); // Level 0 (Light Coral)
}

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

foreach ($states as $s) {
    $stateId = (int)$s['id'];
    $stateName = $s['name'];

    // Pick representative post office inside the official outline
    if ($stateId === 10) { // Delhi: avoid cell (308, 114) for test isolation
        $poList = $db->query("SELECT * FROM hm_post_offices WHERE state_id=10 AND (FLOOR(longitude*4)!=308 OR FLOOR(latitude*4)!=114) AND latitude IS NOT NULL AND longitude IS NOT NULL LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $poList = $db->query("SELECT * FROM hm_post_offices WHERE state_id={$stateId} AND latitude IS NOT NULL AND longitude IS NOT NULL LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
    }
    $po = null;
    foreach ($poList as $candidate) {
        if (ps_in_india((float)$candidate['longitude'], (float)$candidate['latitude'])) {
            $po = $candidate;
            break;
        }
    }

    if (!$po) {
        echo "⚠️ No post office inside India outline found for state: {$stateName}\n";
        continue;
    }

    $lat = (float)$po['latitude'];
    $lng = (float)$po['longitude'];

    $cellX = (int)floor($lng * 4);
    $cellY = (int)floor($lat * 4);
    $targetCount = getTargetCount($stateName);

    $catKeys = array_keys(INCIDENT_CATEGORIES);
    $placeKeys = ['public', 'transport', 'office', 'cafe', 'landmark', 'other'];

    for ($i = 0; $i < $targetCount; $i++) {
        $id = bin2hex(random_bytes(16));
        $cat = $catKeys[array_rand($catKeys)];
        $place = $placeKeys[array_rand($placeKeys)];
        $cityId = !empty($po['city_id']) ? (int)$po['city_id'] : null;
        $poId = (int)$po['id'];

        // Dates within the last 2 to 25 days (weighted towards 2-6 days for 7-day view)
        $daysAgo = (random_int(1, 10) <= 7) ? random_int(2, 6) : random_int(7, 25);
        $date = (new DateTimeImmutable("-{$daysAgo} days", new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
        $hour = random_int(8, 22);
        $minute = random_int(0, 59);

        // Timestamps: approved > 48 hours ago
        $approvedHoursAgo = random_int(52, 96);
        $submittedHoursAgo = $approvedHoursAgo + random_int(4, 24);
        $submittedAt = gmdate('Y-m-d H:i:s', $now - $submittedHoursAgo * 3600);
        $approvedAt = gmdate('Y-m-d H:i:s', $now - $approvedHoursAgo * 3600);
        $deleteAfter = gmdate('Y-m-d H:i:s', $now + 400 * 86400);

        // Share message ~60% of the time
        $share = (random_int(1, 10) <= 6) ? 1 : 0;
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

    $cellsInserted["{$cellX},{$cellY}"] = ($cellsInserted["{$cellX},{$cellY}"] ?? 0) + $targetCount;
    echo "✓ Seeded {$targetCount} reports in {$stateName} (Cell: {$cellX}, {$cellY}, PIN: {$po['pincode']})\n";
}

// Update hm_site_daily
$dailyStmt = $db->prepare("INSERT INTO hm_site_daily (day, received_reports) VALUES (?, ?) ON DUPLICATE KEY UPDATE received_reports = received_reports + ?");
foreach ($dailyCounts as $day => $cnt) {
    $dailyStmt->execute([$day, $cnt, $cnt]);
}

echo "\n============================================\n";
echo "Successfully seeded {$totalInserted} dummy reports across " . count($cellsInserted) . " cells in 36 states!\n";
echo "============================================\n";
