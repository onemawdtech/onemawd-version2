-- Migration: Add receipt upload support for fund payments
-- Date: 2026-03-13

-- Add receipt_image column to store uploaded receipts
ALTER TABLE fund_payments ADD COLUMN receipt_image LONGBLOB DEFAULT NULL AFTER notes;
ALTER TABLE fund_payments ADD COLUMN receipt_mime VARCHAR(50) DEFAULT NULL AFTER receipt_image;

-- Add acknowledgment form support for billing periods
ALTER TABLE fund_billing_periods ADD COLUMN acknowledgment_form LONGBLOB DEFAULT NULL AFTER status;
ALTER TABLE fund_billing_periods ADD COLUMN form_mime VARCHAR(50) DEFAULT NULL AFTER acknowledgment_form;
ALTER TABLE fund_billing_periods ADD COLUMN form_uploaded_at DATETIME DEFAULT NULL AFTER form_mime;

-- Add document tracking for payment ledger forms
CREATE TABLE IF NOT EXISTS fund_ledger_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fund_id INT NOT NULL,
    billing_period_id INT DEFAULT NULL,
    document_number VARCHAR(50) NOT NULL UNIQUE,
    qr_code_data VARCHAR(255) NOT NULL,
    generated_by INT DEFAULT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    FOREIGN KEY (fund_id) REFERENCES funds(id) ON DELETE CASCADE,
    FOREIGN KEY (billing_period_id) REFERENCES fund_billing_periods(id) ON DELETE SET NULL,
    FOREIGN KEY (generated_by) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
