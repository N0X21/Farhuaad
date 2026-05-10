<img src="https://raw.githubusercontent.com/N0X21/farhuaad/main/assets/img/logo.png">

# Farhuaad

Farhuaad is a prediction and dispute trading platform built with native PHP. Users can create disputes, place bets, chat in real time, and track platform statistics with AI-assisted moderation and multilingual support.

---

## Overview

Farhuaad is a modern prediction and dispute trading platform built with native PHP. The platform allows users to create disputes, place bets on outcomes, participate in real-time discussions, and track statistics through a lightweight and scalable architecture. It includes OTP authentication, AI-assisted dispute resolution, multilingual support, encrypted chat functionality, and automated cron-based processing designed for high-performance community-driven prediction markets.

---

## Features

* Prediction & dispute system
* Betting on outcomes
* Real-time dispute chat
* User profiles & statistics
* Leaderboards
* OTP authentication
* AI-assisted moderation & resolution
* Multilingual interface
* Cron-based automation

---

## Tech Stack

* PHP 8+ (native, no framework)
* MySQL (PDO)
* Vanilla JavaScript
* HTML/CSS
* Apache / Nginx
* Cron jobs
* PHPMailer

---

## Project Structure

```
public_html/
├── api/
├── app/
├── assets/
├── data/
├── lang/
├── pages/
├── partials/
├── PHPMailer/
├── scripts/
├── index.php
├── .env
└── .htaccess
```

---

## API

### Auth

* `/api/send_otp.php`
* `/api/verify_otp.php`

### Disputes

* `/api/place_bet.php`
* `/api/dispute_details.php`
* `/api/dispute_chat.php`
* `/api/daily_disputes.php`
* `/api/disputes_cron.php`

### Platform

* `/api/platform_stats.php`
* `/api/presence_ping.php`

---

## Environment Variables

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=farhuaad
DB_USER=root
DB_PASS=password

SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=user@example.com
SMTP_PASS=secret
SMTP_FROM=no-reply@example.com
SMTP_NAME=Farhuaad

DISPUTES_CRON_TOKEN=change_me
DISPUTE_ADMIN_LOGIN=admin
DISPUTE_ADMIN_PASSWORD=strong_password

CLAUDE_RESOLVE_MIN_CONFIDENCE=0.7
CLAUDE_RESOLVE_BATCH=5

FARHUAAD_DISPUTE_CHAT_SECRET=random_32_byte_key

RECAPTCHA_SITE_KEY=site_key
RECAPTCHA_SECRET=secret_key

APP_ENV=production
```

---

## Installation

```bash
git clone https://github.com/N0X21/farhuaad.git
cd farhuaad
```

Set up web server root to `/public_html`, configure `.env`, and import database schema.

Run locally:

```bash
php -S localhost:8000 -t public_html
```

---

## Cron Jobs

```bash
*/5 * * * * php /path/to/public_html/api/disputes_cron.php
```

Used for automated dispute processing and AI resolution.

---

## Security Notes

* Do not commit `.env`
* Use HTTPS
* Protect admin routes
* Rotate credentials regularly
* Enable rate limiting for OTP

---

## Roadmap

* WebSocket real-time updates
* Docker support
* OAuth login
* Advanced analytics dashboard
* CI/CD pipeline

---

## License

No license specified.

```
```
