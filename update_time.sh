#!/bin/bash
#
# Extracts the time parsed from summary.html so the php script can display it on the website
#

SOURCE="../R/summary.html"
DEST="time.html"

# Extract the line and overwrite the destination file
grep '^<b><pre>Report was parsed at' "$SOURCE" > "$DEST"

echo "Wrote report update time to time.html"

