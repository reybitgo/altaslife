# QA Test — Hide binary fields on registration

**Version:** 1.0
**Date:** 2026-10-30
**App:** AltasLife (local development copy)
**Feature under test:** Registration page + genealogy "Register New Member" modal — binary placement visibility, payment defaults, and step bar
**Related documents:** `tmp/plan-hide-binary-fields-on-registration.md`

---

## 0. How to use this document

You do **not** need to know how the system works or read any code. Follow the steps in
order, compare what you see with **You should see**, and tick **Pass** or **Fail**.

- Do every test in **Phase 1** first. It uses the baseline settings.
- Phases 2–6 temporarily change settings. Each phase ends with **restore** steps — do not
  skip them.
- If something looks different, take a screenshot and write the test ID in the defect
  template at the end. Do not try to fix it.
- The whole run takes about 60–90 minutes.

### Safety rules (read before clicking)

1. **Use only the local/development copy** (`http://localhost/altaslife/`). Never run this
   against a live site with real members.
2. The paid tests perform **real e-wallet debits/credits** (for an admin payer the net
   total may not change) and **create real member rows** in this local database. That is
   expected. Record the starting balance and the accounts you create.
3. **Do not delete members, packages, or codes.** If the developer wants cleanup, they
   will do it.
4. **Record every setting before you change it**, and put it back exactly as it was.
5. Do not change settings on tabs you are not told to touch.
6. Use a **new Incognito/Private window** whenever a test says "as a guest".

---

## 1. What this feature is supposed to do (the short version)

The system has packages, a binary tree, and a "Binary Pairing" compensation. The
registration screens must follow these rules:

1. If **packages are disabled** (or there are no active packages), the registration page
   must not show **Binary Placement**, **Binary Upline Username**, or **Binary Position**.
2. If **Binary Pairing is switched off in Compensations** (or all commissions are off),
   those three things must not appear either.
3. When **free registration is on**, the registration page opens with **Free** selected and
   a **two-step bar**: `Account Setup → Confirm` (no "Select Package" step).
4. **Guests never see the package catalogue.** Only a logged-in registrar who chooses
   **E-Wallet** sees it.
5. The first step is labelled **"Select Package"** only for E-Wallet. For Code (or a guest)
   it is labelled **"Payment"**.
6. **Free members are never placed in the binary tree at registration.** They choose their
   placement when they activate. The one exception is a **real referral link** (`?ref=1`).
7. The **genealogy "Register New Member" modal** must behave the same way as
   `?page=register`.
8. A **paid package that participates in binary pairing** still requires an upline and
   position. A **paid package without pairing** does not.

> Words used below: a **package** is a paid membership plan. **Free** creates a pending
> account with no package. **Code** is a pre-paid registration code. **E-Wallet** pays from
> the registrar's balance. **Binary** is the two-legged tree. **Upline** is the member above
> the new member. **Position** is Left or Right under that upline. **Pairing** is the
> compensation that needs the binary tree.

---

## 2. Access and preparation

### 2.1 What you need

- A desktop browser (Chrome or Edge).
- The app URL from your developer. This document assumes
  `http://localhost/altaslife/`.
- An **admin** username and password. **Ask the developer** — do not use a password from an
  old chat or note, because this copy may have a custom password.
  (A fresh copy installed from `install.sql` uses the seed `admin` / `Admin@1234`, but only
  use that if the developer confirms the password was never changed.)
- A normal browser window for admin tests and an **Incognito** window for guest tests.

### 2.2 Useful links (bookmark these)

| What | Address |
|---|---|
| Admin dashboard | `http://localhost/altaslife/?page=admin` |
| System settings | `http://localhost/altaslife/?page=admin_settings` |
| Maintenance & Security tab | `http://localhost/altaslife/?page=admin_settings#tabPane-maint` |
| Compensations tab | `http://localhost/altaslife/?page=admin_settings#tabPane-compensations` |
| Register page | `http://localhost/altaslife/?page=register` |
| Genealogy / binary tree | `http://localhost/altaslife/?page=genealogy` |
| Member search | `http://localhost/altaslife/?page=admin_users` |
| Packages | `http://localhost/altaslife/?page=admin_packages` |
| Registration codes | `http://localhost/altaslife/?page=admin_codes` |

### 2.3 How to open Settings

1. Log in as admin.
2. Click the **⚙️ gear icon** at the top-right. A **Settings** drawer slides in from the
   right.
3. Click the tab you need:
   - **🛡️ Maintenance & Security** — contains **Disable Packages** and **Free
     Registration**.
   - **💰 Compensations** — contains **Master: Enable All Commissions** and **Binary
     Pairing**.
4. Make your change, then click **💾 Save Settings** at the bottom of that tab (the
   Compensations tab says **💾 Save Compensation Settings**). Wait for the green "saved"
   message. **Save before switching tabs** — unsaved changes are lost.

You can also paste the direct tab URLs from the table above.

### 2.4 Baseline record (fill this in first)

Open **Maintenance & Security** and **Compensations** and write down what you see. The
expected baseline for this test run is in the last column.

| Setting | Where | Value you see | Expected baseline |
|---|---|---|---|
| 📦 Disable Packages | Maintenance & Security | | **OFF** (unchecked) |
| 🆓 Free Registration | Maintenance & Security | | **ON** |
| 🛑 Master: Enable All Commissions | Compensations | | **ON** (checked) |
| 🤝 Binary Pairing | Compensations | | **ON** (checked) |
| 👥 Direct Referral | Compensations | | Record actual (may be OFF) |
| 🔗 Indirect/Unilevel Referral | Compensations | | Record actual (may be OFF) |
| 📅 Daily Fixed Income (DFI) | Compensations | | Record actual (may be OFF) |
| Active packages | `?page=admin_packages` | | **Starter only** (pairing ON) |
| Admin e-wallet balance | `?page=ewallet` | | Record it (an admin-paid registration may not change the total) |

> If the baseline is different, **stop** and ask the developer. The expected results in this
> document assume the baseline above.

### 2.5 Test account naming

Use usernames that start with `qa` and are lowercase, letters/numbers/underscore only, for
example:

`qa_free_1`, `qa_paid_1`, `qa_modal_1`, `qa_ref_1`

Use the same password for all test accounts to save time, for example `QaTest1234!`
(minimum 8 characters). Two passwords must match.

If a username is already taken, change the last number (for example `qa_free_2`). Record
every account in the **Test data log** at the end.

---

## 3. Quick expected-results matrix

Use this as a cheat sheet. More detail is in the phases.

| Situation | Step bar | Default payment | Package catalogue | Binary fields |
|---|---|---|---|---|
| Packages disabled | 2: Account Setup → Confirm | Free (forced) | none | **none** |
| Packages on, free on | 2: Account Setup → Confirm | **Free** | hidden until E-Wallet | none |
| ↳ registrar clicks Code | 3: **Payment** → Account Setup → Confirm | Code | none (code defines it) | upline/position only if the code's package pairs |
| ↳ registrar clicks E-Wallet | 3: **Select Package** → Account Setup → Confirm | E-Wallet | **shown** | upline/position only if the package pairs |
| Free off, guest | 3: **Payment** → Account Setup → Confirm | Code | none | only if the code's package pairs |
| Free off, admin with balance | 3: **Select Package** → Account Setup → Confirm | E-Wallet | shown | only if the package pairs |
| Binary Pairing or Master off | same as paid, but | unchanged | shown for E-Wallet | **none** |
| Referral link `?ref=1` | 2: Account Setup → Confirm | Free | none | **prefilled placement** (exception) |

---

## 4. Phase 1 — Baseline settings

Do this phase with the settings you recorded in section 2.4. Do not change anything yet.
Master commissions and Binary Pairing must be **ON**; the other per-type toggles may be OFF
and do not affect these tests.

### T01 — Guest: Free is the default, two-step bar

**Do this**

1. Open a new **Incognito** window.
2. Go to `http://localhost/altaslife/?page=register`.
3. Look at the numbered circles at the top of the form and the payment choices.

**You should see**

- Exactly **two** step labels: **Account Setup** and **Confirm**, numbered **1** and **2**.
- No **Select Package** label anywhere in the step bar.
- Payment choices: **🎁 Free** (already selected) and **🎫 Registration Code**.
- **No** package list, package card, or "Package" dropdown.
- The first screen is the payment choice and a **Continue →** button. **Username** appears
  on the next screen, **Account Setup**, after you click **Continue →**.
- The notice "Free accounts are not placed in the binary network until the member
  activates." is **not shown** for guests. (This is a known open decision — see Appendix C.
  Record it, do **not** fail this test for it.)

**Fail if** you see three step labels, a package list, or any binary field.

---

### T02 — Admin: Free is the default, catalogue hidden

**Do this**

1. In the normal window (logged in as admin), go to
   `http://localhost/altaslife/?page=register`.
2. Look at the step bar, payment choices, and any package area.

**You should see**

- Two step labels: **Account Setup** and **Confirm** (numbered 1 and 2).
- **🎁 Free** selected. **🎫 Code** and **💳 E-Wallet** available.
- No package card/list while Free is selected.
- The notice "🎁 Free accounts are not placed in the binary network until the member
  activates." **is** visible.
- No binary field is expected on this payment step; T05 checks **Account Setup**, where the
  placement controls would appear.

**Fail if** the step bar says "Select Package" or a package card is visible.

---

### T03 — Code changes the bar to three steps and the label to "Payment"

**Do this**

1. On the admin register page from T02, click **🎫 Code**.
2. Look at the step bar and the form.

**You should see**

- Three step labels: **Payment**, **Account Setup**, **Confirm** (numbered 1, 2, 3).
- A **Registration Code** input and a **Validate** button.
- No package card/list.
- The free notice is now hidden.
- No binary fields yet (they appear only after a valid pairing code is validated — see
  T07).

**Fail if** the first label still says "Select Package" or the bar still shows two steps.

---

### T04 — E-Wallet shows "Select Package" and the catalogue

**Do this**

1. On the admin register page, click **💳 E-Wallet**.
2. Look at the step bar and the package area.

**You should see**

- Three step labels: **Select Package**, **Account Setup**, **Confirm**.
- A package card (with one active package, **Starter**, auto-selected) or a package
  dropdown (if there is more than one active package).
- The free notice hidden.
- Placement controls are **not** on this step even for a pairing package; they appear on
  **Account Setup** after you click **Continue →**. T06 checks them there.

**Fail if** the first label says "Payment", or the catalogue is missing for an admin with
enough e-wallet balance.

> If the admin's balance is below the cheapest package, the catalogue still appears but
> **Continue →** stays disabled and a warning explains the shortfall. Record it; this is
> expected.

---

### T05 — Free mode has no placement controls and no review rows

**Do this**

1. On the admin register page, click **🎁 Free**.
2. Click **Continue →** to open **Account Setup**.
3. Fill in a username (for example `qa_review_1`), a password, and confirm it. Use the
   sponsor already filled in (`admin`). Wait until the sponsor hint turns green.
4. Click **Review →**.
5. Look at the summary table.

**You should see**

- No **Binary Placement** toggle, no **Binary Upline Username**, no **Binary Position**
  while Free is selected.
- The summary shows **Activation: Pending**, Username, Sponsor.
- **No Upline row** and **no Position row**.
- The warning "⚠️ Binary position cannot be changed after registration." is not part of the
  Free contract but may still appear on this step. Record it as a cosmetic note (Minor);
  do **not** fail T05 for the warning alone.

**Do not complete this registration.** Click **← Back** and clear the username if needed.

**Fail if** any binary field or binary review row appears in Free mode.

---

### T06 — Paid pairing package requires placement

**Do this**

1. On the admin register page, click **💳 E-Wallet** and make sure **Starter** is selected.
2. Click **Continue →** to open **Account Setup**.
3. Look below the sponsor field.

**You should see**

- A **Binary Placement** switch labelled **Has Binary**, **ON** and locked (you cannot turn
  it off).
- The hint **"Binary placement is required for this package."**
- **Binary Upline Username** and **Binary Position** fields, both required.
- Type `admin` into the upline field and wait. You should see **✓ Found @admin** and a slot
  status line like `↙ Left: ✓ Free` / `↘ Right: ✗ Taken`.
- Pick a side that says **Free**. The position hint should say the slot is available.
- Click **Review →**. The summary must show a **Upline** row and a **Position** row, and the
  warning "Binary position cannot be changed after registration."

> If both slots under `@admin` are taken, use another active member's username from the
> binary tree as the upline.

**Do not complete this registration** (T09 does a paid completion).

**Fail if** the placement controls are missing for a pairing package, or the slot status
never appears.

---

### T07 — A pairing code shows placement after validation

**Do this**

1. Generate one code first:
   - Go to `?page=admin_codes`.
   - Under **⚡ Generate Codes**, choose package **Starter**.
   - Set **Quantity** to `1`; the price fills in automatically (you can leave it).
   - Leave **Code Type** as **🎟️ Registration Code**.
   - Click **🎟️ Generate Codes**, confirm in the pop-up, then copy the new code from the
     codes table.
2. Go back to the admin register page, click **🎫 Code**, paste the code, and click
   **Validate**.
3. Wait for the green package card on the payment step.
4. Click **Continue →** to open **Account Setup** and look below the sponsor field.

**You should see**

- The green package card showing **Starter** on the payment step.
- On **Account Setup**, the **Binary Placement** switch and **Binary Upline Username** /
  **Binary Position** fields appear (because Starter has pairing ON).
- The hint says placement is required.

**Do not complete this registration.** (The code stays unused if you do not submit.)

**Fail if** the code validates but no placement controls appear, or placement appears for a
non-pairing code.

---

### T08 — Complete a Free registration: the member is **not** placed

**Do this**

1. On the admin register page, click **🎁 Free**.
2. Click **Continue →** to open **Account Setup**.
3. Fill in username `qa_free_1`, password `QaTest1234!` (twice).
4. Leave the sponsor as `admin`; wait for the green sponsor hint.
5. Click **Review →**, then **✓ Complete Registration**.
6. Wait for the success message.
7. Open `?page=admin_users`, search for `qa_free_1`, and click **View**.

**You should see**

- A success message such as "Account @qa_free_1 registered successfully. Awaiting
  activation."
- On the member's page, the **🌳 Binary Placement** card shows:
  - **Package: —**
  - **Upline: —** (this is the important part — the member is **not** placed)
- The member status is **Pending**.

**Fail if** the Upline shows a username, or any error appears during registration.

Record the member in the Test data log.

---

### T09 — Complete a paid Starter registration: the member **is** placed

**Do this**

1. On the admin register page, click **💳 E-Wallet**, keep **Starter**.
2. Click **Continue →** to open **Account Setup**.
3. Fill in username `qa_paid_1`, password `QaTest1234!` (twice).
4. In **Binary Upline Username**, type `admin`; wait for the slot status.
5. Pick a free side (Left or Right).
6. Click **Review →**, then **✓ Complete Registration**.
7. Open `?page=admin_users`, search `qa_paid_1`, click **View**.

**You should see**

- Success message; no errors.
- **Package: Starter**, status **Active**.
- **Upline: @admin (left)** or **@admin (right)** — the side you chose.
- The admin e-wallet **total may be unchanged**: the admin is both the payer and the
  revenue recipient, so the debit and the credit cancel out. Check the record on
  `?page=ewallet` but do **not** fail if the total is the same.

**Fail if** the member is unplaced or the wrong side is used.

---

### T10 — Genealogy modal: Free default, no placement

**Do this**

1. Go to `?page=genealogy` (Binary Tree).
2. Find a member card with a small **blue circle with a white +** at its top-right corner.
   Click that **+**.
3. The **Register New Member** modal should open.

**You should see**

- Step bar with **two** labels: **Account Setup** and **Confirm**.
- **🎁 Free** selected.
- The notice about free members not being placed until activation.
- **No** Binary Placement switch, upline, or position.
- The browser console (F12 → Console) has **no red errors**.

**Fail if** three steps, "Select Package", or any binary field appears in Free mode, or the
modal does not open.

---

### T11 — Genealogy modal: E-Wallet shows the full paid layout

**Do this**

1. With the modal still open from T10, click **💳 E-Wallet**.
2. Look at the step bar, package area, and placement area.

**You should see**

- Three step labels: **Select Package**, **Account Setup**, **Confirm**.
- A package card/list (Starter or all active packages).
- The **Binary Placement** switch and upline/position fields, because Starter pairs.
- The upline is already filled with the member whose **+** you clicked, and a position is
  preselected if a side is free.

**Do not complete.** Close the modal with the X or Escape.

**Fail if** the package list is missing, the step bar stays at two steps, or placement is
missing for a pairing package.

---

### T12 — Complete a Free registration from the modal: **not** placed

**Do this**

1. Open the modal again from a **+**.
2. Leave **🎁 Free** selected and click **Continue →**.
3. Fill in username `qa_modal_1`, password `QaTest1234!` (twice).
4. Click **Review →**, then **✓ Complete Registration**.
5. Search `qa_modal_1` in `?page=admin_users` → **View**.

**You should see**

- Success message.
- **Upline: —**, **Package: —**, status **Pending**.

**Fail if** the modal pre-placed the free member under the clicked node.

---

### T13 — Complete a paid registration from the modal: placed

**Do this**

1. Open the modal from a **+** on a node with a free side.
2. Click **💳 E-Wallet**, keep **Starter**.
3. Click **Continue →**; fill in username `qa_modal_paid_1`, password `QaTest1234!` twice.
4. Leave the prefilled upline/position (or pick the other free side).
5. Click **Review →**, then **✓ Complete Registration**.
6. Find the member in `?page=admin_users` → **View**.

**You should see**

- **Package: Starter**, **Active**.
- **Upline: @<clicked member> (left/right)** — not "—".

**Fail if** the paid modal registration is unplaced or errors.

---

### T14 — Referral link keeps the sponsor-anchored placement

**Do this**

1. As admin, open `?page=genealogy`.
2. At the top-right, find the 🔗 field with your referral link and click the **📋 copy**
   button.
3. Open a new **Incognito** window, paste the link, press Enter.
4. Look at the step bar, sponsor, and binary section.
5. Fill in username `qa_ref_1` and password `QaTest1234!` (twice). Wait for the green
   upline hint (it is prefilled; the page checks it automatically).
6. Click **Review →**, then **✓ Complete Registration**.
7. Look the member up in `?page=admin_users` → **View**.

**You should see**

- Two step labels: **Account Setup** and **Confirm**.
- A blue notice saying you are registering via a referral link.
- **Sponsor** locked to `admin`.
- **Binary Upline Username** and **Binary Position** prefilled with a valid slot.
- No payment required.
- After completion: status **Pending**, but **Upline: @admin (left/right)** — placed, unlike
  T08/T12.

> If the page does not show the referral notice or the prefilled upline, the sponsor's
> binary tree may have no free slot. Try another active member's referral link and record
> which one you used.

**Fail if** a real referral link produces an unplaced free member.

---

### T15 — Review summary matches the payment mode

Use the review screens you already saw; no new registration is needed.

| Mode | Activation row | Payment/Code/Package rows | Upline/Position rows |
|---|---|---|---|
| Free | **Pending** | hidden | hidden |
| Code | hidden | shown (Code + Package) | shown only if the code pairs |
| E-Wallet | hidden | shown (E-Wallet + Package) | shown only if the package pairs |

Record any mismatch.

---

### Phase 1 results

| Test | What it checks | Pass/Fail | Notes / screenshot |
|---|---|---|---|
| T01 | Guest free default, 2-step bar | | |
| T02 | Admin free default, catalogue hidden | | |
| T03 | Code → 3 steps, "Payment" | | |
| T04 | E-Wallet → "Select Package" + catalogue | | |
| T05 | Free mode has no placement / review rows | | |
| T06 | Paid pairing requires placement | | |
| T07 | Pairing code shows placement | | |
| T08 | Free registration unplaced | | |
| T09 | Paid Starter placed + wallet debited | | |
| T10 | Modal free default, no placement | | |
| T11 | Modal E-Wallet full layout | | |
| T12 | Modal free unplaced | | |
| T13 | Modal paid placed | | |
| T14 | Referral link placed | | |
| T15 | Review summary matches mode | | |

---

## 5. Phase 2 — Binary Pairing compensation OFF

### T16 — Register page hides placement when pairing is off

**Do this**

1. Go to **Compensations**. If a yellow notice says packages are disabled, go back to
   **Maintenance & Security**, turn **Disable Packages OFF**, save, and return.
2. Uncheck **🤝 Binary Pairing**. Click **💾 Save Compensation Settings**.
3. Go to the admin register page. Confirm Free is selected and the bar has two steps.
4. Click **💳 E-Wallet**. Look at the step bar and catalogue.
5. Click **Continue →** to open **Account Setup** and look below the sponsor field.

**You should see**

- Three steps with **Select Package** (the catalogue is still shown).
- Starter package card.
- **No** Binary Placement switch.
- **No** Binary Upline Username / Binary Position.
- **No** "Binary position cannot be changed" warning.

**Fail if** any binary field is visible while Binary Pairing is off.

---

### T17 — Genealogy modal hides placement when pairing is off

**Do this**

1. While Binary Pairing is still OFF, open `?page=genealogy`.
2. Click a **+** on a member card.
3. In the modal, click **💳 E-Wallet**.
4. Open the browser console (F12 → Console) and watch for red errors.

**You should see**

- The package card/list.
- **No** Binary Placement switch, upline, or position anywhere in the modal.
- No new red console errors when switching between Free, Code, and E-Wallet.

**Fail if** placement appears in the modal, or the console shows an error when switching
payment methods.

---

### T18 — Restore Binary Pairing

1. Go back to **Compensations** and check **🤝 Binary Pairing**. Click
   **💾 Save Compensation Settings**.
2. Reload the admin register page, choose E-Wallet, click **Continue →**, and confirm the
   placement controls are back.
3. Record Pass/Fail for the restore.

---

## 6. Phase 3 — Master commissions OFF

### T19 — Master off behaves like pairing off

**Do this**

1. Go to **Compensations** and uncheck **🛑 Master: Enable All Commissions**. Click
   **💾 Save Compensation Settings**. (The per-type checkboxes stay as they are.)
2. Go to the admin register page, choose **💳 E-Wallet**, then click **Continue →** to open
   **Account Setup**.
3. Check for any binary field.
4. Open the genealogy modal and choose E-Wallet; check again.

**You should see**

- The catalogue is still visible.
- **No** binary placement controls on either surface.
- No console errors.

**Fail if** placement still appears while the master switch is off.

### T20 — Restore the master switch

1. Check **🛑 Master: Enable All Commissions** again. Confirm **🤝 Binary Pairing** is
   still checked (it should be; if the save cleared it, check it). Click
   **💾 Save Compensation Settings**.
2. Reload, choose E-Wallet, click **Continue →**, and confirm placement returns for Starter.

---

## 7. Phase 4 — Packages disabled

### T21 — Guest: no package step, no catalogue, no binary fields

**Do this**

1. Go to **Maintenance & Security**, check **📦 Disable Packages**, and save.
   (The Free Registration select becomes greyed and forced ON — expected.)
   **Important:** saving this also sets **every package inactive** and **switches all
   compensation toggles off**. That is intended. T24 restores them, so do not panic and do
   not change anything else.
2. Open a new **Incognito** window and go to the register page.

**You should see**

- Two step labels: **Account Setup**, **Confirm**.
- The notice "Packages are currently disabled — new accounts start on the free (active)
  plan."
- No package list/card.
- **No** Binary Placement / Upline / Position.
- The form starts at Username.

**Fail if** "Select Package" or any binary field is visible.

---

### T22 — Admin: same as T21

**Do this**

1. In the admin window, open the register page (sidebar **➕ Register Member** is fine).

**You should see**

- The same two-step bar, the packages-disabled notice, no catalogue, no binary fields.
- The dashboard frame/sidebar remains usable.

**Fail if** any binary field or "Select Package" appears.

---

### T23 — Observe (do not fail): referral link with packages disabled

This is a **known open product decision**, not a defect.

1. Copy the referral link from `?page=genealogy` as in T14.
2. Open it in Incognito while packages are disabled.
3. Record whether the upline/position fields appear.

Current expected behavior: **they do not appear**; the member stays unplaced. Write down
what you see in the notes. Do **not** complete the registration.

---

### T24 — Restore packages and compensations

This step is longer because disabling packages has three side effects: every package is
set inactive, every compensation toggle is switched off, and Free Registration is forced
ON. Put all of it back.

1. **Maintenance & Security:** uncheck **📦 Disable Packages**, save.
2. **Packages:** go to `?page=admin_packages` → **Starter** → **Edit** → set **Status** to
   **🟢 Active** → **💾 Update Package**. (If any other package was active in your baseline,
   reactivate it too.)
3. **Compensations:** check **🛑 Master: Enable All Commissions** and **🤝 Binary Pairing**,
   and set **Direct Referral**, **Indirect/Unilevel**, and **DFI** back to the values you
   recorded in section 2.4. Click **💾 Save Compensation Settings**.
4. **Maintenance & Security:** confirm **🆓 Free Registration** is **ON**; if not, set it
   and save.
5. Reload the register page: Free is the default with two steps. Choose E-Wallet and
   Continue →; Starter and the placement controls are back.

If the E-Wallet catalogue says no packages are available, Starter was not reactivated —
repeat step 2.

---

## 8. Phase 5 — Free registration OFF

### T25 — Guest sees the Code-only layout

**Do this**

1. Go to **Maintenance & Security**, set **🆓 Free Registration** to
   **🔴 Off — Free registration disabled; codes/e-wallet only**. Save.
2. Open a new **Incognito** window and go to the register page.

**You should see**

- Three step labels: **Payment**, **Account Setup**, **Confirm**.
- **No** Free option and **no** E-Wallet option (guests cannot use the e-wallet).
- A **Registration Code** input.
- No package catalogue, no binary fields.

**Fail if** Free or E-Wallet is offered to a guest, or the first label says "Select
Package".

---

### T26 — Admin defaults to E-Wallet and sees the catalogue

**Do this**

1. In the admin window, open the register page.
2. Look at the payment choices and step bar.

**You should see**

- Three steps with **Select Package** as the first label.
- **💳 E-Wallet** selected by default (the admin has enough balance).
- **🎫 Code** available; **no Free option**.
- The package catalogue visible.
- Placement is on **Account Setup** (click **Continue →**) and is visible because Starter
  pairs.

**Fail if** Free is still offered, or the default is Code despite sufficient balance.

---

### T27 — Restore free registration

1. Set **🆓 Free Registration** back to **🟢 On**. Save.
2. Reload the register page and confirm Free is the default again.

---

## 9. Phase 6 — Paid package **without** pairing

This phase uses the inactive **Basic** package (entry ₱5,000, pairing OFF).

### T28 — Activate Basic temporarily

1. Go to `?page=admin_packages`.
2. Find the **Basic** row and click **Edit**.
3. Set **Status** to **🟢 Active** and click **💾 Update Package**.
4. Return to the register page and choose **💳 E-Wallet**. Confirm both **Starter** and
   **Basic** are offered (a dropdown appears when more than one package is active).

Record the starting state so you can restore it.

---

### T29 — Paid non-pairing package: no placement

**Do this**

1. On the admin register page, choose **💳 E-Wallet**, then select **Basic** in the package
   dropdown.
2. Click **Continue →** to open **Account Setup**.
3. Look below the sponsor field.

**You should see**

- A **Binary Placement** row with the **Has Binary** switch **OFF and locked**, and the hint
  **"This package is not part of the binary network."** This explains why nothing is
  placed; it is intended.
- **No** Binary Upline Username and **no** Binary Position fields.
- The step bar still shows three steps with **Select Package** (the catalogue is visible).
- The "Binary position cannot be changed" warning may still appear on the review step.
  Record it as a cosmetic note; do **not** fail T29 for the warning alone.

**Fail if** the upline/position fields appear for Basic.

---

### T30 — Complete a Basic registration: active but unplaced

**Do this**

1. With **Basic** selected, click **Continue →** to open **Account Setup**.
2. Fill username `qa_basic_1`, password `QaTest1234!` twice.
3. Click **Review →**, then **✓ Complete Registration**.
4. Open the member in `?page=admin_users` → **View**. Do not expect the admin total to
   drop: the admin is payer and revenue recipient, so the debit and credit cancel out.

**You should see**

- **Package: Basic**, **Active**.
- **Upline: —** (not placed).
- No errors.

---

### T31 — Restore Basic to Inactive

1. Go to `?page=admin_packages` → **Basic** → **Edit**.
2. Set **Status** back to **⚪ Inactive** and click **💾 Update Package**.
3. Confirm **Basic** is inactive and the other packages match your baseline (normally
   only **Starter** is active) on the register page again.

---

## 10. Phase 7 — Final restore and regression

### T32 — Settings restored

Open both tabs and confirm:

| Setting | Required value |
|---|---|
| 📦 Disable Packages | OFF |
| 🆓 Free Registration | ON |
| 🛑 Master: Enable All Commissions | ON |
| 🤝 Binary Pairing | ON |
| Direct Referral / Indirect / DFI | Same as your section 2.4 record |
| Basic package | Inactive |
| Starter package | Active |

### T33 — Normal pages still work

1. Log out and log back in as admin. The dashboard loads.
2. Open `?page=genealogy`; the tree renders and the referral link is visible.
3. Open the register modal from a **+** and close it without submitting.
4. Check the console: no red errors caused by these pages (ignore extension errors).

### T34 — Placement fields return

1. Admin register page → **💳 E-Wallet** → **Starter**.
2. Confirm the Binary Placement switch, upline, and position are visible again.
3. This proves Phases 2–4 were restored correctly.

---

## 11. Test data log

Fill this in as you go. **Do not delete** these accounts.

| Username | Purpose | Package / status | Placed? (Upline) | Notes |
|---|---|---|---|---|
| `qa_free_1` | T08 free page | | | |
| `qa_paid_1` | T09 paid Starter | | | |
| `qa_modal_1` | T12 modal free | | | |
| `qa_modal_paid_1` | T13 modal paid | | | |
| `qa_ref_1` | T14 referral | | | |
| `qa_basic_1` | T30 paid Basic | | | |

Wallet balance: start `__________` → end `__________`.

---

## 12. Final results summary

| Phase | Tests | Pass | Fail | Blocked |
|---|---|---|---|---|
| 1 — Baseline settings | T01–T15 | | | |
| 2 — Pairing OFF | T16–T18 | | | |
| 3 — Master OFF | T19–T20 | | | |
| 4 — Packages disabled | T21–T24 | | | |
| 5 — Free OFF | T25–T27 | | | |
| 6 — Non-pairing package | T28–T31 | | | |
| 7 — Restore / regression | T32–T34 | | | |

**Overall result:** `PASS / FAIL`

**Tester name:** ______________________  **Date:** ____________

**Developer notified of defects:** `Yes / No`

---

## 13. Defect report template

Copy this for every failure.

```
Defect ID:        QA-______
Test ID:          T______
Date / tester:
Environment:      local / http://localhost/altaslife/
Browser:

Preconditions (settings, account, page):

Steps to reproduce:
1.
2.
3.

Expected result:

Actual result:

Screenshot / console error:

Severity:         Blocking / Major / Minor / Cosmetic
```

---

## Appendix A — Exact words to look for

These are the labels the implementation uses. Wording may include an emoji; focus on the
words.

- `Binary Placement`
- `Binary Upline Username`
- `Binary Position`
- `Has Binary`
- `Binary Connection`
- `Select Package`
- `Payment`
- `Account Setup`
- `Confirm`
- `Free accounts are not placed in the binary network until the member activates.`
- `Binary placement is required for this package.`
- `This package is not part of the binary network.`
- `Binary position cannot be changed after registration.`
- `Packages are currently disabled — new accounts start on the free (active) plan.`
- `No payment is required now — the member can activate their account later with a
  registration code or e-wallet.`

Settings labels:

- `📦 Disable Packages`
- `🆓 Free Registration`
- `🛑 Master: Enable All Commissions`
- `🤝 Binary Pairing`

---

## Appendix B — What NOT to report as a defect

These are intended:

- Free mode shows only **two** step indicators.
- Free is preselected whenever free registration is on.
- Guests never see the package catalogue.
- "Select Package" is missing when Free is selected.
- The registration form starts at **Account Setup** when packages are disabled.
- Paid pairing packages require an upline and position.
- Paid non-pairing packages do not. They still show the Binary Placement row with the
  switch OFF and the hint "This package is not part of the binary network."
- The "Binary position cannot be changed" warning may appear on the review step even when
  the member is unplaced (Free or non-pairing). Record it as cosmetic unless the product
  owner says otherwise.
- E-Wallet registrations debit the payer. When the admin is the payer, the fee is credited
  back to the same admin, so the total may not change.
- A free registration creates a **pending** member with no package.
- A referral-link free registration is **placed** even though it is pending.

## Appendix C — Known open decisions (record, do not fail)

1. **Guest free notice** — the "Free accounts are not placed…" notice is currently shown
   only to logged-in registrars. If a guest selects Free and does not see it, record it as
   an observation. The product owner has not decided whether guests should see it.
2. **Referral link with packages disabled** — the current code does not anchor the free
   signup when no package is active. Record what you see; this is awaiting a product
   decision.
3. **Paid registration while the master commission switch is off** — the member can be
   active but unplaced. This is a known edge case, not part of the manual pass; mention it
   only if you happen to test it.

---

## Appendix D — For the developer (not for the manual tester)

Automated checks that already cover this feature:

```
php tests/run.php RegisterFlow
php tests/run.php TemplateLint
php tests/run.php
```

The full suite rebuilds the disposable `altas6_test` schema and should not be pointed at
the app database. Expected: 6 suites pass, 681 assertions.

The feature gate lives in `binary_placement_ui_enabled()` in `core/helpers.php`; the
placement decision is `AuthController::placementRequired()`, covered by an eight-case
matrix in `tests/RegisterFlowTest.php`.
