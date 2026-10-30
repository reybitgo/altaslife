# QA Test Guide — Standard Registration (no "Select Package" step)

**Purpose:** Prove that when packages are disabled, registration skips the
"Select Package" step and creates an **active member** (Standard account) instead of a free/pending member — and that the normal
3-step flow comes back when packages are available again.

**Audience:** Complete beginner. No prior knowledge of this app is needed.
Follow the tests in order and tick each box.

**Estimated time:** 20–30 minutes

**Environment:** Local development copy only

- Site: `http://localhost/altaslife/`
- Admin login (default from `install.sql`): `admin` / `Admin@1234`
  (if it was changed, use the credentials your developer gave you)

> **Never run this guide on the live/production site.**
> It changes settings and creates real test accounts.

---

## The short version — what you are proving

There are two different registration screens:

| State of the site | What the registration screen must look like |
| --- | --- |
| Packages are available (normal) | **3 steps:** 1 Select Package → 2 Account Setup → 3 Confirm |
| Packages are disabled | **2 steps:** 1 Account Setup → 2 Confirm |

When packages are disabled, the page must **not** ask the member to choose a
package, and it must **not** offer Free / Code / E-Wallet buttons. The account
must be created as an **active Standard** account (no package, no payment, `reg_payment_method = 'standard'`). Standard accounts have **no earnings**, **no capping**, and **cannot be activated or upgraded**.

The same 2-step behaviour must appear in:

1. `?page=register` for a visitor who is **not** logged in (guest).
2. `?page=register` for an **admin/member who is logged in**.
3. The **"Register New Member" window** on the Genealogy page.

There is also a server-side safety net: even if someone sends a payment method
from an old/forged page, the server must still create a free/pending account —
never charge an e-wallet and never consume a registration code.

---

## 1. Words used in this guide

| Word | What it means here |
| --- | --- |
| **Package** | A paid entry plan (e.g. Starter). Picking one normally charges a fee or consumes a code. |
| **Free / pending** | An account created without a package. It exists but cannot earn until it is activated later. |
| **Step bar** | The row of numbered circles at the top of the registration card (1, 2, 3 …). |
| **Flash message** | The coloured success/error bar that appears after an action (green = success, red = error). |
| **Genealogy** | The page that draws the member network as a tree (`?page=genealogy`). |
| **Incognito / private window** | A browser window that does not share the login session. Used so you can test as a visitor while staying logged in as admin elsewhere. On Windows: `Ctrl + Shift + N` (Chrome/Edge) or `Ctrl + Shift + P` (Firefox). |
| **DevTools / Console** | The browser's built-in developer panel (`F12`). Only needed for the optional Test 7. |

---

## 2. Before you start

- [ ] The local site opens at `http://localhost/altaslife/`
- [ ] You can log in as admin
- [ ] **Window A** is a normal browser window, logged in as **admin**
- [ ] **Window B** is an **Incognito/private** window, **not logged in**

### 2.1 Record the starting state (important for restoring later)

1. In Window A go to `http://localhost/altaslife/?page=admin_packages`
2. Look at the **Status** column. Write down every package whose badge says
   **● Active**:

   ```text
   Active packages before testing: ______________________________
   ```

3. Go to `http://localhost/altaslife/?page=admin_settings#tabPane-maint`
4. Look at the **Disable Packages** checkbox.

   - If it is **unchecked**, you are ready. Continue to Test 1.
   - If it is **checked**, the site is already in the test state. Uncheck it,
     click **Save Settings**, then go back to Packages and activate at least
     one package (Edit → Status = Active → Save). Then continue to Test 1.
     (Without an active package, the 2-step flow is *also* correct, so you
     cannot use it as the "normal" baseline.)

---

## 3. Test 1 — Baseline: the normal 3-step page

**Goal:** prove the website still has a normal "Select Package" step when
packages are available.

1. Open Window B (Incognito) and go to:

   ```text
   http://localhost/altaslife/?page=register
   ```

2. Look at the step bar at the top of the card.

**Expected — you must see THREE steps:**

```text
(1) Select Package   →   (2) Account Setup   →   (3) Confirm
```

- [ ] Step 1 **Select Package** is present and highlighted
- [ ] If Free Registration is ON (the normal setting), the page shows
      **Free** and **Registration Code** choices (guest view)
- [ ] The word **"Packages are currently disabled"** does **not** appear

> If you see only two steps here, stop. Either no package is Active, or the
> "Disable Packages" toggle is still ON. Fix that first (see section 2.1).

**Do not submit this form.** Close this Incognito window when you are done
looking (or keep it; Test 3 will open a fresh one).

---

## 4. Test 2 — Turn ON "Disable Packages"

1. In Window A (admin) go to:

   ```text
   http://localhost/altaslife/?page=admin_settings#tabPane-maint
   ```

2. Tick the **Disable Packages** checkbox.
3. Click the **Save Settings** button **inside the "Maintenance & Security"
   card** (there is one Save button per card — use the one in the same card).
4. Wait for the page to reload.

**Expected:**

- [ ] Green flash: **"Settings saved."**
- [ ] You are still on the **Maintenance & Security** tab
- [ ] The **Free Registration** dropdown now shows
      **"On — Anyone can start free (pending) registration"**
- [ ] The Free Registration dropdown is **greyed out / disabled** and cannot
      be changed
- [ ] Helper text says free registration is forced on while packages are
      disabled
- [ ] Go to `http://localhost/altaslife/?page=admin_settings#tabPane-compensations` and verify: all compensation toggles are **disabled/greyed out**, the warning **"Packages are disabled. Commissions are tied to packages, so all compensation toggles are disabled while packages are inactive."** is shown, and all compensation settings are forced to **off**.

5. Still in Window A, open `http://localhost/altaslife/?page=admin_packages`

**Expected:**

- [ ] A red warning near the top: **"Packages are disabled. All packages are
      inactive and adding new packages is disabled."**
- [ ] The **+ New Package** button is greyed out and does nothing
- [ ] **Every** package shows **○ Inactive**
- [ ] Every **Edit** button is greyed out
- [ ] **View** still works

---

## 5. Test 3 — Guest registration (MAIN TEST)

**Goal:** prove a visitor is taken straight to Account Setup.

1. Open a **fresh** Incognito window (window B) and go to:

   ```text
   http://localhost/altaslife/?page=register
   ```

2. Check the top of the registration card.

**Expected — the step bar now has TWO steps, renumbered:**

```text
(1) Account Setup   →   (2) Confirm
```

- [ ] The step bar has exactly **two** numbered circles
- [ ] The first circle says **Account Setup**, number **1**, and is highlighted
- [ ] The second circle says **Confirm**, number **2**
- [ ] The text **Select Package** is **not** anywhere on the page
- [ ] There are **no** Free / Code / E-Wallet buttons
- [ ] A blue information box says:

  > 📦 Packages are currently disabled — new accounts start as **Standard** (active). Standard accounts have no earnings, no capping, and cannot be activated or upgraded.

3. Check the form fields on this first screen:

- [ ] **Username** field
- [ ] **Password** and **Confirm Password** fields
- [ ] **Sponsor Username** field, already filled with `admin`
- [ ] Within a second the sponsor hint turns green and says
      **"Sponsor is valid."**
- [ ] **No** "Binary Upline" or "Binary Position" fields (a visitor cannot
      place themselves in the binary tree)
- [ ] The only button at the bottom is **Review →**
- [ ] There is **no "← Back"** button (there is no previous step to go back to)

4. Fill in the form:

   - Username: `qafree` + today's month/day, e.g. `qafree1027`
     (if it says "Username is taken", add a letter, e.g. `qafree1027b`)
   - Password: `QaTest1234`
   - Confirm Password: `QaTest1234`

   Wait until the username hint turns green and says
   **"Username is available."**

5. Click **Review →**.

**Expected on the review screen (step 2 of 2):**

- [ ] The header says **📋 Registration Summary**
- [ ] A row **Account Type** shows **Standard**
- [ ] There is **no** row labelled **Payment**
- [ ] There is **no** row labelled **Code**
- [ ] There is **no** row labelled **Package**
- [ ] A **Username** row shows the username you typed, prefixed with `@`
- [ ] A **Sponsor** row shows `@admin`
- [ ] No Upline / Position rows (because this visitor is not placed in the tree)
- [ ] Buttons are **← Back** and **✓ Complete Registration**
- [ ] The step bar now highlights **2 Confirm**

6. Click **✓ Complete Registration**.

**Expected:**

- [ ] You land on the member **Dashboard**
- [ ] Green flash:
      **"Welcome! Your Standard account has been created."**
- [ ] You are **not** asked for a registration code
- [ ] You are **not** sent back to a package step
- [ ] No red error message appears
- [ ] Sidebar/footer shows **Standard** as the account label (not a package name)

> Leave this Incognito window open only if you need to look at the new member's
> dashboard. You can close it now; the account stays in the database.

---

## 6. Test 4 — Admin confirms the new member is free/pending

1. In Window A (admin) go to:

   ```text
   http://localhost/altaslife/?page=admin_users
   ```

2. In the search box type the username you created in Test 3
   (e.g. `qafree1027`), then click **Search**.

**Expected in the results row:**

- [ ] The member appears
- [ ] **Package** column shows **Standard**
- [ ] **Balance** shows **₱0.00**
- [ ] **Status** badge says **Active**
- [ ] No error flash

3. Optional: click the **@username** link to open the member's detail page.

**Expected:**

- [ ] Status is **Active**
- [ ] Package/label shows **Standard**

---

## 7. Test 5 — Genealogy "Register New Member" window

**Goal:** prove the same 2-step flow appears in the network tree's register
window.

> **Who can do this?**
> Any logged-in member/admin with binary access. The admin account works.
> If you have a member login with a binary package, use that instead.
> If you cannot load a tree with an empty slot, see "Not testable" at the end
> of this section.

1. In Window A (still logged in as admin) go to:

   ```text
   http://localhost/altaslife/?page=genealogy
   ```

2. Wait for the tree drawing to finish loading.
3. Find a member card that has a small **blue circle with a white
   person-plus icon** at its top-right corner. It only appears on cards that
   still have an empty slot. Click that blue circle.

4. Check the **Register New Member** window.

**Expected:**

- [ ] The window title is **Register New Member**
- [ ] The step bar has exactly **two** steps:
      **1 Account Setup → 2 Confirm**
- [ ] **Select Package** is **not** shown
- [ ] There are **no** Free / Code / E-Wallet buttons
- [ ] A blue notice says:
      **"🔗 No payment is required now — new accounts will be created as Standard (active). Standard accounts have no earnings, no capping, and cannot be activated or upgraded."**
- [ ] The bottom of the Account Setup screen has **Review →** but **no
      "← Back"** button
- [ ] Username / Password / Confirm Password / Sponsor fields are shown
- [ ] The Sponsor field is already filled with **your own username** (e.g.
      `admin`) and marked valid

> A binary-capable account may also show a **Has Binary** switch (ON) and
> Binary Upline / Position fields. That is expected — it is how the clicked
> slot is used. If no position is pre-selected, click the free Left or Right
> slot. If you do not want to place the member in the tree, switch **Has
> Binary** OFF and those fields disappear.

5. Fill in a username (`qatree` + today's month/day), password `QaTest1234`,
   and confirm the password. Wait until the username hint says
   **"Username is available."**
6. Click **Review →**.

**Expected on the review screen:**

- [ ] **Account Type: Standard**
- [ ] No **Payment**, **Code**, or **Package** rows
- [ ] Buttons **← Back** and **✓ Complete Registration**

7. Click **✓ Complete Registration**.

**Expected:**

- [ ] The window closes and the browser navigates away from the tree
- [ ] As **admin**, you land on `?page=admin_users`
      (if you used a member login instead, you land on the member Dashboard —
      this is normal)
- [ ] Green flash: **"Account @<username> registered successfully as Standard."**
- [ ] Searching that username shows **Status: Active** and
      **Package: Standard**

**Not testable?** If no blue person-plus circle appears, the tree may have no
empty slots or no data. Record "Test 5: not testable — no empty slot" and ask
your developer for a member login with a binary package. Do not mark it as a
failure without checking this.

---

## 8. Test 6 — Logged-in registration page (recommended)

**Goal:** prove the 2-step flow also applies when an admin registers someone
from the normal page (not the tree window).

1. In Window A (admin) go to:

   ```text
   http://localhost/altaslife/?page=register
   ```

   The header should say **Register New Member** and **Registering as @admin**.

**Expected:**

- [ ] Step bar has **2 steps**: **1 Account Setup → 2 Confirm**
- [ ] No **Select Package**
- [ ] No Free / Code / E-Wallet buttons
- [ ] A **Has Binary** switch is visible (ON) because admin is binary-capable
- [ ] Turning **Has Binary** OFF hides the Binary Upline / Position fields
- [ ] No **← Back** button at the bottom of Account Setup

2. To see the summary: switch **Has Binary** OFF (so no upline is needed),
   fill in a unique username and password, wait for
   **"Username is available."**, then click **Review →**.
   (If you leave Has Binary ON, you must first type a valid Binary Upline and
   pick a free Left/Right position.)

**Expected:**

- [ ] Summary shows **Account Type: Standard**
- [ ] No **Payment**, **Code**, or **Package** rows

3. **Do not click Complete Registration.** Click **Cancel** (top right) or
   use the browser Back button to avoid creating another account.

---

## 9. Test 7 — Server safety net (optional, advanced)

**Goal:** prove the server refuses to charge/consume anything even when a paid
payment method is forced into the form. This is the part a normal visitor can
never trigger, so it is optional.

> This test creates one more account. Use a unique username.
> You need the browser's Developer Tools.

1. Close every Incognito window (so the private session is empty), then open a
   **new** Incognito window.
2. Go to `http://localhost/altaslife/?page=register`.
   Confirm the header says **Create your member account** (guest view).
3. Fill in:
   - Username: `qastale` + today's month/day (e.g. `qastale1027`)
   - Password / Confirm: `QaTest1234`
   - Leave Sponsor as `admin`
   - Wait until the username hint says **"Username is available."** and the
     sponsor hint says **"Sponsor is valid."**
4. Click **Review →** so you are on the summary screen.
5. Press **F12** to open Developer Tools, then click the **Console** tab.
   - If Chrome shows "Warning: Don't paste code into the DevTools Console…",
     type `allow pasting` and press Enter.
6. Paste the following **exactly** and press Enter:

   ```js
   document.querySelectorAll('input[name="payment_method"]').forEach(el => el.checked = false);
   const f = document.getElementById('regForm');
   const h = document.createElement('input');
   h.type = 'hidden';
   h.name = 'payment_method';
   h.value = 'ewallet';
   f.appendChild(h);
   document.getElementById('referralMode').value = '';
   f.submit();
   ```

**Expected:**

- [ ] The account is created as **active Standard** (same success message as
      Test 3)
- [ ] You are **not** redirected to the login page
- [ ] You are **not** told "Insufficient e-wallet balance"
- [ ] In Window A, searching `qastale…` shows **Status: Active** and
      **Package: Standard**

**Why this proves the safety net:** this page never showed an e-wallet option,
and a guest posting `payment_method=ewallet` without the safety net would be
rejected with "Please log in to use e-wallet registration." Getting a pending
account instead means the server forced the free/pending flow.

---

## 10. Test 8 — Restore the site (do not skip)

1. In Window A go to:

   ```text
   http://localhost/altaslife/?page=admin_settings#tabPane-maint
   ```

2. **Uncheck** **Disable Packages**.
3. Click **Save Settings** (in the Maintenance & Security card).

**Expected:**

- [ ] Green flash: **"Settings saved."**
- [ ] The **Free Registration** dropdown is editable again
- [ ] Go to `http://localhost/altaslife/?page=admin_settings#tabPane-compensations` and confirm the compensation toggles are now **editable again** (unlocked) after packages are re-enabled. (While packages were disabled they were forced off/disabled.)
- [ ] Leave Free Registration set to **On**

4. Go to `http://localhost/altaslife/?page=admin_packages`.

**Expected:**

- [ ] **+ New Package** is enabled again
- [ ] **Edit** buttons are enabled again
- [ ] Packages are still **○ Inactive** — this is expected, because disabling
      packages set them all inactive. Re-enabling does **not** reactivate them.

5. Click **Edit** on one of the packages you recorded in section 2.1
   (if you recorded none, pick any package in the list).
6. Set **Status** to **Active** and save the package.
7. Confirm its row now shows **● Active**.

8. Open a **new Incognito** window and go to
   `http://localhost/altaslife/?page=register`.

**Expected — the normal page is back:**

- [ ] **THREE** steps: **1 Select Package → 2 Account Setup → 3 Confirm**
- [ ] **Select Package** is highlighted
- [ ] The disabled-packages banner is gone
- [ ] Free / Registration Code choices are offered again

9. Optional: open the Genealogy register window again and confirm it is back
   to **3 steps**.

> If there were **no** active packages before you started, then leaving all
> packages inactive is the correct restore. In that case the 2-step flow
> staying on is expected (the rule is "no selectable package → skip").

---

## 11. Pass / Fail checklist

Copy this table into your report and fill it in.

| # | Test | Pass? | Notes / screenshot file |
| --- | --- | --- | --- |
| 1 | Packages available → registration shows 3 steps | ☐ | |
| 2a | "Disable Packages" saves; Free Registration forced ON and locked | ☐ | |
| 2b | Packages page shows disabled warning; all rows Inactive; Edit/New disabled | ☐ | |
| 2c | Compensations tab locked with notice; all compensation toggles disabled/off | ☐ | |
| 3a | Guest `?page=register` shows 2 steps, no Select Package, banner present | ☐ | |
| 3b | No Free/Code/E-Wallet buttons; no Back button on Account Setup | ☐ | |
| 3c | Review shows **Account Type: Standard**; no Payment/Code/Package rows | ☐ | |
| 3d | Guest completes → Dashboard + "Standard account created" flash | ☐ | |
| 4 | Admin sees the new member as **Active**, Package **Standard**, Balance ₱0.00 | ☐ | |
| 5a | Genealogy window opens on **Account Setup**, 2 steps, notice shown | ☐ | |
| 5b | Genealogy completion → Standard account created, admin success flash | ☐ | |
| 6 | Logged-in register page also shows 2 steps and no payment toggle | ☐ | |
| 7 | (Optional) Forced `payment_method=ewallet` still creates a Standard account | ☐ | |
| 8 | Toggle OFF + package Active → 3-step registration returns | ☐ | |

**Overall result:** ☐ PASS ☐ FAIL

---

## 12. If a test fails

Do **not** try to fix the code. Record:

1. The **test number** (e.g. "Test 3").
2. The **exact URL** in the address bar.
3. What you **expected** and what you **actually saw** (copy the exact words on
   screen; a screenshot is best).
4. Which window you were in (admin window or Incognito).
5. Whether **Disable Packages** was ticked at that moment.
6. Browser name and version.
7. Any red text in the browser Console (`F12` → Console), if open.

Useful screenshots:

- The step bar (top of the card)
- The whole registration card
- The review/summary table
- The admin user row (blur/avoid real member data if any)
- The Settings card showing the toggle + Free Registration state

---

## 13. Cleanup

- [ ] **Disable Packages** is **unchecked**
- [ ] At least one package is **Active** again (unless the site started with
      none active)
- [ ] All Incognito windows are closed
- [ ] Write down every test account you created so a developer can remove them:

  ```text
  qafree________  (Test 3)
  qatree________  (Test 5)
  qastale_______  (Test 7, if done)
  ```

- [ ] Report filed with the pass/fail table

---

## Appendix A — Exact wording you should see

Small differences in spacing or emoji rendering are fine. The wording should
match.

| Where | Text |
| --- | --- |
| Disabled-packages banner on `?page=register` | **📦 Packages are currently disabled — new accounts start as Standard (active). Standard accounts have no earnings, no capping, and cannot be activated or upgraded.** |
| Genealogy window notice | **🔗 No payment is required now — new accounts will be created as Standard (active). Standard accounts have no earnings, no capping, and cannot be activated or upgraded.** |
| Guest success flash | **Welcome! Your Standard account has been created.** |
| Admin success flash | **Account @&lt;username&gt; registered successfully as Standard.** |
| Valid sponsor hint | **Sponsor is valid.** |
| Step labels when skipping | **Account Setup**, **Confirm** |
| Review label when Standard | **Account Type: Standard** |

---

## Appendix B — Quick troubleshooting

| Problem | Likely cause / fix |
| --- | --- |
| "Disable Packages" checkbox is not visible | You are on the wrong Settings tab. Use `?page=admin_settings#tabPane-maint` and look inside the **Maintenance & Security** card. |
| Still 3 steps after disabling packages | The save did not go through. Check for the green "Settings saved." flash, then hard-refresh the page (`Ctrl + F5`). If it is still 3 steps, that is a real failure — report it. |
| 2 steps when you expected 3 (Test 1 or 8) | No package is Active. Activate one (Edit → Status = Active). Also confirm **Disable Packages** is unchecked. |
| Sponsor hint says "Sponsor not found." | Type the username of an existing member, usually `admin`. Wait a second for the green check. |
| "Username is taken" | Use a different username (add a letter or digit). |
| "Registration is closed. The member seat limit has been reached." | The site hit its member seat limit. Ask the developer to raise it for QA. |
| No blue person-plus circle in the tree (Test 5) | The tree has no empty slots or no data. Record "not testable" and ask for a member login with a binary package. |
| Chrome will not let you paste into the Console (Test 7) | Type `allow pasting` in the Console and press Enter, then paste. |
| You accidentally completed Test 6 | A second pending account was created. Note its username for cleanup; it does not affect the test result. |
| Page looks unstyled or buttons do nothing | Hard-refresh (`Ctrl + F5`). If it persists, the local site's assets/database are not running — tell the developer. |

---

## Appendix C — What "exactly as intended" means (one paragraph)

With packages disabled, a new registration must never involve money or a code.
The UI must hide the package/payment step entirely and show only Account Setup
and Confirm, and the server must coerce any stale/forged paid submission into
an **active Standard** account. Standard accounts have no earnings, no capping,
and cannot be activated or upgraded. Activation with a package or a code remains
available only for non-Standard pending/paid paths as before; this change only
removes the package step from registration while packages are off and creates
Standard accounts. Re-enabling packages must restore the original 3-step page.
