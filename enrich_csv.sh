#!/usr/bin/env bash
#
# Enrich CSV: safe CSV parser using csvkit
#

INPUT_FILE=${1:-critical.csv}
OUTPUT_FILE=${2:-enriched.csv}

if [[ ! -f "$INPUT_FILE" ]]; then
  echo "Input file $INPUT_FILE not found"
  exit 1
fi

echo "Processing $INPUT_FILE …"

cp "$INPUT_FILE" "$OUTPUT_FILE"

# Use csvcut to pull out id + link, which are col 1 and 3
csvcut -c 1,3 "$INPUT_FILE" | tail -n +2 | while IFS=, read -r id link; do
  id=${id#\"}; id=${id%\"}
  link=${link#\"}; link=${link%\"}

  file_path=$(echo "$link" | sed -E 's#https?://nessus-reports\.okte\.sk#/..#')
  file_path=$(echo "$file_path" | sed -E 's/(.html).*/\1/')
  fragment=$(echo "$link" | sed -n 's/.*\(#id[0-9]\+\).*/\1/p')
  fragid=${fragment#\#}   # "id2"

  # FIX: remove accidental leading slash
  file_path=${file_path#/}

  # isolate section: from <div id="idN-container"> until next "idN+1"
  section=$(awk -v start="id=\"$fragid-container\"" -v idpat="id=\"id[0-9]+\"" '
    $0 ~ start {flag=1}
    flag {print}
    $0 ~ idpat && $0 !~ start && flag {exit}
  ' "$file_path")

  # CVSS score: line after "CVSS v3.0 Base Score"
  cvss=$(echo "$section" | awk '/CVSS v3\.0 Base Score/{getline; print}' | sed -E 's/<[^>]+>//g' | xargs)

  # Servers: all <h2>…</h2>
  servers=$(echo "$section" | grep -oP '(?<=<h2>).*?(?=</h2>)' | paste -sd ';' -)

  echo "Row $id: CVSS=$cvss | Servers=$servers"
done

