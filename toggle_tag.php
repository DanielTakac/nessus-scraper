<?php
header('Content-Type: application/json');

$vulnId = $_POST['vuln_id'] ?? null;
$tag = $_POST['tag'] ?? null;
if (!$vulnId || !in_array($tag, ['tag1','tag2','tag3'])) {
    echo json_encode(['success'=>false,'message'=>'Invalid input']);
    exit;
}

$file = "tags/tags.csv";

// Load all into memory
$rows = [];
if (($h = fopen($file, "r")) !== false) {
    $headers = fgetcsv($h);
    while (($row = fgetcsv($h)) !== false) {
        $rows[] = $row;
    }
    fclose($h);
}

// Find row with vuln_id or create new
$found = false;
foreach ($rows as &$r) {
    if ($r[1] == $vulnId) {
        $found = true;
        $idx = array_search($tag, $headers);
        $r[$idx] = ($r[$idx] === "1") ? "0" : "1";
        $newValue = (int)$r[$idx];
        break;
    }
}
unset($r);

// If not found → add
if (!$found) {
    $id = count($rows) + 1;
    $newRow = [$id, $vulnId, 0,0,0];
    $idx = array_search($tag, $headers);
    $newRow[$idx] = "1";
    $newValue = 1;
    $rows[] = $newRow;
}

// Write back
if (($h = fopen($file, "w")) !== false) {
    fputcsv($h, $headers);
    foreach ($rows as $r) { fputcsv($h, $r); }
    fclose($h);
}

echo json_encode(['success'=>true,'value'=>$newValue]);
