import subprocess
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent

print("=" * 50)
print("Nica Xandra Pharmacy POS")
print("AI Forecast Automation")
print("=" * 50)

print("\nTraining Model...\n")

subprocess.run([
    "python",
    str(BASE_DIR / "train_model.py")
])

print("\nGenerating Forecast...\n")

subprocess.run([
    "python",
    str(BASE_DIR / "scripts" / "forecast.py")
])

print("\nAI Pipeline Completed Successfully!")