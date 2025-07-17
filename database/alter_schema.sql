-- Add the order_tracking_id column to the parking_entries table
ALTER TABLE parking_entries
ADD COLUMN order_tracking_id VARCHAR(255) NULL AFTER notification_id;
