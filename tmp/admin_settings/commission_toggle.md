# Analysis, Recommendations & Implementation Plan: Global Compensation Toggles

The goal here is to add a new `#tabPane-compensations` tab in `/?page=admin_settings` that lets the admin globally enable/disable **all** commission types (Direct Referral, Indirect/Unilevel Referral, Binary Pairing, Daily Fixed Income). This is meant for edge cases where there are no packages (or packages are disabled) and we want to stop money movement entirely without having to touch every package.

## 1) Current State Assessment

### How commissions are currently gated

| Type                   | Where it runs                                     | Current gating                                                                                                                                                                                                                                             |
| ---------------------- | ------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Binary Pairing**     | `core/Commission.php::processBinaryPlacement()`   | Package must have `pairing_bonus > 0` and `COALESCE(p.pairing_enabled,1)=1`. Ancestor must be `active`, not `reg_payment_method='standard'`, and `CapEngine::isActiveForPairs()` must allow. Only paid members (`User::isPaidMember()`) contribute volume. |
| **Direct Referral**    | `core/Commission.php::processDirectReferral()`    | Sponsor/new user must be valid, sponsor not `standard`, new member must be `active` and paid. Package must have `direct_ref_bonus > 0`. Subject to CD split + lifetime cap.                                                                                |
| **Indirect/Unilevel**  | `core/Commission.php::processIndirectReferral()`  | New member must be paid and active. Package must have `Package::hasIndirectReferral()` true and `package_indirect_levels` entries. Walks up sponsor chain, skips `standard`/inactive uplines. Subject to CD split + lifetime cap.                          |
| **Daily Fixed Income** | `core/DailyFixedIncome.php::processDailyPayout()` | Per-package: `p.dfi_enabled = 1`, `p.daily_fixed_income > 0`. Member conditions: `role='member'`, `status='active'`, `cap_status='active'`, `dfi_active=1`, `cd_active=0`, `dfi_days_used < p.daily_fixed_income_days`. Also subject to cap.               |

### Observations relevant to this request

- **No global master switch exists today.** The live app relies almost entirely on _per-package_ toggles/amounts (`pairing_bonus`, `direct_ref_bonus`, `package_indirect_levels`, `daily_fixed_income`, `*_enabled` on packages).
- **`tmp/altas/` has `binary_enabled`, `indirect_referral_enabled`, `dfi_enabled` as global settings** with guard logic in `AdminController`/views, but the _current live_ codebase does not enforce a single global "disable all commissions" flag. We should **not** blindly copy tmp/altas wholesale; we need to add a _minimal, safe_ global override that fits the live architecture.
- **"No packages" scenario is real.** `AuthController::showRegister()` already handles `packages_disabled` + `free_registration_enabled` and forces free/pending registration. If packages are missing/inactive, many of the above package checks already fail naturally, but binary placement still runs on the tree and other flows can still be triggered. A global kill-switch is cleaner and safer for this exact case.
- **Settings UI pattern is established.** `views/admin/settings.php` uses vertical tab panes by `#tabPane-<group>`, forms post to `admin_save_settings` with `group=<name>`, checkboxes go through `$checkboxKeys`, and navigation uses `views/partials/settings_offcanvas.php`.
- **Offcanvas nav needs updating.** `settings_offcanvas.php` defines `$offcanvasTabs` and JS handles hash-based switching. Any new tab must be added there.
- **Saving is group-scoped.** `AdminController::saveSettings()` only loops over keys for the submitted group and only writes those keys. Good for isolation.

## 2) Recommendations

### Setting keys (suggested)

I'd lean towards a **master + per-type** approach. This gives flexibility without ambiguity.

| Key                               | Type               | Default | Notes                                                                                                                                                                                                                      |
| --------------------------------- | ------------------ | ------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `commissions_enabled`             | checkbox (`1`/`0`) | `1`     | **Master switch.** If `0`, ALL commission types are disabled globally regardless of package settings. When enabled, per-type toggles below take effect (with package-level rules still applying where they already exist). |
| `commission_direct_ref_enabled`   | checkbox (`1`/`0`) | `1`     | Global toggle for direct referral bonuses                                                                                                                                                                                  |
| `commission_indirect_ref_enabled` | checkbox (`1`/`0`) | `1`     | Global toggle for indirect/unilevel bonuses                                                                                                                                                                                |
| `commission_binary_enabled`       | checkbox (`1`/`0`) | `1`     | Global toggle for binary pairing bonuses (note: binary _placement/tree structure_ can be debated, but the user says "all the commissions" so pairing payouts should be gated)                                              |
| `commission_dfi_enabled`          | checkbox (`1`/`0`) | `1`     | Global toggle for Daily Fixed Income payouts                                                                                                                                                                               |

**Rationale:**

- "Master switch" covers the "no packages" fire-and-forget case with one click
- Per-type toggles allow turning off just recruitment bonuses but keeping DFI, or vice versa, without re-enabling packages
- Keeps backward compatible: defaults to `1` so existing behavior unchanged
- Avoids overloading existing `binary_enabled`/`indirect_referral_enabled` semantics (those appear as global UI flags elsewhere in tmp/altas but the live engine doesn't consistently use them yet)

### Where to enforce (critical)

Enforcement must happen **as early as possible** in the money engines:

| File                                   | Change                                                                                                                                                                                                                                                                                                                                      |
| -------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `core/Commission.php`                  | Add a small helper like `private static function compEnabled(string $type): bool` that checks `commissions_enabled` first, else the specific key. Short-circuit at top of `processBinaryPlacement()`, `processDirectReferral()`, `processIndirectReferral()`. Also consider `creditPairing()` safety net (in case called from other paths). |
| `core/DailyFixedIncome.php`            | Short-circuit in `processDailyPayout()` (and optionally `getMemberDfiStatus()`) if globally disabled.                                                                                                                                                                                                                                       |
| `models/User.php` / registration flows | _Usually not necessary_, but worth noting: binary placement still updates leg counts/volumes in `processBinaryPlacement()` today. Gating payouts is the money side; tree structure changes are usually kept, just no commissions. I'd **only gate payouts/commissions**, not count increments unless there's a strong business reason.      |

### UI placement

- Add to **Settings offcanvas nav**: `views/partials/settings_offcanvas.php` `$offcanvasTabs` with icon `💸` label `Compensations`. Good grouping (separate from Maintenance/Payments).
- Add **new tab pane** in `views/admin/settings.php`: `id="tabPane-compensations"`. Suggest placing it **after "Payments" or before "E-Wallet Transfers"** (logical: compensation policy before payout mechanics). Or at the end before "System Overview" — either works. I'd put it as its own card group with clear description: _"Globally control commission payouts. When master is off, no direct/indirect/binary/DFI commissions are paid regardless of package configuration."_
- Wire form: `<form method="POST" action="<?= APP_URL ?>/?page=admin_save_settings">` with `<input type="hidden" name="group" value="compensations">`

### Controller changes

In `controllers/AdminController.php::saveSettings()`:

- Add `'compensations' => [...]` to `$groups` array with the 5 keys
- Add them to `$checkboxKeys` so unchecked boxes save as `'0'` (same pattern as `packages_disabled`, `shop_enabled`, etc.)
- No special enforcement needed here like packages_disabled does (we're not forcing other settings, just toggling payouts)
- Keep existing behavior for other groups untouched

### Schema/Defaults

- **`install.sql`**: add INSERTs for defaults (`commissions_enabled`='1', all per-type '1') so fresh installs are correct. `tests/run.php` rebuilds from `install.sql` so this matters.
- **No new tables required** — settings table already exists.
- **Migration file optional** but nice-to-have if you want to backfill existing installs. Not strictly necessary if defaults are read with `setting(..., '1')`.

## 3) Implementation Plan (Step by Step)

### Step 1: Add settings defaults

**File:** `install.sql`
Add near other setting seeds (around where `free_registration_enabled` etc are seeded):

```sql
INSERT IGNORE INTO settings (key_name, value) VALUES
  ('commissions_enabled', '1'),
  ('commission_direct_ref_enabled', '1'),
  ('commission_indirect_ref_enabled', '1'),
  ('commission_binary_enabled', '1'),
  ('commission_dfi_enabled', '1');
```

_(Use `INSERT IGNORE` or just `INSERT` consistent with existing style.)_

### Step 2: Update admin controller

**File:** `controllers/AdminController.php`

1. Extend `$groups` in `saveSettings()`:

```php
$groups = [
  // ...existing...
  'compensations' => [
    'commissions_enabled',
    'commission_direct_ref_enabled',
    'commission_indirect_ref_enabled',
    'commission_binary_enabled',
    'commission_dfi_enabled',
  ],
];
```

2. Extend `$checkboxKeys`:

```php
$checkboxKeys = ['gcash_enabled','maya_enabled','reactivation_ewallet_enabled','reactivation_external_enabled','shop_enabled','packages_disabled','commissions_enabled','commission_direct_ref_enabled','commission_indirect_ref_enabled','commission_binary_enabled','commission_dfi_enabled'];
```

_(Keep existing entries intact.)_

### Step 3: Update settings UI navigation

**File:** `views/partials/settings_offcanvas.php`
Add to `$offcanvasTabs`:

```php
$offcanvasTabs = [
  'basics'        => ['icon' => '🌐', 'label' => 'Site Basics'],
  'maint'         => ['icon' => '🛡️', 'label' => 'Maintenance & Security'],
  'payments'      => ['icon' => '🔄', 'label' => 'Payments'],
  'ewallet'       => ['icon' => '💱', 'label' => 'E-Wallet Transfers'],
  'payouts'       => ['icon' => '💸', 'label' => 'Payout Methods'],
  'compensations' => ['icon' => '💰', 'label' => 'Compensations'],
  'password'      => ['icon' => '🔒', 'label' => 'Change Password'],
  'reset'         => ['icon' => '⏱️', 'label' => 'Daily Cap Reset'],
  'documents'     => ['icon' => '🧾', 'label' => 'Invoices & Documents'],
  'overview'      => ['icon' => 'ℹ️', 'label' => 'System Overview'],
];
```

_(Order is flexible; placing near payouts/finance makes sense.)_

### Step 4: Add tab pane to settings page

**File:** `views/admin/settings.php`
Add a new pane, e.g. after TAB 5 (PAYOUT METHODS) or TAB 4. Suggested structure:

```php
<!-- ════════════════════════════════════════════
 TAB X — COMPENSATIONS
 ════════════════════════════════════════════ -->
<div class="tab-pane fade" id="tabPane-compensations" role="tabpanel">
    <form method="POST" action="<?= APP_URL ?>/?page=admin_save_settings">
        <?= csrf_field() ?>
        <input type="hidden" name="group" value="compensations">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <span style="width:28px;height:28px;background:#f0fdf4;border-radius:.45rem;display:flex;align-items:center;justify-content:center;font-size:.85rem;">💰</span>
                <span class="card-title">Global Compensation Controls</span>
            </div>
            <div class="card-body">
                <div class="alert alert-info py-2 mb-3" style="font-size:.8rem;">
                    <strong>Note:</strong> When <em>Master switch</em> is off, <u>all</u> commissions are disabled globally regardless of package settings. Use this when there are no packages or during testing.
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="commissions_enabled" id="commissionsEnabled" value="1" <?= setting('commissions_enabled','1')==='1'?'checked':'' ?>>
                        <label class="form-check-label fw-semibold" for="commissionsEnabled">🛑 Master: Enable All Commissions</label>
                    </div>
                    <div class="form-text">Turning this OFF disables Direct, Indirect, Binary Pairing, and DFI payouts.</div>
                </div>

                <hr class="my-3">

                <label class="form-label fw-semibold mb-2">Per-Type Toggles</label>
                <div class="mb-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input comp-type" type="checkbox" name="commission_direct_ref_enabled" id="compDirect" value="1" <?= setting('commission_direct_ref_enabled','1')==='1'?'checked':'' ?>>
                        <label class="form-check-label" for="compDirect">👥 Direct Referral</label>
                    </div>
                </div>
                <div class="mb-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input comp-type" type="checkbox" name="commission_indirect_ref_enabled" id="compIndirect" value="1" <?= setting('commission_indirect_ref_enabled','1')==='1'?'checked':'' ?>>
                        <label class="form-check-label" for="compIndirect">🔗 Indirect/Unilevel Referral</label>
                    </div>
                </div>
                <div class="mb-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input comp-type" type="checkbox" name="commission_binary_enabled" id="compBinary" value="1" <?= setting('commission_binary_enabled','1')==='1'?'checked':'' ?>>
                        <label class="form-check-label" for="compBinary">🤝 Binary Pairing</label>
                    </div>
                </div>
                <div class="mb-0">
                    <div class="form-check form-switch">
                        <input class="form-check-input comp-type" type="checkbox" name="commission_dfi_enabled" id="compDfi" value="1" <?= setting('commission_dfi_enabled','1')==='1'?'checked':'' ?>>
                        <label class="form-check-label" for="compDfi">📅 Daily Fixed Income (DFI)</label>
                    </div>
                </div>
            </div>
            <div class="card-footer border-top-0 pt-0">
                <button type="submit" class="btn btn-primary w-100">💾 Save Compensation Settings</button>
            </div>
        </div>
    </form>
</div>
```

**Optional JS (nice UX):** when master is unchecked, gray/disable per-type toggles. When checked, enable them. Easy to add in the existing DOMContentLoaded block in `settings.php`.

### Step 5: Enforce in commission engines

**File:** `core/Commission.php`
Add helper near top of class:

```php
private static function isCompEnabled(string $type): bool
{
    // type: direct|indirect|binary
    if (setting('commissions_enabled', '1') !== '1') {
        return false;
    }
    switch ($type) {
        case 'direct':   return setting('commission_direct_ref_enabled', '1') === '1';
        case 'indirect': return setting('commission_indirect_ref_enabled', '1') === '1';
        case 'binary':   return setting('commission_binary_enabled', '1') === '1';
        default:         return true;
    }
}
```

Gates:

- `processBinaryPlacement()`: right after getting `$newUserIsPaid` context and before walking/settling, or at the point where we would call `creditPairing()`/`recordFlush()`. Safest: if `!self::isCompEnabled('binary')` skip `creditPairing()` and `recordFlush()` but **keep updating leg counts/volumes** (tree integrity). That way structure remains correct if re-enabled later.
- `processDirectReferral()`: add `if (!self::isCompEnabled('direct')) return;` near top after basic checks
- `processIndirectReferral()`: add `if (!self::isCompEnabled('indirect')) return;` near top after basic checks

**File:** `core/DailyFixedIncome.php`
Add check in `processDailyPayout()`:

```php
public static function processDailyPayout(): array
{
    if (setting('commission_dfi_enabled','1') !== '1' || setting('commissions_enabled','1') !== '1') {
        return ['processed'=>0,'paid'=>0.0,'skipped'=>0,'reason'=>'disabled'];
    }
    // ...existing...
}
```

Also consider `getMemberDfiStatus()` returning disabled if globally off (optional but consistent).

**Notes on safety:**

- Keep CD split/cap logic untouched when we _do_ pay. When disabled, we just don't pay/record commissions.
- Don't touch `Ewallet` methods.
- `commissions` table won't get new rows while disabled (matches "no commissions").
- Cron `midnight_reset.php` calls DFI — our early return prevents payouts cleanly.

## 4) Testing Plan

| Scenario                           | Expected                                                                                            |
| ---------------------------------- | --------------------------------------------------------------------------------------------------- |
| **Default (fresh install)**        | All enabled. Behavior identical to current.                                                         |
| **Master OFF**                     | No direct/indirect/binary/DFI commissions created. Leg counts may still update (tree) but no money. |
| **Only Binary OFF**                | Direct/Indirect/DFI can still pay per package rules. Binary pairing gives nothing.                  |
| **Packages disabled + master OFF** | Safe "no money" state as requested. Free registration still works.                                  |
| **Re-enable**                      | Commissions resume normally (no schema changes needed).                                             |
| **Save from different tabs**       | Group-scoped saving doesn't affect compensation settings (and vice versa).                          |
| **Offcanvas navigation**           | New "Compensations" tab appears and jumps correctly to `#tabPane-compensations`.                    |

## 5) Risks & Mitigations

| Risk                                      | Mitigation                                                                                                                                                    |
| ----------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Accidentally disabling and forgetting** | Clear UI copy + alert in tab. Defaults to ON.                                                                                                                 |
| **Breaking existing reports**             | We only skip creating `commissions` rows; existing history unchanged.                                                                                         |
| **Binary tree drift concerns**            | Don't gate leg count increments — only gate payout creation.                                                                                                  |
| **Tests**                                 | `SchemaDriftTest`/others don't assert these new keys exist yet. Adding defaults to `install.sql` is sufficient since `tests/run.php` rebuilds schema from it. |
| **`tmp/altas` confusion**                 | Follow live patterns, don't copy its `comp_plan` group verbatim. Use the new `compensations` group.                                                           |

## Final Recommendation

**Go ahead with this plan.** I'd implement in this order: settings defaults → controller → offcanvas nav → UI tab → engine gates. Keep the enforcement minimal and payout-only. This directly addresses "when there's no packages" while being general enough for future use without coupling to package existence.
