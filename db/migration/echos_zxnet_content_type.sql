-- Message body format: text/plain (escape + pre-wrap) or text/html (raw HTML).
ALTER TABLE echos_zxnet
  ADD COLUMN content_type VARCHAR(16) NOT NULL DEFAULT 'text/html'
  AFTER text_en;

-- Existing archive already stores HTML-ish bodies (q1 spans, etc.).
UPDATE echos_zxnet SET content_type = 'text/html' WHERE content_type = '' OR content_type IS NULL;
