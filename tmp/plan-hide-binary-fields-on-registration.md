# Plan — Hide binary fields on registration (and the registration UX cleanup)

**Status:** implemented 2026-10-30 — see §8 Implementation record; two product decisions remain open
**Date:** 2026-10-30
**Source transcript:** `tmp/hide-binary-fields-on-registration.json`
**Surface:** `?page=register` and the "Register New Member" modal in `?page=genealogy`

---

## 1. Origin and state of the source transcript

The JSON is a 151-message OpenCode session export ("Hide binary fields on registration",
model `big-pickle`) with `"outcome": "failed"`. It ended with
`provider.free-limit` (HTTP 429) **immediately after the final edit to the genealogy
modal**, before any test run, syntax check, or browser verification.

The session had four review rounds before implementation:

| Turn | Ask |
|---|---|
| 1 | Remove `Binary Placement`, `Binary Upline Username`, `Binary Position` from `?page=register` when packages are disabled, especially when Binary Pairing is disabled in Compensations |
| 2 | "Select Package" missing even with packages + pairing enabled; do not show it when Free is selected |
| 3 | Five decisions: default method order, hide catalogue from guests, Free collapses to 2 steps, mirror in the genealogy modal, gate placement on the compensation toggles |
| 4 | Free is limited access (especially placement); modal catalogue = all active packages; guest+Code step label = "Payment" |
| 5 | Referral links keep sponsor-anchored placement; modal keeps Free default with a "not placed until activation" notice; logged-in+Code label = "Payment" |
| 6, 7 | "proceed to implementation" (round 7 followed the crash) |

**The contract is the final analysis turn (message 103).** The implementation rounds are
messages 109–148. The transcript is not a spec; this document is the distilled version.

> Security note: the transcript contains a local admin password in plaintext (typed by
> the user for browser verification). Keep the JSON untracked / delete after use; never
> commit it or copy the password into code, docs, or fixtures.

---

## 2. Agreed behaviour contract

### Step-bar modes (register page and modal)

| State | Default method | Indicators | Step-1 label | Catalogue | Placement |
|---|---|---|---|---|---|
| Packages disabled / none active | Standard (free/active) | `Account Setup → Confirm` | hidden | none | none |
| Referral link (`?ref=1`) | free/pending | `Account Setup → Confirm` | hidden | none | sponsor-anchored placement kept **when packages exist** |
| Packages active, free reg ON | **Free** | `Account Setup → Confirm` | hidden | hidden | **none** — "not placed until activation" notice |
| ↳ registrar picks **Code** | Code | `Payment → Account Setup → Confirm` | **Payment** | hidden (code defines package) | per code `pairing_enabled` |
| ↳ registrar picks **E-Wallet** | E-Wallet | `Select Package → Account Setup → Confirm` | **Select Package** | all active packages (logged-in only) | per package `pairing_enabled` |
| Packages active, free reg OFF, logged-in affordable | E-Wallet | 3 steps | Select Package | shown | per package |
| Packages active, free reg OFF, guest / low balance | Code | 3 steps | Payment | hidden | per code |

### Rules encoded above

1. Default: Free if `free_registration_enabled=1` → E-Wallet if the registrar can afford
   the cheapest active package → Code.
2. Guests never see the package catalogue; their step 1 is always "Payment".
3. Logged-in + Code → step 1 is "Payment" (the code determines the package).
4. Free always collapses the bar to 2 steps, on both surfaces.
5. Binary placement UI requires, in order: packages available →
   `commissions_enabled=1` → `commission_binary_enabled=1` → selected package/code
   `pairing_enabled=1`.
6. Free is never placed at registration. Only a real referral link (`?ref=1`, which posts
   `ref_link=1`) anchors a free signup under the sponsor's next slot. Everything else is
   placed at activation (Auto or Manual).
7. The genealogy modal uses **all active packages**, not the pairing-enabled subset.
8. The modal mirrors the defaults, the 2-step collapse, the labels, and the Free notice.

### Panel → indicator mapping

| Panel | standard / referral | free | paid |
|---|---|---|---|
| 1 | n/a | indicator 2 (shown **1**) | indicator 1 (**1**) |
| 2 | indicator 2 (**1**) | indicator 2 (**1**) | indicator 2 (**2**) |
| 3 | indicator 3 (**2**) | indicator 3 (**2**) | indicator 3 (**3**) |

`$twoStepFlow` means "panel 1 hidden" only. Free mode keeps panel 1 (the payment
chooser) visible and hides only the indicator.

---

## 3. What is already implemented (as of 2026-10-30, uncommitted working tree)

Files touched by this feature: `controllers/AuthController.php`,
`controllers/MemberController.php`, `views/auth/register.php`,
`views/member/genealogy.php`, `tests/RegisterFlowTest.php`.

### `controllers/AuthController.php`

- `$defaultPaymentMethod` computed in `showRegister()` (~line 175):
  `free → ewallet → code`.
- `binaryPlacementUiEnabled()` (~line 267): packages available +
  `packages_disabled` off + `commissions_enabled` on + `commission_binary_enabled` on.
- `placementRequired()` (~line 293): public static, pure; returns false when the UI was
  not rendered, so forged `has_binary`/upline fields are ignored. Referral mode requires
  `ref_link=1` + a submitted upline; paid requires `packageId > 0 && pairingEnabled`.
- `doRegister()` uses it (~line 466). Server-side placement rule is final.

### `views/auth/register.php`

- `$binaryUiEnabled` fallback (~line 30), `$freeMode` (~line 38), `$stepOneLabel`
  (~line 41).
- Step bar renders all three indicators and hides / renumbers per mode (~line 116);
  `syncStepBar()` (~line 762).
- `#freeLimitNotice` (~line 193), shown by `syncStepBar()` for logged-in free mode.
- `#hasBinaryRow` (~line 353) rendered only when the gate is on; `#binarySection`
  (~line 379) wrapped in `$binaryUiEnabled`.
- Review rows Upline/Position and the "position cannot change" warning are gated by
  `$binaryUiEnabled` (~line 456).
- JS: `BINARY_UI_ENABLED`, `DEFAULT_PAYMENT_METHOD` (~line 522),
  `applyBinaryState()` (~line 624) — Free branch never places and clears the controls;
  `stepMode()` / `panelToIndicator()` / `stepOneLabel()` (~line 748);
  referral-mode init respects `REFERRAL_DEFERS`.

### `controllers/MemberController.php`

- `genealogy()` computes `$canUseEwallet` for the modal (~line 289), mirroring the
  register page.
- The modal catalogue was switched from `$binaryPackages` to all active `$packages`.

### `views/member/genealogy.php`

- Modal defaults: `$rmDefaultMethod`, `$rmFreeMode`, `$rmStepOneLabel` (~line 995).
- `RM_DEFAULT_METHOD` constant (~line 1023), `RM_FREE_ENABLED`,
  `RM_SKIP_SELECT_STEP`.
- Step bar (~line 1637), payment radios with `rm_pay_*` ids (~line 1666),
  `#rm_freeLimitNotice` (~line 1672).
- `rmApplyBinary()` free branch never places (~line 1130); `rmStepMode()` /
  `rmPanelToIndicator()` / `rmSyncStepBar()` (~line 1257).
- `rmOpen` resets to the default method and syncs sections; the radio change handler
  updates `rm_referralMode` and calls `rmSyncStepBar()` + `rmApplyBinary()`.
- Single-package hidden input now carries `data-pairing`; multi-package options already
  did.

### Tests

- `tests/RegisterFlowTest.php` gained binary-placement visibility cases:
  packages disabled → no controls; Binary Pairing off → no controls; packages + pairing
  on → controls stay.

### Verification actually run during this analysis

| Command | Result |
|---|---|
| `php -l` on the four touched PHP files | clean |
| `php tests/run.php TemplateLint` | **pass**, 59 assertions |
| `php tests/run.php RegisterFlow` | **fail**, 1 of 23: `FAIL skip: the Select Package step is gone` |

---

## 4. Outstanding gaps

### F1 — The modal never got the compensation gate (functional)

`#rm_binarySection` (`genealogy.php` ~line 1722) is rendered unconditionally; the toggle
is gated only by `$pairingEnabled && free_registration_enabled` (~line 1723). There is no
`RM_BINARY_UI_ENABLED` constant and `rmApplyBinary()` has no early return.

Concrete effect with the settings recorded in the transcript
(`commissions_enabled=0`, `commission_binary_enabled=1`, Starter pairing on): the register
page hides placement, but an admin who opens the genealogy modal, picks E-Wallet and
Starter, gets placement controls. Same defect class as the original report, second
surface.

### F2 — `RegisterFlowTest` is red on a stale assertion (test bug)

`tests/RegisterFlowTest.php:76` asserts `!str_contains($html, 'Select Package')`. Round 2
added the literal `'Select Package'` to the JS `stepOneLabel()` function, so the string is
always in the HTML, even when the indicator is hidden. The render is correct; the
assertion expresses the old contract. The companion test "Select Package is still
rendered" passes only because of that same JS literal and therefore asserts nothing
meaningful.

### F3 — Coverage gaps

- No assertion for the Free default (`DEFAULT_PAYMENT_METHOD`), the collapsed bar with
  packages present, or the free notice.
- No test for the `placementRequired()` matrix (free page vs. `ref_link` vs. paid pairing
  vs. paid non-pairing vs. gate off).
- No modal render coverage at all.

### F4 — Minor inconsistencies

- `views/auth/register.php:25` fallback for `$skipSelectStep` omits `$packagesDisabled`
  (the controller supplies the value, so only direct view renders differ).
- `freeLimitNotice` is suppressed for guests by `IS_LOGGED_IN` in `syncStepBar()`, even
  though guests are the most likely to choose Free.
- The modal renders its review Upline/Position rows unconditionally; JS hides them,
  unlike the register page's server-side gate.
- The modal keeps a dead hidden `has_binary=1` fallback in the non-toggle branch.

### F5 — Verification never happened

The transcript's "verified" claims and screenshots belong to round 1 (binary fields
hidden), not round 2 (defaults, step bar, modal). The modal has never been exercised in a
browser.

### F6 — Consequence of the master gate (design edge case)

A paid pairing registration made while `commissions_enabled=0` is created active but
unplaced. `activate()` only places pending users; the upgrade flow offers upline selection
only when moving from non-pairing to pairing. Such members have no UI path to placement.
The contract explicitly chose `commissions_enabled` first, so the plan keeps it, but this
is a conscious trade-off.

---

## 5. Implementation plan

### Step 1 — one source of truth for the gate (recommended)

Add to `core/helpers.php` (already required by `index.php` and autoloaded by the test
harness, so no new `require_once` and no `TemplateLint` change):

```php
function binary_placement_ui_enabled(array $packages): bool
```

It encodes: packages non-empty, `packages_disabled` off, `commissions_enabled` on,
`commission_binary_enabled` on. Replace the body of
`AuthController::binaryPlacementUiEnabled()` and the view fallback with it.

Least-code alternative: duplicate the four-condition expression in
`MemberController::genealogy()`. Not recommended — that makes three copies.

### Step 2 — finish the modal (the only functional code left)

1. `MemberController::genealogy()`: compute `$binaryUiEnabled` via the shared gate and
   pass it to the view.
2. `views/member/genealogy.php`:
   - wrap `#rm_binarySection` and the two review rows in `$binaryUiEnabled`;
   - remove the hidden `has_binary` fallback;
   - add `const RM_BINARY_UI_ENABLED = ...`;
   - make `rmApplyBinary()` return early (and hide the section) when false.
3. Keep `$pairingEnabled` solely for `REGISTRAR_HAS_BINARY` — it means "registrar has a
   binary plan", not "Binary Pairing compensation is enabled".

### Step 3 — tests

1. Rewrite the two step-bar assertions:
   - skip mode: `id="step-ind-1"` carries `display:none`;
   - normal mode: the indicator is visible and, for a guest, its label is `Payment`.
2. Add a free-mode render case (packages present, free enabled) asserting:
   `const DEFAULT_PAYMENT_METHOD = "free";`, indicator 1 hidden,
   `freeLimitNotice` present, `pay_free` checked.
3. Add a `placementRequired()` matrix test (pure, fast):
   - gate off → false, even with upline + pairing;
   - referral mode without `ref_link` → false (forged free placement ignored);
   - referral mode with `ref_link` + upline → true;
   - paid package + pairing → true;
   - paid package without pairing → false.
4. Modal render test is optional/heavier. Only add it if the modal block is extracted or
   rendered with a full var set; otherwise rely on the browser pass.

### Step 4 — verification

Commands, in order:

```
php -l controllers/AuthController.php controllers/MemberController.php \
       views/auth/register.php views/member/genealogy.php
php tests/run.php RegisterFlow
php tests/run.php TemplateLint
php tests/run.php            # full suite once, allow >2 minutes
```

Browser matrix:

| Settings | Surface | Expect |
|---|---|---|
| packages off | register, guest + admin | 2 steps, no placement, free/standard notice |
| packages on + free on | register guest | 2 steps, Free checked, no catalogue, no placement |
| packages on + free on | register admin | same; catalogue appears only on E-Wallet |
| packages on + free off | register guest | 3 steps, label Payment, hidden `payment_method=code` |
| packages on + free off | register admin | 3 steps, E-Wallet default if affordable |
| `commission_binary_enabled=0` | register + modal | no placement anywhere |
| `commissions_enabled=0` | register + modal | no placement anywhere |
| paid pairing registration | register + modal | placement required; review rows show |
| `?ref=1` | register guest | 2 steps, prefilled sponsor/upline, POST places under the sponsor slot |
| `?ref=1` + packages off | register guest | no placement (see open question 2) |

Also check the console for JS errors in every state, and the modal opened from a specific
binary slot (Free default + notice).

### Step 5 — hygiene

- No schema or migration changes are needed.
- Do not commit the transcript JSON (plaintext local admin password).
- The other modified files in `git status` (`views/admin/settings.php`,
  `views/partials/settings_offcanvas.php`, `controllers/AdminController.php`,
  `assets/css/auth.css`, `core/Commission.php`, `core/DailyFixedIncome.php`,
  `install.sql`, `tmp/admin_*`) belong to earlier tasks. Do not stage them with this
  change.

---

## 6. Open decisions

1. **Guest-free notice:** show "not placed until activation" to guests too, or keep the
   existing guest "no payment required now" alert only? Recommendation: show it whenever
   Free is selected.
2. **Referral link with packages disabled:** current code checks the binary gate first, so
   `?ref=1` does not anchor when no package is active. The transcript's table says
   "sponsor-anchored placement kept" but also gates placement on an active package.
   Recommendation: keep the package requirement (it matches the original request); change
   only if referral links must anchor during pre-package mode.

---

## 7. What NOT to implement from the transcript

_(See §8 for what was completed; the items below remain non-goals.)_

- The local admin password typed during verification — it is a credential, not code.
  Do not hardcode it anywhere; `install.sql` keeps the documented `admin` / `Admin@1234`
  seed.
- Throwaway probe scripts written to the OS temp directory during the session.
- Screenshots / preview artifacts (ephemeral).
- Any edits to `AdminController` / settings admin pages: those changes pre-date this
  feature and come from the commission-toggle work. They are a dependency (the `setting()`
  keys), not part of this change.

---

## 8. Implementation record (2026-10-30)

### Changes landed

- `core/helpers.php`: new `binary_placement_ui_enabled(array $packages): bool` — the
  single source of truth for the compensation gate.
- `controllers/AuthController.php`: `showRegister()` and `doRegister()` call the helper;
  the private duplicate `binaryPlacementUiEnabled()` was removed. The rest of the
  placement contract (`$defaultPaymentMethod`, `placementRequired()`) was already in the
  working tree from the transcript round and is unchanged.
- `controllers/MemberController.php`: `genealogy()` computes `$binaryUiEnabled` with the
  same helper and passes it to the view.
- `views/member/genealogy.php`: `$rmBinaryUiEnabled` + `RM_BINARY_UI_ENABLED` added;
  `#rm_binarySection`, the review Upline/Position rows, and the "position cannot change"
  warning are now server-gated; the dead hidden `has_binary=1` fallback was removed;
  `rmApplyBinary()` early-returns and clears required flags when the gate is off.
- `views/auth/register.php`: the `$binaryUiEnabled` fallback now uses the helper; the
  `$skipSelectStep` fallback also honours `$packagesDisabled` (F4).
- `tests/RegisterFlowTest.php`: the stale `Select Package` substring assertion now checks
  the indicator's visibility; added a Code-mode label test, a Free-mode default/collapse
  test, and an eight-case `placementRequired()` matrix.

### Gaps closed

| Gap | Status |
|---|---|
| F1 modal compensation gate | closed |
| F2 stale `RegisterFlow` assertion | closed |
| F3 default/free/placement-rule coverage | closed (modal render test still optional) |
| F4 `$skipSelectStep` fallback | closed; guest-notice item intentionally left to the open decision |
| F5 verification | register + modal exercised live (gate on); gate-off states covered by tests |
| F6 paid-while-master-off edge case | unchanged by design; still a decision, not a defect fix |

### Verification run

| Check | Result |
|---|---|
| `php -l` on helpers, both controllers, both views, the test file | clean |
| `php tests/run.php RegisterFlow` | pass, 37 assertions |
| `php tests/run.php TemplateLint` | pass, 59 assertions |
| `php tests/run.php` (full) | pass, 6 suites / 681 assertions |

Live check (local app, settings all on, admin session): the genealogy modal opens in Free
mode with the indicator collapsed to 1/2, the "not placed until activation" notice
visible, and no placement section or required position. Switching to E-Wallet restores the
three-step bar, label "Select Package", the catalogue, and a required placement. Switching
back to Free hides and un-requires it again. No console errors.

### Still open

- The two product decisions in §6 (guest free notice, referral links with packages
  disabled) are unchanged and still need a call.
- No exhaustive browser sweep of every toggle combination was run; the gate-off paths are
  covered by the render tests, and the live modal was checked with the gate on.
