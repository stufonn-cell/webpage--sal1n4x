-- ---------------------------------------------------------------------------
-- PsiClinic - security tables
-- Idempotent: it can run any number of times without further effects.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

-- Counters of the application rate limiter (src/Core/RateLimiter.php).
-- PHP-FPM workers share no memory, so the counters live here. One row per
-- key and fixed window; rate_key is an HMAC of the profile and the client
-- (IP or user id), so no address is stored in clear. Rows expire on their
-- own (expires_at, Unix time) and the limiter deletes them now and then.
CREATE TABLE IF NOT EXISTS rate_limits (
    rate_key      CHAR(64)     NOT NULL,
    window_start  INT UNSIGNED NOT NULL,
    bucket        VARCHAR(40)  NOT NULL,
    hits          INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at    INT UNSIGNED NOT NULL,
    PRIMARY KEY (rate_key, window_start),
    KEY idx_rate_limits_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
