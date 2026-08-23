-- A small curated catalog for the storefront. Safe to re-run because slugs are unique.
INSERT INTO
  public.categories (name, slug, description)
VALUES
  ('Audio', 'audio', 'Sound, without the noise.'),
  (
    'Workspace',
    'workspace',
    'Tools for thoughtful work.'
  ),
  ('Living', 'living', 'Warmth for everyday spaces.'),
  ('Travel', 'travel', 'Made to move.'),
  (
    'Wellness',
    'wellness',
    'Small rituals, considered.'
  )
ON CONFLICT (slug) DO UPDATE
SET
  name = excluded.name,
  description = excluded.description;

INSERT INTO
  public.products (
    category_id,
    name,
    slug,
    description,
    short_description,
    price,
    old_price,
    stock,
    image_url,
    is_active,
    is_featured
  )
VALUES
  (
    (
      SELECT
        id
      FROM
        public.categories
      WHERE
        slug = 'audio'
    ),
    'Quiet Form Headphones',
    'quiet-form-headphones',
    'Over-ear headphones with balanced sound, soft memory-foam cushions and up to 40 hours of listening.',
    'Immersive sound, without visual noise.',
    349,
    399,
    18,
    'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=1200&q=85',
    TRUE,
    TRUE
  ),
  (
    (
      SELECT
        id
      FROM
        public.categories
      WHERE
        slug = 'workspace'
    ),
    'Aluminum Desk Lamp',
    'aluminum-desk-lamp',
    'A dimmable task light made from recycled aluminum with a warm, focused beam and adjustable arm.',
    'A precise light for focused work.',
    189,
    NULL,
    9,
    'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?auto=format&fit=crop&w=1200&q=85',
    TRUE,
    TRUE
  ),
  (
    (
      SELECT
        id
      FROM
        public.categories
      WHERE
        slug = 'audio'
    ),
    'Arc Bluetooth Speaker',
    'arc-bluetooth-speaker',
    'Room-filling wireless audio in a compact aluminum body with tactile controls and a twelve-hour battery.',
    'Clear sound in a compact form.',
    229,
    269,
    14,
    'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?auto=format&fit=crop&w=1200&q=85',
    TRUE,
    TRUE
  ),
  (
    (
      SELECT
        id
      FROM
        public.categories
      WHERE
        slug = 'wellness'
    ),
    'Hinoki Essential Set',
    'hinoki-essential-set',
    'A calming trio of hinoki bath oil, incense and hand balm made for slow evening rituals.',
    'A quiet ritual for the end of the day.',
    78,
    NULL,
    25,
    'https://images.unsplash.com/photo-1603006905003-be475563bc59?auto=format&fit=crop&w=1200&q=85',
    TRUE,
    TRUE
  ),
  (
    (
      SELECT
        id
      FROM
        public.categories
      WHERE
        slug = 'travel'
    ),
    'Everyday Carry Pack',
    'everyday-carry-pack',
    'A weather-resistant 22-liter backpack with a padded laptop sleeve and considered internal organization.',
    'Built for daily movement.',
    245,
    NULL,
    7,
    'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=1200&q=85',
    TRUE,
    FALSE
  ),
  (
    (
      SELECT
        id
      FROM
        public.categories
      WHERE
        slug = 'living'
    ),
    'Ripple Glass Carafe',
    'ripple-glass-carafe',
    'Hand-blown recycled glass with a subtle ripple texture. Each carafe is finished individually.',
    'Useful glassware with a human touch.',
    96,
    120,
    20,
    'https://images.unsplash.com/photo-1603899122634-f086ca5f5ddd?auto=format&fit=crop&w=1200&q=85',
    TRUE,
    FALSE
  ),
  (
    (
      SELECT
        id
      FROM
        public.categories
      WHERE
        slug = 'workspace'
    ),
    'Contour Keyboard',
    'contour-keyboard',
    'A low-profile wireless keyboard with quiet switches, an aluminum frame and multi-device pairing.',
    'Quiet keys, considered proportions.',
    159,
    NULL,
    12,
    'https://images.unsplash.com/photo-1587829741301-dc798b83add3?auto=format&fit=crop&w=1200&q=85',
    TRUE,
    FALSE
  ),
  (
    (
      SELECT
        id
      FROM
        public.categories
      WHERE
        slug = 'living'
    ),
    'Linen Throw',
    'linen-throw',
    'A soft, breathable throw woven from European linen and finished with a gently washed texture.',
    'Natural warmth for slower spaces.',
    135,
    160,
    6,
    'https://images.unsplash.com/photo-1583845112203-454c2254edc7?auto=format&fit=crop&w=1200&q=85',
    TRUE,
    FALSE
  )
ON CONFLICT (slug) DO UPDATE
SET
  category_id = excluded.category_id,
  name = excluded.name,
  description = excluded.description,
  short_description = excluded.short_description,
  price = excluded.price,
  old_price = excluded.old_price,
  stock = excluded.stock,
  image_url = excluded.image_url,
  is_active = excluded.is_active,
  is_featured = excluded.is_featured,
  updated_at = now();
