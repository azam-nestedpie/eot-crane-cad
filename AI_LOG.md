# AI Log

> Draft. Review it, correct anything that isn't true for you, and add your own
> checks and experiences before submitting.

## Tools used

| Tool | Used for |
|---|---|
| Claude Code | Reading and explaining the assignment PDF and the reference drawing; scaffolding the Laravel API, calculator, tests and Vue/SVG frontend; README draft |
| Laravel Boost | Gives the AI Laravel-13-specific guidelines (artisan `make:` commands, Form Requests, API Resources, Pint) |
| Headless Chrome + curl | Checking the AI's output: screenshots of the drawing, real HTTP calls to the API |

Not used for: `DECISIONS.md` (written by me without AI).

## Key prompts

1. *"First check and understand my project and tell me in simple [words]…"* (with the PDF attached)
   to get a plain-language summary of the brief, the must-haves and the grading order.
2. *"Understand the diagram which is mentioned in the PDF."* This walked through
   every view and number of the reference drawing. The useful result was
   that the numbers are consistent: 7900 + 620 − 8321 ≈ 200 means the girder underside is
   about 200 mm above the rail, and 1569 − 1369 = 200 is the roof clearance.
3. *"Now my stack is Laravel + Vue.js."* After this the AI built the app. I chose
   to commit in small steps and the scope (single girder; front view, plan view, data table).
4. _Add 1–2 of your own prompts here, e.g. follow-ups you asked while reviewing._

## Times the AI was wrong, and how it was caught

1. **Estimates were presented as exact.** The first calculator tagged
   "crane top above rail", "min building height" and "max lifting height" as
   `derived` (exact geometry), but each is built on a guessed value (girder
   depth or hook headroom). That breaks the honesty rule in the brief. It was caught by going
   through every value's source label one by one. Fixed in commit
   `Flag values computed from estimates as estimates`.
2. **The API leaked internals and gave a misleading error.** All automated tests
   passed, but calling the running server with curl showed two problems. A missing design returned
   a full stack trace with the internal model class name, and a broken JSON body
   returned *"The name field is required"* instead of saying the JSON was bad.
   Fixed with a clean 404 and a 400 middleware, with tests added
   (`Harden API errors…` commit).
3. **Drawing labels overlapped.** Short dimensions (200 mm roof gap, 1390 crane
   height) placed their text in the same spot. This was only visible in a screenshot, not
   in the code. Fixed by moving labels of short dimensions beside the line.
4. **Invalid PHP.** The calculator used `end(self::CONSTANT)`, which PHP doesn't
   allow because `end()` needs a variable. It was caught by reading the code before running it.

_Pick the one you can explain best for the interview, and add any you caught yourself._
