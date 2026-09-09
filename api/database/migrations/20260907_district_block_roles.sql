ALTER TABLE user_master
  ADD COLUMN district_code VARCHAR(20) NULL AFTER u_identity,
  ADD COLUMN block_code VARCHAR(20) NULL AFTER district_code;
