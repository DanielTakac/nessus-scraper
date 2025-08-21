#!/usr/bin/env bash
#
# Enrich CSV: read each row of summary.csv
# map "Link" column -> local HTML file path
#

INPUT_FILE=${1:-summary.csv}
OUTPUT_FILE=${2:-enriched.csv}

if [[ ! -f "$INPUT_FILE" ]]; then
  echo "Input file $INPUT_FILE not found"
  exit 1
fi

echo "Processing $INPUT_FILE …"

# Copy the original into OUTPUT – later we will enrich it
cp "$INPUT_FILE" "$OUTPUT_FILE"

# Extract just id (col1) + link (col4), skip header
csvcut -c 1,4 "$INPUT_FILE" | tail -n +2 | \
while IFS=, read -r id link; do
  # Strip quotes
  id="${id%\"}"; id="${id#\"}"
  link="${link%\"}"; link="${link#\"}"

  # If empty, skip
  if [[ -z "$link" ]]; then
    continue
  fi

  # Replace hostname with ..
  local_path="${link/https:\/\/nessus-reports.okte.sk/..}"
  local_path="${local_path/http:\/\/nessus-reports.okte.sk/..}"

  # Trim after .html
  local_path=$(echo "$local_path" | sed -E 's/(\.html).*/\1/')

  # Debug output
  echo "Row $id: $link -> $local_path"

done
