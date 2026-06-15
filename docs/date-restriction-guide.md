# Download Date Restriction Guide

This document explains how the WooCommerce Product Download Dates plugin controls subscriber access to downloadable files.

---

## Overview

The plugin restricts which downloadable files a subscriber can see based on **when they held an active subscription**. Instead of showing all files to all subscribers, each file is stamped with a date window, and a subscriber can only download files whose window overlaps with their subscription period.

This is ideal for magazine-style or monthly content subscriptions — a subscriber who paid for months 1, 2, and 4 (but not 3) will only see the files for those months.

---

## How to Enable It

1. Open a **Subscription** product in the WooCommerce product editor.
2. On the **General** tab (for simple subscriptions) or the **Variations** tab (for variable subscriptions), tick the **Enable Download Date Filtering** checkbox.
3. Save the product. The date fields will now appear next to each downloadable file.

> If the checkbox is not ticked, the plugin has no effect on that product — all files are shown normally.

---

## Setting Dates on Files

Each downloadable file gets two date fields:

| Field | Purpose |
|-------|---------|
| **Start date** | The earliest date from which the file should be accessible. |
| **End date** | The latest date until which the file should be accessible. Optional — see default below. |

Dates are entered in `YYYY-MM-DD` format via the calendar picker.

### Default end date behaviour

If you leave the **End date** blank, the plugin automatically sets it to **6 months after the start date**. This means a file set to `2024-01-01` with no end date will be accessible to any subscriber active between `2024-01-01` and `2024-07-01`.

### Files with no dates

If a file has **no start date at all**, it is always visible to all subscribers of that product, regardless of when they subscribed.

---

## How Access Is Determined

When a subscriber views their downloads, the plugin runs the following logic for each file:

1. **Release date gate** — if today's date is earlier than the file's start date, the file is hidden from everyone, regardless of subscription status. Future-dated files cannot be seen before they are released.

2. **Gather subscription periods** — the plugin looks at all of the customer's subscriptions for that product (up to 100) and builds a list of date ranges:
   - **Start** = the date the subscription was created.
   - **End** = the subscription's end date, if one exists. If the subscription has no set end date (e.g. it is ongoing or cancelled without a scheduled end), the range defaults to **subscription start + 1 year**.

3. **Check the file release date against each subscription period** — a file is accessible if the file's **start date** falls within at least one of the subscriber's subscription periods: `subscription_start ≤ file_start ≤ subscription_end`.

4. **Show or hide** — if no subscription period contains the file's release date, the file is hidden from the subscriber.

---

## Subscription Status and Access

The plugin intentionally bypasses WooCommerce's default download permission rules. Subscribers with the following statuses can **still see their historically accessible files**:

- `on-hold`
- `cancelled`
- `expired`

Access is determined purely by **dates**, not by current subscription status. A cancelled subscriber retains access to files from the period they were actively paying.

---

## Worked Examples

### Example 1 — Monthly magazine, subscriber who joined mid-run

A product has three files:

| File | Start date | End date |
|------|-----------|---------|
| Issue 1 (Mar) | 2025-03-26 | *(none set)* |
| Issue 2 (Jun) | 2025-06-26 | *(none set)* |
| Issue 3 (Sep) | 2025-09-26 | *(none set)* |

A subscriber who joined on `2025-06-15` has a subscription range of `2025-06-15 → 2026-06-15`.

- **Issue 1 (Mar)** — release date `2025-03-26` is before subscription start `2025-06-15`. **Hidden.**
- **Issue 2 (Jun)** — release date `2025-06-26` falls within subscription range. **Accessible** (from June 26 onwards).
- **Issue 3 (Sep)** — release date `2025-09-26` falls within subscription range. **Accessible** (from September 26 onwards).

> Note: Issue 2 is also hidden before `2025-06-26` even if the subscriber tries to access it early — the release date gate prevents it.

### Example 2 — Cancelled subscriber retains historical access

A subscriber joined `2025-01-01` and cancelled on `2025-04-30`. Their subscription range is `2025-01-01 → 2025-04-30`.

- **Issue 1 (Mar)** — release date `2025-03-26` falls within subscription range. **Accessible.**
- **Issue 2 (Jun)** — release date `2025-06-26` is after subscription end. **Hidden.**

### Example 3 — Paying a skipped month

A subscriber was active in January, lapsed in February, then renewed again in March. The plugin sees two separate subscription ranges. They later pay the missed February renewal — a third range is created — and they immediately gain access to the February file.

---

## Variable / Variation Products

For variable subscription products, each **variation** is managed independently. The Enable checkbox and file dates are set per-variation, not on the parent product.

---

## Technical Reference

| Post meta key | Applies to | Contents |
|---------------|-----------|---------|
| `_enable_subscription_download_filtering` | Product or variation | `yes` or `no` |
| `_wc_file_dates` | Simple subscription product | Comma-separated start dates, one per file, in file order |
| `_wc_file_dates_end` | Simple subscription product | Comma-separated end dates, one per file, in file order |
| `_wc_variation_file_dates` | Subscription variation | Comma-separated start dates for variation files |
| `_wc_variation_file_dates_end` | Subscription variation | Comma-separated end dates for variation files |

The plugin also exposes a filter hook for advanced customisation:

```php
// Modify the subscription date intervals used for access checks
add_filter( 'wc_pdd_subscription_intervals', function( $intervals ) {
    // $intervals is keyed by product ID, each value is an array of
    // [ 'start' => WC_DateTime, 'end' => WC_DateTime ] ranges
    return $intervals;
} );
```
