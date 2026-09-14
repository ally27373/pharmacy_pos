from utils.database_loader import load_sales_data


df = load_sales_data()


print("\nSales Data:")
print(df)


print("\nNumber of Records:")
print(len(df))