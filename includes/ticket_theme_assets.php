<?php
if (empty($GLOBALS['includeTicketThemeCss'])) {
    return;
}
$theme = $_SESSION['Theme'] ?? 'primary';
if (!empty($GLOBALS['ticketThemeOverride'])) {
    $theme = $GLOBALS['ticketThemeOverride'];
}
$theme = strtolower((string)$theme);
$safeTheme = htmlspecialchars($theme, ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="/gg_app/pages/ticket/theme_overrides.css">
<script>window.ticketThemeName = '<?= $safeTheme; ?>';</script>
<script src="/gg_app/pages/ticket/theme_helper.js" defer></script>
