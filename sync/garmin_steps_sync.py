#!/usr/bin/env python3
"""
Fetches daily step counts for the last 365 days from Garmin Connect.
Reads config from .env file (in parent directory) or environment variables.

.env keys:
  GARMIN_TOKEN_DIR  - path to garth OAuth token directory
  CACHE_FILE        - path to JSON cache file (must be writable)
"""
import json, os, sys, time
from datetime import date, timedelta
from pathlib import Path

# ── Load .env from parent directory ──────────────────────────────────
env_path = Path(__file__).resolve().parent.parent / '.env'
if env_path.exists():
    for line in env_path.read_text().splitlines():
        line = line.strip()
        if not line or line.startswith('#') or '=' not in line:
            continue
        k, _, v = line.partition('=')
        os.environ.setdefault(k.strip(), v.strip())

TOKEN_DIR  = os.environ.get('GARMIN_TOKEN_DIR', str(Path.home() / '.garth'))
CACHE_FILE = os.environ.get('CACHE_FILE', '/var/lib/garmin-cache/steps.json')

try:
    from garminconnect import Garmin
except ImportError:
    print("ERROR: garminconnect not installed. Run: pip install garminconnect", file=sys.stderr)
    sys.exit(1)

# ── Load existing cache ───────────────────────────────────────────────
try:
    with open(CACHE_FILE) as f:
        cache = json.load(f)
except Exception:
    cache = {}

# ── Connect ───────────────────────────────────────────────────────────
try:
    client = Garmin()
    client.login(TOKEN_DIR)
except Exception as e:
    print(f"Login failed: {e}", file=sys.stderr)
    sys.exit(1)

# ── Fetch last 365 days ───────────────────────────────────────────────
end           = date.today()
start         = end - timedelta(days=364)
today_key     = end.strftime("%Y-%m-%d")
yesterday_key = (end - timedelta(days=1)).strftime("%Y-%m-%d")
cur           = start
fetched       = 0

while cur <= end:
    key = cur.strftime("%Y-%m-%d")
    if key not in cache or key in (today_key, yesterday_key):
        try:
            stats = client.get_stats(key)
            fetched_steps = stats.get("totalSteps") or 0
            prev_steps = int(cache.get(key, 0) or 0)
            # Guard against temporary Garmin lag/regressions for today/yesterday.
            # Keep the highest observed value for the date.
            cache[key] = max(prev_steps, fetched_steps)
            fetched += 1
            if fetched % 10 == 0:
                print(f"  fetched {fetched} days...", file=sys.stderr)
                with open(CACHE_FILE, "w") as f:
                    json.dump(cache, f)
            time.sleep(0.3)
        except Exception as e:
            print(f"  {key}: {e}", file=sys.stderr)
            cache[key] = 0
    cur += timedelta(days=1)

# ── Prune old entries & save ──────────────────────────────────────────
cutoff = (end - timedelta(days=364)).strftime("%Y-%m-%d")
cache  = {k: v for k, v in cache.items() if k >= cutoff}

with open(CACHE_FILE, "w") as f:
    json.dump(cache, f)

print(f"Done. {fetched} new dates fetched, {len(cache)} total in cache.")
