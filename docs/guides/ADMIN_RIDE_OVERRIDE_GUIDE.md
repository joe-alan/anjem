# Admin Ride Override System - User Guide

## 🎯 What You CAN Do

### 1. **View Stuck Rides**
- **Where**: Admin Dashboard → Live Monitoring → Stuck Rides section
- **What**: See all rides that haven't been updated in over 2 hours
- **Details Shown**:
  - Ride ID
  - Driver and Rider names
  - Route (pickup → destination)
  - Current status (matched, accepted, driver_arrived, in_progress)
  - How long it's been stuck (in hours)

### 2. **Force Complete a Stuck Ride**
- **When**: Driver app crashed, network issues, or ride genuinely completed but status not updated
- **How**:
  1. Click "Force Update" button on stuck ride
  2. Select "Complete" from dropdown
  3. Enter reason (min 10 characters) - **REQUIRED**
  4. Choose whether to notify users (checked by default)
  5. Confirm action
- **Result**:
  - Ride status changes to `completed`
  - Audit log created with your action
  - WebSocket event sent to both apps
  - **Orange banner appears** in rider and driver apps showing your reason
  - Optional push notifications sent

### 3. **Force Cancel a Stuck Ride**
- **When**: Ride cannot be completed (driver quit, accident, severe technical issues)
- **How**: Same as force complete, but select "Cancel" instead
- **Result**:
  - Ride status changes to `cancelled`
  - Same logging and notification flow as complete

### 4. **View Detailed Ride Information**
- **What**: Before forcing an update, the modal shows:
  - Ride ID and current status
  - Driver and rider information
  - Route details
- **Purpose**: Make informed decision before overriding

### 5. **Track All Admin Actions**
- **Where**: Database table `admin_audit_logs`
- **What's Logged**:
  - Your admin user ID
  - Action type (force_ride_status)
  - Target ride ID
  - Old status → New status
  - Your reason for the action
  - Timestamp, IP address, user agent
  - Whether notifications were sent

---

## ❌ What You CANNOT Do

### 1. **Cannot Force Status to Intermediate States**
- ❌ Cannot force to `matched`, `accepted`, or `driver_arrived`
- ✅ Can only force to `completed` or `cancelled`
- **Why**: Intermediate states should only be set by drivers through normal flow

### 2. **Cannot Update Recently Active Rides**
- ❌ Only rides stuck for > 2 hours appear in Stuck Rides list
- ✅ Active rides updating normally won't appear
- **Why**: Prevents accidental interference with working rides

### 3. **Cannot Edit Ratings or Feedback**
- ❌ No ability to modify user-submitted ratings
- ❌ No ability to delete ratings
- ❌ No ability to view rating details from this interface
- **Why**: This feature was NOT implemented in current phase (rating management is separate feature request)

### 4. **Cannot Modify Ride Financial Details**
- ❌ Cannot change fare amount
- ❌ Cannot issue refunds
- ❌ Cannot adjust driver earnings
- **Why**: Financial changes require separate accounting system

### 5. **Cannot Delete Rides**
- ❌ Cannot permanently delete ride records
- ✅ Can only change status to completed/cancelled
- **Why**: Data preservation for analytics and dispute resolution

### 6. **Cannot Force Start or Resume Rides**
- ❌ Cannot reactivate a cancelled ride
- ❌ Cannot change completed ride back to in_progress
- **Why**: One-way terminal state protection

### 7. **Cannot Bypass Audit Trail**
- ❌ Cannot delete audit log entries
- ❌ Cannot force update without providing reason
- ❌ Cannot hide admin override from users
- **Why**: Transparency and accountability

### 8. **Cannot Bulk Update Multiple Rides**
- ❌ Must update each stuck ride individually
- ❌ No "Force Complete All" button
- **Why**: Each ride situation is unique and requires explicit review

---

## 📱 User Experience (What Rider/Driver See)

### When You Force Update a Ride:

**Mobile Apps Display**:
```
┌─────────────────────────────────────────────┐
│  🛡️ Admin Override: Driver app crashed     │
│  and unable to complete ride. Manually     │
│  marking as completed after confirming     │
│  with driver.                              │
└─────────────────────────────────────────────┘
```
- **Color**: Orange background with dark orange border
- **Icon**: Admin panel settings icon
- **Position**: Below status card, above map
- **Visibility**: Shows on BOTH rider and driver screens
- **Timing**: Appears immediately via WebSocket (no page refresh needed)

**Push Notifications** (if enabled):
- Title: "Ride Status Updated"
- Body: "Your ride status has been updated by admin: [your reason]"

---

## 🔒 Security & Permissions

### Who Can Use This?
- **REQUIRED**: User must have `role = 'admin'` in database
- **REQUIRED**: API token must have `admin:*` abilities
- **REQUIRED**: Must be authenticated via Laravel Sanctum

### Rate Limits
- **100 requests per minute** on admin endpoints
- Same as other admin operations

### Protection Against Abuse
1. **Minimum reason length**: 10 characters (prevents lazy explanations)
2. **Audit logging**: Every action is permanently logged
3. **Confirmation dialog**: Must confirm before force updating
4. **Status validation**: Only completed/cancelled allowed
5. **Ride ID validation**: Must be valid existing ride

---

## 🧪 Testing the Feature

### Setup (One-Time)
```bash
# 1. Generate admin token
php artisan tinker --execute="
\$admin = App\Models\User::where('email', 'admin@anjem.app')->first();
echo 'Token: ' . \$admin->createTokenWithAbilities(false, true) . PHP_EOL;
"

# 2. Store token in browser
# Open admin dashboard: http://localhost:8000/admin-dashboard.html
# In browser console (F12):
localStorage.setItem('adminToken', 'YOUR_TOKEN_HERE');
// Refresh page
```

### Test Scenarios

**Scenario 1: Force Complete a Stuck Ride**
1. Navigate to Live Monitoring page
2. Check "Stuck Rides" section
3. If no stuck rides, manually update a ride's `updated_at` to 3 hours ago in database
4. Click "Force Update" on stuck ride
5. Select "Complete" status
6. Enter reason: "Testing admin override - driver confirmed ride completed via phone call"
7. Keep "Send notifications" checked
8. Click "Force Update Status"
9. Verify:
   - ✅ Success alert appears
   - ✅ Ride disappears from stuck rides list
   - ✅ Check `admin_audit_logs` table for new entry
   - ✅ Open mobile app and see orange banner

**Scenario 2: Force Cancel Due to Driver Issue**
1. Follow same steps but select "Cancel"
2. Enter reason: "Driver vehicle breakdown, unable to complete ride. Manually cancelling after contacting both parties."
3. Verify same results

**Scenario 3: Verify Audit Trail**
```sql
-- View all admin override actions
SELECT
    aal.id,
    u.name as admin_name,
    aal.action_type,
    aal.target_id as ride_id,
    aal.changes,
    aal.reason,
    aal.created_at
FROM admin_audit_logs aal
JOIN users u ON u.id = aal.admin_id
WHERE aal.action_type = 'force_ride_status'
ORDER BY aal.created_at DESC;
```

---

## 📊 API Endpoints (For Reference)

### 1. List Stuck Rides
```http
GET /api/admin/rides/stuck?hours=2
Authorization: Bearer {admin_token}

Response:
{
  "success": true,
  "data": [...],
  "meta": {
    "current_page": 1,
    "total": 5,
    "threshold_hours": 2
  }
}
```

### 2. Get Ride Details
```http
GET /api/admin/rides/{rideId}
Authorization: Bearer {admin_token}

Response:
{
  "success": true,
  "data": {
    "id": 123,
    "status": "in_progress",
    "driver": {...},
    "rider": {...},
    ...
  }
}
```

### 3. Force Update Status
```http
POST /api/admin/rides/{rideId}/force-status
Authorization: Bearer {admin_token}
Content-Type: application/json

{
  "status": "completed",  // or "cancelled"
  "reason": "Driver app crashed, manually completing after driver confirmation",
  "notify_users": true    // optional, default true
}

Response:
{
  "success": true,
  "message": "Ride status force updated successfully",
  "data": {...}
}
```

---

## ⚠️ Best Practices

### DO:
- ✅ Always provide detailed, specific reasons
- ✅ Verify ride details before forcing update
- ✅ Contact driver/rider before overriding when possible
- ✅ Use complete status only when ride was genuinely completed
- ✅ Use cancel status for technical/safety issues
- ✅ Enable notifications so users understand what happened

### DON'T:
- ❌ Don't force update rides less than 2 hours old (they won't appear anyway)
- ❌ Don't use vague reasons like "fixing" or "stuck"
- ❌ Don't force complete if ride wasn't actually completed
- ❌ Don't bypass confirmation dialogs
- ❌ Don't delete audit logs (they're permanent by design)

### Example Good Reasons:
- ✅ "Driver app crashed during ride. Driver confirmed via phone that rider was successfully dropped off at destination. Manually completing ride."
- ✅ "Network outage caused ride to freeze at driver_arrived status. Both parties confirmed ride was completed 3 hours ago. Force completing to match reality."
- ✅ "Driver vehicle breakdown 10 minutes into ride. Unable to complete trip. Driver and rider both notified and agreed to cancellation."

### Example Bad Reasons:
- ❌ "stuck" (too vague)
- ❌ "fixing" (doesn't explain why)
- ❌ "test" (don't test on production rides!)

---

## 🔍 Troubleshooting

### "No stuck rides showing"
- Check if rides in database have `status` in non-terminal states
- Verify `updated_at` is > 2 hours old
- Try adjusting hours parameter: `/api/admin/rides/stuck?hours=1`

### "Force update fails with 403"
- Verify admin token is valid
- Check user has `role = 'admin'` in database
- Ensure token has `admin:*` abilities

### "Orange banner not showing in mobile app"
- Verify WebSocket connection is active
- Check mobile app console logs for status update events
- Verify `ActiveRideProvider` is subscribed to ride channel

### "Audit log not created"
- Check database migration was run: `admin_audit_logs` table exists
- Verify AdminAuditLog model is properly loaded
- Check Laravel logs for any errors

---

## 📝 Summary

**What This System Is For:**
- Emergency intervention when rides get stuck due to technical issues
- Maintaining data accuracy when normal flow is broken
- Providing transparency to users about admin actions

**What This System Is NOT For:**
- Regular ride management (use normal driver app flow)
- Financial adjustments (use separate accounting system)
- Rating management (separate feature)
- Bulk operations (must review each ride individually)

**Key Feature:**
- **Full transparency**: Users ALWAYS see when admin overrides occur and WHY

---

**Last Updated**: December 1, 2025
**Version**: 1.0
**Admin Dashboard**: http://localhost:8000/admin-dashboard.html
