#!/usr/bin/env python3
import requests
import pandas as pd
import os

# Nessus API details
# NESSUS_URL = "https://10.35.160.166:8834"
NESSUS_URL = "https://10.0.0.105:8834"
ACCESS_KEY = "b1858b6f2a8098fa217a39561fd4df7902ab7e01a7422f2039eeb6f23fb66d10"
SECRET_KEY = "1dd7e1f0b9f8c925cec9d7819c571c95b642eb9f5184eabf2f4e11d2b0c177e9"

# Target output CSV file path
OUTFILE = "./nessus.csv"

# API headers
HEADERS = {
    "X-ApiKeys": f"accessKey={ACCESS_KEY}; secretKey={SECRET_KEY}"
}

def fetch_scans():
    url = f"{NESSUS_URL}/scans"
    r = requests.get(url, headers=HEADERS, verify=False)  # set verify=True if using proper certs
    r.raise_for_status()
    return r.json()

def main():
    data = fetch_scans()

    scans = data.get("scans", [])

    # Turn it into a DataFrame
    df = pd.DataFrame(scans)

    # Save to CSV (overwrite)
    df.to_csv(OUTFILE, index=False)

if __name__ == "__main__":
    main()
