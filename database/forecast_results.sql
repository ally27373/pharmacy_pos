/*==========================================================
  FORECAST RESULTS TABLE
==========================================================*/

CREATE TABLE IF NOT EXISTS forecast_results (

    forecast_id INT AUTO_INCREMENT PRIMARY KEY,

    product_id INT NOT NULL,

    forecast_month DATE NOT NULL,

    predicted_quantity DECIMAL(10,2) NOT NULL,

    confidence_score DECIMAL(5,2) DEFAULT NULL,

    model_name VARCHAR(50) NOT NULL DEFAULT 'SARIMA',

    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_forecast_product
        FOREIGN KEY(product_id)
        REFERENCES products(product_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE

);