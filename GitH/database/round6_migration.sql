-- =========================================================
-- LASSO — Round 6 migration
--   * email_verifications: 6-digit codes that prove a student owns
--     their school email before they can register (hash only)
--   * Non-body pages now also hide pages from the 5-page preview,
--     so old "excluded from preview" values move into non_body_pages
--   * The OR number is now typed by the admin when approving, so it
--     is cleared on receipts that are still waiting for review
-- Run ONCE in Supabase's SQL Editor (new query tab), whole file at once.
-- Requires round5_migration.sql.
-- =========================================================
CREATE TABLE email_verifications (
  id SERIAL PRIMARY KEY,
  email VARCHAR(150) NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,          -- wrong guesses; the code dies at 5
  expires_at TIMESTAMPTZ NOT NULL,          -- 10 minutes after it was sent
  verified_at TIMESTAMPTZ,
  used_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX idx_email_verifications_email ON email_verifications (email, created_at DESC);

-- Merge each material's preview-excluded pages into its non-body pages (no duplicates, sorted)
UPDATE instructional_materials m
SET non_body_pages = (
      SELECT COALESCE(jsonb_agg(p ORDER BY p), '[]'::jsonb)
      FROM (
        SELECT DISTINCT (x)::int AS p
        FROM jsonb_array_elements_text(COALESCE(m.non_body_pages, '[]'::jsonb) || COALESCE(m.preview_excluded_pages, '[]'::jsonb)) AS x
      ) s
    ),
    preview_excluded_pages = '[]'::jsonb
WHERE jsonb_typeof(m.preview_excluded_pages) = 'array' AND jsonb_array_length(m.preview_excluded_pages) > 0;

UPDATE billing_statements SET receipt_number = NULL WHERE status = 'pending';
