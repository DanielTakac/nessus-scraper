#!/usr/bin/env python3
import csv, os, sys
from lxml import html

if len(sys.argv) != 2:
    print("Usage: extend_cvss.py <enriched.csv>", file=sys.stderr)
    sys.exit(1)

INPUT_CSV = sys.argv[1]
if not os.path.isfile(INPUT_CSV):
    print(f"Error: '{INPUT_CSV}' not found", file=sys.stderr)
    sys.exit(1)

# Prepare extended output path
base = os.path.basename(INPUT_CSV)
name, ext = os.path.splitext(base)
EXT_DIR = "ext"
os.makedirs(EXT_DIR, exist_ok=True)
EXT_CSV = os.path.join(EXT_DIR, f"{name}_extended{ext}")

HOST = "nessus-reports.okte.sk"
LOCAL_ROOT = ".."

# Read input CSV
with open(INPUT_CSV, newline='') as fin:
    reader     = csv.DictReader(fin)
    rows       = list(reader)
    fieldnames = reader.fieldnames

# Build a map of which fragments live in which file
file_map = {}  # path -> set(fragIDs)
for r in rows:
    link = r.get("Link","").strip()
    if not link:
        continue
    url, _, frag = link.partition("#")
    path = url.replace(f"http://{HOST}", "").replace(f"https://{HOST}", "")
    path = os.path.join(LOCAL_ROOT, path.lstrip("/"))
    file_map.setdefault(path, set()).add(frag)

# Parse each HTML once and cache (fragID -> (cvss_full, description))
cache = {}
for path, frags in file_map.items():
    cache[path] = {}
    if not os.path.isfile(path):
        print(f"⚠️  Missing file: {path}", file=sys.stderr)
        continue

    doc = html.parse(path).getroot()
    for frag in frags:
        # Locate the container for this fragment
        container = doc.find(f".//div[@id='{frag}-container']")
        if container is None:
            cache[path][frag] = ("","")
            continue

        # 1) full CVSS v3.0 Base Score (with parentheses)
        cvss_full = ""
        for hdr in container.findall(".//div[@class='details-header']"):
            txt = "".join(hdr.itertext()).strip()
            if "CVSS v3.0 Base Score" in txt:
                nxt = hdr.getnext()
                if nxt is not None:
                    cvss_full = "".join(nxt.itertext()).strip()
                break

        # 2) description (text immediately under the "Description" header)
        desc = ""
        for hdr in container.findall(".//div[@class='details-header']"):
            txt = "".join(hdr.itertext()).strip()
            if txt == "Description":
                nxt = hdr.getnext()
                if nxt is not None:
                    # collapse whitespace
                    desc = " ".join(nxt.itertext()).strip()
                break

        cache[path][frag] = (cvss_full, desc)

# Now produce the extended CSV and simultaneously trim the main CSV’s CVSS field
extended_fieldnames = ["id","cvss_base_full","description"]
with open(EXT_CSV, "w", newline='') as fout:
    ext_writer = csv.DictWriter(fout, fieldnames=extended_fieldnames)
    ext_writer.writeheader()

    # Trim CVSS in original rows in-place
    for r in rows:
        link = r.get("Link","").strip()
        cvss_full = ""
        desc       = ""
        if link:
            url, _, frag = link.partition("#")
            path = url.replace(f"http://{HOST}", "").replace(f"https://{HOST}", "")
            path = os.path.join(LOCAL_ROOT, path.lstrip("/"))
            cvss_full, desc = cache.get(path,{}).get(frag,("",""))

        # 1) write extended CSV row
        ext_writer.writerow({
            "id": r.get("id",""),
            "cvss_base_full": cvss_full,
            "description": desc
        })

        # 2) trim the original CVSS field to numeric only
        full = r.get("CVSS v3.0","").strip()
        # take up to first space or parenthesis
        short = full.split()[0] if full else ""
        r["CVSS v3.0"] = short

# Overwrite the input CSV with trimmed CVSS
with open(INPUT_CSV, "w", newline='') as fout:
    writer = csv.DictWriter(fout, fieldnames=fieldnames)
    writer.writeheader()
    writer.writerows(rows)

print(f"Trimmed CVSS field in  '{INPUT_CSV}'", file=sys.stderr)
print(f"Wrote extended data to '{EXT_CSV}'", file=sys.stderr)
