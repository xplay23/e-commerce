CREATE TABLE IF NOT EXISTS auth_actions (
  id bigint UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id bigint UNSIGNED NOT NULL,
  token_hash char(64) NOT NULL UNIQUE,
  purpose enum('reset', 'verify', 'admin_login') NOT NULL,
  expires_at timestamp NOT NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT auth_actions_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  INDEX auth_actions_expiry_idx (expires_at)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS rate_limits (
  bucket char(64) PRIMARY KEY,
  attempts int UNSIGNED NOT NULL,
  reset_at timestamp NOT NULL,
  INDEX rate_limits_expiry_idx (reset_at)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS email_outbox (
  id bigint UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id bigint UNSIGNED NOT NULL,
  payload text NOT NULL,
  attempts tinyint UNSIGNED NOT NULL DEFAULT 0,
  available_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT email_outbox_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  INDEX email_outbox_available_idx (available_at)
) ENGINE = InnoDB;
