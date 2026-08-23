CREATE EXTENSION if NOT EXISTS pgcrypto;

CREATE TYPE public.user_role AS enum('customer', 'admin');

CREATE TYPE public.order_status AS enum(
  'pending',
  'processing',
  'shipped',
  'completed',
  'cancelled'
);

CREATE TABLE public.categories (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name text NOT NULL,
  slug text NOT NULL UNIQUE,
  description text,
  image_url text,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE public.products (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  category_id uuid REFERENCES public.categories (id) ON DELETE SET NULL,
  name text NOT NULL,
  slug text NOT NULL UNIQUE,
  description text NOT NULL,
  short_description text,
  price numeric(12, 2) NOT NULL CHECK (price >= 0),
  old_price numeric(12, 2) CHECK (old_price >= 0),
  stock integer NOT NULL DEFAULT 0 CHECK (stock >= 0),
  image_url text,
  is_active boolean NOT NULL DEFAULT TRUE,
  is_featured boolean NOT NULL DEFAULT FALSE,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE public.product_images (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  product_id uuid NOT NULL REFERENCES public.products (id) ON DELETE CASCADE,
  url text NOT NULL,
  sort_order integer NOT NULL DEFAULT 0,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE public.profiles (
  id uuid PRIMARY KEY REFERENCES auth.users (id) ON DELETE CASCADE,
  name text,
  phone text,
  role public.user_role NOT NULL DEFAULT 'customer',
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE public.favorites (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid NOT NULL REFERENCES auth.users (id) ON DELETE CASCADE,
  product_id uuid NOT NULL REFERENCES public.products (id) ON DELETE CASCADE,
  created_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (user_id, product_id)
);

CREATE TABLE public.orders (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id uuid REFERENCES auth.users (id) ON DELETE SET NULL,
  customer_name text NOT NULL,
  customer_email text NOT NULL,
  customer_phone text NOT NULL,
  address text NOT NULL,
  city text NOT NULL,
  comment text,
  status public.order_status NOT NULL DEFAULT 'pending',
  subtotal numeric(12, 2) NOT NULL CHECK (subtotal >= 0),
  total numeric(12, 2) NOT NULL CHECK (total >= 0),
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE public.order_items (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id uuid NOT NULL REFERENCES public.orders (id) ON DELETE CASCADE,
  product_id uuid REFERENCES public.products (id) ON DELETE SET NULL,
  product_name text NOT NULL,
  price numeric(12, 2) NOT NULL CHECK (price >= 0),
  quantity integer NOT NULL CHECK (quantity > 0),
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX products_category_idx ON public.products (category_id);

CREATE INDEX products_active_created_idx ON public.products (is_active, created_at DESC);

CREATE INDEX order_items_order_idx ON public.order_items (order_id);

CREATE INDEX orders_user_idx ON public.orders (user_id);

CREATE FUNCTION public.is_admin () returns boolean language sql stable security definer
SET
  search_path = '' AS $$select exists(select 1 from public.profiles where id=auth.uid() and role='admin')$$;

CREATE FUNCTION public.handle_new_user () returns trigger language plpgsql security definer
SET
  search_path = '' AS $$begin insert into public.profiles(id,name) values(new.id,new.raw_user_meta_data->>'name');return new;end$$;

CREATE TRIGGER on_auth_user_created
AFTER INSERT ON auth.users FOR EACH ROW
EXECUTE PROCEDURE public.handle_new_user ();

CREATE FUNCTION public.touch_updated_at () returns trigger language plpgsql AS $$begin new.updated_at=now();return new;end$$;

CREATE TRIGGER products_updated
BEFORE UPDATE ON public.products FOR EACH ROW
EXECUTE PROCEDURE public.touch_updated_at ();

CREATE TRIGGER orders_updated
BEFORE UPDATE ON public.orders FOR EACH ROW
EXECUTE PROCEDURE public.touch_updated_at ();

ALTER TABLE public.categories enable ROW level security;

ALTER TABLE public.products enable ROW level security;

ALTER TABLE public.product_images enable ROW level security;

ALTER TABLE public.profiles enable ROW level security;

ALTER TABLE public.favorites enable ROW level security;

ALTER TABLE public.orders enable ROW level security;

ALTER TABLE public.order_items enable ROW level security;

CREATE POLICY "categories public read" ON public.categories FOR
SELECT
  USING (TRUE);

CREATE POLICY "active products public read" ON public.products FOR
SELECT
  USING (
    is_active
    OR public.is_admin ()
  );

CREATE POLICY "images public read" ON public.product_images FOR
SELECT
  USING (
    EXISTS (
      SELECT
        1
      FROM
        public.products p
      WHERE
        p.id = product_id
        AND (
          p.is_active
          OR public.is_admin ()
        )
    )
  );

CREATE POLICY "profile self read" ON public.profiles FOR
SELECT
  USING (
    id = auth.uid ()
    OR public.is_admin ()
  );

CREATE POLICY "profile self update" ON public.profiles
FOR UPDATE
  USING (id = auth.uid ())
WITH
  CHECK (
    id = auth.uid ()
    AND role = (
      SELECT
        role
      FROM
        public.profiles
      WHERE
        id = auth.uid ()
    )
  );

CREATE POLICY "favorites own read" ON public.favorites FOR
SELECT
  USING (user_id = auth.uid ());

CREATE POLICY "favorites own insert" ON public.favorites FOR insert
WITH
  CHECK (user_id = auth.uid ());

CREATE POLICY "favorites own delete" ON public.favorites FOR delete USING (user_id = auth.uid ());

CREATE POLICY "orders own read" ON public.orders FOR
SELECT
  USING (
    user_id = auth.uid ()
    OR public.is_admin ()
  );

CREATE POLICY "items own read" ON public.order_items FOR
SELECT
  USING (
    EXISTS (
      SELECT
        1
      FROM
        public.orders o
      WHERE
        o.id = order_id
        AND (
          o.user_id = auth.uid ()
          OR public.is_admin ()
        )
    )
  );

CREATE POLICY "admin categories all" ON public.categories FOR ALL USING (public.is_admin ())
WITH
  CHECK (public.is_admin ());

CREATE POLICY "admin products all" ON public.products FOR ALL USING (public.is_admin ())
WITH
  CHECK (public.is_admin ());

CREATE POLICY "admin images all" ON public.product_images FOR ALL USING (public.is_admin ())
WITH
  CHECK (public.is_admin ());

CREATE POLICY "admin orders update" ON public.orders
FOR UPDATE
  USING (public.is_admin ())
WITH
  CHECK (public.is_admin ());

INSERT INTO
  storage.buckets (id, name, public)
VALUES
  ('products', 'products', TRUE)
ON CONFLICT DO NOTHING;

CREATE POLICY "product images public storage" ON storage.objects FOR
SELECT
  USING (bucket_id = 'products');

CREATE POLICY "admins manage product storage" ON storage.objects FOR ALL USING (
  bucket_id = 'products'
  AND public.is_admin ()
)
WITH
  CHECK (
    bucket_id = 'products'
    AND public.is_admin ()
  );

CREATE FUNCTION public.create_order (
  customer_name text,
  customer_email text,
  customer_phone text,
  shipping_address text,
  shipping_city text,
  order_comment text,
  cart_items jsonb
) returns uuid language plpgsql security definer
SET
  search_path = '' AS $$declare new_id uuid;calculated numeric(12,2);item jsonb;p public.products%rowtype;begin if jsonb_array_length(cart_items)=0 then raise exception 'Cart is empty';end if;calculated:=0;for item in select * from jsonb_array_elements(cart_items) loop select * into p from public.products where id=(item->>'product_id')::uuid and is_active for update;if p.id is null or p.stock<(item->>'quantity')::int then raise exception 'Product unavailable';end if;calculated:=calculated+p.price*(item->>'quantity')::int;end loop;insert into public.orders(user_id,customer_name,customer_email,customer_phone,address,city,comment,subtotal,total) values(auth.uid(),customer_name,customer_email,customer_phone,shipping_address,shipping_city,order_comment,calculated,calculated) returning id into new_id;for item in select * from jsonb_array_elements(cart_items) loop select * into p from public.products where id=(item->>'product_id')::uuid;insert into public.order_items(order_id,product_id,product_name,price,quantity) values(new_id,p.id,p.name,p.price,(item->>'quantity')::int);update public.products set stock=stock-(item->>'quantity')::int where id=p.id;end loop;return new_id;end$$;

GRANT
EXECUTE ON function public.create_order (text, text, text, text, text, text, jsonb) TO anon,
authenticated;
