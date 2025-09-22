-- Migration to add path_type column to admit_card_records table
-- Date: 2025-07-29
-- Description: Adding path_type VARCHAR(55) column to store file path types

USE admin_cards;

-- Add the path_type column to admit_card_records table
ALTER TABLE admit_card_records ADD COLUMN path_type VARCHAR(55);

-- Optional: Add a comment to describe the column purpose
-- ALTER TABLE admit_card_records MODIFY COLUMN path_type VARCHAR(55) COMMENT 'Stores the type of file path (e.g., photo, document, signature)';

-- Optional: Set a default value if needed
-- UPDATE admit_card_records SET path_type = 'default' WHERE path_type IS NULL;

-- Verify the column was added successfully
SHOW COLUMNS FROM admit_card_records;
