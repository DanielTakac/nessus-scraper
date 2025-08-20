#!/usr/bin/env bash
#
# parse_summary.sh  – turn summary.html into CSV of (group, link, vulnerability)
#

INPUT_FILE=${1:-summary.html}
OUTPUT_FILE=${2:-summary.csv}

rm $OUTPUT_FILE

# Print header
printf 'id,vuln_id,server_group,link,vulnerability\n' > "$OUTPUT_FILE"

current_group=""
row_id=0

# Pre‐split the file so each <li>…</li> sits on its own line
# then read line-by-line
sed -E 's#<li#\n<li#g; s#</li>#</li>\n#g' "$INPUT_FILE" |
while IFS= read -r line; do

  # 1) Capture a new group name when you see <h3>…</h3>
  if [[ $line =~ \<h3[^\>]*\>(.*)\<\/h3\> ]]; then
    raw="${BASH_REMATCH[1]}"
    # strip ANY leftover HTML tags & trim
    current_group=$(echo "$raw" \
      | sed -E 's/<[^>]*>//g' \
      | sed -E 's/^[[:space:]]+//;s/[[:space:]]+$//')
    continue
  fi

  # 2) When you see an <li> with href="…", grab link + text
  if [[ $line =~ href=\"([^\"]+)\" ]]; then
    link="${BASH_REMATCH[1]}"

    # extract the text between the first > and </a>
    vuln=$(echo "$line" \
      | sed -E 's/.*href="[^"]*"[^>]*>([^<]+)<.*/\1/' \
      | sed -E 's/^[[:space:]]+//;s/[[:space:]]+$//')

    # Build string to hash
    to_hash="${current_group}|${link}|${vuln}"
    hash=$(echo -n "$to_hash" | sha1sum | cut -c1-10)

    vuln_id="${hash}"

    row_id=$((row_id+1))

    # emit CSV row, quoting fields
    printf '"%d","%s","%s","%s","%s"\n' \
      "$row_id" "$vuln_id" "$current_group" "$link" "$vuln" \
      >> "$OUTPUT_FILE"
  fi

done

echo "Done: wrote $(wc -l < "$OUTPUT_FILE") lines (including header) to $OUTPUT_FILE"

