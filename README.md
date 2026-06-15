# Online Book Reading System

A Laravel 12 REST API for managing a personal book reading experience with font-size-aware page tracking, race condition safety, structured activity logging, and book content delivery.

---

## Table of Contents

- [Quick Start (Docker)](#quick-start-docker)
- [Architecture & Design Decisions](#architecture--design-decisions)
    - [Layer Structure](#layer-structure)
    - [DTOs & Resources](#dtos--resources)
    - [Font-Size-Agnostic Page Tracking](#font-size-agnostic-page-tracking)
    - [Page Content Delivery](#page-content-delivery)
    - [Race Condition Safety](#race-condition-safety)
    - [Caching](#caching)
    - [Activity Logging & Observability](#activity-logging--observability)
- [API Reference](#api-reference)
    - [1. Add Book to Library](#1-add-book-to-library)
    - [2. Open a Book](#2-open-a-book)
    - [3. Turn Page](#3-turn-page)
- [Seeded Books (IDs 1–5)](#seeded-books-ids-15)
- [Running Tests](#running-tests)
- [Postman Collection](#postman-collection)
- [Project Structure](#project-structure)

---

## Quick Start (Docker)

```bash
git clone git@github.com:sadafiiman/online_book_reading_system.git
cd online_book_reading_system

docker compose up -d --build
```

That's it. The entrypoint automatically:
- Generates an `APP_KEY` (if not already set)
- Runs migrations
- Seeds 5 books into the database

**Base URL:** `http://localhost:8080/api`

---

## Architecture & Design Decisions

### Layer Structure

```
HTTP Layer      →  Controllers, FormRequests, Resources, Middleware
Service Layer   →  BookService (business logic, cache, content retrieval)
Repository      →  BookRepository / CachedBookRepository (DB + locking + cache-aside)
                    BookContentRepository / CachedBookContentRepository (book text storage + cache)
Logging         →  BookActivityLogger (structured activity & observability logging)
Model Layer     →  Book, UserBook (domain logic)
```

### DTOs & Resources

All data flowing between layers is typed via **Data Transfer Objects (DTOs)**. HTTP responses are shaped by dedicated **Resource** classes, keeping controllers thin and serialization logic explicit.

| DTO | Purpose |
|-----|---------|
| `AddBookData` | Carries validated input for adding a book to a library |
| `BookProgressData` | Represents current reading position, page metadata, and page text |
| `LibraryEntryData` | Encapsulates a user's library entry (book + metadata) |
| `OpenBookData` | Input for opening a book with optional font size |
| `TurnPageData` | Input for turning a page with optional font size |
| `TurnPageResultData` | Result of a page-turn operation, including page text |

| Resource | Shapes |
|----------|--------|
| `BookProgressResource` | Reading progress responses |
| `LibraryEntryResource` | Library entry responses |
| `TurnPageResultResource` | Page-turn responses |

All endpoints return a consistent envelope via `ApiResponse`:

```json
{
  "success": true,
  "message": "...",
  "data": { ... }
}
```

### Font-Size-Agnostic Page Tracking

**The key design decision:** We store `last_read_char_position` (a raw character offset), not a page number. Pages are computed dynamically on each request based on font size.

```
chars_per_page = base_chars_per_page × (base_font_size / current_font_size)
current_page   = floor(char_position / chars_per_page) + 1
```

This means changing font size never corrupts the user's reading position — the character offset remains stable, and the page number adjusts automatically.

### Page Content Delivery

Both **Open Book** and **Turn Page** responses include `page_text` — the raw text of the current page, sliced from the book's stored content based on the user's character position and font size:

```
page_start = floor(char_position / chars_per_page) × chars_per_page
page_text  = substr(book_content, page_start, chars_per_page)
```

Book content is read via `BookContentRepository` from a dedicated `book` storage disk and cached for 24 hours by `CachedBookContentRepository` (content is static per book, so this is a long-lived cache with no invalidation concerns).

### Race Condition Safety

`BookRepository::turnPage()` uses **MySQL pessimistic locking** (`SELECT ... FOR UPDATE`) inside a transaction:

```php
DB::transaction(function () {
    $userBook = UserBook::where(...)->lockForUpdate()->first();
    // Only ONE concurrent request can hold this lock.
    // Others queue and wait. No lost updates.
    $userBook->advanceToNextPage($fontSize);
    $userBook->save();
});
```

This prevents the "lost update" problem: two simultaneous requests both reading page 5 and both writing page 6.

### Caching

`CachedBookRepository` is a cache-aside decorator around `BookRepository`:

- **Book metadata** (title, author, total_chars) is cached for 24 hours — books don't change.
- **User reading state** (`is_active`, `last_read_char_position`) is cached with a short 5-minute TTL for fast reads, but **writes always go through the database first** (with row locking via `turnPage`/`switchActiveBook`), and the cache is refreshed afterward from the committed result — the cache is never the source of truth for correctness-critical paths.
- Cached values are stored as plain attribute arrays (`toArray()`) and rehydrated via `setRawAttributes()` on read, to avoid `__PHP_Incomplete_Class` serialization issues and fillable/guard interference with Eloquent model instances across cache drivers.

`CachedBookContentRepository` follows the same cache-aside pattern for raw book text, with a 24-hour TTL since content never changes after seeding.

### Activity Logging & Observability

`BookActivityLogger` (implementing `BookActivityLoggerInterface`) writes structured, dedicated-channel logs (`book_activity`) for:

- **Cache hits/misses** — `cache.hit`, `cache.miss` (debug level)
- **Cache refreshes** — `cache.refresh` after writes (info level)
- **Domain events** — `book.added_to_library`, `book.opened`, `book.page_turned` (info level)
- **Rejected actions** — `book.add_to_library_rejected`, `book.open_rejected`, etc., with a `reason` field (warning level)
- **Lock contention** — `db.lock_contention`, logged when acquiring a row lock exceeds a 50ms threshold (warning level)

This gives visibility into cache effectiveness, user behavior, and database contention without coupling business logic (`BookService`) or repositories to a concrete logging implementation — both depend on `BookActivityLoggerInterface`, injected via the service container.

Logs are written to a dedicated `book_activity` channel — configure this in `config/logging.php`.

---

## API Reference

All requests require the `X-User-Id` header (integer).

### 1. Add Book to Library

```
POST /api/library/books
X-User-Id: 1
Content-Type: application/json

{ "book_id": 1 }
```

**Response 201:**
```json
{
  "success": true,
  "message": "Book added to your library.",
  "data": {
    "book_id": 1,
    "title": "The Art of Clean Code",
    "author": "Robert C. Martin",
    "added_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

**Errors:** `404` book not found · `409` already in library · `422` missing/invalid `book_id`

---

### 2. Open a Book

```
POST /api/library/books/{bookId}/open
X-User-Id: 1
Content-Type: application/json

{ "font_size": 18 }   ← optional, defaults to 16
```

**Response 200:**
```json
{
  "success": true,
  "message": "Book opened successfully.",
  "data": {
    "book_id": 1,
    "title": "The Art of Clean Code",
    "last_page": 3,
    "total_pages": 60,
    "font_size": 18,
    "page_text": "Clean code is simple and direct. Clean code reads like well-written prose..."
  }
}
```

**Errors:** `404` book not in library · `422` out-of-range font size

---

### 3. Turn Page

```
POST /api/library/books/{bookId}/turn-page
X-User-Id: 1
Content-Type: application/json

{ "font_size": 18 }   ← optional, defaults to 16
```

**Response 200:**
```json
{
  "success": true,
  "message": "Page turned successfully.",
  "data": {
    "book_id": 1,
    "current_page": 4,
    "total_pages": 60,
    "font_size": 18,
    "is_last_page": false,
    "page_text": "Each function should do one thing. Functions should do one thing..."
  }
}
```

**Errors:** `404` book not in library · `422` book not active (open it first) · `422` already on last page

---

## Seeded Books (IDs 1–5)

| ID | Title | Author | ~Pages (font 16) |
|----|-------|--------|-------------------|
| 1 | The Art of Clean Code | Robert C. Martin | 60 |
| 2 | Design Patterns in PHP | Gang of Four | 48 |
| 3 | Laravel: Up and Running | Matt Stauffer | 40 |
| 4 | Domain-Driven Design | Eric Evans | 73 |
| 5 | The Pragmatic Programmer | Andrew Hunt | 55 |

---

## Running Tests

Tests run against a dedicated test database/cache, isolated from the dev environment, configured via `.env.testing` and `phpunit.xml`.

```bash
# Clear cached config first (important — stale config overrides test env vars)
docker compose exec app php artisan config:clear

# Run the full suite
docker compose exec app php artisan test

# Or directly via PHPUnit
docker compose exec app ./vendor/bin/phpunit

# Run a single test
docker compose exec app php artisan test --filter adding_a_book_twice_returns_409
```

> **Note:** Avoid running `php artisan config:cache` / `route:cache` in local development — cached config silently overrides `phpunit.xml` environment variables and can cause tests to connect to the wrong database/cache.

Feature tests for open/turn-page use `Storage::fake('book')` to provide in-memory book content without touching the real `book` disk.


## Postman Collection

A ready-to-use Postman collection is included in the project root: **`API.postman_collection.json`**.

### Import

1. Postman → **Import** → select `API.postman_collection.json` from the project root.
2. Set the collection variable `base_url` to `http://localhost:8080/api` (the default for the Docker setup).
3. Set the collection variable `user_id` — this is sent as the `X-User-Id` header on every request, since the system doesn't implement authentication.

### Suggested flow

The requests are grouped to exercise the full reading flow in order:

1. **Add Book to Library** — `POST /library/books` with a `book_id` from 1–5 (the seeded books).
2. **Open Book** — `POST /library/books/{bookId}/open`, marks it active and returns the last read page along with its text.
3. **Turn Page** — `POST /library/books/{bookId}/turn-page`, run repeatedly to advance through the book and confirm `current_page` and `page_text` update correctly.

Try opening a second book after step 2 — it deactivates the first, since only one book can be active per user at a time. Then try `turn-page` on the now-inactive book to see the `422` response.


---

## Project Structure

```
app/
├── DTOs/
│   ├── AddBookData.php
│   ├── BookProgressData.php
│   ├── LibraryEntryData.php
│   ├── OpenBookData.php
│   ├── TurnPageData.php
│   └── TurnPageResultData.php
├── Http/
│   ├── Controllers/
│   │   └── BookController.php            # Thin — delegates to service
│   ├── Middleware/
│   │   └── ResolveUserIdMiddleware.php   # Extracts X-User-Id header
│   ├── Requests/
│   │   ├── ApiRequest.php                # Shared base request
│   │   ├── AddBookRequest.php
│   │   ├── OpenBookRequest.php
│   │   └── TurnPageRequest.php
│   └── Resources/
│       ├── BookProgressResource.php
│       ├── LibraryEntryResource.php
│       └── TurnPageResultResource.php
├── Http/
│   └── Responses/
│       └── ApiResponse.php               # Consistent JSON envelope
├── Logging/
│   ├── BookActivityLoggerInterface.php
│   └── BookActivityLogger.php            # Structured book_activity channel logging
├── Models/
│   ├── Book.php                          # totalPagesForFontSize()
│   └── UserBook.php                      # currentPage(), charsPerPage(), advanceToNextPage()
├── Repositories/
│   ├── Interfaces/
│   │   ├── BookRepositoryInterface.php
│   │   └── BookContentRepositoryInterface.php
│   ├── BookRepository.php                # DB locking for race conditions
│   ├── CachedBookRepository.php          # Cache-aside decorator
│   ├── BookContentRepository.php         # Reads book text from storage
│   └── CachedBookContentRepository.php   # Cache-aside decorator for book content
├── Services/
│   └── BookService.php                   # Business logic, cache orchestration, page text extraction
├── Exceptions/
│   └── BookExceptions/                   # Domain exceptions
└── Providers/
    └── AppServiceProvider.php            # Interface → Implementation binding

database/
├── factories/
│   ├── BookFactory.php
│   └── UserBookFactory.php
├── migrations/
│   ├── ..._create_books_table.php
│   └── ..._create_user_books_table.php
└── seeders/
    ├── BookSeeder.php
    ├── UserSeeder.php
    └── DatabaseSeeder.php

tests/
├── Feature/
│   ├── Api/
│   │   └── LibraryApiTest.php            # Full HTTP integration tests
│   └── Repositories/
│       └── BookRepositoryTest.php        # Repository + DB behavior tests
└── Unit/
    ├── Models/
    │   ├── BookTest.php                  # Font-size/page math tests
    │   └── UserBookTest.php
    ├── Repositories/
    │   └── CachedBookRepositoryTest.php  # Cache-aside behavior tests
    └── Services/
        └── BookServiceTest.php           # Business logic tests (mocked repo)

docker/
├── nginx/default.conf
└── php/
    ├── Dockerfile
    └── entrypoint.sh
```
