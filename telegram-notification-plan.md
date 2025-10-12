# Telegram Notification Channel Implementation Plan

## Overview

This document outlines the complete process for implementing Telegram as a notification channel for your Laravel application, specifically to replace email notifications for `spatie/laravel-backup`.

**Problem**: Email service limits are preventing reliable backup notifications.

**Solution**: Use Telegram Bot API to send notifications directly to your Telegram account/group.

---

## Part 1: Telegram Bot Setup (Manual Steps)

Before any code changes, you need to create a Telegram bot and get your credentials.

### Step 1.1: Create a Telegram Bot

1. Open Telegram and search for `@BotFather`
2. Start a chat and send `/newbot`
3. Follow the prompts:
   - Choose a name for your bot (e.g., "David's Backup Bot")
   - Choose a username ending in "bot" (e.g., "davidharting_backup_bot")
4. BotFather will respond with your **Bot Token**. It looks like:
   ```
   123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ
   ```
5. **Save this token securely** - you'll need it for configuration

### Step 1.2: Get Your Chat ID

You need to know where to send notifications. There are two options:

#### Option A: Send to yourself (Personal Chat)
1. Search for your new bot in Telegram by username
2. Start a chat and send any message (e.g., "/start")
3. Visit this URL in your browser (replace `YOUR_BOT_TOKEN`):
   ```
   https://api.telegram.org/botYOUR_BOT_TOKEN/getUpdates
   ```
4. Look for `"chat":{"id":` in the JSON response
5. Your chat ID will be a number like `123456789`

#### Option B: Send to a group/channel
1. Create a Telegram group
2. Add your bot to the group as a member
3. Send a message in the group
4. Visit the getUpdates URL (same as above)
5. Look for the chat ID in the response (may be negative like `-987654321`)

### Step 1.3: Test Your Bot (Optional but Recommended)

Before proceeding, verify your bot works:

```bash
# Replace YOUR_BOT_TOKEN and YOUR_CHAT_ID
curl -X POST "https://api.telegram.org/botYOUR_BOT_TOKEN/sendMessage" \
  -d "chat_id=YOUR_CHAT_ID" \
  -d "text=Test message from my bot"
```

If successful, you should receive a message in Telegram.

---

## Part 2: Package Selection

**Recommended Package**: `laravel-notification-channels/telegram`

### Why this package?

- **Official Laravel Notification Channel**: Part of the laravel-notification-channels organization
- **Well-maintained**: Active development and community support
- **Feature-rich**: Supports text, files, photos, inline buttons, polls, etc.
- **Laravel-native**: Works seamlessly with Laravel's notification system
- **Documentation**: Comprehensive docs at https://laravel-notification-channels.com/telegram/

### Alternatives Considered

- `telegram-notifications` by babenkoivan - Also good, but less integrated with Laravel ecosystem
- Custom implementation - Too much overhead and maintenance

---

## Part 3: Installation

### Step 3.1: Install the Package

```bash
composer require laravel-notification-channels/telegram
```

### Step 3.2: Add Environment Variables

Add to your `.env` file:

```bash
TELEGRAM_BOT_TOKEN=your_bot_token_from_botfather
TELEGRAM_CHAT_ID=your_chat_id_from_getupdates
```

Update `.env.example` as well:

```bash
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=
```

---

## Part 4: Configuration

### Step 4.1: Update config/services.php

Add Telegram configuration:

```php
<?php

return [
    // ... existing services ...

    'telegram-bot-api' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],
];
```

### Step 4.2: Update config/backup.php

Change the notification channels from 'mail' to 'telegram':

**Before** (lines 198-205):
```php
'notifications' => [
    \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => ['mail'],
    \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => ['mail'],
    \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => ['mail'],
    \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class => [],
    \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class => ['mail'],
    \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class => ['mail'],
],
```

**After**:
```php
'notifications' => [
    \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => ['telegram'],
    \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => ['telegram'],
    \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => ['telegram'],
    \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class => ['telegram'],
    \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class => ['telegram'],
    \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class => ['telegram'],
],
```

**Note**: I've also enabled `BackupWasSuccessfulNotification` so you know when backups succeed.

### Step 4.3: Create Custom Notifiable (Recommended)

By default, spatie/laravel-backup uses its own Notifiable class. To use Telegram, we need to customize it.

Create a new file: `app/Notifications/BackupNotifiable.php`

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Notifiable as NotifiableTrait;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;

class BackupNotifiable
{
    use NotifiableTrait;

    public function routeNotificationForTelegram(): string
    {
        return config('services.telegram-bot-api.chat_id', env('TELEGRAM_CHAT_ID'));
    }

    public function getKey()
    {
        return 1;
    }
}
```

### Step 4.4: Update Backup Configuration to Use Custom Notifiable

In `config/backup.php`, update the notifiable class (around line 211):

**Before**:
```php
'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,
```

**After**:
```php
'notifiable' => \App\Notifications\BackupNotifiable::class,
```

---

## Part 5: Testing

### Step 5.1: Test Telegram Notifications

Create a test command to verify Telegram notifications work:

```bash
php artisan tinker
```

```php
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Telegram\TelegramMessage;

$notifiable = new \App\Notifications\BackupNotifiable();

Notification::send($notifiable, new class extends \Illuminate\Notifications\Notification {
    public function via($notifiable)
    {
        return ['telegram'];
    }

    public function toTelegram($notifiable)
    {
        return TelegramMessage::create()
            ->content('Test notification from davidharting.com');
    }
});
```

You should receive a message in Telegram.

### Step 5.2: Test Backup Notifications

Run a backup to test:

```bash
php artisan backup:run
```

You should receive a Telegram notification about the backup result.

### Step 5.3: Test Backup Monitor

Check backup health:

```bash
php artisan backup:monitor
```

You should receive a Telegram notification about backup health status.

---

## Part 6: Deployment

### Step 6.1: Update Production Environment

1. SSH into your Digital Ocean droplet
2. Add the environment variables to your production `.env`:
   ```bash
   TELEGRAM_BOT_TOKEN=your_actual_token
   TELEGRAM_CHAT_ID=your_actual_chat_id
   ```

### Step 6.2: Deploy Code Changes

Follow your normal deployment process:

```bash
# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader

# Clear and cache config
php artisan config:clear
php artisan config:cache

# Restart services
docker-compose restart
```

### Step 6.3: Verify in Production

Monitor your scheduled backups to ensure notifications arrive via Telegram.

---

## Part 7: Advanced Configuration (Optional)

### Customize Notification Messages

If you want more control over the notification format, you can extend the backup notification classes:

```php
<?php

namespace App\Notifications;

use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification as BaseNotification;
use NotificationChannels\Telegram\TelegramMessage;

class BackupHasFailedNotification extends BaseNotification
{
    public function toTelegram($notifiable)
    {
        return TelegramMessage::create()
            ->content("🚨 *Backup Failed*\n\n" .
                     "Site: davidharting.com\n" .
                     "Time: " . now()->toDateTimeString())
            ->button('View Logs', 'https://davidharting.com/admin');
    }
}
```

Then update `config/backup.php` to use your custom notification classes.

### Group Multiple Backups

If you run multiple sites, consider:
1. Using different bots for different sites
2. Using the same bot but different groups
3. Adding site identifiers to messages

---

## Part 8: Rate Limits and Best Practices

### Telegram Rate Limits

- Maximum 30 messages per second per bot
- Your backup schedule (daily) is well within limits
- If sending many notifications, space them out

### Best Practices

1. **Keep token secure**: Never commit `.env` to git
2. **Monitor bot health**: Occasionally check if bot is still responding
3. **Backup your bot token**: Save it somewhere secure in case you need it
4. **Test before going live**: Always test in staging/local first
5. **Consider alerting for silence**: Set up monitoring if backups stop completely

---

## Part 9: Troubleshooting

### Problem: No notifications received

**Check**:
1. Bot token is correct in `.env`
2. Chat ID is correct in `.env`
3. Bot hasn't been blocked by you
4. Test with curl (see Part 1, Step 1.3)
5. Check Laravel logs: `storage/logs/laravel.log`

### Problem: "Chat not found" error

**Solution**:
- For personal chats: Message the bot first before it can message you
- For groups: Ensure bot is added as a member and has permission to send messages

### Problem: Notifications work locally but not in production

**Check**:
1. Environment variables are set in production `.env`
2. Config cache has been cleared: `php artisan config:clear`
3. Queue is running if using queued notifications

### Problem: Messages are ugly or poorly formatted

**Solution**:
- Telegram supports markdown formatting
- Use `TelegramMessage::create()->content()` with markdown
- Add line breaks with `\n`
- Use emojis for visual clarity

---

## Part 10: Rollback Plan

If something goes wrong:

1. **Quick rollback**: Change `config/backup.php` back to use 'mail' channel
2. **Clear config cache**: `php artisan config:clear && php artisan config:cache`
3. **Keep the package installed**: It won't interfere with other channels

---

## Summary Checklist

### Pre-implementation
- [ ] Create Telegram bot via @BotFather
- [ ] Get bot token
- [ ] Get chat ID
- [ ] Test bot with curl

### Implementation
- [ ] Install `laravel-notification-channels/telegram` package
- [ ] Add environment variables to `.env` and `.env.example`
- [ ] Update `config/services.php` with Telegram config
- [ ] Create `app/Notifications/BackupNotifiable.php`
- [ ] Update `config/backup.php` notification channels to 'telegram'
- [ ] Update `config/backup.php` notifiable class

### Testing
- [ ] Test Telegram notifications manually
- [ ] Test backup:run command
- [ ] Test backup:monitor command
- [ ] Verify messages are readable and contain useful info

### Deployment
- [ ] Add environment variables to production `.env`
- [ ] Deploy code changes
- [ ] Clear and cache config in production
- [ ] Monitor scheduled backups for 24-48 hours

### Documentation
- [ ] Document bot token in secure location
- [ ] Update team docs if applicable
- [ ] Add to runbook/incident response docs

---

## Additional Resources

- **Package Documentation**: https://laravel-notification-channels.com/telegram/
- **Package GitHub**: https://github.com/laravel-notification-channels/telegram
- **Telegram Bot API Docs**: https://core.telegram.org/bots/api
- **Spatie Backup Docs**: https://spatie.be/docs/laravel-backup

---

## Questions?

If you encounter issues not covered here, check:
1. Package GitHub issues
2. Laravel notification docs
3. Telegram Bot API documentation

**Last Updated**: 2025-10-10
