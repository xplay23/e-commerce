INSERT INTO
  public.categories (id, name, slug, description)
VALUES
  (
    '00000000-0000-4000-8000-000000000001',
    'Audio',
    'audio',
    'Sound, without the noise.'
  ),
  (
    '00000000-0000-4000-8000-000000000002',
    'Workspace',
    'workspace',
    'Tools for thoughtful work.'
  ),
  (
    '00000000-0000-4000-8000-000000000003',
    'Living',
    'living',
    'Warmth for everyday spaces.'
  ),
  (
    '00000000-0000-4000-8000-000000000004',
    'Travel',
    'travel',
    'Made to move.'
  ),
  (
    '00000000-0000-4000-8000-000000000005',
    'Wellness',
    'wellness',
    'Small rituals, considered.'
  )
ON CONFLICT DO NOTHING;

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
    is_featured
  )
SELECT
  c.id,
  'Object ' || n || ' — ' || c.name,
  lower(c.slug || '-object-' || n),
  'Designed with restraint and made to last. Honest materials, thoughtful details.',
  'Quietly functional. Intentionally made.',
  (35 + n * 17)::numeric,
  CASE
    WHEN n % 4 = 0 THEN (55 + n * 19)::numeric
    ELSE NULL
  END,
  CASE
    WHEN n % 11 = 0 THEN 0
    ELSE 4 + n
  END,
  'https://images.unsplash.com/photo-1524758631624-e2822e304c36?auto=format&fit=crop&w=1000&q=80',
  n <= 6
FROM
  generate_series(1, 30) n
  JOIN public.categories c ON c.id = (
    '00000000-0000-4000-8000-' || lpad((((n -1) % 5) + 1)::text, 12, '0')
  )::uuid
ON CONFLICT (slug) DO NOTHING;
