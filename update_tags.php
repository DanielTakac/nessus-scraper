<?php
header('Content-Type: application/json');
$sg = $_POST['server_group'] ?? null;
$tags = $_POST['tags'] ?? '';

if (!$sg) { echo json_encode(['success'=>false]); exit; }

$file = "tags/tags.csv";
$lines = [];
if (file_exists($file) && ($h=fopen($file,"r"))) {
    $header = fgetcsv($h);
    while(($r=fgetcsv($h))!==false) { $lines[] = $r; }
    fclose($h);
} else {
    $header = ['id','server_group','tags'];
}

$found=false;
foreach($lines as &$r){
    if($r[1]===$sg){
        $r[2]=$tags;
        $found=true;
        break;
    }
}
unset($r);

if(!$found) {
    $lines[] = [count($lines)+1, $sg, $tags];
}

$h=fopen($file,"w");
fputcsv($h,$header);
foreach($lines as $r) fputcsv($h,$r);
fclose($h);

echo json_encode(['success'=>true,'tags'=>explode(";", $tags)]);
