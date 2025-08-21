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

// Load tags from tags/tags.csv into associative array by vuln_id
$tags = [];
if (($handle = fopen("tags/tags.csv", "r")) !== false) {
    $headersTags = fgetcsv($handle); // skip header
    while (($row = fgetcsv($handle)) !== false) {
        $rowAssoc = array_combine($headersTags, $row);
        $tags[$rowAssoc['vuln_id']] = $rowAssoc;
    }
    fclose($handle);
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
<div id="report-time" style="margin-left:50px;">
    <?php
        $filename = 'time.html';
        if (file_exists($filename)) {
            include $filename; 
        } else {
            echo "<em>No report time available.</em>";
        }
    ?>
</div>
<div class="container">
    <div class="tag-filters" style="text-align:left; margin:15px;">
        <button class="filter-btn" data-filter="all">Remove Filter</button>
        <button class="filter-btn" data-filter="tag1">Filter External <?php echo '<img src="img/external-sys-tag.png" alt="External" width="15" height="15"/>'; ?></button>
        <button class="filter-btn" data-filter="tag2">Filter Internal <?php echo '<img src="img/internal-sys-tag.png" alt="Internal" width="15" height="15"/>'; ?></button>
        <button class="filter-btn" data-filter="tag3">Filter Important <?php echo '<img src="img/warning-tag.png" alt="Important" width="15" height="15"/>'; ?></button>
    </div>
    <table id="myTable" class="display nowrap stripe hover" style="width:100%">
        <thead>
        <tr>
            <?php foreach ($headers as $head): ?>
                <th><?php echo htmlspecialchars($head); ?></th>
            <?php endforeach; ?>
	    <th>Tags</th>
	    <th class="tag1" style="display:none;">tag1</th>
	    <th class="tag2" style="display:none;">tag2</th>
	    <th class="tag3" style="display:none;">tag3</th>
        </tr>
        </thead>
        <tbody>
<?php foreach ($data as $row): 
      $vulnId = $row[1]; // Assuming vuln_id is column 1 (adjust index)
      $rowTags = isset($tags[$vulnId]) ? $tags[$vulnId] : ['tag1'=>0,'tag2'=>0,'tag3'=>0];
?>
    <tr 
  data-tag1="<?= $rowTags['tag1'] ?>" 
  data-tag2="<?= $rowTags['tag2'] ?>" 
  data-tag3="<?= $rowTags['tag3'] ?>">
	<?php foreach ($row as $colIdx => $cell): ?>
      <?php if ($colIdx === 3): // 4th column = link ?>
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
	<td>
            <?php for ($i=1; $i<=3; $i++): 
                $active = $rowTags["tag$i"] == "1";
            ?>
                <button class="tag-btn <?= $active ? 'active' : '' ?>" 
                        data-vuln="<?= $vulnId ?>" data-tag="tag<?= $i ?>">
                    <?php
			if($i == 1) {
			   echo '<img src="img/external-sys-tag.png" alt="External" width="20" height="20"/>';
			} elseif($i == 2) {
                           echo '<img src="img/internal-sys-tag.png" alt="Internal" width="20" height="20"/>';
                        } elseif($i == 3) {
                           echo '<img src="img/warning-tag.png" alt="Important" width="20" height="20"/>';
                        }
		    ?>
                </button>
            <?php endfor; ?>
        </td>
	<td style="display:none;"><?= $rowTags['tag1'] ?></td>
	<td style="display:none;"><?= $rowTags['tag2'] ?></td>
	<td style="display:none;"><?= $rowTags['tag3'] ?></td>
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

$.fn.dataTable.ext.search.push(function(settings, data, dataIndex){
  if(activeFilter === 'all') return true;
  // get the <tr> for this row
  var rowNode = table.row(dataIndex).node();
  // read the live data-attribute
  return $(rowNode).attr('data-' + activeFilter) === '1';
});

// Button binding
$('.filter-btn').on('click', function() {
    $('.filter-btn').removeClass('active');
    $(this).addClass('active');
    activeFilter = $(this).data('filter');
    table.draw();
});
});

$('#myTable tbody').on('click', '.tag-btn', function(){
  var btn    = $(this),
      tr     = btn.closest('tr'),
      tag    = btn.data('tag'),     // "tag1","tag2","tag3"
      vulnId = btn.data('vuln');

  $.post('toggle_tag.php', { vuln_id: vulnId, tag: tag }, function(res){
    if(!res.success){
      return alert("Error: "+res.message);
    }
    // 1) toggle the button UI
    btn.toggleClass('active', res.value === 1);

    // 2) update the <tr> data-attribute
    tr.attr('data-' + tag, res.value);

    // 3) re-filter/re-draw immediately
    table.draw(false);
  }, 'json');
});

$('#run-parse').on('click', function() {
  // if (!confirm("This will regenerate the CSV files. Continue?")) return;

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
</script>

</body>
<footer class="site-footer">
    <p>🔒 Nessus Scraper &copy; <?php echo date("Y"); ?> Daniel Takáč</p>
</footer>
</html>
