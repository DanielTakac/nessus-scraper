#!/usr/bin/env bash
#
# Append "no vulnerability" server groups to an existing CSV
#

INPUT_FILE=${1:-summary.html}
OUTPUT_FILE=${2:-summary.csv}

# find maximum current ID in the csv, skipping header
row_id=$(tail -n +2 "$OUTPUT_FILE" | cut -d',' -f1 | sort -n | tail -1)
row_id=${row_id:-0}   # default 0 if none

current_group=""
capture_group=""

# pre-split so each <h3> or <font> is on its own line
sed -E 's#<h3#\n<h3#g; s#</h3>#</h3>\n#g; s#<font#\n<font#g; s#</font>#</font>\n#g' "$INPUT_FILE" |
while IFS= read -r line; do
  # detect h3 header
  if [[ $line =~ \<h3[^\>]*\>(.*)\<\/h3\> ]]; then
    raw="${BASH_REMATCH[1]}"
    current_group=$(echo "$raw" \
      | sed -E 's/<[^>]*>//g' \
      | sed -E 's/^[[:space:]]+//;s/[[:space:]]+$//')
    capture_group="$current_group"
    continue
  fi

  # detect "No critical vulnerability..." OR "No high vulnerability..."
  if [[ $line =~ No[[:space:]]+(critical|high)[[:space:]]+vulnerability ]]; then
    vuln_text=$(echo "$line" \
      | sed -E 's/.*(No (critical|high) vulnerability[^<]*).*/\1/' \
      | sed -E 's/^[[:space:]]+//;s/[[:space:]]+$//')
    if [[ -n "$capture_group" ]]; then
      row_id=$((row_id+1))
      printf '"%d","%s","%s","%s","%s","%s"\n' \
        "$row_id" "$capture_group" "" "" "" "$vuln_text" \
        >> "$OUTPUT_FILE"
      capture_group=""
    fi
  fi
done

echo "Done: appended 'no vulnerability' groups. CSV now has $(wc -l < "$OUTPUT_FILE") lines (incl header)."
