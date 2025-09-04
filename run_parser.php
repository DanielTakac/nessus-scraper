<?php
header('Content-Type: application/json');

// 1) Clear any old log
file_put_contents('parser.log', '');

// 2) Fire off parse.sh in the background, redirecting both stdout+stderr
//    nohup and trailing & detach it immediately
$cmd = 'nohup '.escapeshellcmd(__DIR__.'/parse.sh').' > '.escapeshellarg(__DIR__.'/parser.log').' 2>&1 & echo $!';
$pid = trim(shell_exec($cmd));

// 3) Return success + pid
echo json_encode([
  'success' => true,
  'pid'     => $pid
]);
