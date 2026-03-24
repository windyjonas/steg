# Steg — Garmin Steps Dashboard

A web app that displays your daily Garmin step data with charts and goal tracking.

## Features

- Daily, weekly, and monthly step charts
- 365-day rolling average
- Configurable daily step goal
- Green/purple bars based on goal completion
- ISO week support (week 1 includes Dec 29–31)

## Setup

### 1. Configure

Copy `.env.example` to `.env` and fill in your values:

```bash
cp .env.example .env
```

```env
CACHE_FILE=/var/lib/garmin-cache/steps.json
GARMIN_TOKEN_DIR=/home/youruser/.garth
GOAL_PER_DAY=10000
```

### 2. Authenticate with Garmin

Run the one-time setup to log in and save OAuth tokens:

```bash
pip install garminconnect garth
python3 sync/garmin_setup.py
```

### 3. Initial sync

Fetch the last 365 days of step data:

```bash
python3 sync/garmin_steps_sync.py
```

### 4. Deploy

Serve the app directory with PHP-FPM + Nginx (or Apache with PHP). The web server user needs read access to `CACHE_FILE`.

### 5. Schedule daily sync

Add to crontab (runs at 08:30 Stockholm time):

```
30 8 * * * cd /path/to/steg && python3 sync/garmin_steps_sync.py
```

## Multiple users

Deploy separate instances in different directories, each with their own `.env` pointing to different `CACHE_FILE` and `GARMIN_TOKEN_DIR` paths.

## File structure

```
steg/
├── .env              # Your config (not in git)
├── .env.example      # Config template
├── index.html        # Frontend (Chart.js)
├── data.php          # API endpoint
└── sync/
    ├── garmin_steps_sync.py   # Daily sync script
    ├── garmin_setup.py        # One-time auth setup
    └── requirements.txt
```
