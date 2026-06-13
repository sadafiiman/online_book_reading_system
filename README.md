# Online Book Reading System

A Laravel 12 REST API for managing a personal book reading experience with font-size-aware page tracking and race condition safety.

---

## Quick Start (Docker)

```bash
git clone git@github.com:sadafiiman/online_book_reading_system.git
cd book-reading-system

docker compose up -d --build
```

That's it. The entrypoint automatically:
- Generates an `APP_KEY`
- Runs migrations
- Seeds 5 books into the database

**Base URL:** `http://localhost:8080/api`

---

## Architecture & Design Decisions

### Layer Structure

```
HTTP Layer      →  Controllers, FormRequests, Middleware
Service Layer   →  BookService (business logic, cache)
Repository      →  BookRepository (DB + locking)
Model Layer     →  Book, UserBook (domain logic)
```

### Font-Size-Agnostic Page Tracking

**The key design decision:** We store `last_read_char_position` (a raw character offset), not a page number. Pages are computed dynamically on each request based on font size.

```
chars_per_page = base_chars_per_page × (base_font_size / current_font_size)
current_page   = floor(char_position / chars_per_page) + 1
```

This means changing font size never corrupts the user's reading position — the character offset remains stable, and the page number adjusts automatically.

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

Book metadata (title, author, total_chars) is cached in Redis for 24 hours — books don't change. User state (`is_active`, `last_read_char_position`) is **never** cached because it changes on every turn-page request.

---

## API Reference

All requests require the `X-User-ID` header (integer).

### 1. Add Book to Library

```
POST /api/library/books
X-User-ID: 1
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

**Errors:** `404` book not found · `409` already in library · `400` missing header

---

### 2. Open a Book

```
POST /api/library/books/{bookId}/open
X-User-ID: 1
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
    "font_size": 18
  }
}
```

**Errors:** `404` book not found or not in library

---

### 3. Turn Page

```
POST /api/library/books/{bookId}/turn-page
X-User-ID: 1
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
    "is_last_page": false
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

```bash
# Inside the container
docker compose exec app php artisan test

# Or with coverage
docker compose exec app php artisan test --coverage
```

---

## Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   └── BookController.php        # Thin — delegates to service
│   ├── Middleware/
│   │   └── ResolveUserId.php         # Extracts X-User-ID header
│   └── Requests/
│       ├── AddBookRequest.php
│       ├── OpenBookRequest.php
│       └── TurnPageRequest.php
├── Models/
│   ├── Book.php                      # totalPagesForFontSize()
│   └── UserBook.php                  # currentPage(), advanceToNextPage()
├── Repositories/
│   ├── Interfaces/
│   │   └── BookRepositoryInterface.php
│   └── BookRepository.php            # DB locking for race conditions
├── Services/
│   └── BookService.php               # Business logic + Redis cache
├── Exceptions/
│   └── BookExceptions.php            # Domain exceptions
└── Providers/
    └── AppServiceProvider.php        # Interface → Implementation binding

database/
├── migrations/
│   ├── ..._create_books_table.php
│   └── ..._create_user_books_table.php
└── seeders/
    ├── BookSeeder.php
    └── DatabaseSeeder.php

tests/
├── Feature/
│   └── BookApiTest.php               # Full HTTP integration tests
└── Unit/
    ├── PageCalculationTest.php        # Font-size math tests
    └── BookRepositoryTest.php         # Repository behavior tests

docker/
├── nginx/default.conf
└── php/
    ├── Dockerfile
    └── entrypoint.sh
```
