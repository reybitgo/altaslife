# QA Test: Global Commission Toggles (Compensations Tab)

**Goal:** Verify the new "Compensations" tab in Admin Settings correctly enables/disables commissions globally (and per-type) without breaking registration, tree structure, or the rest of the system. 

**Test Environment:** Local (Laragon) using the live app at `http://localhost/altaslife/`
**Prerequisites:** Admin account logged in, access to MySQL (via phpMyAdmin/HeidiSQL), at least 2-3 test members available or ability to register new ones.
**Time Required:** ~20-30 minutes

> **Important Notes:**
> - This test checks *payouts/commissions*. Binary leg counts/volumes may still update even when toggles are OFF (this is intentional to keep tree structure intact).
> - DFI only runs via cron/manual reset. You'll need to trigger it manually or wait for cron to test.
> - Always note starting balances/commissions before making changes.


## 1) Verify UI is Present
**Objective:** Confirm the new tab appears and is wired correctly.

| Step | Action | Expected Result | Pass/Fail |
|---|---|---|---|
| 1.1 | Login as Admin (`admin` / password) | Redirect to Admin Dashboard | ☐ |
| 1.2 | Go to `http://localhost/altaslife/?page=admin_settings` | Settings page loads (Site Basics tab active) | ☐ |
| 1.3 | Open the settings offcanvas (gear icon in topbar) | Offcanvas slides in from the right | ☐ |
| 1.4 | Look for "Compensations" with 💰 icon | Entry exists between "Payout Methods" and "Change Password" | ☐ |
| 1.5 | Click "Compensations" | Page switches to `#tabPane-compensations`, form visible with card "Global Compensation Controls" | ☐ |


## 2) Verify Default State (All Enabled)
**Objective:** Confirm defaults are correct and existing behavior is unchanged.

| Step | Action | Expected Result | Pass/Fail |
|---|---|---|---|
| 2.1 | On Compensations tab, check toggles | `Master: Enable All Commissions` is **checked**. All 4 per-type toggles (Direct, Indirect/Unilevel, Binary Pairing, DFI) are **checked**. | ☐ |
| 2.2 | Click "Save Compensation Settings" (no changes) | Flash message `Settings saved.` appears, hash remains `#tabPane-compensations` | ☐ |
| 2.3 | Open DB/settings table | `SELECT * FROM settings WHERE key_name LIKE 'commiss%' OR key_name LIKE 'commission_%'` shows all 5 keys with `value='1'` | ☐ |


## 3) Test Master Switch OFF (All Commissions Disabled)
**Objective:** When master is off, NO commissions of any type should be created.

### Setup
- Pick a **sponsor** (must be active and eligible to earn - not `reg_payment_method='standard'`).
- Pick or register a **new recruit** with a package that has `direct_ref_bonus > 0`, binary pairing enabled, and indirect levels set (use your live packages).
- Note sponsor's `ewallet_balance` and count of `commissions` rows: `SELECT COUNT(*) FROM commissions WHERE user_id = <sponsor_id>;`
- Note recruit's `ewallet_balance` if applicable.

| Step | Action | Expected Result | Pass/Fail |
|---|---|---|---|
| 3.1 | Go to `/?page=admin_settings#tabPane-compensations` | Compensations tab open | ☐ |
| 3.2 | **Uncheck** `Master: Enable All Commissions` | Per-type toggles become unchecked/disabled in effect (optional UX). Do not change per-type manually. | ☐ |
| 3.3 | Click "Save Compensation Settings" | Flash `Settings saved.`, master is saved as `0` in settings table | ☐ |
| 3.4 | Register a **new paid member** under the test sponsor (use a paid method: registration code or e-wallet). Complete all steps. | Registration succeeds. Account created as expected. | ☐ |
| 3.5 | Check sponsor's commissions | `SELECT * FROM commissions WHERE source_user_id = <recruit_id> ORDER BY id DESC;` returns **NO new rows** for direct/indirect/binary. | ☐ |
| 3.6 | Check sponsor's e-wallet | `SELECT ewallet_balance, withdrawable_balance FROM users WHERE id = <sponsor_id>;` **unchanged** from before step 3.4 (no commission credits). | ☐ |
| 3.7 | Check binary tree structure | `SELECT left_count, right_count, left_count_paid, right_count_paid, left_pair_volume, right_pair_volume FROM users WHERE id = <sponsor_id>;` Leg counts/volumes may have **increased** (tree updated) but no pairing commissions created. | ☐ |
| 3.8 | Check recruit's details | Recruit is placed correctly in binary tree (if applicable). Placement still works. | ☐ |


## 4) Test Per-Type Toggles (Master ON)
**Objective:** With master ON, disable each type individually and confirm only that type is blocked.

**Setup:** Set master back to ON first.

| Step | Action | Expected Result | Pass/Fail |
|---|---|---|---|
| 4.1 | Set `Master: Enable All Commissions` to **checked**, save. Verify all per-type checked. | Master = `1`, all per-type = `1` | ☐ |
| 4.2 | **Uncheck only `Direct Referral`**, save. | Settings saved. Only direct_ref disabled. | ☐ |
| 4.3 | Register a new paid recruit under sponsor. | No `direct_referral` commission row created. Sponsor e-wallet unchanged from direct bonus. Indirect/binary/DFI still work per package rules. | ☐ |
| 4.4 | Re-enable Direct Referral (check + save). Uncheck **only `Indirect/Unilevel Referral`**, save. | Settings updated. | ☐ |
| 4.5 | Register a new paid recruit under sponsor (ensure sponsor has uplines with indirect levels configured). | No `indirect_referral` rows created for any upline. Direct/binary/DFI unaffected. | ☐ |
| 4.6 | Re-enable Indirect. Uncheck **only `Binary Pairing`**, save. | Settings updated. | ☐ |
| 4.7 | Place a new paid member in binary tree (trigger binary placement). | No pairing commissions created. Leg counts/volumes may still increment, but no `pairing` commission rows or e-wallet credits from pairing. | ☐ |
| 4.8 | Re-enable Binary Pairing. Uncheck **only `Daily Fixed Income (DFI)`**, save. | Settings updated. | ☐ |
| 4.9 | Trigger DFI payout. Options: run `php cron/midnight_reset.php` (if safe locally) OR click "Run Daily Reset Now" in Settings > Daily Cap Reset (with "Also trigger DFI payout now" checked if available). | No `daily_fixed_income` commission rows created. No DFI credits to e-wallet. Cron/logs show DFI skipped/disabled as expected. | ☐ |
| 4.10 | Re-enable DFI. Save with all toggles checked. | All commissions enabled again. | ☐ |


## 5) Test With Packages Disabled (Requested Use Case)
**Objective:** Verify the toggle works in the exact scenario mentioned - when packages are disabled.

| Step | Action | Expected Result | Pass/Fail |
|---|---|---|---|
| 5.1 | Go to `/?page=admin_settings#tabPane-maint` | Maintenance & Security tab open | ☐ |
| 5.2 | Check `Disable Packages`, save settings | Packages become inactive, free registration forced on. Flash `Settings saved.` | ☐ |
| 5.3 | Go to `/?page=admin_settings#tabPane-compensations` | Compensations tab open | ☐ |
| 5.4 | Turn **Master OFF**, save. | All commissions globally disabled. | ☐ |
| 5.5 | Register a new member via free registration (`/?page=register`) - use a referral/sponsor if needed. | Registration succeeds (free/pending or as configured). | ☐ |
| 5.6 | Check for any commission activity | No commissions created for sponsor/uplines. E-wallets unchanged. | ☐ |
| 5.7 | Re-enable packages? *Optional:* Uncheck "Disable Packages" in Maintenance tab and save. Or just re-enable commissions. | Restore to desired state. | ☐ |
| 5.8 | Turn **Master ON**, save all checked. | Commissions enabled again. | ☐ |


## 6) Edge Cases & Regression Checks
**Objective:** Ensure nothing else is broken.

| Step | Action | Expected Result | Pass/Fail |
|---|---|---|---|
| 6.1 | Save from other tabs (Site Basics, Payments, etc.) | Other settings save normally. Compensations settings remain unchanged. | ☐ |
| 6.2 | Refresh Compensations tab after saving from another tab | Toggles still show correct values. | ☐ |
| 6.3 | Check binary placement still occurs when master OFF | New members still placed in binary tree (leg counts update). No payout rows. | ☐ |
| 6.4 | Check direct/indirect still evaluated? | Engines return early with no commissions - no errors, no fatals. | ☐ |
| 6.5 | Check DFI cron doesn't error when disabled | `cron/midnight_reset.php` runs without errors. Returns 'disabled' reason if logged. | ☐ |
| 6.6 | Check admin can still access all pages | Dashboard, Members, Packages, Payouts, etc. all load normally. | ☐ |
| 6.7 | Verify settings table has all keys | `SELECT * FROM settings WHERE key_name IN ('commissions_enabled','commission_direct_ref_enabled','commission_indirect_ref_enabled','commission_binary_enabled','commission_dfi_enabled');` shows 5 rows with correct values. | ☐ |


## 7) Quick SQL Verification (For Reference)
Use these queries during testing to verify quickly:

```sql
-- Check current settings
SELECT key_name, value FROM settings 
WHERE key_name LIKE 'commiss%' OR key_name LIKE 'commission_%' 
ORDER BY key_name;

-- Check recent commissions
SELECT id, user_id, type, amount, source_user_id, description, status, created_at 
FROM commissions 
ORDER BY id DESC 
LIMIT 20;

-- Check sponsor earnings
SELECT id, username, ewallet_balance, withdrawable_balance 
FROM users 
WHERE username IN ('test_sponsor', 'test_recruit');

-- Check binary tree state
SELECT id, username, left_count, right_count, left_count_paid, right_count_paid, 
       left_pair_volume, right_pair_volume, pairs_volume_paid, pairs_volume_flushed 
FROM users 
WHERE username IN ('test_sponsor', 'test_recruit');
```

## Final Sign-Off
- [ ] All UI elements present and working
- [ ] Master switch works (disables all)
- [ ] Each per-type toggle works independently
- [ ] Works with packages disabled
- [ ] Tree structure remains intact when disabled
- [ ] No errors in logs
- [ ] Settings persist correctly

**Tester Name:** __________________  
**Date:** __________________  
**Notes:** __________________
