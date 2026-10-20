# System Refactoring & Workflow Implementation Instructions

This document outlines the required refactoring and implementation steps for the Raflora Enterprises Flower Arrangement Event Booking & Inventory System.

---

## 1. Sidebar & Navigation Consolidation
* **Goal:** Eliminate redundancy between `Quotations` and `All Bookings`.
* **Action:**
  * Remove/hide the `Quotations` sidebar menu item in the Admin view (`resources/views/layouts/admin.blade.php` or equivalent).
  * Consolidate all booking and quotation operations inside the `All Bookings` view (`/admin/bookings`).

---

## 2. Downpayment Verification & Stock Locking (Step 1)
* **Goal:** Allow admins to verify submitted downpayment reference numbers and lock allocated inventory.
* **Database Target:** `bookings` table.
* **Status Trigger:** `payment_pending` -> `downpayment_received`.
* **Implementation Details:**
  * In the Admin `All Bookings` view, when a booking status is `payment_pending`, display a **Payment Review Card/Modal** showing the client's submitted GCash/Payment reference details.
  * Add an action button: **`Verify Downpayment & Lock Booking`**.
  * **Controller Logic:**
    * Update booking `status` to `downpayment_received`.
    * Deduct/reserve allocated stock items from `inventory` based on the booking's item list to prevent double-booking.
    * Send/flag notification to the client that their booking is confirmed.

---

## 3. Date & Booking Status Display (Step 2)
* **Goal:** Clear visual status updates without restrictive date-blocking.
* **Implementation Details:**
  * **Client Panel:** Update the Client Booking History view to display the payment badge status (e.g., `Booking Confirmed / Downpayment Received`).
  * **Admin Panel:** Keep multi-booking support for the same date. Display events on the calendar/dashboard as categorized tags:
    * `Delivery / Home Assembly`
    * `On-Site Setup`

---

## 4. Admin Sidebar Notification Panel (Step 3)
* **Goal:** Provide a dedicated space for inventory alerts and payment verifications.
* **Implementation Details:**
  * Add a dedicated **`Notifications`** navigation item to the Admin sidebar.
  * Optionally display a red badge count for active alerts (e.g., pending payments, low stock checks).
  * System alerts to capture:
    1. *New Payment Reference Submitted by Client (Pending Verification)*.
    2. *Low Stock Alert: 4-week pre-event stock check trigger*.

---

## 5. Lifecycle Status Progression & Return Tracking (Steps 4 & 5)
* **Goal:** Complete the event lifecycle so non-perishable assets enter the Return Tracking module.
* **Status Progression Sequence:**
  `downpayment_received` -> `event_in_progress` -> `completed`
* **Implementation Details:**
  * **Admin Action Buttons:**
    * On `downpayment_received`: Add button **`Mark as Event In Progress / On-Site`**.
    * On `event_in_progress`: Add button **`Mark Event Completed`**.
  * **Event Completion Logic:**
    * Perishable goods (fresh flora, foam boards) are set as consumed/disposed.
    * Non-perishable items (metal arch frames, glass vases, lights) automatically populate into the **`Return Tracking`** module for return logging and damage auditing.

---

## 6. Final Payment & Financial Balance Tracking (Step 6)
* **Goal:** Give clients and admins clear insights into total quotes, paid amounts, and balances.
* **Database Columns to Verify/Ensure:**
  * `final_quoted_price`
  * `downpayment_amount`
  * `remaining_balance` (Calculated: `final_quoted_price` - `downpayment_amount`)
* **Implementation Details:**
  * Add financial columns to the `All Bookings` datatable and Client Booking details view:
    * **Total Quote** | **Downpayment Paid** | **Remaining Balance** | **Status**
  * When remaining balance is paid, provide an Admin action **`Log Final Payment`**.
  * Sets `remaining_balance` to `0.00` and updates status to **`Fully Paid & Completed`**.