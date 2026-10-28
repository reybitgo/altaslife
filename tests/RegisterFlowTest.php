<?php

/**
 * @file   tests/RegisterFlowTest.php
 * @brief  Registration skips "Select Package" when there is nothing to select.
 *
 * WHY THIS EXISTS
 *   "Disable Packages" forces free registration on and inactivates every
 *   package. The registration page then still opened on a "Select Package"
 *   step whose only working option was Free. The page (and the genealogy
 *   register modal) now open directly on Account Setup in that state.
 *
 *   The views had no render coverage at all, so this renders the real
 *   views/auth/register.php in both states and fails on any PHP diagnostic.
 *
 * Run:  php tests/run.php RegisterFlow
 */

require_once __DIR__ . '/bootstrap.php';

// The view reads free_registration_enabled from settings (not from the render
// vars). Pin it on for determinism; suites run in their own process and the
// disposable schema is dropped afterwards.
db()->prepare(
    "INSERT INTO settings (key_name, value) VALUES ('free_registration_enabled', '1')
     ON DUPLICATE KEY UPDATE value = VALUES(value)"
)->execute();
setting_forget('free_registration_enabled');

/**
 * Render a view while capturing the HTML and any PHP diagnostic.
 *
 * @return array{html: string, problems: string[]}
 */
function render_register_view(array $vars): array
{
    $problems = [];
    set_error_handler(static function (int $no, string $msg, string $file = '', int $line = 0) use (&$problems): bool {
        $problems[] = $msg . ' at ' . basename($file) . ':' . $line;
        return true;
    });

    Fixtures::logout();
    ob_start();
    try {
        extract($vars, EXTR_SKIP);
        /** @noinspection PhpIncludeInspection */
        require REPO_ROOT . '/views/auth/register.php';
    } catch (Throwable $e) {
        $problems[] = 'THROWN: ' . get_class($e) . ': ' . $e->getMessage();
    } finally {
        $html = (string) ob_get_clean();
        restore_error_handler();
    }

    return ['html' => $html, 'problems' => $problems];
}

T::group('Registration without selectable packages');

test('packages disabled → Account Setup is the first step', function (): void {
    $result = render_register_view([
        'packages'         => [],      // every package forced inactive
        'packagesDisabled' => true,
        'skipSelectStep'   => true,
        'isReferralMode'   => false,
        'degradedReferral' => false,
        'canUseEwallet'    => false,
        'prefillSponsor'   => '',
        'prefillUpline'    => '',
        'prefillPosition'  => 'left',
    ]);
    $html = $result['html'];

    T::ok($result['problems'] === [], 'skip: renders without PHP diagnostics', implode(' | ', $result['problems']));
    T::ok(!str_contains($html, 'Select Package'), 'skip: the Select Package step is gone');
    T::ok(str_contains($html, 'Account Setup'), 'skip: Account Setup is rendered');
    T::ok(str_contains($html, 'const SKIP_SELECT_STEP = true;'), 'skip: JS knows the flow is two-step');
    T::ok(
        str_contains($html, 'new accounts start on the free (pending) plan'),
        'skip: the page explains why there is no package step'
    );
    T::ok(
        str_contains($html, 'name="referral_mode" id="referralMode" value="1"'),
        'skip: the pending/free flow is preselected for JS-disabled submits'
    );
});

test('packages available → Select Package is still rendered', function (): void {
    $result = render_register_view([
        'packages'         => [['id' => 1, 'name' => 'Starter', 'entry_fee' => 500.00, 'pairing_bonus' => 100.00,
                                'daily_pair_cap' => 3, 'pairing_enabled' => 1]],
        'packagesDisabled' => false,
        'skipSelectStep'   => false,
        'isReferralMode'   => false,
        'degradedReferral' => false,
        'canUseEwallet'    => false,
        'prefillSponsor'   => '',
        'prefillUpline'    => '',
        'prefillPosition'  => 'left',
    ]);
    $html = $result['html'];

    T::ok($result['problems'] === [], 'normal: renders without PHP diagnostics', implode(' | ', $result['problems']));
    T::ok(str_contains($html, 'Select Package'), 'normal: the Select Package step is present');
    T::ok(str_contains($html, 'const SKIP_SELECT_STEP = false;'), 'normal: JS keeps the three-step flow');
});

exit(T::summary());
