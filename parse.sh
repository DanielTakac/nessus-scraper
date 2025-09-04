#!/usr/bin/env bash

rm critical.csv
rm high.csv
rm combined.csv

rm ext/critical_extended.csv
rm ext/high_extended.csv
rm ext/combined_extended.csv

./parse_summary.sh ../R/summary.html critical.csv

./update_time.sh

./parse_summary.sh ../R/summary_high.html high.csv

python3 enrich_csv.py critical.csv critical.csv
python3 enrich_csv.py high.csv high.csv

python3 build_csv.py critical.csv
python3 build_csv.py high.csv

./clean_csv.sh critical.csv
./clean_csv.sh high.csv

./append_empty_rows.sh ../R/summary.html critical.csv
./append_empty_rows.sh ../R/summary_high.html high.csv

./append_empty_rows_ext.sh ../R/summary.html ext/critical_extended.csv
./append_empty_rows_ext.sh ../R/summary_high.html ext/high_extended.csv

./combine_csv.sh critical.csv high.csv combined.csv
./combine_csv.sh ext/critical_extended.csv ext/high_extended.csv ext/combined_extended.csv

