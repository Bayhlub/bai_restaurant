# ຮ້ານອາຫານ ໄບ · Bai Restaurant

A table-ordering and point-of-sale system for a single restaurant. Guests scan the
QR code on their table, order from their phone in Lao or English, the kitchen sees
the ticket appear, and the cashier settles the bill in cash and prints an 80 mm
receipt.

Everything runs on one PC on the restaurant's own network — no internet or cloud
account is needed during service.

---

## How a meal flows through the system

```
Guest (Table 1)          Kitchen                    Cashier
──────────────────       ────────────────────       ──────────────────────
scan QR → menu
add items → send  ────▶  ticket appears + beep
                         per item: ✔ or ✖ sold out
                         ◀──── "Sold out" shown on the guest's phone
                         🔥 Start cooking → ✔ Ready → 🍽 Served
order more (same bill) ─▶ new ticket, same table session
tap 💵 Request bill ───────────────────────────────▶  beep + "Table 1 wants the bill"
                                                      discount, cash, change
                                                      → invoice prints, table freed
```

A **table session** opens on the first order and closes when the cashier takes
payment, so several rounds of ordering end up on one bill.

## Features

**Guest (no login, phone only)**
- Bilingual menu (ລາວ / English) with photos, categories and per-item notes
- Live order status: pending → cooking → ready, with sold-out items struck through
  and the kitchen's reason shown
- Running total, 🔔 Call staff and 💵 Request bill

**Kitchen**
- New / Cooking / Ready board, refreshed every 3 s with a sound alert on new tickets
- Reject a single line as sold out (which also hides the dish from the menu), or
  cancel the whole ticket
- Tickets turn red after 10 minutes waiting
- Availability panel to mark dishes sold out or back on

**Cashier**
- Table grid with running totals, colour-coded for food in the kitchen, food ready
  and bill requested
- Discount, cash received and change, with quick-cash buttons
- 80 mm thermal invoice, bilingual, reprintable; daily takings and recent invoices

**Admin**
- Menu: categories, dishes, prices, photos, availability
- Tables: seats, live status, QR code per table and a printable QR sheet
- Reports: revenue, invoices, average bill, top dishes and totals per cashier, by
  day / week / month or a custom range

## Built with

| | |
|---|---|
| PHP 8.3+ · Laravel 13 | application framework |
| Livewire 3 + Volt | single-file reactive components |
| Tailwind CSS 3 | styling; brand palette in `tailwind.config.js` |
| Alpine.js | small client-side interactions |
| SQLite (WAL) | database — swap for MySQL by changing `DB_*` |
| bacon/bacon-qr-code | table QR codes as inline SVG |
| PHPUnit | 115 tests |

## Getting started

Requires PHP 8.3+, Composer and Node. On Windows the project is served by
[Laravel Herd](https://herd.laravel.com).

```bash
git clone https://github.com/Bayhlub/bai_restaurant.git
cd bai_restaurant
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

Herd serves the site at `http://bai-restaurant.test`. Otherwise run
`php artisan serve`.

### Seeded staff accounts

| Role | Email | Lands on |
|---|---|---|
| Admin | `admin@bai.local` | `/admin/menu` |
| Kitchen | `kitchen@bai.local` | `/kitchen` |
| Cashier | `cashier@bai.local` | `/cashier` |

All three are seeded with the password `password`. **Change them before using the
system for real** — staff sign in at `/login`; public registration is disabled.

## Letting phones reach the app

Guests' phones must be able to open the address encoded in the QR codes, so it has
to be the PC's LAN IP, not a `.test` domain that only resolves on the PC itself.

1. Give the restaurant PC a fixed IP on the local network.
2. Set it in `.env`:

   ```bash
   CUSTOMER_URL=http://192.168.0.168:8000
   ```

3. Serve the app on that address. Either run
   `php artisan serve --host=0.0.0.0 --port=8000`, or — better under load — add an
   nginx server block listening on `0.0.0.0:8000` (Herd users can drop a `.conf`
   into `~/.config/herd/config/valet/Nginx/`). Allow the port through the firewall.
4. Print the QR sheet from **Admin → Tables → Print all QR codes** and put one card
   on each table.

Changing `CUSTOMER_URL` later means reprinting the cards.

## Configuration

Beyond the standard Laravel variables:

| Variable | Purpose |
|---|---|
| `APP_NAME` | English restaurant name, shown in the logo and on prints |
| `RESTAURANT_NAME_LO` | Lao restaurant name, shown beside it |
| `CUSTOMER_URL` | base address encoded in the table QR codes |
| `RESTAURANT_ADDRESS`, `RESTAURANT_PHONE` | printed on the receipt |
| `RESTAURANT_FOOTER_LO`, `RESTAURANT_FOOTER_EN` | thank-you lines on the receipt |

Defaults live in [`config/restaurant.php`](config/restaurant.php).

## Languages

Lao and English throughout. Guests default to Lao, staff to English, and either can
switch with the ລາວ / EN toggle, which is remembered in the session. Menu items
carry both names (`name_lo`, `name_en`); interface strings live in
[`lang/lo.json`](lang/lo.json) and `lang/lo/`.

## Development

```bash
php artisan test            # run the suite (115 tests)
vendor/bin/pint             # format PHP to the project style
npm run dev                 # rebuild assets on change
```

Notes for anyone working on the code:

- Screens poll (kitchen 3 s, cashier 5 s, guest 4 s) rather than using websockets —
  Laravel Reverb did not support Laravel 13 when this was built. `wire:poll` is easy
  to swap for broadcasting later.
- Order items **snapshot** the dish name and price when ordered, so editing the menu
  never changes an old bill.
- Colour classes returned from PHP enums (`app/Enums/Area.php`, status badges) are
  only kept by Tailwind because `./app/Enums/*.php` is in the `content` paths.
- `public/build` is not committed — run `npm run build` after pulling.

## Not in version control

`.env`, `database/database.sqlite` (sales history and password hashes) and uploaded
menu photos in `storage/app/public` are deliberately ignored. **A fresh clone has
code but no data**, so back up the database file and the photo directory separately.

## Project layout

```
app/
  Enums/            roles, order and session states, area colours
  Livewire/Actions/ PlaceOrder, CloseBill — the write paths
  Models/           Table, TableSession, Order, OrderItem, MenuItem, Invoice
resources/views/
  livewire/customer/  guest menu
  livewire/kitchen/   kitchen board
  livewire/cashier/   table grid and bill
  livewire/admin/     menu, tables, reports
  invoices/print      80 mm receipt
  admin/tables-qr     printable QR sheet
```
