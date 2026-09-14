/*==========================================================
  FORECAST LOGS TABLE
==========================================================*/

CREATE TABLE IF NOT EXISTS forecast_logs (

    log_id INT AUTO_INCREMENT PRIMARY KEY,

    execution_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    status ENUM(
        'SUCCESS',
        'FAILED',
        'WARNING'
    ) NOT NULL,

    execution_time DECIMAL(8,3) DEFAULT NULL,

    message TEXT

);