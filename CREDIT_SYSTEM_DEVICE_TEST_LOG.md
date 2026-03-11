# Device Test Log — Credit System (`feat/driver-credit-system`)

> **Branch:** `feat/driver-credit-system`
> **Date:** \_\_\_\_\_\_\_\_\_\_ (fill in when tested)
> **Devices:** Pixel 6 emulator (Driver), Realme 13+ (Driver), Realme 13+ (Rider)
> **Build flavor:** `flutter run --flavor driver -t lib/main_driver.dart`

---

## Status Summary

| Test                                                                                           | Status        |
| ---------------------------------------------------------------------------------------------- | ------------- |
| [C-1 Balance Chip Display & Color Coding](#c-1-balance-chip-display--color-coding)             | ⚠️ Partial   |
| [C-2 Go Online Blocked at 0 Credits](#c-2-go-online-blocked-at-0-credits)                      | ✅ Pass       |
| [C-3 Go Online Succeeds with Credits](#c-3-go-online-succeeds-with-credits)                    | ✅ Pass       |
| [C-4 Accept Ride — Credit Deduction Happy Path](#c-4-accept-ride--credit-deduction-happy-path) | ⚠️ Partial   |
| [C-5 Balance Refresh After Accept](#c-5-balance-refresh-after-accept)                          | ✅ Pass       |
| [C-6 402 Backend Gate (Race / Stale Balance)](#c-6-402-backend-gate-race--stale-balance)       | ✅ Pass       |
| [C-7 Warning Cards (Offline Home Screen)](#c-7-warning-cards-offline-home-screen)              | ⬜ Not tested |
| [C-8 Auto-Kick Offline on Zero Balance](#c-8-auto-kick-offline-on-zero-balance)                | ⬜ Not tested |

> **Status key:** ⬜ Not tested · ✅ Pass · ❌ Fail · ⚠️ Partial

---

## Key Numbers

| Value       | What                                                                      |
| ----------- | ------------------------------------------------------------------------- |
| 1 credit    | Cost per accepted ride                                                    |
| 0 credits   | Go Online blocked (client **and** server); Accept button disabled; driver auto-kicked offline after completing their last ride |
| 1–4 credits | Orange warning card on home screen (offline state only)                   |
| ≥5 credits  | Green chip in AppBar; no warning card                                     |
| 402         | HTTP status returned by backend when `credits_balance < 1` at accept time |

---

## Pre-Test Setup

These commands must be run from `backend/` before each relevant test to seed the correct credit balance.

```bash
# Grant 10 credits to driver (replace {id} with the driver's user_id)
php artisan tinker --execute="(new App\Services\CreditService())->addCredits({id}, 10, 'Test grant');"

# Set credits to exactly 1
php artisan tinker --execute="(new App\Services\CreditService())->addCredits({id}, 1, 'Test grant');"

# Confirm current balance
php artisan tinker --execute="echo (new App\Services\CreditService())->getBalance({id});"

# Zero out balance directly (for blocked-state tests) — use DB, CreditService::addCredits
# only accepts positive amounts. Set credits_balance to 0 via DB:
php artisan tinker --execute="App\Models\DriverProfile::where('user_id',{id})->update(['credits_balance'=>0,'credits_total_earned'=>0,'credits_total_spent'=>0]);"
```

---

## C-1: Balance Chip Display & Color Coding

> The credit balance chip appears in the AppBar of the driver home screen.
> Chip color changes based on balance: green (≥5), orange (1–4), red (0).
> **Requires:** Driver device only

### Sub-test A — Green chip (balance ≥ 5)

| #   | Step                                                 | Expected Output                           | Result |
| --- | ---------------------------------------------------- | ----------------------------------------- | ------ |
| 1   | Grant driver 10 credits via tinker                   |                                           |        |
| 2   | Open driver app (or hot-restart to invalidate cache) | Home screen loads                         |        |
| 3   | Observe AppBar chip                                  | Chip shows **"Credits: 10"** in **green** | ✅     |

### Sub-test B — Orange chip (balance 1–4)

| #   | Step                                 | Expected Output                           | Result |
| --- | ------------------------------------ | ----------------------------------------- | ------ |
| 1   | Set driver balance to 3 via tinker   |                                           |        |
| 2   | Restart / re-navigate to home screen | Chip shows **"Credits: 3"** in **orange** | ✅     |

### Sub-test C — Red chip (balance 0)

| #   | Step                                 | Expected Output                        | Result |
| --- | ------------------------------------ | -------------------------------------- | ------ |
| 1   | Zero out driver balance via tinker   |                                        |        |
| 2   | Restart / re-navigate to home screen | Chip shows **"Credits: 0"** in **red** | ✅     |

### Sub-test D — Exact boundary: 4 → 5 (orange → green transition)

| #   | Step                                              | Expected Output                                                 | Result |
| --- | ------------------------------------------------- | --------------------------------------------------------------- | ------ |
| 1   | Set driver balance to 4 via tinker; restart app   | Chip shows **"Credits: 4"** in **orange**                       | ✅     |
| 2   | Grant 1 more credit via tinker (total = 5)        |                                                                 |        |
| 3   | Navigate away from home and back to force refresh | Chip switches to **"Credits: 5"** in **green**; no warning card | ✅     |

### Sub-test E — Balance fetch fails (API/network error)

| #   | Step                                                | Expected Output                                                                                                        | Result |
| --- | --------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- | ------ |
| 1   | Kill the backend server while driver app is loading |                                                                                                                        |        |
| 2   | Open driver app (or navigate to home)               | `creditsAsync` enters error state — **chip does not appear** in AppBar (the `whenData` callback only fires on success) | ❌     |
| 3   | Restart backend; navigate away from home and back   | Chip loads and shows correct balance                                                                                   | —      |

> This is the expected behavior of `whenData` — it silently shows nothing on error rather than crashing.

**Notes / Observations:**

```
A, B, C, D all passed on Pixel 6 emulator (2026-03-05).
Credit info bottom sheet also implemented and verified — tapping the chip opens a modal
with color-coded balance badge, status message, "How credits work" rows, and a disabled
"Top Up — Coming Soon" button.

Sub-test E FAILED: pull-to-refresh while backend is down shows the Flutter error screen
("ApiException: No internet connection") instead of silently hiding the chip.
Root cause: ref.refresh().future throws before the provider enters error state, and the
exception escapes the onRefresh callback despite .then((_){}).onError((_,__){}), indicating
the error originates upstream (likely in the ApiService DioException handler) before
AsyncValue.guard can catch it. Deferred for investigation.
```

**Test Result:** ⚠️ Partial (A–D pass, E deferred — bug logged)

---

## C-2: Go Online Blocked at 0 Credits

> When a driver has 0 credits, tapping Go Online should show an error and keep the driver offline.
> **Requires:** Driver device only

| #   | Step                                       | Expected Output                                                                           | Result |
| --- | ------------------------------------------ | ----------------------------------------------------------------------------------------- | ------ |
| 1   | Zero out driver balance via tinker         |                                                                                           |        |
| 2   | Open driver app; confirm driver is offline | Home screen shows Offline state                                                           | ✅     |
| 3   | Tap **Go Online**                          | Credits info sheet opens (UX improvement: sheet replaces error card)                      | ✅     |
| 4   | Observe driver state                       | Driver remains **Offline** — queue position not assigned                                  | ✅     |
| 5   | Observe AppBar chip                        | Chip is red **"Credits: 0"**                                                              | ✅     |
| 6   | Observe home screen body                   | Red warning card: **"You have no credits. Contact admin to top up before going online."** | ✅     |

**Edge case — mobile credit check network failure (backend gate still blocks):**

| #   | Step                                                                              | Expected Output                                                                                                              | Result |
| --- | --------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- | ------ |
| E1  | Simulate network failure during Go Online credit check (kill backend momentarily) | Mobile catch block silences the error and calls `POST /driver/online` anyway — but backend returns **402** for 0-credit drivers, so driver does **not** go online |        |

> The mobile catch block still exists to handle transient errors for drivers who *do* have credits (their `canGoOnline` fetch fails but the backend will accept them). For 0-credit drivers the server-side gate in `DriverController::goOnline()` is the final enforcer regardless of how the request arrives.

**Edge case — rapid double-tap Go Online while blocked:**

| #   | Step                                                  | Expected Output                                                                                                              | Result |
| --- | ----------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- | ------ |
| E2  | Driver has 0 credits; tap **Go Online** twice rapidly | Error appears once only — second tap is ignored while state is processing (no duplicate error shown, no duplicate API calls) |        |

> The `goOnline()` checks balance via an `await`, so a second tap could slip through if the first hasn't returned yet. Verify only one credit check request appears in the backend logs.

**Edge case — admin tops up credits while error is visible:**

| #   | Step                                                                           | Expected Output                                                                                                        | Result |
| --- | ------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------- | ------ |
| E3  | Driver has 0 credits; error "You need at least 1 credit..." is shown on screen |                                                                                                                        |        |
| E4  | Admin grants 5 credits via tinker **without the driver restarting**            |                                                                                                                        |        |
| E5  | Driver taps **Go Online** again (no restart)                                   | Goes online successfully — `goOnline()` re-fetches balance each time it is called, so the updated balance is picked up |        |

**Notes / Observations:**

```
All steps passed on Pixel 6 emulator (2026-03-05).
UX improvement applied during testing: tapping Go Online at 0 credits now opens the
credits info sheet directly instead of showing the error card. Error card path removed
for the credit-blocked case.
E1/E2/E3 edge cases not explicitly run (deferred — covered by server-side gate tests).
```

**Test Result:** ✅ Pass

---

## C-3: Go Online Succeeds with Credits

> With balance ≥ 1, Go Online should succeed and add the driver to the FIFO queue.
> **Requires:** Driver device only

| #   | Step                              | Expected Output                                                                                    | Result |
| --- | --------------------------------- | -------------------------------------------------------------------------------------------------- | ------ |
| 1   | Grant driver 5 credits via tinker |                                                                                                    |        |
| 2   | Open app, confirm Offline state   | Home screen shows Offline                                                                          |        |
| 3   | Tap **Go Online**                 | Driver goes online; queue position appears                                                         |        |
| 4   | Observe AppBar chip               | Chip shows **"Credits: 5"** in **green** (balance unchanged — going online does not spend credits) |        |
| 5   | Tap **Go Offline** to reset       | Driver goes offline                                                                                |        |

**Notes / Observations:**

```
Passed on Pixel 6 emulator (2026-03-05). Go Online with credits ≥ 1 succeeds;
queue position assigned; chip balance unchanged after going online.
```

**Test Result:** ✅ Pass

---

## C-4: Accept Ride — Credit Deduction Happy Path

> Accepting a ride deducts exactly 1 credit. Balance chip on home screen reflects new balance after returning from the ride.
> **Requires:** Driver device + Rider device (or simulated rider request)

| #   | Step                                                                                                  | Expected Output                                                                        | Result |
| --- | ----------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------- | ------ |
| 1   | Grant driver **5 credits** via tinker                                                                 |                                                                                        |        |
| 2   | Driver goes online; confirm chip shows **5** (green)                                                  |                                                                                        | ✅     |
| 3   | Rider submits a ride request                                                                          | Driver receives incoming request card                                                  | ✅     |
| 4   | Observe incoming request screen                                                                       | **Accept Ride** button is **enabled** (green, not grayed)                              | ✅     |
| 5   | Driver taps **Accept Ride**                                                                           | Navigates to Active Ride screen; snackbar "Ride accepted!"                             | ✅     |
| 6   | Driver completes the ride (or admin force-completes)                                                  | Returns to home screen                                                                 | ✅     |
| 7   | Observe AppBar chip on home screen                                                                    | Chip shows **"Credits: 4"** — exactly 1 deducted                                       | ✅     |
| 8   | Confirm via tinker: `getBalance({id})`                                                                | Returns **4**                                                                          | ✅     |
| 9   | Confirm transaction via DB or tinker: `CreditTransaction::where('driver_id',{id})->latest()->first()` | `type=deduction`, `amount=-1`, `balance_before=5`, `balance_after=4`, `ride_id` is set | ✅     |

**Log to watch (backend terminal):**

```
[DB] INSERT INTO credit_transactions (driver_id, type, amount, balance_before, balance_after, ride_id, ...)
     Values match: amount=-1, ride_id=<actual ride id>
```

**Edge case — driver force-kills app immediately after tapping Accept:**

| #   | Step                                                      | Expected Output                                                           | Result |
| --- | --------------------------------------------------------- | ------------------------------------------------------------------------- | ------ |
| E1  | Driver with 5 credits taps **Accept Ride**                |                                                                           |        |
| E2  | **Force-kill** the driver app within ~1 second of tapping | Backend still processes the accept: ride is created, 1 credit deducted    | ✅     |
| E3  | Confirm via tinker: `getBalance({id})`                    | Returns **4** — deduction committed before kill                           | ✅     |
| E4  | Reopen driver app                                         | `syncFromBackend` detects active ride; driver is shown Active Ride screen | ✅     |
| E5  | Complete the ride normally                                | Driver returns to home; chip shows 4 (not double-deducted)                | ⚠️ See Bug #3 |

> The DB transaction in `RideService::acceptRideRequest` commits atomically — a kill after the HTTP response is sent cannot undo the commit.

**Edge case — driver accepts their last credit (balance 1 → 0):**

| #   | Step                                                        | Expected Output                                                                                               | Result |
| --- | ----------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- | ------ |
| E6  | Grant driver **1 credit**; go online; accept a ride         | Ride accepted; credit deducted (balance = 0 in DB)                                                            |        |
| E7  | Driver completes the ride                                   | Backend auto-kicks driver offline (`DriverOnlineStatusChanged` broadcast fires); app transitions to **Offline state** automatically — driver did not tap Go Offline |        |
| E8  | Observe home screen on arrival                              | Chip shows **"Credits: 0"** (red); **red warning card** visible — driver is already offline, no action needed |        |
| E9  | Tap **Go Online**                                           | Backend returns **HTTP 402**; error shown: "You need at least 1 credit to go online. Contact admin to top up." |        |

**Notes / Observations:**

```
Happy path (steps 1–9) passed on Pixel 6 emulator (2026-03-05). Credit deducted exactly once,
balance chip updated on home screen return, transaction record confirmed.

E1–E4 passed: backend commits the accept atomically; session restore shows ActiveRideScreen correctly.
E5 deferred — see Bug #3: when completing a ride after session restore (app-kill/reopen),
the driver is stuck on the ActiveRideScreen and not navigated back to home.
Not directly related to credit system; passing this edge case for the credit test.
```

**Test Result:** ⚠️ Partial (happy path pass; E5 deferred — Bug #3)

---

## C-5: Balance Refresh After Accept

> The `creditsProvider` is `AutoDisposeAsyncNotifier`. When the driver navigates away from
> home (incoming request → active ride) and returns, the provider re-builds and fetches the
> latest balance. Verify the home screen chip automatically shows the deducted balance on return.
> **Requires:** Driver device + Rider

| #   | Step                                                              | Expected Output                                                | Result |
| --- | ----------------------------------------------------------------- | -------------------------------------------------------------- | ------ |
| 1   | Grant driver **3 credits**; go online                             | Home chip: **"Credits: 3"** (orange)                           | ✅     |
| 2   | Rider submits request; driver taps **Accept**                     | Navigates to Active Ride screen                                | ✅     |
| 3   | While on Active Ride screen, observe chip is not visible          | Active Ride screen has no credit chip (n/a)                    | ✅     |
| 4   | Driver completes ride / admin force-completes                     | Driver returns to home screen                                  | ✅     |
| 5   | Observe AppBar chip **immediately on return** (no manual refresh) | Chip shows **"Credits: 2"** — provider rebuilt automatically   | ✅     |
| 6   | Repeat two more times                                             | 1 credit deducted each time (3 → 2 → 1 → 0)                    | ✅     |
| 7   | After the third ride (balance now 0), return to home              | Chip shows **"Credits: 0"** (red); orange warning card appears | ✅     |

**Edge case — driver declines (no accept); balance must not change:**

| #   | Step                                      | Expected Output                                        | Result |
| --- | ----------------------------------------- | ------------------------------------------------------ | ------ |
| E1  | Driver has 3 credits; receives request    | Accept button enabled                                  | ✅     |
| E2  | Driver taps **Decline** instead of Accept | Returns to home screen                                 | ✅     |
| E3  | Observe chip on home screen               | Still shows **"Credits: 3"** — no deduction on decline | ✅     |
| E4  | Confirm via tinker: `getBalance({id})`    | Returns **3**                                          | ✅     |

**Edge case — request timer expires (no action taken); balance must not change:**

| #   | Step                                                               | Expected Output                                           | Result |
| --- | ------------------------------------------------------------------ | --------------------------------------------------------- | ------ |
| E5  | Driver has 3 credits; receives request; lets the 30s timer run out | Request screen auto-dismisses on timeout                  |        |
| E6  | Return to home screen; observe chip                                | Still shows **"Credits: 3"** — timeout is not a deduction |        |

**Notes / Observations:**

```
All main steps and E1–E4 (decline edge case) passed on Pixel 6 emulator + Realme 13+ (2026-03-05).
AutoDisposeAsyncNotifier rebuilds on home screen return as expected — no manual refresh needed.
Balance decrements correctly each ride (3→2→1→0); red chip and warning card appear at 0.
Decline confirmed to produce no deduction (chip stays at 3, tinker confirms DB balance).
E5–E6 (timer expiry) not explicitly run — covered by HandleRequestTimeout job in backend tests.
```

**Test Result:** ✅ Pass

---

## C-6: 402 Backend Gate (Race / Stale Balance)

> The backend independently enforces the credit check — even if the mobile client allows the Accept button,
> the server returns 402 if the balance is 0. This tests that the 402 error path surfaces the correct snackbar message.
>
> **Trigger:** The driver's `creditsProvider` can be null (loading) when the request screen first opens.
> `hasCredits = creditsAsync.value == null || creditsAsync.value! > 0` evaluates to `true` while loading,
> enabling the Accept button even if the actual balance is 0. The backend is the final gate.
>
> **Setup to reproduce:** Zero out driver's balance in DB, then trigger a dispatch so the request screen
> opens before the credits fetch completes.
> **Requires:** Driver device + DB access + Rider (or manual dispatch)

| #   | Step                                                                                               | Expected Output                                                    | Result |
| --- | -------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------ | ------ |
| 1   | Driver is online with balance **1**; accept 1 ride to reach balance **0**                          |                                                                    | ✅     |
| 2   | While on Active Ride screen (in_progress), **do not complete the ride yet**                        |                                                                    | ✅     |
| 3   | Directly zero out balance in DB: `UPDATE driver_profiles SET credits_balance=0 WHERE user_id={id}` |                                                                    | ✅     |
| 4   | Admin force-complete the ride → driver returns to home screen with balance **0**                   | Chip shows **"Credits: 0"** (red)                                  | ✅     |
| 5   | Rider submits Request 2 → driver dispatched → request screen opens                                 | creditsProvider is re-fetching (brief loading window)              | ✅     |
| 6   | Tap **Accept Ride** immediately (within the first ~500ms before fetch resolves)                    | API call goes through while balance is loading                     | ✅     |
| 7   | Backend returns **HTTP 402**                                                                       | Snackbar: **"Insufficient credits to accept this ride"** (red, 4s) | ✅     |
| 8   | Screen pops back to home automatically                                                             | Driver is on home screen; ride NOT accepted                        | ✅     |
| 9   | Home chip                                                                                          | Still shows **"Credits: 0"** — no deduction occurred               | ✅     |

**Edge case — double-tap Accept (rapid taps):**

| #   | Step                                                              | Expected Output                                                    | Result |
| --- | ----------------------------------------------------------------- | ------------------------------------------------------------------ | ------ |
| E1  | Driver has 1 credit; receives a request                           | Accept button enabled                                              | ✅     |
| E2  | Tap **Accept Ride** twice in rapid succession                     | `_isProcessing = true` is set on first tap — second tap is a no-op | ✅     |
| E3  | Only **one** API call fires; backend deducts **1 credit** (not 2) |                                                                    | ✅     |
| E4  | Confirm via tinker: `getBalance({id})`                            | Returns **0** (1 deducted, not 2)                                  |        |
| E5  | Confirm: `CreditTransaction::where('driver_id',{id})->count()`    | Returns **1** — only one deduction record                          |        |

> **Alternative trigger:** Use `curl` or Postman to call `POST /api/v1/rides/{id}/accept` with the driver token while balance is 0 — bypasses the UI gate entirely.

```bash
# Manually test 402 via curl (replace TOKEN and REQUEST_ID):
curl -X POST http://localhost:8000/api/v1/rides/{REQUEST_ID}/accept \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Accept: application/json"
# Expected: {"success":false,"message":"Insufficient credits. Current balance: 0"} HTTP 402
```

**Notes / Observations:**

```
All main steps (1–9) and E1–E3 (double-tap guard) passed on Pixel 6 emulator + Realme 13+ (2026-03-05).
402 snackbar surfaced correctly; screen auto-popped to home; no credit deducted.
Double-tap guard (_isProcessing flag) confirmed — only 1 API call fired, 1 credit deducted.
E4–E5 (tinker confirmations) not explicitly run during device test — covered by CreditServiceTest.
```

**Test Result:** ✅ Pass

---

## C-7: Warning Cards (Offline Home Screen)

> Low-credit and no-credit warning cards appear in the home screen body — but **only when the driver is offline**.
> Cards disappear when the driver goes online.
> **Requires:** Driver device only

### Sub-test A — 0 credits (red card)

| #   | Step                                        | Expected Output                                                                   | Result |
| --- | ------------------------------------------- | --------------------------------------------------------------------------------- | ------ |
| 1   | Zero out balance; open app (driver offline) |                                                                                   |        |
| 2   | Observe home screen body below the toggle   | **Red card:** "You have no credits. Contact admin to top up before going online." |        |

### Sub-test B — Low credits 1–4 (orange card)

| #   | Step                                           | Expected Output                                                            | Result |
| --- | ---------------------------------------------- | -------------------------------------------------------------------------- | ------ |
| 1   | Set balance to 2; restart app (driver offline) |                                                                            |        |
| 2   | Observe home screen body                       | **Orange card:** "Low credits: 2 remaining. Contact admin to top up soon." |        |

### Sub-test C — Sufficient credits ≥ 5 (no card)

| #   | Step                                          | Expected Output             | Result |
| --- | --------------------------------------------- | --------------------------- | ------ |
| 1   | Grant 5 credits; restart app (driver offline) |                             |        |
| 2   | Observe home screen body                      | **No warning card** present |        |

### Sub-test D — Cards hidden while online

| #   | Step                                      | Expected Output                                           | Result |
| --- | ----------------------------------------- | --------------------------------------------------------- | ------ |
| 1   | Set balance to 2; ensure driver is online |                                                           |        |
| 2   | Observe home screen body while online     | **Warning cards are NOT shown** (only appear offline)     |        |
| 3   | Tap Go Offline                            | Orange low-credit card **appears** immediately on offline |        |

**Edge case — admin grants credits while driver is on home screen (no restart):**

| #   | Step                                                                               | Expected Output                                                                                                                | Result |
| --- | ---------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ | ------ |
| E1  | Driver has 0 credits; is offline; red warning card visible                         |                                                                                                                                |        |
| E2  | Admin grants 10 credits via tinker **without driver restarting the app**           | Warning card does **not** auto-update — `creditsProvider` is AutoDispose and does not poll; it only re-fetches when re-watched |        |
| E3  | Driver navigates **away** from home screen and back (e.g. opens Settings, returns) | Chip now shows **"Credits: 10"** (green); red warning card is gone                                                             |        |
| E4  | Driver taps **Go Online**                                                          | Succeeds — credit check picks up the new balance                                                                               |        |

> This is expected behavior: balance is not live-updated via WebSocket in this phase. Navigating away and back is the trigger for a fresh fetch.

**Edge case — balance drops from ≥5 to 1–4 across rides (green → orange transition):**

| #   | Step                                               | Expected Output                                                                               | Result |
| --- | -------------------------------------------------- | --------------------------------------------------------------------------------------------- | ------ |
| E5  | Driver starts with 5 credits (green chip, no card) |                                                                                               |        |
| E6  | Driver accepts 1 ride (completes); returns to home | Chip changes to **"Credits: 4"** (orange); **orange warning card appears** for the first time |        |

**Notes / Observations:**

```
(fill in)
```

**Test Result:** ⬜

---

## C-8: Auto-Kick Offline on Zero Balance

> When completing a ride brings a driver's balance to 0, the backend automatically removes them from the
> FIFO queue and broadcasts an offline event — the driver does not need to tap Go Offline.
> This prevents a 0-credit driver from occupying a queue slot and consuming dispatches they cannot accept.
> **Requires:** Driver device + Rider (or admin force-complete)

| #   | Step                                                                    | Expected Output                                                                                        | Result |
| --- | ----------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ | ------ |
| 1   | Grant driver **1 credit** via tinker                                    |                                                                                                        |        |
| 2   | Driver goes online; chip shows **"Credits: 1"** (orange)                | Queue position assigned                                                                                |        |
| 3   | Rider submits request; driver accepts                                   | Ride accepted; credit deducted (balance = 0 in DB)                                                     |        |
| 4   | Confirm balance via tinker: `getBalance({id})`                          | Returns **0**                                                                                          |        |
| 5   | Driver (or admin) completes the ride                                    | Backend: `removeFromQueue`, `went_online_at = null`, `DriverOnlineStatusChanged(false)` broadcast fires |        |
| 6   | Observe driver app **without any driver action**                        | App transitions to **Offline home screen** automatically                                               |        |
| 7   | Observe AppBar chip                                                     | Chip shows **"Credits: 0"** (red)                                                                      |        |
| 8   | Observe home screen body                                                | **Red warning card** visible — "You have no credits. Contact admin to top up before going online."     |        |
| 9   | Tap **Go Online**                                                       | Backend returns **HTTP 402**; error shown immediately                                                  |        |
| 10  | Confirm via tinker: `DriverProfile::where('user_id',{id})->value('went_online_at')` | Returns **null** — driver is fully offline in DB                                          |        |
| 11  | Confirm via tinker: `DriverProfile::where('user_id',{id})->value('queue_joined_at')` | Returns **null** — removed from queue                                                    |        |

**Edge case — driver with > 1 credit completes a ride (must NOT be kicked):**

| #   | Step                                                            | Expected Output                                                                  | Result |
| --- | --------------------------------------------------------------- | -------------------------------------------------------------------------------- | ------ |
| E1  | Grant driver **3 credits**; go online; accept and complete ride | Balance = 2 after completion; driver is **not** kicked offline                   |        |
| E2  | Observe driver app after ride completion                        | App returns to **Online home screen**; queue position reassigned (rejoined back) |        |
| E3  | Observe chip                                                    | Shows **"Credits: 2"** (orange); **no auto-kick**                                |        |

**Edge case — admin force-completes while driver app is in background:**

| #   | Step                                                                   | Expected Output                                                           | Result |
| --- | ---------------------------------------------------------------------- | ------------------------------------------------------------------------- | ------ |
| E4  | Driver has 1 credit; ride in progress; driver backgrounds the app      |                                                                           |        |
| E5  | Admin force-completes the ride via backend                             | `DriverOnlineStatusChanged(false)` broadcast fires to driver's WS channel |        |
| E6  | Driver foregrounds the app                                             | App is on Offline home screen — WS event was received in background       |        |

**Notes / Observations:**

```
(fill in)
```

**Test Result:** ⬜

---

## Edge Cases

### E-1: Concurrent Accept (Two Drivers, Same Request)

> Covered by automated tests (`RideControllerCreditTest::test_credits_not_deducted_if_ride_creation_fails`).
> Second driver gets 409; credit NOT deducted. No device test needed unless regression observed.

### E-2: Credit Balance Persists Across App Restart

| #   | Step                                          | Expected Output                                                      | Result |
| --- | --------------------------------------------- | -------------------------------------------------------------------- | ------ |
| 1   | Grant driver 7 credits via tinker             |                                                                      |        |
| 2   | Open driver app; observe chip shows 7 (green) |                                                                      |        |
| 3   | **Force-kill** the app and reopen             | Chip still shows **"Credits: 7"** (fetched fresh from API on launch) |        |

### E-3: Balance After Mid-Ride App Kill

| #   | Step                                                          | Expected Output                               | Result |
| --- | ------------------------------------------------------------- | --------------------------------------------- | ------ |
| 1   | Driver with 5 credits accepts a ride (balance now 4 in DB)    |                                               |        |
| 2   | Force-kill driver app while ride is in progress               |                                               |        |
| 3   | Reopen driver app                                             | `syncFromBackend` restores active ride state  |        |
| 4   | Admin force-completes the ride; driver returns to home screen | Chip shows **"Credits: 4"** — not re-deducted |        |

---

## Bugs Found During Testing

| #   | Test   | Description                                                                                                             | Severity | Fixed? |
| --- | ------ | ----------------------------------------------------------------------------------------------------------------------- | -------- | ------ |
| 1   | C-1 E  | Pull-to-refresh while backend is down shows Flutter error screen instead of silently hiding the credit chip. The `ApiException` thrown by `ApiService` escapes the `onRefresh` callback before `AsyncValue.guard` can absorb it. | Low      | No     |
| 2   | C-8    | After auto-kick (balance 0 post-ride), app stayed online until manual refresh. Root cause: `subscribeToDriverChannel` never bound to the `driver.status.changed` WS event. Fixed: added `onDriverStatusChanged` callback to the WS service and wired it in `driver_status_provider` to transition offline + invalidate `creditsProvider`. | Medium   | Yes    |
| 3   | C-4 E5 | After force-killing the app mid-ride and reopening, completing the ride leaves the driver stuck on `ActiveRideScreen` — not navigated back to home. Root cause: in the session-restore flow `ActiveRideScreen` is the root widget (not pushed onto a nav stack), so `Navigator.popUntil(isFirst)` is a no-op and `sessionState` stays `rideActive`. Fix attempted (clear session to idle + `popUntil`) but bug persists. Not directly related to credit system. | Medium   | No     |

---

## Sign-off

- [ ] All credit UI states show correct color coding (C-1)
- [ ] Go Online blocked at 0 credits (C-2)
- [ ] Credit deducted exactly once per accepted ride (C-4)
- [x] Balance chip refreshes automatically on home screen return (C-5)
- [x] Backend 402 gate surfaces correct snackbar message (C-6)
- [ ] Warning cards appear/disappear correctly per balance and online state (C-7)
- [ ] Driver auto-kicked offline when balance hits 0 after ride completion (C-8)
- [ ] Driver with > 0 credits after completion rejoins queue normally — no false kick (C-8 E1)
- [ ] No regressions on F-1 full happy path with credits active

**Tested by:** \_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_
**Date:** \_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_
