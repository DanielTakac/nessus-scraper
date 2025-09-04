#!/bin/bash
#
# removes unnecessary protocol and port info from csv files
#

if [ $# -lt 1 ]; then
    echo "Usage: $0 input.csv"
    exit 1
fi

infile="$1"

# This sed command removes "(tcp/...)" and "(udp/...)" blocks
# \((tcp|udp)/[^)]*\) means:
#   \(         literal open paren
#   (tcp|udp)  tcp or udp
#   /          a slash
#   [^)]*      any chars up until a closing paren
#   \)         literal close paren
# Those matches are replaced with nothing.
sed -Ei 's/\((tcp|udp)\/[^)]*\)//g' "$infile"

echo "Done: Cleaned $infile"
