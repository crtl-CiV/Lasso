-- =========================================================
-- LASSO — Receipt (OR) flow migration
-- Students no longer enter a validation code. After paying the school
-- cashier they submit the official receipt (OR) in the system, and an admin
-- validates it and grants access. Run ONCE in Supabase's SQL Editor.
-- The old validation_codes table is kept for history but is no longer used.
-- =========================================================
-- (safe even if confirmed_by was not added earlier)
ALTER TABLE billing_statements ADD COLUMN IF NOT EXISTS confirmed_by INT REFERENCES administrators(id);
ALTER TABLE billing_statements ADD COLUMN IF NOT EXISTS confirmed_at TIMESTAMPTZ;

ALTER TABLE billing_statements ADD COLUMN receipt_number VARCHAR(60);
ALTER TABLE billing_statements ADD COLUMN receipt_photo VARCHAR(255);        -- private bucket path
ALTER TABLE billing_statements ADD COLUMN receipt_status VARCHAR(20) NOT NULL DEFAULT 'none'
  CHECK (receipt_status IN ('none','submitted','rejected','approved'));
ALTER TABLE billing_statements ADD COLUMN receipt_note TEXT;                 -- rejection reason
ALTER TABLE billing_statements ADD COLUMN receipt_submitted_at TIMESTAMPTZ;
ALTER TABLE billing_statements ADD CONSTRAINT uq_receipt_number UNIQUE (receipt_number);

-- Billings the admin had confirmed under the old flow but whose code the
-- student never redeemed (no access was granted): put them back to pending
-- so they go through the new receipt step.
UPDATE billing_statements b
SET status = 'pending', confirmed_by = NULL, confirmed_at = NULL
FROM validation_codes v
WHERE v.billing_id = b.id AND v.status = 'ready' AND b.status = 'paid';

-- Everything still marked paid already has its subscriptions activated.
UPDATE billing_statements SET receipt_status = 'approved' WHERE status = 'paid';
