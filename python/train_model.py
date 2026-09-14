import pandas as pd
from pathlib import Path
import matplotlib.pyplot as plt
from statsmodels.tsa.statespace.sarimax import SARIMAX
import joblib

from utils.database_loader import load_sales_data

print("=" * 50)
print("Nica Xandra Pharmacy POS")
print("SARIMA AI Training Module")
print("=" * 50)

print("=" * 50)
print("Nica Xandra Pharmacy POS")
print("SARIMA AI Training Module")
print("=" * 50)


print("\nLoading sales data from MySQL...\n")

BASE_DIR = Path(__file__).resolve().parent

df = load_sales_data()


print("\nDataset Loaded Successfully!\n")

print("\nDataset Loaded Successfully!\n")

# ---------------------------------------------------
# DATA VALIDATION
# ---------------------------------------------------

if df.empty:
    print("No historical sales found.")
    print("Please import historical receipts or record POS transactions first.")
    exit()

print("Dataset contains", len(df), "records.")

print("\nChecking for missing values...")

print(df.isnull().sum())

print("\nChecking duplicate rows...")

duplicates = df.duplicated().sum()

print("Duplicate Rows:", duplicates)

print("\nConverting date column...")

df["date"] = pd.to_datetime(df["date"])

print("Date conversion successful.")

print("\nSorting records...")

df = df.sort_values("date")

print("Sorting complete.")

print("\nAggregating Daily Sales...")

daily_sales = (
    df.groupby("date")["quantity_sold"]
      .sum()
      .reset_index()
)

print("Aggregation Complete!")

print("\nDaily Sales Preview\n")

print(daily_sales.head())

print("\nPreparing Time Series...")

daily_sales["date"] = pd.to_datetime(
    daily_sales["date"]
)

daily_sales = daily_sales.set_index("date")

daily_sales = daily_sales.asfreq("D", fill_value=0)

print("\nTime Series Ready!\n")
print(daily_sales)

print("Time Series Ready!\n")

print(daily_sales.head())

print("\nData Validation Completed Successfully!")

plt.figure(figsize=(12,5))

plt.figure(figsize=(12,5))

plt.plot(
    daily_sales.index,
    daily_sales["quantity_sold"],
    linewidth=2
)

plt.title("Daily Medicine Demand")

plt.xlabel("Date")

plt.ylabel("Quantity Sold")

plt.grid(True)

plt.tight_layout()

plt.show()

plt.title("Daily Medicine Demand")

plt.xlabel("Date")

plt.ylabel("Quantity Sold")

plt.grid(True)

plt.show()

print("\nTraining SARIMA Model...")

model = SARIMAX(
    daily_sales["quantity_sold"],
    order=(1,1,1),
    seasonal_order=(1,1,1,7)
)

results = model.fit()

print("Training Complete!")

model_path = BASE_DIR / "models" / "sarima_model.pkl"

joblib.dump(results, model_path)

print("\nModel Saved Successfully!")

print(model_path)