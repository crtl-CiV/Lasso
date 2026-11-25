<?php
// =========================================================
// LASSO — Emailer (Mailjet Send API v3.1, over HTTPS)
// Render's free plan blocks SMTP, so mail goes out through
// Mailjet's web API instead. Keys come from Render env vars:
//   MAILJET_API_KEY, MAILJET_SECRET_KEY,
//   MAIL_FROM_EMAIL (a sender verified in Mailjet), MAIL_FROM_NAME
// To switch providers later, only sendEmail() needs to change.
//
// Sending never throws and never blocks the main action: if an
// email fails, the approval / rejection still goes through and
// the error is written to the PHP error log.
// =========================================================

const APP_URL = 'https://lasso-3tyo.onrender.com';

function mailConfigured(): bool {
    return getenv('MAILJET_API_KEY') && getenv('MAILJET_SECRET_KEY') && getenv('MAIL_FROM_EMAIL');
}

/** Send one email. Returns true when Mailjet accepted it. */
function sendEmail(string $toEmail, string $toName, string $subject, string $html, string $text = ''): bool {
    if (!mailConfigured()) {
        error_log('[emailer] Mailjet env vars are missing; email to ' . $toEmail . ' not sent.');
        return false;
    }
    $payload = json_encode(['Messages' => [[
        'From' => ['Email' => getenv('MAIL_FROM_EMAIL'), 'Name' => getenv('MAIL_FROM_NAME') ?: 'LASSO'],
        'To' => [$toName !== '' ? ['Email' => $toEmail, 'Name' => $toName] : ['Email' => $toEmail]],
        'Subject' => $subject,
        'TextPart' => $text !== '' ? $text : trim(html_entity_decode(strip_tags(str_replace(['<br>', '</p>', '</tr>'], "\n", $html)))),
        'HTMLPart' => $html,
    ]]]);
    $url = getenv('MAILJET_API_URL') ?: 'https://api.mailjet.com/v3.1/send'; // override only used for local testing
    $auth = base64_encode(getenv('MAILJET_API_KEY') . ':' . getenv('MAILJET_SECRET_KEY'));

    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nAuthorization: Basic $auth\r\n",
        'content' => $payload,
        'timeout' => 10,
        'ignore_errors' => true,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $status = (int)$m[1];
    }
    $data = json_decode((string)$body, true);
    $ok = $status === 200 && (($data['Messages'][0]['Status'] ?? '') === 'success');
    if (!$ok) error_log('[emailer] Mailjet send to ' . $toEmail . ' failed (HTTP ' . $status . '): ' . substr((string)$body, 0, 500));
    return $ok;
}

/** Shared look for every LASSO email. $bodyHtml must already be escaped. */
function emailLayout(string $heading, string $bodyHtml, ?string $buttonText = null, ?string $buttonUrl = null): string {
    $btn = $buttonText
        ? '<p style="margin:28px 0 8px;"><a href="' . htmlspecialchars($buttonUrl) . '" style="background:#4f46e5;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;display:inline-block;">' . htmlspecialchars($buttonText) . '</a></p>'
        : '';
    return '<!DOCTYPE html><html><body style="margin:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 12px;"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;padding:32px;">'
        . '<tr><td style="padding-bottom:20px;"><img src="' . APP_URL . '/assets/img/logo-email.png" width="32" height="32" alt="" style="vertical-align:middle;border:0;border-radius:8px;"> <span style="font-size:20px;font-weight:700;color:#4f46e5;vertical-align:middle;">LASSO</span></td></tr>'
        . '<tr><td><h1 style="font-size:20px;margin:0 0 16px;">' . htmlspecialchars($heading) . '</h1>'
        . '<div style="font-size:15px;line-height:1.6;color:#334155;">' . $bodyHtml . '</div>' . $btn . '</td></tr>'
        . '<tr><td style="padding-top:28px;border-top:1px solid #e2e8f0;margin-top:28px;font-size:12px;color:#64748b;">'
        . 'This is an automated message from LASSO (Learning Asset Subscription and Sales Operation). Please do not reply to this email.'
        . '</td></tr></table></td></tr></table></body></html>';
}

function emailEsc(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function fmtEmailDate(string $ymd): string { return date('F j, Y', strtotime($ymd)); }

// ---------------- Ready-made messages ----------------

function emailResetCode(string $to, string $name, string $code): bool {
    $html = emailLayout('Your password reset code',
        '<p>Hi ' . emailEsc($name) . ',</p><p>Use this code to reset your LASSO password:</p>'
        . '<p style="font-size:32px;font-weight:700;letter-spacing:8px;margin:20px 0;color:#0f172a;">' . emailEsc($code) . '</p>'
        . '<p>The code expires in <b>10 minutes</b> and can only be used once.</p>'
        . '<p>If you did not ask to reset your password, you can ignore this email. Your password stays the same.</p>');
    return sendEmail($to, $name, 'Your LASSO password reset code: ' . $code, $html,
        "Hi $name,\n\nYour LASSO password reset code is: $code\n\nIt expires in 10 minutes and can only be used once.\nIf you did not ask to reset your password, ignore this email.");
}

function emailVerifyCode(string $to, string $code): bool {
    $html = emailLayout('Verify your school email',
        '<p>Hi,</p><p>Use this code to verify your email and continue creating your LASSO account:</p>'
        . '<p style="font-size:32px;font-weight:700;letter-spacing:8px;margin:20px 0;color:#0f172a;">' . emailEsc($code) . '</p>'
        . '<p>The code expires in <b>10 minutes</b> and can only be used once.</p>'
        . '<p>If you did not try to register on LASSO, you can ignore this email.</p>');
    return sendEmail($to, '', 'Your LASSO verification code: ' . $code, $html,
        "Your LASSO verification code is: $code\n\nIt expires in 10 minutes and can only be used once.\nIf you did not try to register on LASSO, ignore this email.");
}

function emailAdminCode(string $to, string $name, string $code, string $creator): bool {
    $html = emailLayout('Confirm your new LASSO admin account',
        '<p>Hi ' . emailEsc($name) . ',</p><p>' . ($creator !== '' ? emailEsc($creator) : 'A LASSO administrator') . ' is creating a LASSO <b>administrator account</b> for you. Give them this code to confirm that this email is yours:</p>'
        . '<p style="font-size:32px;font-weight:700;letter-spacing:8px;margin:20px 0;color:#0f172a;">' . emailEsc($code) . '</p>'
        . '<p>The code expires in <b>10 minutes</b> and can only be used once.</p>'
        . '<p>If you were not expecting this, do not share the code. No account is created without it.</p>');
    return sendEmail($to, $name, 'LASSO admin account code: ' . $code, $html,
        "Hi $name,\n\n" . ($creator !== '' ? $creator : 'A LASSO administrator') . " is creating a LASSO administrator account for you.\nGive them this code to confirm your email: $code\n\nIt expires in 10 minutes and can only be used once. If you were not expecting this, do not share the code.");
}

function emailPasswordChanged(string $to, string $name): bool {
    $html = emailLayout('Your password was changed',
        '<p>Hi ' . emailEsc($name) . ',</p><p>The password for your LASSO account was just reset.</p>'
        . '<p>If this was not you, contact the LASSO administrator right away.</p>', 'Log in', APP_URL . '/index.html');
    return sendEmail($to, $name, 'Your LASSO password was changed', $html);
}

/** $items: [['title' => ..., 'expiry_date' => 'Y-m-d'], ...] */
function emailReceiptApproved(string $to, string $name, string $billingCode, string $orNumber, array $items): bool {
    $rows = '';
    foreach ($items as $it) {
        $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;">' . emailEsc($it['title']) . '</td>'
              . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;text-align:right;white-space:nowrap;">until ' . emailEsc(fmtEmailDate($it['expiry_date'])) . '</td></tr>';
    }
    $html = emailLayout('Your receipt was approved',
        '<p>Hi ' . emailEsc($name) . ',</p><p>Your official receipt <b>' . emailEsc($orNumber) . '</b> for billing <b>' . emailEsc($billingCode) . '</b> was approved. You can now open these materials in your Library:</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;margin:12px 0;">' . $rows . '</table>',
        'Open my Library', APP_URL . '/student/library.html');
    return sendEmail($to, $name, 'Receipt approved — your materials are ready', $html);
}

function emailReceiptRejected(string $to, string $name, string $billingCode, string $orNumber, string $reason): bool {
    $html = emailLayout('Your receipt needs another look',
        '<p>Hi ' . emailEsc($name) . ',</p><p>Your official receipt' . ($orNumber !== '' ? ' <b>' . emailEsc($orNumber) . '</b>' : '') . ' for billing <b>' . emailEsc($billingCode) . '</b> was not approved.</p>'
        . '<p><b>Reason:</b> ' . nl2br(emailEsc($reason)) . '</p><p>Please submit a corrected receipt from your Cart page.</p>',
        'Go to my Cart', APP_URL . '/student/cart.html');
    return sendEmail($to, $name, 'Receipt not approved — action needed', $html);
}

function emailYearRequestResult(string $to, string $name, bool $approved, string $year, string $note): bool {
    $body = '<p>Hi ' . emailEsc($name) . ',</p>'
        . ($approved
            ? '<p>Your request to change your year level to <b>' . emailEsc($year) . '</b> was approved. The store now shows the materials for your new year level.</p>'
            : '<p>Your request to change your year level to <b>' . emailEsc($year) . '</b> was not approved.</p>')
        . ($note !== '' ? '<p><b>Note from the administrator:</b> ' . nl2br(emailEsc($note)) . '</p>' : '')
        . ($approved ? '' : '<p>You can send a new request from your Profile page.</p>');
    $html = emailLayout($approved ? 'Year level change approved' : 'Year level change not approved', $body,
        $approved ? 'Browse the store' : 'Go to my Profile', APP_URL . ($approved ? '/student/store.html' : '/student/profile.html'));
    return sendEmail($to, $name, $approved ? 'Year level change approved' : 'Year level change not approved', $html);
}

/** $items: [['title' => ..., 'expiry_date' => 'Y-m-d'], ...] */
function emailExpiryReminder(string $to, string $name, array $items): bool {
    $rows = '';
    foreach ($items as $it) {
        $days = (int)round((strtotime($it['expiry_date']) - strtotime(date('Y-m-d'))) / 86400);
        $when = $days <= 0 ? 'today' : ($days === 1 ? 'tomorrow' : 'in ' . $days . ' days');
        $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;">' . emailEsc($it['title']) . '</td>'
              . '<td style="padding:8px 0;border-bottom:1px solid #e2e8f0;text-align:right;white-space:nowrap;">' . emailEsc(fmtEmailDate($it['expiry_date'])) . ' (' . $when . ')</td></tr>';
    }
    $n = count($items);
    $html = emailLayout($n === 1 ? 'A subscription is about to expire' : $n . ' subscriptions are about to expire',
        '<p>Hi ' . emailEsc($name) . ',</p><p>Your access to the following material' . ($n === 1 ? '' : 's') . ' will end soon:</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;margin:12px 0;">' . $rows . '</table>'
        . '<p>To keep reading, subscribe again from the store before the expiry date.</p>',
        'Open LASSO', APP_URL . '/student/library.html');
    return sendEmail($to, $name, 'Your LASSO subscription expires soon', $html);
}
