CREATE DATABASE IF NOT EXISTS nord_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE nord_store;

CREATE TABLE categories (
  id bigint UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name varchar(120) NOT NULL,
  slug varchar(140) NOT NULL UNIQUE,
  description text NULL,
  image_url varchar(2048) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE products (
  id bigint UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id bigint UNSIGNED NULL,
  name varchar(180) NOT NULL,
  slug varchar(200) NOT NULL UNIQUE,
  description text NOT NULL,
  short_description varchar(500) NULL,
  price decimal(12, 2) NOT NULL,
  old_price decimal(12, 2) NULL,
  stock int UNSIGNED NOT NULL DEFAULT 0,
  image_url varchar(2048) NULL,
  is_active boolean NOT NULL DEFAULT TRUE,
  is_featured boolean NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT products_category_fk FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
  CONSTRAINT products_price_check CHECK (price >= 0),
  CONSTRAINT products_old_price_check CHECK (
    old_price IS NULL
    OR old_price >= 0
  ),
  INDEX products_catalog_idx (is_active, created_at),
  INDEX products_category_idx (category_id)
) ENGINE = InnoDB;

CREATE TABLE product_images (
  id bigint UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id bigint UNSIGNED NOT NULL,
  url varchar(2048) NOT NULL,
  sort_order int NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT product_images_product_fk FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE users (
  id bigint UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name varchar(120) NOT NULL,
  email varchar(190) NOT NULL UNIQUE,
  phone varchar(50) NULL,
  password_hash varchar(255) NOT NULL,
  role enum('customer', 'admin') NOT NULL DEFAULT 'customer',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE auth_tokens (
  id bigint UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id bigint UNSIGNED NOT NULL,
  token_hash char(64) NOT NULL UNIQUE,
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT auth_tokens_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  INDEX auth_tokens_expiry_idx (expires_at)
) ENGINE = InnoDB;

CREATE TABLE favorites (
  id bigint UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id bigint UNSIGNED NOT NULL,
  product_id bigint UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY favorites_unique (user_id, product_id),
  CONSTRAINT favorites_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT favorites_product_fk FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE orders (
  id bigint UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id bigint UNSIGNED NULL,
  customer_name varchar(120) NOT NULL,
  customer_email varchar(190) NOT NULL,
  customer_phone varchar(50) NOT NULL,
  address varchar(500) NOT NULL,
  city varchar(120) NOT NULL,
  comment text NULL,
  status enum(
    'pending',
    'processing',
    'shipped',
    'completed',
    'cancelled'
  ) NOT NULL DEFAULT 'pending',
  subtotal decimal(12, 2) NOT NULL,
  total decimal(12, 2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT orders_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  INDEX orders_user_idx (user_id)
) ENGINE = InnoDB;

CREATE TABLE order_items (
  id bigint UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id bigint UNSIGNED NOT NULL,
  product_id bigint UNSIGNED NULL,
  product_name varchar(180) NOT NULL,
  price decimal(12, 2) NOT NULL,
  quantity int UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT order_items_order_fk FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT order_items_product_fk FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL
) ENGINE = InnoDB;
