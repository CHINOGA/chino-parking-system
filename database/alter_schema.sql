ALTER TABLE parking_entries
ADD COLUMN notification_id VARCHAR(255) NULL,
ADD COLUMN payment_status VARCHAR(50) DEFAULT 'PENDING';
