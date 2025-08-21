#!/usr/bin/env bash

./parse_summary.sh ../R/summary.html critical.csv

./update_time.sh

./parse_summary.sh ../R/summary_high.html high.csv

