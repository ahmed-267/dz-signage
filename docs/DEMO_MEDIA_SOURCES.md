# Demo & marketing media sources

Curated **real photographs** used by:

1. Public landing page (`public/images/marketing/`)
2. Local demo Media library (`DemoWorkspaceSeeder` → `owner@dz.local` / North & Bean Café)

Files are downloaded once, optimised (~1600px wide, JPEG quality ~82), and **committed** under `resources/demo/photos/`. Runtime UI **never hotlinks** Unsplash/Pexels.

Licence: [Unsplash License](https://unsplash.com/license) — free to use commercially; photographer attribution appreciated but not required by the licence.

Sync landing copies: `php artisan marketing:generate-assets --force`

## Assets

| Local filename           | Display name (demo seed)  | Subject             | Source (download origin)                                     |
| ------------------------ | ------------------------- | ------------------- | ------------------------------------------------------------ |
| `cafe-iced-coffee.jpg`   | Iced Latte.jpg            | Coffee / iced drink | https://images.unsplash.com/photo-1461023058943-07fcbe16d735 |
| `espresso-machine.jpg`   | Latte Art Cups.jpg        | Coffee / latte art  | https://images.unsplash.com/photo-1495474472287-4d71bcdd2085 |
| `cafe-interior.jpg`      | Café Interior.jpg         | Café interior       | https://images.unsplash.com/photo-1554118811-1e0d58224f24    |
| `pastry-display.jpg`     | Pastry Display.jpg        | Bakery / food       | https://images.unsplash.com/photo-1509440159596-0249088772ff |
| `restaurant-special.jpg` | Plated Lunch Special.jpg  | Restaurant food     | https://images.unsplash.com/photo-1504674900247-0877df9cc836 |
| `retail-sale.jpg`        | Retail Fashion Floor.jpg  | Retail / fashion    | https://images.unsplash.com/photo-1441986300917-64674bd600d8 |
| `fashion-rack.jpg`       | Fashion Clothing Rack.jpg | Fashion             | https://images.unsplash.com/photo-1558769132-cb1aea458c5e    |
| `masjid-prayer.jpg`      | Masjid Interior.jpg       | Masjid              | https://images.unsplash.com/photo-1564769625905-50e93615e769 |
| `corporate-welcome.jpg`  | Corporate Office.jpg      | Corporate office    | https://images.unsplash.com/photo-1497366216548-37526070297c |
| `office-meeting.jpg`     | Office Meeting Space.jpg  | Corporate           | https://images.unsplash.com/photo-1497366754035-f200968a6e72 |
| `hotel-lobby.jpg`        | Hotel Lobby.jpg           | Hotel               | https://images.unsplash.com/photo-1566073771259-6a8506099945 |
| `gym-classes.jpg`        | Gym Floor.jpg             | Gym                 | https://images.unsplash.com/photo-1534438327276-14e5300c3a48 |
| `education-campus.jpg`   | Education Campus.jpg      | Education           | https://images.unsplash.com/photo-1509062522246-3755977927d7 |
| `healthcare-clinic.jpg`  | Healthcare Clinic.jpg     | Healthcare          | https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d |
| `property-home.jpg`      | Property Exterior.jpg     | Property / house    | https://images.unsplash.com/photo-1564013799919-ab600027ffc6 |
| `events-stage.jpg`       | Conference Stage.jpg      | Events              | https://images.unsplash.com/photo-1540575467063-178a50c2df87 |
| `landscape-hills.jpg`    | Mountain Landscape.jpg    | Landscape           | https://images.unsplash.com/photo-1506905925346-21bda4d32df4 |
| `cat-pet.jpg`            | Cat Portrait.jpg          | Pet (optional)      | https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba |

Catalogue code: `App\Support\Demo\DemoPhotoCatalog`.

## E2E isolation

- Playwright fixtures use the `E2E ` name prefix.
- `E2eArtifactCleaner` (run from `DemoWorkspaceSeeder` / `LocalDevSeeder`) strips those rows and their storage files.
- Demo seed does **not** create Untitled / blank Screen Designs; it also deletes demo-workspace junk matching safe criteria (`Untitled%`, `Blank%`, empty schema) so re-seeds stay clean.
- Demo TV presence (heartbeats / `last_seen_at`) is seeded **only** for the local-dev demo workspace — never as production fake presence logic.

## Logos / PDFs

Brand-mark logos remain small GD-generated PNGs (`DemoImageFactory::logo`) — they are marks, not photographic substitutes. Sample PDFs use `DemoPdfFactory`.
