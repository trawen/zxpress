-- Speeds up ZXNet topic views: SELECT * FROM echos_zxnet WHERE echo_id=? AND subj_id=? ORDER BY date
CREATE INDEX idx_echo_subj_date ON echos_zxnet (echo_id, subj_id, date);
