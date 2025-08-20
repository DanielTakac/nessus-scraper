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
    <title>Dynamic Table Example</title>
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" 
          href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css"/>
    <link rel="stylesheet" type="text/css" 
          href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css"/>

    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: #f4f6f9;
        }

        h2 {
            text-align: center;
            padding: 20px;
            margin: 0;
            background: #3949ab;
            color: white;
            font-weight: 500;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }

        .container {
            padding: 30px;
        }

        table.dataTable {
            border-collapse: collapse;
            width: 100% !important;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        table.dataTable thead {
            background: #3949ab;
            color: white;
        }

        table.dataTable thead th {
            padding: 12px;
            font-size: 14px;
        }

        table.dataTable tbody tr:nth-child(even) {
            background: #f9f9f9;
        }

        table.dataTable tbody tr:hover {
            background: #e8eaf6;
        }

        table.dataTable td {
            padding: 10px;
            font-size: 14px;
        }

        /* Style buttons (export, column vis, etc.) */
        .dt-buttons button {
            background: #3949ab !important;
            border: none !important;
            color: white !important;
            padding: 6px 12px !important;
            margin-right: 5px;
            border-radius: 4px !important;
            cursor: pointer;
            transition: 0.3s;
        }

        .dt-buttons button:hover {
            background: #303f9f !important;
        }

        /* Search bar and page length dropdown styling */
        .dataTables_filter input {
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 5px;
            margin-left: 8px;
        }

        .dataTables_length select {
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 4px;
        }

        /* Pagination buttons */
        .dataTables_paginate a {
            padding: 6px 10px !important;
            border-radius: 4px;
            margin: 2px;
            border: 1px solid #ddd !important;
            color: #3949ab !important;
            text-decoration: none !important;
        }

        .dataTables_paginate a.current {
            background-color: #3949ab !important;
            color: white !important;
            border: 1px solid #3949ab !important;
        }
	.file-picker select {
    padding: 6px 10px;
    font-size: 14px;
    border-radius: 4px;
    border: 1px solid #ccc;
    margin-left: 5px;
}
.file-picker label {
    font-weight: bold;
    margin-right: 5px;
}

.tag-btn {
    margin: 2px;
    padding: 5px 10px;
    border: 1px solid #3949ab;
    border-radius: 4px;
    background: white;
    color: #3949ab;
    cursor: pointer;
    transition: 0.2s;
}
.tag-btn:hover { background: #e8eaf6; }
.tag-btn.active {
    background: #3949ab;
    color: white;
    box-shadow: 0 0 8px rgba(57,73,171,0.8);
}

.tag-filters {
    text-align: left;
    margin: 15px;
}

.filter-btn {
    display: inline-block;
    padding: 8px 14px;
    margin: 0 6px 8px 0;
    font-size: 14px;
    font-weight: 500;
    border-radius: 10px;
    border: 2px solid #3949ab;
    background: #fff;
    color: #3949ab;
    cursor: pointer;
    transition: all 0.25s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
}

.filter-btn:hover {
    background: #e8eaf6;
    box-shadow: 0 3px 6px rgba(0,0,0,0.15);
    transform: translateY(-1px);
}

.filter-btn.active {
    background: #3949ab;
    color: #fff;
    border-color: #3949ab;
    box-shadow: 0 4px 8px rgba(57,73,171,0.3);
}

.site-footer {
    text-align: center;
    padding: 15px;
    background: #3949ab;
    color: white;
    font-size: 14px;
    position: fixed;   /* Sticks to bottom */
    bottom: 0;
    left: 0;
    width: 100%;
    box-shadow: 0 -2px 6px rgba(0,0,0,0.15);
}

.site-footer p {
    margin: 0;
}

/* Cap max column width and ellipsize overflow */
#myTable td {
    max-width: 200px;        /* ← set your X pixels here */
    white-space: nowrap;     /* prevent wrapping */
    overflow: hidden;        /* hide overflow text */
    text-overflow: ellipsis; /* show … when truncated */
    position: relative;      /* needed for tooltip */
    cursor: help;
}

/* Show full content on hover via tooltip-like effect */
#myTable td:hover::after {
    content: attr(title);     /* display full text from title attr */
    position: absolute;
    left: 0;
    top: 100%;
    white-space: normal;
    background: #333;
    color: #fff;
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 13px;
    max-width: 400px;
    z-index: 10;
    box-shadow: 0 3px 6px rgba(0,0,0,0.2);
}
    </style>
</head>
<body>

<h2>📝 Nessus Scraper - <b>"Vycucávač"</b></h2>
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
<div class="container">
    <div class="tag-filters" style="text-align:left; margin:15px;">
        <button class="filter-btn" data-filter="all">Reset Filter</button>
        <button class="filter-btn" data-filter="tag1">Filter Tag #1</button>
        <button class="filter-btn" data-filter="tag2">Filter Tag #2</button>
        <button class="filter-btn" data-filter="tag3">Filter Tag #3</button>
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
	<?php foreach ($row as $cell): ?>
            <td title="<?php echo htmlspecialchars($cell); ?>">
    		<?php echo htmlspecialchars($cell); ?>
	    </td>
        <?php endforeach; ?>
	<td>
            <?php for ($i=1; $i<=3; $i++): 
                $active = $rowTags["tag$i"] == "1";
            ?>
                <button class="tag-btn <?= $active ? 'active' : '' ?>" 
                        data-vuln="<?= $vulnId ?>" data-tag="tag<?= $i ?>">
                    <?php
			if($i == 1) {
			   echo "1️⃣";
			} elseif($i == 2) {
                           echo "2️⃣2️⃣";
                        } elseif($i == 3) {
                           echo "3️⃣3️⃣3️⃣";
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
</script>

</body>
<footer class="site-footer">
    <p>🔒 Nessus Scraper &copy; <?php echo date("Y"); ?> Daniel Takáč + GPT-5 :)</p>
</footer>
</html>
