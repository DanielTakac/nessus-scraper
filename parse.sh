#!/usr/bin/env bash

rm critical.csv
rm high.csv
rm combined.csv

rm ext/critical_extended.csv
rm ext/high_extended.csv
rm ext/combined_extended.csv

echo "--- update_time.sh - Getting new report time ---"
./update_time.sh

echo "--- parse_summary.sh - Parsing ../R/summary.html ---"
./parse_summary.sh ../R/summary.html critical.csv
./parse_summary.sh ../R/summary_high.html high.csv

echo "--- enrich_csv.py - Extracting CVSS & Servers Affected from every link ---"
python3 enrich_csv.py critical.csv critical.csv
python3 enrich_csv.py high.csv high.csv

echo "--- build_csv.py - Trimming CVSS and adding full CVSS and Description to extended CSV files ---"
python3 build_csv.py critical.csv
python3 build_csv.py high.csv

echo "--- clean_csv.sh - Removing unnecessary protocol and port info from Servers Affected column ---"
./clean_csv.sh critical.csv
./clean_csv.sh high.csv

echo "--- append_empty_rows.sh - Adding empty server groups to CSV files ---"
./append_empty_rows.sh ../R/summary.html critical.csv
./append_empty_rows.sh ../R/summary_high.html high.csv

echo "--- append_empty_rows.sh - Adding empty server groups to extended CSV files ---"
./append_empty_rows_ext.sh ../R/summary.html ext/critical_extended.csv
./append_empty_rows_ext.sh ../R/summary_high.html ext/high_extended.csv

echo "--- combine_csv.sh - Merging critical and high CSV files into combined.csv ---"
./combine_csv.sh critical.csv high.csv combined.csv
./combine_csv.sh ext/critical_extended.csv ext/high_extended.csv ext/combined_extended.csv

echo
echo "=== DONE ==="

