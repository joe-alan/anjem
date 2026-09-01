# scripts/

API-driven helper scripts for exercising ride flows during development. They talk to the running
backend over `/api/v1`; there is no app build required.

| Script | Purpose |
| --- | --- |
| `test-rider-flow.sh` | Drive a ride end-to-end from the **driver** side via the API, so you can test the rider app without running the driver app. |
| `test-driver-debug.sh` | Ad-hoc driver-side API calls for debugging (go online, location updates, accept/decline). |
| `test-edge-cases.sh` | Fire a batch of edge-case requests (cancellations, timeouts, double-accept). Paste rider/driver tokens into the script before running. |

Prerequisites: `jq`, `curl`, and the backend running locally
(`php artisan serve` + `php artisan reverb:start`), plus a KYC-approved driver test account
(seeded by `php artisan migrate --seed`).

## `test-rider-flow.sh`

```bash
./test-rider-flow.sh simulate <request_id>   # accept -> en route -> arrived -> in progress -> completed
./test-rider-flow.sh accept   <request_id>   # accept only, then drive the rest manually
./test-rider-flow.sh complete <ride_id>      # complete a ride accepted earlier
```

Override defaults with env vars: `API_URL`, `DRIVER_EMAIL`, `DRIVER_PASSWORD`.

While `simulate` runs, the rider app should move through: driver matched → live driver marker →
"driver arrived" → active-ride tracking → rating screen.
