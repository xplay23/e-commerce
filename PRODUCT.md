# Product

<!-- impeccable:product-schema 1 -->

## Platform

Web. Existing Vue 3 / TypeScript frontend, PHP API and MySQL database.

## Users and purpose

Visitors browse the NORD catalog, compare products and place orders. The owner
uses a synthetic catalog of 100 lamps to test product search and consultation.
The requested assistant answers questions about the site and products using
OpenAI API and the current local database.

## Operating context

Development, database changes and verification are local. Remote deployment
requires an explicit user request. Local services run through Docker Compose.

## Capabilities and constraints

Preserve the existing storefront and checkout. Assistant tools read only public
active products and verified site information. Test products remain clearly
identified. Unknown business policies must not be invented. Online payment is
not implemented. Credentials stay in ignored server-side environment files.

## Evidence

Product data: `backend/database/test-lamps.php`. Site behavior and copy:
`src/pages`, `src/router`, `backend/public/index.php`. Existing visual system:
`src/assets/scss/main.scss` and reusable components.
