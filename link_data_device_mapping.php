<?php

declare(strict_types=1);

/**
 * Usage:
 *   php link_data_device_mapping.php <link_status.csv> <link_sdvn.csv> [output.csv]
 */

if ($argc < 3 || $argc > 4) {
    fwrite(STDERR, "Usage: php link_data_device_mapping.php <link_status.csv> <link_sdvn.csv> [output.csv]\n");
    exit(1);
}

$statusCsvPath = $argv[1];
$sdvnCsvPath = $argv[2];
$outputCsvPath = $argv[3] ?? 'output.csv';

$allowedBuckets = [1, 32, 64, 128, 256, 512, 1024];

/**
 * Parse a port value in the form DEVICE-ENET-NUMBER.
 *
 * @return array{device_name: string, enet_number: int}|null
 */
function parsePort(string $port): ?array
{
    $port = trim($port);
    if ($port === '') {
        return null;
    }

    if (!preg_match('/^(.*)-ENET-(\d+)$/i', $port, $matches)) {
        return null;
    }

    $deviceName = trim($matches[1]);
    $enetNumber = (int)$matches[2];

    if ($deviceName === '' || $enetNumber <= 0) {
        return null;
    }

    return [
        'device_name' => $deviceName,
        'enet_number' => $enetNumber,
    ];
}

function normalizeHeaderValue(string $value): string
{
    $value = ltrim($value, "\xEF\xBB\xBF");
    return strtolower(trim($value));
}

function smallestBucketForMaxEnet(int $maxEnet, array $buckets): int
{
    foreach ($buckets as $bucket) {
        if ($maxEnet <= $bucket) {
            return $bucket;
        }
    }

    return (int)end($buckets);
}

$statusMap = [];
$statusHandle = fopen($statusCsvPath, 'r');
if ($statusHandle === false) {
    fwrite(STDERR, "Unable to open link status file: {$statusCsvPath}\n");
    exit(1);
}

$statusRowNum = 0;
while (($row = fgetcsv($statusHandle)) !== false) {
    $statusRowNum++;
    if ($row === [null] || count($row) < 2) {
        continue;
    }

    $linkName = trim((string)$row[0]);
    $linkState = trim((string)$row[1]);

    if ($statusRowNum === 1 && normalizeHeaderValue($linkName) === 'link_name') {
        continue;
    }

    if ($linkName === '') {
        continue;
    }

    $statusMap[$linkName] = $linkState;
}
fclose($statusHandle);

$records = [];
$maxEnetByDevice = [];
$presentEnetByDevice = [];

$sdvnHandle = fopen($sdvnCsvPath, 'r');
if ($sdvnHandle === false) {
    fwrite(STDERR, "Unable to open link SDVN file: {$sdvnCsvPath}\n");
    exit(1);
}

$sdvnRowNum = 0;
while (($row = fgetcsv($sdvnHandle)) !== false) {
    $sdvnRowNum++;
    if ($row === [null] || count($row) < 5) {
        continue;
    }

    $linkName = trim((string)$row[0]);
    $startPort = trim((string)$row[1]);
    $startPortCapacity = trim((string)$row[2]);
    $endPort = trim((string)$row[3]);
    $endPortCapacity = trim((string)$row[4]);

    if ($sdvnRowNum === 1 && normalizeHeaderValue($linkName) === 'link_name') {
        continue;
    }

    $portCandidates = [
        [$startPort, $startPortCapacity],
        [$endPort, $endPortCapacity],
    ];

    foreach ($portCandidates as [$portValue, $capacityValue]) {
        $parsed = parsePort($portValue);
        if ($parsed === null) {
            continue;
        }

        $deviceName = $parsed['device_name'];
        $enetNumber = $parsed['enet_number'];

        if (!preg_match('/(NATX|EXE|IPX)/i', $deviceName)) {
            continue;
        }

        $records[] = [
            'device_name' => $deviceName,
            'enet_number' => $enetNumber,
            'link_capacity' => $capacityValue,
            'link_name' => $linkName,
        ];

        if (!isset($maxEnetByDevice[$deviceName]) || $enetNumber > $maxEnetByDevice[$deviceName]) {
            $maxEnetByDevice[$deviceName] = $enetNumber;
        }

        if (!isset($presentEnetByDevice[$deviceName])) {
            $presentEnetByDevice[$deviceName] = [];
        }
        $presentEnetByDevice[$deviceName][$enetNumber] = true;
    }
}
fclose($sdvnHandle);

$bucketByDevice = [];
foreach ($maxEnetByDevice as $deviceName => $maxEnet) {
    $bucketByDevice[$deviceName] = smallestBucketForMaxEnet((int)$maxEnet, $allowedBuckets);
}

$outputHandle = fopen($outputCsvPath, 'w');
if ($outputHandle === false) {
    fwrite(STDERR, "Unable to write output file: {$outputCsvPath}\n");
    exit(1);
}

fputcsv($outputHandle, ['device_naame', 'enet_number', 'link_capacity', 'link_state']);

$outputRows = [];
foreach ($records as $record) {
    $deviceName = $record['device_name'];
    $linkName = $record['link_name'];
    $linkState = $statusMap[$linkName] ?? '';

    $outputRows[] = [
        'device_name' => $deviceName,
        'enet_number' => (int)$record['enet_number'],
        'link_capacity' => (string)$record['link_capacity'],
        'link_state' => $linkState,
    ];
}

foreach ($bucketByDevice as $deviceName => $bucketLimit) {
    for ($enetNumber = 1; $enetNumber <= $bucketLimit; $enetNumber++) {
        if (isset($presentEnetByDevice[$deviceName][$enetNumber])) {
            continue;
        }

        $outputRows[] = [
            'device_name' => $deviceName,
            'enet_number' => $enetNumber,
            'link_capacity' => '',
            'link_state' => '',
        ];
    }
}

usort($outputRows, static function (array $a, array $b): int {
    $deviceCompare = strcmp($a['device_name'], $b['device_name']);
    if ($deviceCompare !== 0) {
        return $deviceCompare;
    }

    return $a['enet_number'] <=> $b['enet_number'];
});

foreach ($outputRows as $row) {
    fputcsv($outputHandle, [
        $row['device_name'],
        (string)$row['enet_number'],
        $row['link_capacity'],
        $row['link_state'],
    ]);
}

fclose($outputHandle);

fwrite(STDOUT, "Wrote " . count($outputRows) . " rows to {$outputCsvPath}\n");
