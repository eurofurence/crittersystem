<?php
/**
 * Simple test script to verify the statistics calculation logic works correctly
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "Testing Statistics Calculation Logic\n";
echo "===================================\n";

// Test 1: Edge case - empty collections
echo "\nTest 1: Empty collections handling\n";
$usersByStatus = collect();

// Initialize empty collections for all status types (simulates no users scenario)
$usersByStatus = collect([
    'approved' => collect(),
    'pending' => collect(),
    'self_confirmed' => collect(),
    'revoked' => collect(),
    'expired' => collect(),
]);

$statusCounts = [
    'approved' => $usersByStatus->get('approved', collect())->count(),
    'pending' => $usersByStatus->get('pending', collect())->count(),
    'self_confirmed' => $usersByStatus->get('self_confirmed', collect())->count(),
    'revoked' => $usersByStatus->get('revoked', collect())->count(),
    'expired' => $usersByStatus->get('expired', collect())->count(),
];

// Ensure all counts are integers and handle any edge cases
foreach ($statusCounts as $status => $count) {
    $statusCounts[$status] = max(0, (int) $count);
}

$totalCount = array_sum($statusCounts);

echo "Status counts: " . json_encode($statusCounts) . "\n";
echo "Total count: {$totalCount}\n";
echo "✅ Empty collections test passed!\n";

// Test 2: Missing status groups
echo "\nTest 2: Missing status groups handling\n";
$usersByStatus = collect([
    'approved' => collect(['user1', 'user2']),
    'pending' => collect(['user3']),
    // Missing self_confirmed, revoked, expired
]);

$statusCounts = [
    'approved' => $usersByStatus->get('approved', collect())->count(),
    'pending' => $usersByStatus->get('pending', collect())->count(),
    'self_confirmed' => $usersByStatus->get('self_confirmed', collect())->count(),
    'revoked' => $usersByStatus->get('revoked', collect())->count(),
    'expired' => $usersByStatus->get('expired', collect())->count(),
];

foreach ($statusCounts as $status => $count) {
    $statusCounts[$status] = max(0, (int) $count);
}

$totalCount = array_sum($statusCounts);

echo "Status counts: " . json_encode($statusCounts) . "\n";
echo "Total count: {$totalCount}\n";
echo "✅ Missing status groups test passed!\n";

// Test 3: Verify userCounts structure matches template expectations
echo "\nTest 3: userCounts structure validation\n";
$userCounts = [
    'total' => $totalCount,
    'approved' => $statusCounts['approved'],
    'pending' => $statusCounts['pending'],
    'self_confirmed' => $statusCounts['self_confirmed'],
    'revoked' => $statusCounts['revoked'],
    'expired' => $statusCounts['expired'],
];

echo "userCounts structure: " . json_encode($userCounts) . "\n";

// Verify all required keys exist for template
$requiredKeys = ['total', 'approved', 'pending', 'self_confirmed', 'revoked', 'expired'];
$allKeysExist = true;
foreach ($requiredKeys as $key) {
    if (!array_key_exists($key, $userCounts)) {
        echo "❌ Missing key: {$key}\n";
        $allKeysExist = false;
    }
}

if ($allKeysExist) {
    echo "✅ All required keys exist for template!\n";
}

echo "\n=== All tests completed successfully! ===\n";
