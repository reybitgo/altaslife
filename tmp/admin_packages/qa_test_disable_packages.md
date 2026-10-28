# QA Test Guide: "Disable Packages" Feature

**Purpose:** Verify that enabling "Disable Packages" in Admin Settings works exactly as specified.  
**Audience:** Beginner QA tester with no prior system knowledge  
**Estimated time:** 10-15 minutes  
**Environment:** Local dev site at `http://localhost/altaslife/`  
**Login needed:** Admin account (default from install: `admin` / `Admin@1234` if not changed)

---

## Pre-test Checklist
- [ ] You can log into the admin panel
- [ ] Go to `Admin > Packages` (`/?page=admin_packages`) and confirm there is at least one package shown (or know the current state)
- [ ] Go to `Admin > Settings > Maintenance & Security` (`/?page=admin_settings#tabPane-maint`)

---

## Test Steps

### 1) Enable "Disable Packages"
1. Log in as admin
2. Open `http://localhost/altaslife/?page=admin_settings#tabPane-maint`
3. Find the section labeled **"Disable Packages"** with checkbox `Disable Packages`
4. Check the checkbox to enable it
5. Scroll down and click **Save Settings** (button at bottom of the Maintenance & Security card)
6. You should see a success flash message (e.g. "Settings saved.")

**Expected:**
- Settings save successfully
- Page stays on Maintenance & Security tab

---

### 2) Verify Free Registration is forced ON and read-only
1. Still on `/?page=admin_settings#tabPane-maint`
2. Look at **"🆓 Free Registration"** dropdown

**Expected:**
- The dropdown shows **"🟢 On — Anyone can start free (pending) registration"** selected
- The dropdown is **disabled/grayed out** (cannot be changed)
- There is helper text saying free registration is forced on while packages are disabled (or similar)
- If you inspect, there is a hidden input ensuring value stays `1`

---

### 3) Verify Packages page behavior
1. Go to `Admin > Packages` (`/?page=admin_packages`)

**Expected:**
- A notice appears near the top: "Packages are disabled. All packages are inactive and adding new packages is disabled." (or similar warning)
- The **"+ New Package"** button is **disabled** (grayed out, cannot click/open modal)
- **Every package row** shows status as **"○ Inactive"** (even if it was Active before)
- For each package, the toggle badges (Binary/Indirect/DFI) appear **grayed out/slashed** (treated as off)
- The **"Edit"** button on each package is **disabled** (cannot open edit modal)
- The **"View"** button still works (you can click View to see package details)

**Try to add a package (should be blocked):**
1. Try to click "+ New Package" - it should not open anything
2. If you somehow open the modal via URL/direct access, try to save - should be blocked by server with error message

---

### 4) Verify server-side blocking (adding new package)
1. While packages are disabled, try to access the create flow directly by attempting to save a package
   - Open browser dev tools Network tab (optional) or just try the normal flow
   - Since UI is disabled, the key check is: go to packages page, try clicking Edit/New - both blocked
   - Alternatively, if you manually submit to `/?page=admin_save_package` with POST data, it should redirect back to packages page with an error flash like "Packages are disabled. Adding or modifying packages is not allowed."

**Expected:**
- No new package can be created
- No existing package can be edited/saved while disabled

---

### 4b) Verify registration skips "Select Package"
1. While packages are still disabled, open a private/incognito window and go to `http://localhost/altaslife/?page=register`

**Expected:**
- The step bar shows only **2 steps**: **1 Account Setup** → **2 Confirm**
- There is **no "Select Package" step** and no Free/Code/E-Wallet toggle
- An info banner explains that packages are disabled and new accounts start on the free (pending) plan

2. Fill in a username, password and confirm password, then click **Review →**

**Expected:**
- The summary shows **Activation: Pending**
- There is no Payment / Code / Package row
- Completing the form logs you in and lands on the dashboard

3. (Optional) As admin, log in and check the new member (`/?page=admin_users`)
   - `registration payment` should be **pending**
   - the member should have **no package** assigned

4. Verify the genealogy modal uses the same 2-step flow:
   - Log in as a member with a binary account and open `/?page=genealogy`
   - Click a node's **Register** action
   - **Expected:** the modal opens on **Account Setup**, no "Select Package" step

5. Server-side check (optional, for testers comfortable with DevTools):
   - With packages disabled, POST `payment_method=ewallet` (or `code`) to `/?page=do_register`
   - **Expected:** the account is created as free/pending, the e-wallet is not charged, and no code is consumed

---

### 5) Disable the toggle and verify restoration
1. Go back to `Admin > Settings > Maintenance & Security` (`/?page=admin_settings#tabPane-maint`)
2. Uncheck **"Disable Packages"**
3. Click **Save Settings**
4. You should see success message

**Expected:**
- Free Registration dropdown becomes **enabled** again (you can change it)
- Go to `Admin > Packages` (`/?page=admin_packages`)
- **"+ New Package"** button is enabled again
- **Edit** buttons are enabled again
- Packages return to their previous states as they were before disabling (they were set to inactive when disabled, so after re-enabling they remain inactive unless you manually activate them - this is expected behavior based on implementation)

---

## Pass/Fail Criteria
- [ ] Toggle enables/disables correctly
- [ ] Free Registration forced ON and read-only when packages disabled
- [ ] All packages show as Inactive when disabled
- [ ] "+ New Package" button disabled when packages disabled
- [ ] "Edit" buttons disabled when packages disabled
- [ ] "View" still works
- [ ] Server blocks saving packages when disabled
- [ ] Registration (`?page=register`) skips "Select Package" and creates a free/pending member
- [ ] Genealogy "Register New Member" modal also opens on Account Setup
- [ ] Re-enabling restores UI state (free reg editable again, buttons enabled)

---

## Notes for Tester
- If the toggle doesn't appear, check you're on the Maintenance & Security tab
- The implementation forces all packages to inactive when disabling - this is intentional per requirements
- Free registration must remain ON while packages are disabled (cannot be turned off)
- This feature is admin-only
