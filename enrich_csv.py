#!/usr/bin/env python3
import csv, os, sys
from bs4 import BeautifulSoup
import re

# --- CONFIG ---
INPUT_CSV   = sys.argv[1] if len(sys.argv)>1 else "critical.csv"
OUTPUT_CSV  = sys.argv[2] if len(sys.argv)>2 else "enriched.csv"
HOST_PREFIX = "nessus-reports.okte.sk"
LOCAL_ROOT  = ".."   # relative path prefix to your /PLUGIN folder

# --- load input ---
with open(INPUT_CSV, newline='') as fin:
    reader = csv.DictReader(fin)
    rows   = list(reader)
    fieldnames = reader.fieldnames + ["CVSS v3.0 Base Score", "Servers Affected"]

# --- process each row ---
for row in rows:
    link = row.get("Link","").strip()
    if not link:
        row["CVSS v3.0 Base Score"] = ""
        row["Servers Affected"]      = ""
        continue

    # build local path + fragment
    # e.g. http://nessus-reports.okte.sk/PLUGIN/08/foo.html#id2
    if "#" in link:
        html_url, frag = link.split("#",1)
        frag = frag.strip()
    else:
        html_url, frag = link, ""
    # strip hostname, prepend LOCAL_ROOT
    path = html_url.replace(f"http://{HOST_PREFIX}", "").replace(f"https://{HOST_PREFIX}", "")
    path = os.path.join(LOCAL_ROOT, path.lstrip("/"))
    if not os.path.isfile(path):
        print(f"⚠️  Warning: file not found: {path}", file=sys.stderr)
        row["CVSS v3.0 Base Score"] = ""
        row["Servers Affected"]      = ""
        continue

    # parse HTML
    soup = BeautifulSoup(open(path, encoding="utf-8"), "lxml")

    # narrow to the single vuln section if frag present
    section = soup
    if frag:
        # find the container div for this frag
        container = soup.find(id=f"{frag}-container")
        if container:
            # we will search *inside* container only
            section = container
        else:
            print(f"⚠️  Can't find container for #{frag} in {path}", file=sys.stderr)

    # --- extract CVSS v3.0 Base Score ---
    cvss_val = ""
    # find all headers, pick the one containing our label
    for hdr in section.find_all("div", class_="details-header"):
        text = hdr.get_text(separator=" ", strip=True)
        if "CVSS v3.0 Base Score" in text:
            # next sibling div holds the score
            nxt = hdr.find_next_sibling("div")
            if nxt:
                cvss_val = nxt.get_text(strip=True)
            break
    row["CVSS v3.0 Base Score"] = cvss_val
    print(f"Row {row.get('id')} → CVSS='{cvss_val}'", file=sys.stderr)

    # --- extract Servers Affected (Plugin Output host list) ---
    servers = []
    # The "Plugin Output" block often has <h2>hostname ...</h2>
    # so we gather all <h2> under this section
    for h2 in section.find_all("h2"):
        servers.append(h2.get_text(strip=True))
    row["Servers Affected"] = ";".join(servers)

    print(f"Row {row.get('id')} → CVSS='{cvss_val}', Servers={len(servers)} hosts", file=sys.stderr)

# --- write enriched CSV ---
with open(OUTPUT_CSV, "w", newline='') as fout:
    writer = csv.DictWriter(fout, fieldnames=fieldnames)
    writer.writeheader()
    for row in rows:
        writer.writerow(row)

print(f"Done: wrote {len(rows)} rows to {OUTPUT_CSV}", file=sys.stderr)

