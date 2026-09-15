import os
import mysql.connector
import pandas as pd


def get_sales_history():

    connection = mysql.connector.connect(
        host=os.getenv("DB_HOST", "localhost"),
        port=int(os.getenv("DB_PORT", "3306")),
        database=os.getenv("DB_DATABASE", "pharmacy_pos"),
        user=os.getenv("DB_USERNAME", "pharma"),
        password=os.getenv("DB_PASSWORD", "ALLYSA")
    )

    query = """

        SELECT

            DATE(s.created_at) AS date,

            SUM(si.quantity) AS quantity_sold

        FROM sales s

        INNER JOIN sale_items si

            ON s.sale_id = si.sale_id

        WHERE s.transaction_status = 'Completed'

        GROUP BY DATE(s.created_at)

        ORDER BY date ASC

    """

    df = pd.read_sql(query, connection)

    connection.close()

    return df