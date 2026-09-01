# Corner Shop

A small, deliberately plain ecommerce site: browse a catalog, add things to a
cart, check out, get an order confirmation. Flask + SQLite, server-rendered
templates, no build step and no JavaScript framework.

It exists as a realistic-but-small codebase to test tooling against.

## Running it

```bash
python3 -m venv .venv && source .venv/bin/activate
pip install -r requirements-dev.txt

flask --app shop:create_app init-db   # creates shop.sqlite and loads the demo catalog
python run.py                         # http://127.0.0.1:5000
```

In production, point a WSGI server at `wsgi:app` and set `SECRET_KEY`:

```bash
gunicorn wsgi:app
```

## Tests

```bash
pytest
```

The test suite covers the catalog, cart arithmetic, and the checkout flow. Each
test gets a fresh seeded SQLite file, so they can run in any order.

## Layout

```
run.py               dev entrypoint
wsgi.py              production entrypoint
shop/
  __init__.py        app factory
  config.py          env-driven settings (tax rate, shipping, secret key)
  db.py              sqlite3 connection handling + the init-db CLI command
  schema.sql         tables
  seed.sql           ten demo products in three categories
  catalog.py         product read queries
  cart.py            session-backed cart
  pricing.py         money formatting, tax, shipping, totals
  orders.py          order placement and stock decrement
  views/
    catalog.py       /  and  /products/<slug>
    cart.py          /cart/*
    checkout.py      /checkout  and  /orders/<number>
    api.py           read-only JSON under /api
  templates/         Jinja2
  static/            one stylesheet, one small progressive-enhancement script
tests/
```

## Layout and styling

`templates/base.html` is the only layout, and every page extends it, so the
shell is defined once:

```
skip link  →  header.site-header (sticky: brand · form.search · nav.site-nav)
           →  main.site-main > .wrap (flash messages, then {% block content %})
           →  footer.site-footer (.footer-note · nav.footer-nav)
```

`.wrap` is the shared centred column (`--max-width`, `margin-inline: auto`).
The catalog page stacks a `.hero` panel, a `.toolbar` holding the three filter
controls (categories, origins, sort), and a `ul.grid` of `li.card` products;
the grid uses `repeat(auto-fit, minmax(min(100%, 18rem), 1fr))`, so it reflows
from one to four columns with no breakpoints of its own.

All colours, spacing, radii, shadows, fonts and the page measure live as CSS
custom properties in the `:root` block at the top of `static/css/style.css`
(`--bg`, `--surface`, `--text`, `--text-muted`, `--accent`, `--border`,
`--radius`, `--shadow`, `--space-1`…`--space-6`, `--font-sans`,
`--font-display`, `--max-width`). Rules read those tokens rather than literal
values, so retuning the look means editing that one block. A
`@media (prefers-color-scheme: dark)` block at the bottom of the file
re-declares only the colour tokens, which is the whole of dark mode. Older
token names (`--ink`, `--muted`, `--line`, `--paper`, `--card`) are kept as
aliases of the new ones.

## Routes

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/` | Catalog, with `?category=`, `?q=`, `?origin=`, and `?sort=` controls |
| GET | `/products/<slug>` | Product detail |
| GET | `/cart/` | Cart contents |
| POST | `/cart/add` | Add a product by slug |
| POST | `/cart/update` | Set an absolute quantity (0 removes) |
| POST | `/cart/remove` | Remove a line |
| GET, POST | `/checkout` | Address form, then place the order |
| GET | `/orders/<number>` | Order confirmation |
| GET | `/api/health` | `{"status": "ok"}` |
| GET | `/api/products` | Catalog as JSON, same filters as `/` |
| GET | `/api/products/<slug>` | One product as JSON |
| GET | `/api/cart` | Current session cart and totals as JSON |

## Notes on how it works

- **Money is integer cents** everywhere, formatted only at render time by
  `pricing.money`. Tax is 8.5%; shipping is a flat $4.95 and free over $50.
  All three are configurable in `shop/config.py`.
- **The cart lives in the signed session cookie** as `{product_id: quantity}`.
  Nothing about a cart is persisted server-side until an order is placed.
  Quantities are clamped to 0–99, and products deleted from the catalog are
  silently dropped from the cart rather than raising.
- **Orders are transactional.** `orders.place_order` writes the order and
  decrements stock in one transaction, using
  `UPDATE ... WHERE stock >= ?` so two concurrent checkouts cannot both take
  the last unit. A shortfall raises `OutOfStock` and rolls the whole thing back.
- **No payment integration and no user accounts.** Checkout collects a shipping
  address, marks the order `paid`, and stops there.
- **Product images are two-letter placeholders** rendered as CSS tiles, so the
  repo carries no binary assets.

## Configuration

| Variable | Default | Meaning |
| --- | --- | --- |
| `SECRET_KEY` | `dev-secret-change-me` | Session cookie signing key |
| `DATABASE` | `shop.sqlite` | SQLite file path |
| `PORT` | `5000` | Dev server port |
| `DEBUG` | `1` | Dev server reloader/debugger |

End of the README