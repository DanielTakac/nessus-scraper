<?php
// Scan directory for CSV files
$csvFiles = glob("*.csv");

// If user picked a file via GET param, use it, otherwise the first one
$currentFile = isset($_GET['file']) && in_array($_GET['file'], $csvFiles)
    ? $_GET['file']
    : (count($csvFiles) ? $csvFiles[0] : null);

$data = [];
$headers = [];
if ($currentFile && ($handle = fopen($currentFile, "r")) !== false) {
    $headers = fgetcsv($handle); // First line = headers
    while (($row = fgetcsv($handle)) !== false) {
        $data[] = $row;
    }
    fclose($handle);
}

// Load tag associations
$tagsData = [];
$tagsFile = "tags/tags.csv";
if (file_exists($tagsFile) && ($h = fopen($tagsFile, "r")) !== false) {
    $hdr = fgetcsv($h);
    while (($row = fgetcsv($h)) !== false) {
        [$id, $sg, $tagsStr] = $row;
        $tagsData[$sg] = array_filter(explode(";", $tagsStr));
    }
    fclose($h);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>🔎 Nessus Scraper</title>
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" 
          href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css"/>
    <link rel="stylesheet" type="text/css" 
          href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css"/>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="new_style.css">

</head>
<body>

<h2>📝 Nessus Scraper<!-- - <b>"Vycucávač"--></b></h2>
<div style="text-align:center; margin:15px;">
    <button id="run-parse" class="btn-run-parse">🔄 Refresh Nessus Report</button>
</div>
<div class="file-picker" style="text-align:center; margin:15px 0;">
    <form method="get">
        <label for="file">📂 Choose CSV File:</label>
        <select id="file" name="file" onchange="this.form.submit()">
            <?php foreach ($csvFiles as $f): ?>
                <option value="<?php echo htmlspecialchars($f); ?>" 
                    <?php if ($f === $currentFile) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($f); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>
<div id="report-time" style="text-align:center;">
    <?php
        $filename = 'time.html';
        if (file_exists($filename)) {
            include $filename; 
        } else {
            echo "<em>No report time available.</em>";
        }
    ?>
</div>
<div class="tag-filter-mode">
    <label>Tag Filter Mode:</label>
    <button id="mode-switch" class="mode-btn" data-mode="ALL">AND</button>
</div>
<div class="tag-filters">
<button class="filter-btn" data-filter="all">Show All</button>
<?php
$allTags = [];
foreach($tagsData as $sg=>$tg) $allTags = array_merge($allTags,$tg);
$allTags = array_unique($allTags);
foreach($allTags as $t){
    echo '<button class="filter-btn" data-filter="'.htmlspecialchars($t).'">'.htmlspecialchars($t).'</button>';
}
?>
</div>
<div class="container">
    <table id="myTable" class="display nowrap stripe hover" style="width:100%">
        <thead>
        <tr>
            <?php foreach ($headers as $head): ?>
                <th><?php echo htmlspecialchars($head); ?></th>
            <?php endforeach; ?>
	    <th>Tags</th>
        </tr>
        </thead>
<tbody>
<?php foreach ($data as $row): ?>
    <tr>
        <?php foreach ($row as $colIdx => $cell): ?>
            <?php if ($colIdx === 3): // Example: 4th column is a link ?>
                <td class="link-cell" title="<?php echo htmlspecialchars($cell) ?>">
                  <a href="<?php echo htmlspecialchars($cell) ?>"
                     target="_blank"
                     rel="noopener noreferrer">
                    🔗 Link
                  </a>
                </td>
            <?php else: ?>
                <td title="<?php echo htmlspecialchars($cell) ?>">
                  <?php echo htmlspecialchars($cell) ?>
                </td>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php
        // Lookup tags by server_group
        $serverGroupIndex = array_search('Server Group', $headers);
        $sg = $serverGroupIndex !== false ? $row[$serverGroupIndex] : '';
        $rowTags = $tagsData[$sg] ?? [];
	?>
	<td>
    	    <div class="tag-cell" 
                data-sg="<?php echo htmlspecialchars($sg) ?>" 
                data-tags="<?php echo htmlspecialchars(implode(';', $rowTags)) ?>">
                <button class="tag-dropdown-btn" title="<?php echo htmlspecialchars(implode(';', $rowTags)) ?>">Tags ▾</button>
    	    </div>
	</td>
    </tr>
<?php endforeach; ?>
</tbody>
    </table>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<!-- DataTables Buttons plugins -->
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>

<script>
$(document).ready(function() {
    // PHP variable output so JS knows which file is currently active
    var currentFile = "<?php echo $currentFile; ?>";

    var table = $('#myTable').DataTable({
        dom: 'Bfrtip',
        buttons: ['colvis','pageLength','colvisRestore','copy','csv','excel','pdf'],
        responsive: true,
        paging: true,
        searching: true,
        ordering: true,
	stateSave: true,
	stateDuration: -1,
	lengthMenu: [
            [10, 25, 50, 100, -1],
            [10, 25, 50, 100, "All"]
        ],
        columnDefs: [{ targets: [-3, -2, -1], visible: false, searchable: false }],

        // 👉 Use per-file storage key
        stateSaveCallback: function(settings, data) {
            localStorage.setItem('DataTables_' + currentFile, JSON.stringify(data));
        },
        stateLoadCallback: function(settings) {
            var data = localStorage.getItem('DataTables_' + currentFile);
            return data ? JSON.parse(data) : null;
        }
    });

    // Keep track of active filter
    var activeFilter = 'all';

$('#run-parse').on('click', function() {
  $(this).prop('disabled', true).text('⏳ Generating…');

  $.get('run_parser.php', function(res) {
    if (res.message !== "CSV generated successfully") {
      alert(res.message);
    }
    console.log(res.output); // see server logs in console
    location.reload(); // refresh to show new CSVs
  }, 'json').fail(function() {
    alert("Error: could not run parser script");
  }).always(function() {
    $('#run-parse').prop('disabled', false).text('✅ CSV File Generated');
  });
});

$(document).on('click', '.tag-dropdown-btn', function() {
    var cell = $(this).closest('.tag-cell');
    var sg = cell.data('sg');
    var currentTags = [];
    var tagsAttr = cell.data('tags');
    if (tagsAttr) {
        currentTags = tagsAttr.toString().split(';').filter(t => t.trim() !== '');
    }
    var newTags = prompt("Edit tags for "+sg+" (semicolon separated):", currentTags.join(";"));
    if(newTags === null) return;

    $.post('update_tags.php', { server_group: sg, tags: newTags }, function(res){
	if(res.success){
            $('.tag-cell[data-sg="'+sg+'"]').attr('data-tags', res.tags.join(';'));
            $('.tag-cell[data-sg="'+sg+'"] .tag-dropdown-btn').attr('title', res.tags.join("; "));
        }
    }, 'json');
});

var activeTags = []; // <-- keep track of selected tags
var matchMode = 'ALL'; // default

$.fn.dataTable.ext.search.push(function(settings, data, dataIndex){
    if(activeTags.length === 0) return true; // no filters = show all
    var row = table.row(dataIndex).node();
    var tagsAttr = $(row).find('.tag-cell').data('tags');
    var rowTags = tagsAttr ? tagsAttr.toString().split(';').map(t=>t.trim()) : [];

    if(matchMode === 'ALL') {
        // Row must have *all* selected tags
        return activeTags.every(tag => rowTags.includes(tag));
    } else { // ANY
        // Row must have *at least one* selected tag
        return activeTags.some(tag => rowTags.includes(tag));
    }
});

// Toggle tag filters (same as before)
$(document).on('click', '.filter-btn', function(){
    var tag = $(this).data('filter');

    if(tag === 'all') {
        activeTags = [];
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');
    } else {
        $(this).toggleClass('active');
        $('.filter-btn[data-filter="all"]').removeClass('active');

        if($(this).hasClass('active')) {
            if(!activeTags.includes(tag)) activeTags.push(tag);
        } else {
            activeTags = activeTags.filter(t => t !== tag);
        }
    }

    table.draw();
});

// Mode switch between ALL/ANY
$('#mode-switch').on('click', function(){
    if(matchMode === 'ALL') {
        matchMode = 'ANY';
        $(this).text('OR').attr('data-mode','ANY');
    } else {
        matchMode = 'ALL';
        $(this).text('AND').attr('data-mode','ALL');
    }
    table.draw();
});

});
</script>

</body>
<footer class="site-footer">
    <p>🔒 Nessus Scraper &copy; <?php echo date("Y"); ?> Daniel Takáč</p>
</footer>
</html>
