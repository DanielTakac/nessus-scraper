#!/usr/bin/env bash
#
# Debug enrich_csv.sh
#

INPUT_FILE=${1:-critical.csv}
OUTPUT_FILE=${2:-enriched.csv}

if [[ ! -f "$INPUT_FILE" ]]; then
  echo "ERROR: Input file $INPUT_FILE not found" >&2
  exit 1
fi

echo "Processing $INPUT_FILE …" >&2

cp "$INPUT_FILE" "$OUTPUT_FILE"

# Adjust "3" below if Link is NOT in column 3
csvcut -c 1,3 "$INPUT_FILE" | tail -n +2 | while IFS=, read -r id link; do
  id=${id#\"}; id=${id%\"}
  link=${link#\"}; link=${link%\"}

  echo "----" >&2
  echo "Row $id ▶ raw link='$link'" >&2

  # Build local path correctly
  file_path=$(echo "$link" \
      | sed -E 's#https?://nessus-reports\.okte\.sk##' \
      | sed -E 's/(.html).*/\1/')
  file_path="..$file_path"

  echo "Row $id ▶ resolved file_path='$file_path'" >&2

  if [[ ! -f "$file_path" ]]; then
    echo "‼️ WARNING: file '$file_path' does NOT exist" >&2
  fi

  # Extract fragment (#idN)
  fragment=""
  if [[ "$link" =~ (#id[0-9]+)$ ]]; then
    fragment="${BASH_REMATCH[1]}"
  fi
  fragid=${fragment#\#}
  echo "Row $id ▶ fragment='$fragment' (fragid='$fragid')" >&2

  # isolate section: from <div id="idN-container"> until the next vuln header <div xmlns="" id="idM" style="box-sizing
  # … after you’ve computed file_path and fragid …

  # 1) grab from idN-container to next idX-container (inclusive)
  section=$(
    sed -n -E \
      "/id=\"${fragid}-container\"/,/id=\"id[0-9]+-container\"/p" \
      "$file_path" \
    | sed '1d;$d'   # drop the start/end marker lines themselves
  )

  # Debug: show us more of the section
  echo "Row $id ▶ SECTION lines:" >&2
  echo "$section" | sed -n '1,15p' >&2

  # ─────────────────────────────────────────────
  # 1) Flatten the section into one line
  flat_section=$(printf "%s" "$section" | tr '\n' ' ')

  # 2) Extract CVSS v3.0 Base Score with sed
  cvss=$(printf "%s" "$flat_section" \
    | sed -nE 's#.*<div class="details-header">CVSS v3\.0 Base Score</div>[[:space:]]*<div[^>]*>\s*([^<]+)<.*#\1#p')

  # Trim whitespace
  cvss=${cvss##*( )}
  cvss=${cvss%%*( )}

  echo "Row $id ▶ extracted CVSS='$cvss'" >&2
  # ─────────────────────────────────────────────

  # Extract servers
  servers=$(echo "$section" \
            | grep -oP '(?<=<h2>).*?(?=</h2>)' \
            | paste -sd ';' -)
  echo "Row $id ▶ extracted Servers='$servers'" >&2

  # Print summary
  echo "Row $id: CVSS=$cvss | Servers=$servers"

done

