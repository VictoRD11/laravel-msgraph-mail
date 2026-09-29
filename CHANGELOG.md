# Changelog

All notable changes to `laravel-msgraph-mail` will be documented in this file.

## v1.2.0 - 2026-09-29

### What's Changed

* Deliver calendar invitations to Cc and Bcc recipients by @VictoRD11 in https://github.com/VictoRD11/laravel-msgraph-mail/pull/3

**Full Changelog**: https://github.com/VictoRD11/laravel-msgraph-mail/compare/v1.1.0...v1.2.0

## Unreleased

- Fixed Cc and Bcc recipients not receiving calendar invitations sent as MIME. Exchange delivers meeting
  requests only to the calendar attendees, so To and Cc recipients are now added as `ATTENDEE` entries and
  every Bcc recipient receives a separate invitation that lists only themselves.

## v1.1.0 - 2026-09-14

**Full Changelog**: https://github.com/VictoRD11/laravel-msgraph-mail/compare/v1.0.0...v1.1.0

## 1.1.0 - 2026-09-14

- Added `mime_mode` option (`auto`, `always`, `never`). Messages with `text/calendar` attachments are now sent
  as raw MIME so that Outlook receives them as meeting requests (Accept/Decline) instead of a plain `.ics` file.

## 1.0.0 - 2023-02-13

Initial Release 🍾

**Full Changelog**: https://github.com/InnoGE/laravel-msgraph-mail/commits/1.0.0
