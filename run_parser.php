<?php
header('Content-Type: application/json');

$script = './parse.sh';

// Make sure the script exists and is executable
if (!file_exists($script)) {
    echo json_encode(['success' => false, 'message' => 'parse.sh not found']);
    exit;
}
if (!is_executable($script)) {
    chmod($script, 0755);
}

exec($script . ' 2>&1', $output, $return_var);

echo json_encode([
    'success' => $return_var === 0,
    'message' => $return_var === 0 ? 'CSV generated successfully' : 'Error running script',
    'output'  => $output
]);

