#!/usr/bin/env bash

rm critical.csv
rm high.csv

rm ext/critical_extended.csv
rm ext/high_extended.csv

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

