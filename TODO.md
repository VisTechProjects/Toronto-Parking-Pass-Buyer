# Future Features

## App Push Notifications for Expiry Reminders
Add Firebase Cloud Messaging (FCM) to send push notifications to the Android app when permits are about to expire. Server-side cron would trigger FCM at the same time it sends email reminders (0/1/2 days before expiry).

**Why FCM over local scheduling:**
- Single reminder system (server-side cron already knows expiry dates)
- No duplicate scheduling logic in the app
- Works reliably without fighting Android battery optimization
