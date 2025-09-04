#!/bin/bash
# merge two CSV files and shift ids
# usage: ./combine_csv first.csv second.csv combined.csv

if [ "$#" -ne 3 ]; then
  echo "Usage: $0 first.csv second.csv combined.csv"
  exit 1
fi

file1="$1"
file2="$2"
outfile="$3"

# Detect header
header=$(head -n 1 "$file1")

# Find the last ID in file1 (assume first column)
last_id=$(tail -n +2 "$file1" | awk -F, 'NF > 0 {id=$1} END{print id+0}')

# If file1 only has a header, set last_id = 0
if [ -z "$last_id" ]; then
  last_id=0
fi

# Write header to output
echo "$header" > "$outfile"

# Append rows from first file (skip header)
tail -n +2 "$file1" >> "$outfile"

# Append rows from second file (skip header), shifting IDs
tail -n +2 "$file2" | awk -F, -v OFS=',' -v offset="$last_id" '{
  $1 = $1 + offset
  print
}' >> "$outfile"

echo "Combined CSV written to $outfile"
