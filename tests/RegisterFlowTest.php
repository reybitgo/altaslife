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
    T::ok(
        preg_match('/id="step-ind-1"\s+style="display:none;"/s', $html) === 1,
        'skip: the Select Package indicator is hidden'
    );
    T::ok(str_contains($html, 'Account Setup'), 'skip: Account Setup is rendered');
    T::ok(str_contains($html, 'const SKIP_SELECT_STEP = true;'), 'skip: JS knows the flow is two-step');
    T::ok(
        str_contains($html, 'new accounts start on the free (active) plan'),
        'skip: the page explains why there is no package step'
    );
    T::ok(
        str_contains($html, 'name="referral_mode" id="referralMode" value="1"'),
        'skip: the pending/free flow is preselected for JS-disabled submits'
    );
});

test('packages available + Code → step 1 is visible and labelled Payment', function (): void {
    $result = render_register_view([
        'packages'             => [['id' => 1, 'name' => 'Starter', 'entry_fee' => 500.00, 'pairing_bonus' => 100.00,
                                    'daily_pair_cap' => 3, 'pairing_enabled' => 1]],
        'packagesDisabled'     => false,
        'skipSelectStep'       => false,
        'isReferralMode'       => false,
        'degradedReferral'     => false,
        'canUseEwallet'        => false,
        'defaultPaymentMethod' => 'code',
        'prefillSponsor'       => '',
        'prefillUpline'        => '',
        'prefillPosition'      => 'left',
    ]);
    $html = $result['html'];

    T::ok($result['problems'] === [], 'code: renders without PHP diagnostics', implode(' | ', $result['problems']));
    T::ok(
        preg_match('/id="step-ind-1"\s+style="display:none;"/s', $html) !== 1,
        'code: step 1 is visible'
    );
    T::ok(str_contains($html, 'id="step-ind-1-label">Payment</div>'), 'code: the step-1 label is Payment');
    T::ok(str_contains($html, 'const SKIP_SELECT_STEP = false;'), 'code: JS keeps the three-step flow');
});

test('packages available + Free → Free is the default and the bar collapses', function (): void {
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

    T::ok($result['problems'] === [], 'free: renders without PHP diagnostics', implode(' | ', $result['problems']));
    T::ok(str_contains($html, 'const DEFAULT_PAYMENT_METHOD = "free";'), 'free: Free is the default method');
    T::ok(
        preg_match('/id="step-ind-1"\s+style="display:none;"/s', $html) === 1,
        'free: the Select Package indicator is hidden'
    );
    T::ok(str_contains($html, 'id="freeLimitNotice"'), 'free: the limited-access notice is rendered');
    T::ok(
        preg_match('/id="pay_free" name="payment_method" value="free" checked/', $html) === 1,
        'free: the Free radio is checked'
    );
});

T::group('Registration binary placement visibility');

test('packages disabled → no binary placement controls are rendered', function (): void {
    $result = render_register_view([
        'packages'         => [],
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

    T::ok($result['problems'] === [], 'disabled: renders without PHP diagnostics', implode(' | ', $result['problems']));
    T::ok(!str_contains($html, 'name="upline_username"'), 'disabled: no Binary Upline Username field');
    T::ok(!str_contains($html, 'name="binary_position"'), 'disabled: no Binary Position radios');
    T::ok(!str_contains($html, 'name="has_binary"'), 'disabled: no Binary Placement toggle');
    T::ok(!str_contains($html, 'id="binarySection"'), 'disabled: no binary section at all');
    T::ok(str_contains($html, 'const BINARY_UI_ENABLED = false;'), 'disabled: JS knows placement UI is off');
});

test('Binary Pairing off in Compensations → no binary placement controls', function (): void {
    db()->prepare(
        "INSERT INTO settings (key_name, value) VALUES ('commission_binary_enabled', '0')
         ON DUPLICATE KEY UPDATE value = VALUES(value)"
    )->execute();
    setting_forget('commission_binary_enabled');

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

    T::ok($result['problems'] === [], 'pairing off: renders without PHP diagnostics', implode(' | ', $result['problems']));
    T::ok(!str_contains($html, 'name="upline_username"'), 'pairing off: no Binary Upline Username field');
    T::ok(!str_contains($html, 'name="binary_position"'), 'pairing off: no Binary Position radios');
    T::ok(str_contains($html, 'const BINARY_UI_ENABLED = false;'), 'pairing off: JS knows placement UI is off');

    db()->prepare(
        "INSERT INTO settings (key_name, value) VALUES ('commission_binary_enabled', '1')
         ON DUPLICATE KEY UPDATE value = VALUES(value)"
    )->execute();
    setting_forget('commission_binary_enabled');
});

test('packages available + Binary Pairing on → placement controls stay', function (): void {
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

    T::ok($result['problems'] === [], 'enabled: renders without PHP diagnostics', implode(' | ', $result['problems']));
    T::ok(str_contains($html, 'name="upline_username"'), 'enabled: Binary Upline Username field is rendered');
    T::ok(str_contains($html, 'name="binary_position"'), 'enabled: Binary Position radios are rendered');
    T::ok(str_contains($html, 'const BINARY_UI_ENABLED = true;'), 'enabled: JS knows placement UI is on');
});

T::group('Registration placement rule');

test('placementRequired accepts only real referral links and paid pairing packages', function (): void {
    // placementRequired($uiEnabled, $referralMode, $forceReferralAnchor, $hasUpline, $packageId, $pairing)
    T::eq(false, AuthController::placementRequired(false, true, true, true, 0, false), 'gate off: never required');
    T::eq(false, AuthController::placementRequired(true, true, false, true, 0, false), 'free page: forged upline is ignored');
    T::eq(true, AuthController::placementRequired(true, true, true, true, 0, false), 'referral link: anchored with an upline');
    T::eq(false, AuthController::placementRequired(true, true, true, false, 0, false), 'referral link: no upline, no placement');
    T::eq(true, AuthController::placementRequired(true, false, false, false, 7, true), 'paid pairing: auto-select may place');
    T::eq(false, AuthController::placementRequired(true, false, false, false, 7, false), 'paid non-pairing: no placement');
    T::eq(false, AuthController::placementRequired(true, false, false, true, 0, true), 'paid without a package: no placement');
    T::eq(true, AuthController::placementRequired(true, false, true, true, 7, true), 'paid pairing with ref marker: still placed');
});

exit(T::summary());
