<?php
declare(strict_types=1);

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Send one branded email. $paragraphs are already-escaped HTML snippets.
 * Returns true on success; every attempt is written to mail_log.
 */
function send_mail(string $to, string $subject, array $paragraphs, ?array $button = null): bool
{
    $html = mail_template($subject, $paragraphs, $button);
    $text = mail_plaintext($paragraphs, $button);
    $ok = false;
    $err = null;

    if (cfg('mail_dry_run')) {
        @file_put_contents(APP_ROOT . '/storage/mail.log',
            '[' . date('c') . "] TO: $to\nSUBJECT: $subject\n$text\n----\n", FILE_APPEND);
        $ok = true;
    } else {
        try {
            $m = new PHPMailer(true);
            $m->isSMTP();
            $dbg = '';
            $m->SMTPDebug = 2;
            $m->Debugoutput = function ($str) use (&$dbg) {
                // keep the conversation, never the AUTH payload
                if (stripos($str, 'AUTH') === false && !preg_match('/^CLIENT -> SERVER: [A-Za-z0-9+\/=]{8,}\s*$/', trim($str))) $dbg .= trim($str) . "\n";
            };
            $m->Timeout = 20;
            $m->Host = cfg('smtp.host');
            $m->Port = (int)cfg('smtp.port', 465);
            $m->SMTPAuth = true;
            $m->Username = cfg('smtp.user');
            $m->Password = cfg('smtp.pass');
            $m->SMTPSecure = cfg('smtp.secure', 'ssl') === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
            $m->CharSet = 'UTF-8';
            $m->setFrom(cfg('smtp.from'), cfg('smtp.from_name', 'HLTV Awards Mingle'));
            $m->addAddress($to);
            $m->Subject = $subject;
            $m->isHTML(true);
            $m->Body = $html;
            $m->AltBody = $text;
            $m->send();
            $ok = true;
        } catch (Throwable $ex) {
            $err = $ex->getMessage() . (isset($dbg) && $dbg !== '' ? "\n--- SMTP log ---\n" . mb_substr($dbg, -1500) : '');
        }
    }

    try {
        q('INSERT INTO hm_mail_log (to_email, subject, ok, error) VALUES (?, ?, ?, ?)', [$to, $subject, $ok ? 1 : 0, $err]);
    } catch (Throwable $ignored) {}

    return $ok;
}

function mail_template(string $title, array $paragraphs, ?array $button): string
{
    $logo = url('assets/logo-email.png');
    $body = '';
    foreach ($paragraphs as $p) {
        $body .= '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#e6e2da">' . $p . '</p>';
    }
    if ($button) {
        $body .= '<p style="margin:24px 0"><a href="' . e($button[1]) . '" style="display:inline-block;background:#E1C693;color:#181818;'
            . 'text-decoration:none;font-weight:700;padding:12px 22px;font-size:14px;letter-spacing:2px;text-transform:uppercase">' . e($button[0]) . '</a></p>';
    }
    return '<!doctype html><html><body style="margin:0;background:#111111;font-family:Arial,Helvetica,sans-serif">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#111111;padding:32px 12px"><tr><td align="center">'
        . '<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#181818;border:1px solid #3a3326">'
        . '<tr><td style="padding:24px 28px;border-bottom:2px solid #E1C693">'
        . '<img src="' . e($logo) . '" alt="HLTV Awards" width="144" height="50" style="display:block;width:144px;height:50px;border:0"></td></tr>'
        . '<tr><td style="padding:28px">'
        . '<h1 style="margin:0 0 20px;font-size:20px;letter-spacing:1px;text-transform:uppercase;color:#E1C693;font-family:Orbitron,Arial,sans-serif">' . e($title) . '</h1>' . $body
        . '</td></tr>'
        . '<tr><td style="padding:18px 28px;background:#121212;font-size:12px;line-height:1.5;color:#8c877e">'
        . 'This is an automated message — please do not reply to this email, replies are not monitored.<br>'
        . 'HLTV Awards Mingle · with help from <a href="https://relog.gg" style="color:#E1C693;text-decoration:none">Relog Media</a></td></tr>'
        . '</table></td></tr></table></body></html>';
}

function mail_plaintext(array $paragraphs, ?array $button): string
{
    $t = '';
    foreach ($paragraphs as $p) {
        $t .= html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $p)), ENT_QUOTES, 'UTF-8') . "\n\n";
    }
    if ($button) $t .= $button[0] . ': ' . $button[1] . "\n\n";
    return $t . "This is an automated message — please do not reply to this email.\n";
}

/* ---------- Message types ----------
   Each builder returns [subject, paragraphs (HTML-escaped), button|null].
   $ctx can override the activity / group (used by the admin email preview). */

const MAIL_TYPES = [
    'accepted' => ['label' => 'Spot confirmed',     'when' => 'When an admin presses Accept 1st / Accept 2nd / Accept into another activity with "Email guest" ticked — or later, with "Send decision emails" on the Sign-ups page.'],
    'declined' => ['label' => 'No spot available',  'when' => 'When an admin presses Decline with "Email guest" ticked — or later, with "Send decision emails" on the Sign-ups page.'],
    'group'    => ['label' => 'Group assignment',   'when' => 'Only when an admin presses "Email all groups" or "Email group" on the Groups page.'],
    'results'  => ['label' => 'Results published',  'when' => 'When an admin presses "Publish results" for an activity with "email participants" ticked.'],
];

function mail_build(string $type, array $s, array $ctx = []): array
{
    $hi = 'Hi ' . e($s['first_name']) . ',';
    $act = $ctx['activity'] ?? (!empty($s['activity_id']) ? activity((int)$s['activity_id']) : null);
    switch ($type) {
        case 'received':
            $prim = $ctx['primary'] ?? (!empty($s['primary_activity_id']) ? activity((int)$s['primary_activity_id']) : null);
            $sec  = $ctx['secondary'] ?? (!empty($s['secondary_activity_id']) ? activity((int)$s['secondary_activity_id']) : null);
            return ['We received your sign-up', [$hi,
                'Thanks for signing up for HLTV Awards Mingle activities. We have received your choices:',
                '<strong>1st choice:</strong> ' . e($prim['name'] ?? '—') . '<br><strong>2nd choice:</strong> ' . e($sec['name'] ?? '—'),
                'Spots are limited, so every sign-up is reviewed by the organisers. You will receive another email letting you know whether you have secured a spot.'],
                ['View my status', status_link($s)]];
        case 'accepted':
            $p = [$hi, 'Good news — your spot is confirmed for <strong>' . e($act['name'] ?? '') . '</strong>.'];
            if (!empty($act['details'])) $p[] = nl2br(e($act['details']));
            $p[] = 'We will let you know which group you are in closer to the activity. You can always check your status at the link below.';
            return ['You are in: ' . ($act['name'] ?? 'activity confirmed'), $p, ['View my status', status_link($s)]];
        case 'declined':
            return ['About your HLTV Awards Mingle sign-up', [$hi,
                'Thank you for your interest in HLTV Awards Mingle activities. Unfortunately, both of your chosen activities are fully booked and we are not able to offer you a spot this time.',
                'We hope to see you at the HLTV Awards!'], null];
        case 'group':
            $g = $ctx['group'] ?? (!empty($s['group_id']) ? q('SELECT * FROM hm_groups WHERE id = ?', [$s['group_id']])->fetch() : null);
            $p = [$hi, 'Your group for <strong>' . e($act['name'] ?? '') . '</strong> is ready: <strong>' . e($g['name'] ?? '') . '</strong>.'];
            if (!empty($g['info'])) $p[] = nl2br(e($g['info']));
            if (!empty($act['details'])) $p[] = nl2br(e($act['details']));
            $p[] = 'Your status page shows your group and teammates, and will show results after the activity.';
            return ['Your group: ' . ($g['name'] ?? '') . ' — ' . ($act['name'] ?? ''), $p, ['View my group', status_link($s)]];
        case 'results':
            return ['Results are in: ' . ($act['name'] ?? ''), [$hi,
                'Results for <strong>' . e($act['name'] ?? '') . '</strong> are now live. Thanks for taking part!'],
                ['See results', status_link($s)]];
    }
    throw new InvalidArgumentException('Unknown mail type ' . $type);
}

function mail_type(string $type, array $s, array $ctx = [], ?string $to = null): bool
{
    [$subject, $p, $button] = mail_build($type, $s, $ctx);
    return send_mail($to ?? $s['email'], $subject, $p, $button);
}

/** Records which decision (accepted/declined) the guest was last emailed about. */
function mark_notified(array $s, string $status): void
{
    q('UPDATE hm_signups SET notified = ? WHERE id = ?', [$status, $s['id']]);
}

function mail_signup_received(array $s): bool { return mail_type('received', $s); }
function mail_accepted(array $s): bool { $ok = mail_type('accepted', $s); if ($ok) mark_notified($s, 'accepted'); return $ok; }
function mail_declined(array $s): bool { $ok = mail_type('declined', $s); if ($ok) mark_notified($s, 'declined'); return $ok; }
function mail_group(array $s): bool { return mail_type('group', $s); }
function mail_results(array $s): bool { return mail_type('results', $s); }
