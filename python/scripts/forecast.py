from pathlib import Path
import joblib

BASE_DIR = Path(__file__).resolve().parent.parent

model_path = BASE_DIR / "models" / "sarima_model.pkl"

if not model_path.exists():
    print("No trained model found.")
    exit()

print("Loading trained model...")

try:
    model = joblib.load(model_path)

except Exception:

    print("\nNo trained AI model found.")

    print("Please train the model first.")

    exit()

print("Generating Forecast...")

forecast = model.get_forecast(steps=30)

prediction = forecast.predicted_mean


forecast_df = prediction.reset_index()

forecast_df.columns = [
    "date",
    "forecast_quantity"
]

print("\nForecast Result\n")

print("\nForecast Preview\n")

print(forecast_df.head())

output = BASE_DIR / "output" / "forecast_results.csv"

output.parent.mkdir(exist_ok=True)

forecast_df.to_csv(
    output,
    index=False
)

print("\nForecast saved successfully!")

print(output)