#!/usr/bin/env python3
"""
One-time interactive setup: authenticates with Garmin Connect (handles 2FA)
and saves OAuth tokens to ~/.garth/ for future non-interactive use.
"""
import os
from garminconnect import Garmin

EMAIL = os.environ.get("GARMIN_EMAIL", "jonas.nordstrom@gmail.com")
PASSWORD = os.environ.get("GARMIN_PASSWORD", "")
TOKEN_DIR = os.path.expanduser("~/.garth")

print(f"Logging in as {EMAIL}...")
print("If 2FA is enabled, you'll be prompted for the code sent to your email/authenticator.\n")

client = Garmin(email=EMAIL, password=PASSWORD, prompt_mfa=lambda: input("Enter 2FA code: "))
client.login()
client.garth.dump(TOKEN_DIR)
print(f"\n✅ Tokens saved to {TOKEN_DIR}")
print("Future runs will authenticate silently.")
