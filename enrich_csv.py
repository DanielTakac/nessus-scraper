#!/usr/bin/env python3
import csv, os, sys, time
from lxml import html

INPUT_CSV   = sys.argv[1] if len(sys.argv)>1 else "critical.csv"
OUTPUT_CSV  = sys.argv[2] if len(sys.argv)>2 else "enriched.csv"
HOST_PREFIX = "nessus-reports.okte.sk"
LOCAL_ROOT  = ".."

start = time.time()

# 1) Load CSV rows
with open(INPUT_CSV, newline='') as fin:
    reader     = csv.DictReader(fin)
    rows       = list(reader)

    # start with whatever columns are in the file
    orig = reader.fieldnames  

    # only append these once
    extras = []
    for col in ["CVSS v3.0","Servers Affected"]:
        if col not in orig:
            extras.append(col)

    fieldnames = orig + extras

# 2) Group rows by HTML file
file_map = {}
for row in rows:
    link = row.get("Link","").strip()
    if not link: continue
    html_url, _, frag = link.partition('#')
    path = html_url.replace(f"http://{HOST_PREFIX}", "") \
                   .replace(f"https://{HOST_PREFIX}", "")
    path = os.path.join(LOCAL_ROOT, path.lstrip("/"))
    file_map.setdefault(path, []).append(frag or "")

# 3) Parse each file once, extract all sections
cache = {}  # cache[path] = { fragID: (cvss, servers) }
for path, frags in file_map.items():
    fragset = set(frags)
    cache[path] = {}
    if not os.path.isfile(path):
        print(f"⚠️ missing: {path}", file=sys.stderr)
        continue

    # parse HTML with lxml
    tree = html.parse(path)
    root = tree.getroot()

    # find all <div id="idN-container">
    for container in root.xpath('//div[contains(@id,"-container")]'):
        cid = container.get("id","")
        if not cid.endswith("-container"): 
            continue
        frag = cid.rsplit("-",1)[0]  # "id2"
        if frag not in fragset:
            continue

        # extract CVSS
        cvss = ""
        # find header, then next div sibling
        hdrs = container.xpath('.//div[@class="details-header"]')
        for h in hdrs:
            txt = "".join(h.itertext()).strip()
            if "CVSS v3.0 Base Score" in txt:
                nxt = h.getnext()
                if nxt is not None:
                    cvss = "".join(nxt.itertext()).strip()
                break

        # extract servers (all <h2> text in this container)
        servers = [s.strip() for s in container.xpath('.//h2/text()') if s.strip()]

        cache[path][frag] = (cvss, ";".join(servers))

# 4) Fill rows from cache
for idx, row in enumerate(rows,1):
    link = row.get("Link","").strip()
    if not link:
        row["CVSS v3.0"] = ""
        row["Servers Affected"] = ""
        continue
    html_url, _, frag = link.partition('#')
    path = html_url.replace(f"http://{HOST_PREFIX}", "") \
                   .replace(f"https://{HOST_PREFIX}", "")
    path = os.path.join(LOCAL_ROOT, path.lstrip("/"))
    val = cache.get(path,{}).get(frag,("", ""))
    row["CVSS v3.0"], row["Servers Affected"] = val

    # progress every 100 rows
    if idx % 100 == 0 or idx == len(rows):
        print(f"Processed {idx}/{len(rows)} rows in {time.time()-start:.1f}s", 
              file=sys.stderr)

# 5) Write enriched CSV
with open(OUTPUT_CSV, "w", newline='') as fout:
    writer = csv.DictWriter(fout, fieldnames=fieldnames)
    writer.writeheader()
    writer.writerows(rows)

print(f"Done: {len(rows)} rows, total time {time.time()-start:.1f}s", 
      file=sys.stderr)

