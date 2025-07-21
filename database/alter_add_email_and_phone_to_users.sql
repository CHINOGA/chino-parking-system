ALTER TABLE chinotra_chino_parking.users
ADD COLUMN email VARCHAR(255) NULL AFTER username,
ADD COLUMN phone_number VARCHAR(15) NULL AFTER email;
