-- The column 'phone_number' may already exist. To avoid duplicate column errors, check before adding.

ALTER TABLE chinotra_chino_parking.users
ADD COLUMN IF NOT EXISTS phone_number VARCHAR(15) NULL AFTER password_hash;
