<?php
declare(strict_types=1);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

// Find the private backend folder "awh-app" by walking up from here, so the
// same files work in /home/USER/public_html (main domain) and in an addon
// domain folder such as /home/USER/public_html/example.com.
// awh-app must sit OUTSIDE public_html, e.g. /home/USER/awh-app.
(function () {
    for ($dir = dirname(__DIR__); $dir !== dirname($dir); $dir = dirname($dir)) {
        if (is_file($dir . '/awh-app/bootstrap.php')) {
            require $dir . '/awh-app/bootstrap.php';
            return;
        }
        // Local development: the repository keeps it as backend/app (marked).
        if (is_file($dir . '/app/.awh-backend')) {
            require $dir . '/app/bootstrap.php';
            return;
        }
    }
    http_response_code(500);
    exit('Backend folder awh-app not found. Extract awh-app.zip in your home folder.');
})();

header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

start_session();

// Inline line icons (24px grid, stroke = currentColor).
function icon(string $name, int $size = 18): string
{
    $paths = [
        'today' => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M4.6 4.6l1.4 1.4M18 18l1.4 1.4M2.5 12h2M19.5 12h2M4.6 19.4L6 18M18 6l1.4-1.4"/>',
        'bookings' => '<path d="M4 7h16M4 12h16M4 17h10"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'stays' => '<path d="M3 18V8M3 14h18v4M21 14v-2.5a3 3 0 00-3-3h-7v5.5"/><circle cx="7" cy="11" r="1.8"/>',
        'guests' => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c.6-3.2 2.8-5 5.5-5s4.9 1.8 5.5 5M16 5.2a3 3 0 010 5.6M17.5 14.5c1.6.6 2.7 2.2 3 4.5"/>',
        'emails' => '<rect x="3" y="5.5" width="18" height="13" rx="2"/><path d="M3.5 7l8.5 6 8.5-6"/>',
        'settings' => '<path d="M4 7h10M18 7h2M4 17h4M12 17h8"/><circle cx="16" cy="7" r="2"/><circle cx="10" cy="17" r="2"/>',
        'services' => '<path d="M12 3l8 4.5v9L12 21l-8-4.5v-9z"/><path d="M4 7.5l8 4.5 8-4.5M12 12v9"/>',
        'practitioners' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c.8-4 3.6-6 7-6s6.2 2 7 6"/>',
        'users' => '<rect x="4" y="10.5" width="16" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 018 0v3"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/>',
        'bell' => '<path d="M6 16V11a6 6 0 0112 0v5l1.5 2h-15zM10 20.5a2.2 2.2 0 004 0"/>',
        'logout' => '<path d="M14 4h4a2 2 0 012 2v12a2 2 0 01-2 2h-4M10 16l-4-4 4-4M6 12h10"/>',
        'whatsapp' => '<path d="M4 20l1.3-4A8 8 0 1112 20a8 8 0 01-4-1.1z"/><path d="M9 9.5c.3 2.2 2.3 4.2 4.5 4.5l1-1.2 1.8.8-.3 1.6c-3.4.3-7.4-3.7-7.1-7.1l1.6-.3.8 1.8z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
    ];
    return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}

function lotus(int $size = 34): string
{
    return '<svg viewBox="0 0 40 40" width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" aria-hidden="true">'
        . '<path d="M20 30c-3-3-4.5-7-4.5-11S17 11 20 8c3 3 4.5 7 4.5 11S23 27 20 30z"/>'
        . '<path d="M20 30c-4-1-8-4-9.5-8.5-.6-2-.6-3.8-.2-5.5 3 .8 6 3 7.4 6M20 30c4-1 8-4 9.5-8.5.6-2 .6-3.8.2-5.5-3 .8-6 3-7.4 6"/>'
        . '<path d="M20 30c-5 .5-10-.8-13.5-4M20 30c5 .5 10-.8 13.5-4M14 33h12"/></svg>';
}

// Stable pastel colour per name, for initials avatars.
function avatar(string $name): string
{
    $colors = ['#a8c99a', '#8fc2e0', '#e3b867', '#e8a27b', '#c9b2e0', '#9fd3c7', '#e0a8b8'];
    $parts = preg_split('/\s+/', trim($name)) ?: [''];
    $initials = mb_strtoupper(mb_substr($parts[0], 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
    $c = $colors[abs(crc32($name)) % count($colors)];
    return '<span class="av" style="background:' . $c . '">' . e($initials) . '</span>';
}

function admin_header(string $title, ?array $user): void
{
    $current = basename($_SERVER['SCRIPT_NAME']);
    $aliases = ['booking.php' => 'bookings.php'];
    $current = $aliases[$current] ?? $current;
    $pending = $user ? (int) Db::value("SELECT COUNT(*) FROM bookings WHERE status = 'requested'") : 0;
    $failed = $user ? (int) Db::value("SELECT COUNT(*) FROM email_queue WHERE status = 'failed'") : 0;
    $nav = [
        ['index.php', 'Today', 'today', 0],
        ['bookings.php', 'Bookings', 'bookings', $pending],
        ['calendar.php', 'Calendar', 'calendar', 0],
        ['stays.php', 'Stays', 'stays', 0],
        ['guests.php', 'Guests', 'guests', 0],
        ['emails.php', 'Emails', 'emails', $failed],
        null,
        ['services.php', 'Services', 'services', 0],
        ['practitioners.php', 'Practitioners', 'practitioners', 0],
        ['settings.php', 'Settings', 'settings', 0],
    ];
    if ($user && $user['role'] === 'developer') {
        $nav[] = ['users.php', 'Users', 'users', 0];
    }
    $f = flash();
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Wellness House</title>
<link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body>
<?php if ($user): ?>
<aside class="side">
  <a class="brand" href="/admin/"><?= lotus() ?><span><b>Wellness House</b><small>Clinic admin</small></span></a>
  <nav>
    <?php foreach ($nav as $item): ?>
      <?php if ($item === null): ?><span class="sep"></span><?php continue; endif; [$href, $label, $ic, $n] = $item; ?>
      <a href="/admin/<?= $href ?>"<?= $current === $href ? ' class="on"' : '' ?>><?= icon($ic) ?><?= e($label) ?><?php if ($n): ?><span class="badge"><?= $n ?></span><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>
  <?php if ((config('mail')['driver'] ?? '') === 'log'): ?><span class="pill warn" title="Emails are stored under Emails and not really sent">Local test mode</span><?php endif; ?>
  <div class="me">
    <?= avatar($user['name']) ?>
    <span class="who"><b><?= e($user['name']) ?></b><small><?= $user['role'] === 'developer' ? 'Developer' : 'Admin' ?></small></span>
    <a href="/admin/logout.php" title="Log out"><?= icon('logout') ?></a>
  </div>
</aside>
<?php endif; ?>
<main class="<?= $user ? 'main' : 'auth' ?>">
<?php if ($user): ?>
<div class="topbar">
  <form class="search" method="get" action="/admin/guests.php" role="search">
    <?= icon('search', 16) ?><input name="q" placeholder="Find a guest" aria-label="Find a guest">
  </form>
  <a class="bell" href="/admin/bookings.php?view=list&status=requested" title="Requests waiting"><?= icon('bell') ?><?php if ($pending): ?><span class="dot"><?= $pending ?></span><?php endif; ?></a>
</div>
<?php endif; ?>
<?php if ($f): ?><div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endif; ?>
<?php
}

function status_pill(string $status): string
{
    return '<span class="pill st-' . e($status) . '">' . e(Booking::STATUS_LABELS[$status] ?? $status) . '</span>';
}

function wa_link(string $whatsapp, string $text = ''): string
{
    return 'https://wa.me/' . preg_replace('/\D+/', '', $whatsapp) . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

function valid_date(string $s): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s;
}

function admin_footer(): void
{
    echo "</main>\n</body>\n</html>\n";
}
