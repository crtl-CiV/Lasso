-- Round 8: printed billing statement (IIRGP form).
-- Counts how many times each statement was printed; the printed "Rev. No." is the count before that print (first print = 0).
-- Safe to run more than once. Changes no existing data.
ALTER TABLE billing_statements ADD COLUMN IF NOT EXISTS print_count INT NOT NULL DEFAULT 0;
