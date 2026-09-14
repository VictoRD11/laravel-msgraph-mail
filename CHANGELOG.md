# Changelog

All notable changes to `laravel-msgraph-mail` will be documented in this file.

## 1.1.0 - 2026-09-14

- Added `mime_mode` option (`auto`, `always`, `never`). Messages with `text/calendar` attachments are now sent
  as raw MIME so that Outlook receives them as meeting requests (Accept/Decline) instead of a plain `.ics` file.

## 1.0.0 - 2023-02-13

Initial Release 🍾

**Full Changelog**: https://github.com/InnoGE/laravel-msgraph-mail/commits/1.0.0
