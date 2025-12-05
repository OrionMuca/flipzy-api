# Project Scope: Demo & Waiting List Implementation

**Project:** Flipzy Platform | **Frontend:** Angular | **Backend:** Laravel 12 API

---

## Demo Functionality

The demo provides a fully functional preview of the Flipzy platform, allowing users to experience core features without registration. Users can browse properties, use search and filters, view property details, and explore the platform interface. Demo sessions are time-limited and do not persist data. The demo includes clear call-to-action prompts directing users to join the waiting list for full access.

**Demo Workflow:** User visits demo → Explores properties and features → Sees platform capabilities → Prompted to join waiting list

---

## Waiting List System

### User Registration Workflow

1. **Landing Page**: User visits waiting list landing page displaying available subscription plans (Free, Premium, VIP) with pricing and features.

2. **Plan Selection**: User selects desired subscription plan and optionally enters a coupon code for discount validation.

3. **Registration**: User provides email and name to register for the waiting list. System sends welcome email with verification link.

4. **Payment Processing**: User proceeds to Stripe Checkout to complete payment. System creates Stripe Checkout session with applied discount (if coupon used).

5. **Payment Confirmation**: Upon successful payment, Stripe webhook updates entry status and sends payment confirmation email to user.

6. **Account Conversion**: When platform launches, admin runs conversion command to automatically create user accounts from completed waiting list entries. Users receive account credentials via email.

### Coupon System Workflow

- **Coupon Validation**: Real-time validation checks coupon code, usage limits, date restrictions, and plan applicability.
- **Discount Application**: Supports percentage and fixed amount discounts applied during Stripe Checkout.
- **Usage Tracking**: System tracks coupon usage per user and total usage for analytics.

### Admin Management Workflow

- **Entry Management**: View, filter, and search waiting list entries. Monitor status (pending, payment_completed, account_created).
- **Coupon Management**: Create, edit, and manage discount coupons with usage analytics.
- **Account Conversion**: Run automated or manual conversion of waiting list entries to user accounts with bulk processing capability.
- **Analytics Dashboard**: View waiting list statistics, conversion rates, and coupon performance metrics.

---

## Technology Stack

**Frontend (Angular):** Angular framework with TypeScript, RxJS for API communication, and Stripe.js for payment integration.

**Backend (Laravel):** RESTful API with Laravel 12, MySQL database, Stripe payment integration, Laravel queues for email processing, and OpenAPI/Swagger documentation.

---

## Deliverables

**Backend:** Complete RESTful API endpoints for waiting list operations, Stripe integration, coupon management, email notifications, admin endpoints, and API documentation.

**Frontend:** Waiting list landing page, plan selection interface, coupon validation, registration form, Stripe Checkout integration, status tracking, and responsive design.

**Admin:** Waiting list management interface, coupon management dashboard, analytics, and account conversion tools.

---

## Success Criteria

Users can successfully register for waiting list, select plans, apply coupons, complete payments, and be converted to accounts. Admin can manage entries, create coupons, and monitor analytics. System processes payments securely and sends all required email notifications.

---

**Note:** This document outlines workflow and feature scope. Detailed technical specifications will be provided during development.
