-- Migration 004: Add object_type column to share_logs table
-- This adds better categorization of object types in share logs

-- Add object_type column
ALTER TABLE share_logs 
ADD COLUMN object_type VARCHAR(50) DEFAULT 'unknown' AFTER object_name;

-- Add index for better performance on object_type queries
CREATE INDEX idx_share_logs_object_type ON share_logs(object_type);

-- Update existing records to have a default object_type
UPDATE share_logs 
SET object_type = CASE 
    WHEN object_name IS NOT NULL AND object_name != 'N/A' AND object_name != '' THEN 'file_or_folder'
    WHEN share_name IS NOT NULL THEN 'share_root'
    ELSE 'unknown'
END 
WHERE object_type = 'unknown' OR object_type IS NULL;