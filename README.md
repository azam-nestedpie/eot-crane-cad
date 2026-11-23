# EOT Crane Drawing Generator

A small full-stack web app that generates a dimensioned 2D layout drawing of a
**single-girder, top-running overhead (EOT) crane** from a few inputs, and lets
you save, list, reopen, edit and delete designs.

Every number on the drawing says where it came from: **input**, **reference**
(read off the sample drawing), **derived** (exact geometry) or **estimate**
(my guess, shown in amber with `≈`).

## Run it

Requirements: PHP 8.3+, Composer 2, Node 20+ (tested with PHP 8.5, Node 24).

```bash
git clone <this repo> && cd eot-crane-cad
composer setup        # installs PHP + JS deps, creates .env and SQLite DB, migrates, seeds, builds the frontend
php artisan serve     # open http://127.0.0.1:8000
```

For development with hot reload use `composer dev` instead of `php artisan serve`.

Run the tests (unit + API feature tests, in-memory SQLite):

```bash
composer test
```

> If `npm install` fails with `EACCES` on `~/.npm`, your npm cache has
> root-owned files. Fix it with `sudo chown -R $(id -u):$(id -g) ~/.npm`, or
> run setup with a temporary cache: `npm_config_cache=/tmp/npm-cache composer setup`.

## Using it

1. The form starts with the reference crane (10 t, 25 m span, 7.9 m lift, rail at 8321 mm).
2. Move a slider or type a value. The drawing and the summary cards update live
   (the server validates and calculates each change).
3. Invalid input shows the server's message under the field. The last valid drawing stays visible but faded.
4. **Save design** (or Ctrl/⌘ + S) stores it. Saved designs are listed on the left and can be searched.
   Click one to reopen it, edit it, and **Save changes**.
5. Switch the drawing between **Paper** and **Blueprint**, zoom it, or download it as **SVG**.
   The **Dimensions** tab lists every value with its source and can be filtered by source.

## Stack and why

| Part | Choice | Why |
|---|---|---|
| Backend | Laravel 13 (PHP) | Form Requests for validation, Eloquent + migrations, JSON error handling and a test runner built in. Most of the graded work is here. |
| Frontend | Vue 3 + Vite, served by Laravel | One repo, one server, no CORS. Small reactive UI without a separate build setup. |
| Database | SQLite | No setup. Allowed by the brief. Switching to MySQL/Postgres is a `.env` change. |
| Drawing | Hand-built SVG | Vector, sharp at any zoom, selectable text, easy to download. No CAD library needed for 2 views. |

## How it works

```
Vue form ──POST /api/v1/calculate──▶ CraneInputRequest (validation)
                                        └─▶ CraneCalculator ──▶ dimensions[] + warnings[]
Vue SVG  ◀───────────── JSON (all values in mm, each with source + basis) ─┘
```

- **`app/Services/Crane/CraneCalculator.php`** is the single source of truth.
  Every rule and constant is there, with a comment on where it came from.
  The frontend only maps millimetres to pixels and does no engineering.
- **Validation happens on the server in two layers** (`app/Http/Requests/CraneInputRequest.php`):
  1. Field rules: required, type, range, capacity in 0.5 t steps, duty group enum.
  2. Physical cross-field rules, which run only if layer 1 passes. The hook cannot
     go higher than the hoist under the girder allows. The building, if given,
     must be taller than the crane plus 200 mm roof clearance.
- **Only inputs are stored.** Derived dimensions are recalculated on every read,
  so a fixed formula applies to all saved designs.
- Errors: 422 with per-field messages, 404 `{"message":"Not found."}`,
  400 for a malformed JSON body.

### API (`/api/v1`)

| Method | Path | Does |
|---|---|---|
| GET | `/input-schema` | Input limits, units and steps (the UI builds its sliders from this) |
| POST | `/calculate` | Validate + calculate, nothing saved (live preview) |
| GET | `/designs` | List saved designs (inputs only, newest edit first) |
| POST | `/designs` | Create, returns design + calculation |
| GET | `/designs/{id}` | One design + calculation |
| PUT | `/designs/{id}` | Full update, returns design + calculation |
| DELETE | `/designs/{id}` | Delete (204) |

## Inputs

| Input | Range | Unit |
|---|---|---|
| Rated capacity | 1 – 20, steps of 0.5 | t |
| Span (rail centre to rail centre) | 5 000 – 30 000 | mm |
| Rail height (floor to top of rail) | 3 500 – 22 000 | mm |
| Lifting height (floor to hook, highest) | 2 500 – 20 000 | mm |
| Building clear height (optional) | 4 000 – 30 000 | mm |
| Duty group (FEM) | A3 – A6 (display only) | – |

The full list of derived dimensions, with the source and basis of each one, is
shown in the app's table and in `CraneCalculator.php`. My reasoning is in
`DECISIONS.md`.

## Known limitations

- **All estimates are calibrated on one sample drawing** (10 t, 25 m). Far from
  that point they are extrapolations, and the app warns about it.
- **No structural engineering**: no girder strength or deflection check, no
  wheel loads, no crane weight. Girder depth is a span ratio, not a calculation.
- Hoist-related values (hook headroom, hook approach) come from the reference
  10 t hoist or a guessed table. Real values come from a hoist catalogue.
- Single-girder top-running crane only. No double-girder or underslung crane,
  and no side section (A-A) or wheel detail (Z).
- On very narrow or very tall cranes some labels can crowd together.
- No users or auth. Concurrent edits are last-write-wins.
- Millimetres only. SVG export only (no PDF or DXF).

## Hours spent

_TODO: fill in your real hours._

## Other docs

- `DECISIONS.md`: my decisions and assumptions (written by me, without AI).
- `AI_LOG.md`: how AI was used and checked.
