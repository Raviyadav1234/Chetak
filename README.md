# Concurrent-Safe Booking System

This repository contains a full-stack booking system built with Laravel (Backend API) and React (Frontend).

## Setup Instructions

### Using Docker (Recommended)
You can run the entire stack using the provided `docker-compose.yml`.
1. Make sure you have Docker and Docker Compose installed.
2. Run `docker-compose up -d --build`.
3. The Laravel API will be available at `http://localhost:8000`.
4. The React Frontend will be available at `http://localhost:5173`.
5. Enter the `backend` container and run migrations and seeders:
   ```bash
   docker-compose exec backend php artisan migrate --seed
   ```

### Local Setup (XAMPP/MAMP)
1. Navigate to the `backend` folder.
2. Copy `.env.example` to `.env` and set your database credentials.
3. Run `composer install`, `php artisan key:generate`, and `php artisan migrate --seed`.
4. Start the Laravel server: `php artisan serve`.
5. Navigate to the `frontend` folder, run `npm install` and `npm run dev`.

---

## Architecture Decisions & Trade-offs
- **Separation of Concerns:** The project is split into entirely separate `backend` (Laravel API) and `frontend` (React + Vite) directories. This mimics real-world microservice/API-first architecture.
- **Mock Payment Queue:** A job (`ProcessPayment`) is dispatched to handle the payment simulation asynchronously, introducing a 20% failure rate to demonstrate transaction rollbacks on the frontend.
- **Trade-offs for time:** 
  - Authentication (Sanctum) is set up, but the frontend currently uses a hardcoded mocked user ID `1` for the sake of speed. Real-world apps would use Sanctum's SPA authentication flow with CSRF cookies.
  - The queue driver is synchronous by default in local setup for easier testing, but can be switched to `database` or `redis` for production.

---

## Concurrency and Overselling Prevention

**How does your solution prevent overselling under concurrent load?**
I used **Pessimistic Locking** (`lockForUpdate()`) provided by Laravel's Eloquent. 
When a user attempts to book a product, the code wraps the operation in a database transaction (`DB::beginTransaction()`). It then selects the product with `lockForUpdate()`. This tells the database (e.g., MySQL) to acquire a row-level write lock on that product. If another request comes in at the exact same millisecond trying to book the same product, the database will force the second request to wait until the first request completes its transaction (commits or rolls back).
This guarantees that the stock check (`$product->stock < $quantity`) and subsequent decrement happen atomically, making overselling impossible.

**How did you verify it?**
I created a feature test `tests/Feature/ConcurrencyBookingTest.php` that verifies the logic. In a real CI environment, we would execute this test using simulated parallel requests (e.g., using Laravel's Process pool or Guzzle async promises) to ensure that only the allowed number of bookings succeed and the stock never drops below 0.
