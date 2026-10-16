# HLTV Awards Mingle — hltv.relog.gg

Guest sign-up for HLTV Awards mingle activities, with an admin panel and per-activity coordinators.
Plain PHP 8 + MySQL, no build step, runs on cPanel shared hosting.

## Features
- **Public sign-up** (`/`): first name, last name, email, phone, 1st choice (required), 2nd choice (optional), consent. One sign-up per email.
- **Personal status page** (`/status.php?t=…`): link in every email. Shows status, assigned activity, group, groupmates, results (once published).
- **Automatic emails** (from `hltv-mingle@relog.gg`, with a "do not reply" footer): sign-up received → accepted / declined → group assignment → results.
- **Main admins**: accept into 1st / 2nd / any activity (capacity enforced), decline, back to pending, edit details, delete, CSV export; per-activity limits (0 = unlimited), open/close each activity; groups; results; documents; team; settings and mail log.
- **Coordinators**: only the activities assigned to them. Check-in (mobile friendly, instant), guest details, documents, entering results. They cannot accept/decline, change activities or group assignment.
- **Public results** (`/results.php`) per activity, after a main admin publishes them.

## Install (cPanel)
1. **Subdomain** `hltv.relog.gg` — set the document root to `<folder>/public` (preferred). If the document root has to stay the repo root, the root `.htaccess` rewrites into `public/` and blocks `lib/`, `storage/` and `config.php`.
2. **MySQL**: create a database and user (MySQL Databases), then import `install.sql` in phpMyAdmin. It already contains the 6 activities.
3. **Email account** `hltv-mingle@relog.gg` (Email Accounts). The SMTP host/port are under *Connect Devices*.
4. Copy `config.sample.php` → `config.php` and fill in base URL, DB and SMTP.
5. Make `storage/` and `storage/docs/` writable by PHP (755 is normally enough on cPanel).
6. Open `https://hltv.relog.gg/admin/` → the first visit shows a one-time form to create the first main admin.
7. In **Settings**, click *Send test email to me*. Failures show up in the mail log on the same page.
8. In **Team**, add the other main admins and the coordinators, and tick each coordinator's activities.

## Auto-deploy from GitHub
`.github/workflows/deploy.yml` lints the PHP and uploads over FTP on every push to `main`.
Add these repo secrets: `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`. The FTP account should point at the subdomain folder.
`config.php` and uploaded documents are never deployed or overwritten.

## Branding
- Logos: put `logo-hltv-awards.png` (header and emails, light-on-dark, about 36 px tall) and `logo-relog.png` (footer) in `public/assets/`. Until they are there, a text fallback is shown. Optional: `favicon.png`.
- Colours: the CSS variables at the top of `public/assets/style.css` (`--accent` etc.). The email accent colour is `#F2B705` in `lib/mail.php`.

## Typical flow
1. Main admin sets limits per activity (Activities).
2. Guests sign up → they get "we received your sign-up".
3. Main admin goes through *Pending*: **Accept 1st** → if full, **Accept 2nd** → otherwise **Decline**. Each action emails the guest (untick "email guest" to skip).
4. Groups page: create groups (name + time/meeting point), assign people, then **Email all groups**.
5. On the day: coordinators use **Check-in** on their phone.
6. Afterwards: coordinators or admins enter group and individual results → main admin clicks **Publish results** (emails participants).

## Testing locally
Set `'mail_dry_run' => true` in `config.php` — emails are written to `storage/mail.log` instead of being sent.
