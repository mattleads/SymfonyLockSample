# Symfony Lock Component Examples

This project provides working examples for the concepts covered in the article about the Symfony Lock Component.

## 1. Project Setup

**Prerequisites:**
- PHP 8.2 or higher
- Composer
- Redis server running on `localhost:6379`

**Installation:**

1.  Clone the repository:

```bash
git clone https://github.com/mattleads/SymfonyLockSample.git
cd SymfonyLockSample
```

2. Install dependencies:

```bash
composer install
```

## 2. Running the Examples

This project uses the Symfony local web server for controller-based examples.

**Prerequisites for Symfony Local Server:**
-   Symfony CLI installed globally. If not, you can install it via Composer:
    ```bash
    composer global require symfony/cli
    ```

Open a new terminal and run the Symfony local server:

```bash
symfony serve
```

This will typically start the server on `http://127.0.0.1:8000` or a similar address. You will run the command-line examples from a separate terminal.

---

### Example 1: The Try-Finally Pattern

This example uses a service (`OrderProcessor`) and a command (`app:process-order`) to demonstrate how a lock is always released, even if an exception occurs.

**How to run:**

Open a terminal and run the command to process order #123:
```bash
php bin/console app:process-order 123
```
You will see logs indicating the lock is acquired, the order is processed, and the lock is released.

**To test the `finally` block:**

Run the command with the `--crash` option. This will simulate an exception during processing.
```bash
php bin/console app:process-order 123 --crash
```
You will see an error, but the logs (and checking Redis) will show that the lock was still released thanks to the `finally` block.

**To see locking in action:**

1.  In one terminal, run: `php bin/console app:process-order 123`
2.  Quickly, in a second terminal, run the same command: `php bin/console app:process-order 123`

The second command will fail, throwing a "Resource is currently locked" exception, because the first process holds the lock.

---

### Example 2: Declarative Locking with Attributes

This example shows how to use a custom PHP attribute `#[Lock]` to protect a controller action from concurrent access.

**How to run:**

1.  Make sure the Symfony local web server is running (`symfony serve`).
2.  Open a web browser and navigate to: `http://localhost:8000/invoice/55/generate`
    The page will load for 10 seconds (simulating work) and then display a success message.
3.  To see the lock in action, open two browser tabs to the same URL and load them at the same time. The second tab will immediately receive a "429 Too Many Requests" error because the first request has acquired the lock.

---

### Example 3: Refreshing Locks for Long-Running Tasks

This example demonstrates a command that runs for a long time and periodically refreshes its lock to prevent it from expiring mid-process.

**How to run:**

Run the following command in your terminal:
```bash
php bin/console app:long-task
```
The task will acquire a lock with a 5-second TTL. It will then loop 10 times, sleeping for 2 seconds on each iteration. You will see output showing that the lock is refreshed every 2 seconds, preventing it from ever expiring during the 20-second task.

---

### Example 4: Blocking vs. Non-Blocking

This example uses a single command with different flags to show various lock acquisition strategies. You will need two terminals for this.

**Scenario:**

1.  **Terminal 1 (Holder):** Acquire and hold a lock.
    ```bash
    php bin/console app:blocking-test --hold
    ```
    This command will acquire a lock and sleep for 10 seconds. You have 10 seconds to run the commands in Terminal 2.

2.  **Terminal 2 (Acquirer):**
    -   **Non-Blocking:** Run this while Terminal 1 is holding the lock. It will fail immediately.
        ```bash
        php bin/console app:blocking-test --non-blocking
        ```
    -   **Blocking:** Run this while Terminal 1 is holding the lock. It will wait until the lock is released by Terminal 1 and then acquire it.
        ```bash
        php bin/console app:blocking-test --blocking
        ```
    -   **Retry Loop:** Run this while Terminal 1 is holding the lock. It will try to acquire the lock 5 times, waiting 1 second between attempts.
        ```bash
        php bin/console app:blocking-test --retry
        ```
