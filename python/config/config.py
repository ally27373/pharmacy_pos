"""
Configuration File
SARIMA Forecast Module
VPS defaults (Hostinger KVM 2 - Ubuntu 26.04)
Override via environment variables.
"""
import os

DB_HOST = os.getenv("DB_HOST", "localhost")

DB_PORT = int(os.getenv("DB_PORT", "3306"))

DB_NAME = os.getenv("DB_DATABASE", "pharmacy_pos")

DB_USER = os.getenv("DB_USERNAME", "pharma")

DB_PASSWORD = os.getenv("DB_PASSWORD", "ALLYSA")

FORECAST_MONTHS = 3

MODEL_NAME = "SARIMA"