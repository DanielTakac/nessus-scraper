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

  # … after computing file_path & fragid …

  # 1) pull section from id="idN-container" down to the next vuln header
  section=$(
    awk -v start="id=\"$fragid-container\"" \
        -v endpat='^<div xmlns="" id="id[0-9]+"[^>]*style=' \
    '
      $0 ~ start     {flag=1; next}   # begin after the container marker
      flag {
        if ($0 ~ endpat) exit         # stop at next vuln header
        print
      }
    ' "$file_path"
  )

  echo "Row $id ▶ SECTION snippet:" >&2
  echo "$section" | sed -n '1,12p' >&2

  # 2) Flatten & extract CVSS with a single sed regex
  flat=$(printf "%s" "$section" | tr "\n" " ")
  cvss=$(printf "%s" "$flat" \
    | sed -nE 's#.*CVSS v3\.0 Base Score</div>[[:space:]]*<div[^>]*>\s*([^<]+)<.*#\1#p' )
  cvss=${cvss##*( )}; cvss=${cvss%%*( )}  # trim whitespace

  echo "Row $id ▶ extracted CVSS='$cvss'" >&2

  # 3) Servers extraction stays the same
  servers=$(echo "$section" \
            | grep -oP '(?<=<h2>).*?(?=</h2>)' \
            | paste -sd';' -)
  echo "Row $id ▶ extracted Servers='$servers'" >&2

  echo "Row $id: CVSS=$cvss | Servers=$servers"

done

