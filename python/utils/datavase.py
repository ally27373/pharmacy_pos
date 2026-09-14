import mysql.connector
import pandas as pd


def get_sales_history():

    connection = mysql.connector.connect(
        host="localhost",
        database="pharmacy_pos",
        user="root",
        password=""
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