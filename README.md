# Content Hub

A workspace dashboard for creating text, images and video with AI. Add context to a library, include it in projects, and chat with an agent that picks the workflow. The plan is in [PLAN.md](PLAN.md) and the stack in [STACK.md](STACK.md).

## Requirements

- PHP 8.3 or newer with `pdo_sqlite`, `mbstring`, `intl`, `gd`, `fileinfo`, `zip`. Use a PHP build that runs on your CPU (see the note in STACK.md).
- Composer, Node 22 or newer, npm
- FFmpeg and FFprobe, for describing video context

## Setup

```bash
composer install
npm install
cp .env.example .env        # skip if .env exists
php artisan key:generate
touch database/database.sqlite database/queue.sqlite
php artisan migrate
npm run build
php artisan ai:sync-models  # loads OpenRouter's model list; no key needed
```

Then fill in `.env`:

| Variable                              | Needed for                                                        |
| ------------------------------------- | ----------------------------------------------------------------- |
| `OPENROUTER_API_KEY`                  | Chat, images, video, media reading                                |
| `FIRECRAWL_API_KEY`                   | Web scraping                                                      |
| `FFMPEG_BINARIES`, `FFPROBE_BINARIES` | Paths to FFmpeg, if they are not on the web server's PATH         |
| `CONTENTHUB_MONTHLY_SPEND_LIMIT`      | Per-user monthly spend ceiling in USD (default 20)                |
| `CONTENTHUB_MONTHLY_SCRAPE_CREDITS`   | Per-user monthly Firecrawl credits (default 200)                  |
| `CONTENTHUB_TEXT_MODEL` and friends   | App-wide default models; workspaces can override them in settings |

## Running

Three processes: the web server, a queue worker (video generation, scraping) and the scheduler (daily model sync and backups).

```bash
php artisan serve
php artisan queue:work
php artisan schedule:work
```

`composer run dev` starts the server, a queue worker and Vite together.

Open http://localhost:8000, register, and you land in your personal workspace.

## Test account

`php artisan db:seed` creates a local test user. These are development-only values from `database/seeders/DatabaseSeeder.php` and the user factory; never use them on a public server.

|            |                             |
| ---------- | --------------------------- |
| Login page | http://localhost:8000/login |
| Email      | `test@example.com`          |
| Password   | `password`                  |
| Workspace  | Test Workspace              |

After login you land on http://localhost:8000/test-workspace/dashboard. The other pages are under the same workspace path:

| Page               | URL                                  |
| ------------------ | ------------------------------------ |
| Dashboard          | `/test-workspace/dashboard`          |
| Chats              | `/test-workspace/chats`              |
| Projects           | `/test-workspace/projects`           |
| Context library    | `/test-workspace/context`            |
| Outputs            | `/test-workspace/outputs`            |
| Workspace settings | `/test-workspace/workspace/settings` |
| Account settings   | `/settings/profile`                  |

## How it fits together

- **Workspaces** are the starter kit's teams. Invite members from the workspace switcher. Everything below belongs to one workspace.
- **Context library**: text, images, video and scraped web pages.
- **Projects** include library items and can set instructions that add to or replace the workspace prompt.
- **Chats**: a free chat starts empty; a project chat gets the project's context. Attach files for one chat only, or type `/scrape <url>`.
- **Agent** (`app/Ai/Agents/ContentAgent.php`) writes text directly, or calls `generate_image` / `generate_video`. Videos run in the background and appear when ready.
- **Capability checks** (`app/Services/Context/ChatContextBuilder.php`): images go to models that read them; otherwise the media reading model describes them. Video is described from sampled frames. Descriptions are cached.
- **Scraped pages** are cleaned, previewed before use, wrapped as untrusted data, and never load remote images. A video started while a chat holds scraped content waits for confirmation.

## Checks

```bash
vendor/bin/pest          # 140 tests
vendor/bin/phpstan analyse
vendor/bin/pint --test
npm run check            # lint and format
npx tsc --noEmit
```
