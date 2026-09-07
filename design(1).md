# Past Question Marketplace PWA
## Product & Technical Design Specification

**Document Status:** Draft / MVP Specification  
**Version:** 1.0  
**Platform:** Progressive Web Application (PWA)  
**Primary Market:** Tertiary students in Ghana  
**Document Purpose:** Product, UX, technical architecture, security, and implementation reference

---

## 1. Executive Summary

The Past Question Marketplace is a student-focused Progressive Web Application (PWA) for discovering, purchasing, and accessing academic past questions and related study materials.

The platform will initially operate as an **online-first digital repository and marketplace**. Students create accounts, search for academic materials, purchase selected resources, and access their purchases through a secure web-based document viewer.

The system will avoid distributing original documents through ordinary public download links. Instead, purchased resources will remain in private storage and be delivered through an authenticated viewing system.

A later phase will introduce controlled offline reading through the PWA, allowing students to access previously purchased resources without an active internet connection while maintaining entitlement and security controls.

The long-term vision is to evolve the platform from a past-question repository into a broader academic marketplace supporting past questions, lecture notes, revision materials, practice resources, and potentially third-party student sellers.

---

# 2. Product Vision

## Vision Statement

> Build a trusted digital academic marketplace where students can easily find, purchase, and securely access the academic resources they need.

## Core Value Proposition

For students:

- Fast discovery of relevant past questions
- Simple purchasing
- Centralized personal library
- Mobile-first access
- Online and controlled offline reading
- No need to search through WhatsApp groups or scattered files

For the platform owner:

- Centralized inventory
- Digital payments
- Automated access management
- Sales and revenue tracking
- Reduced uncontrolled redistribution
- A scalable foundation for future academic products

---

# 3. Product Principles

The platform should follow these principles:

### 3.1 Mobile First

Most users are expected to access the platform from smartphones. Every important workflow must work comfortably on a mobile screen.

### 3.2 Simple

Students should be able to find and access a past question with minimal steps.

### 3.3 Secure by Default

Original academic files must never be exposed through predictable public URLs.

### 3.4 Searchable

Every academic resource should contain structured metadata so students can find it using course code, course name, institution, programme, level, semester, and academic year.

### 3.5 Scalable

The architecture must support future expansion without requiring a complete rewrite.

---

# 4. User Roles

## 4.1 Student

Students can:

- Register and log in
- Search for resources
- Browse categories
- Filter resources
- View product information
- Purchase resources
- View purchase history
- Access their personal library
- Read purchased resources online
- Save eligible resources for offline reading
- Manage their account
- Receive notifications

## 4.2 Administrator

Administrators can:

- Manage students
- Manage institutions
- Manage programmes
- Manage courses
- Upload resources
- Edit resources
- Publish/unpublish resources
- Set prices
- Manage orders
- Monitor payments
- View revenue
- View analytics
- Manage promotional content
- Suspend accounts
- Manage platform settings

## 4.3 Future Seller

A future marketplace version may allow students or approved contributors to sell their own materials.

Seller capabilities may include:

- Seller registration
- Resource submission
- Sales dashboard
- Earnings
- Payouts
- Resource management

This role is **not required for the initial MVP**.

---

# 5. Core User Journey

## Student Purchase Journey

```text
Landing Page
      |
      v
Search / Browse
      |
      v
Resource Details
      |
      v
Buy Now
      |
      v
Checkout
      |
      v
Payment
      |
      v
Payment Verification
      |
      v
Entitlement Created
      |
      v
My Library
      |
      v
Secure Viewer
```

The system must never grant access solely because a student reached the payment page.

Access is granted only after verified payment and successful creation of the student's entitlement.

---

# 6. Information Architecture

## Public Area

- Home
- Browse
- Search
- Institutions
- Programmes
- Courses
- Resource Details
- Login
- Registration
- Terms
- Privacy Policy
- Help

## Student Area

- Dashboard
- My Library
- Purchase History
- Recently Viewed
- Favourites
- Notifications
- Account Settings

## Admin Area

- Dashboard
- Resources
- Institutions
- Programmes
- Courses
- Students
- Orders
- Payments
- Revenue
- Analytics
- Promotions
- Settings

---

# 7. Homepage Design

The homepage should immediately communicate the purpose of the platform.

## Hero Section

Suggested messaging:

> **Find the Past Questions You Need.**

Supporting text:

> Search past questions and academic resources by institution, programme, level, course, semester, and academic year.

Primary action:

**Search Past Questions**

## Homepage Sections

1. Hero / Search
2. Browse by Institution
3. Browse by Programme
4. Popular Resources
5. Recently Added
6. How It Works
7. Benefits
8. Call to Action
9. Footer

---

# 8. Search and Discovery

Search is one of the most important features.

Students should be able to search by:

- Course code
- Course name
- Institution
- Programme
- Level
- Semester
- Academic year
- Resource title

Example:

```text
database
```

Possible results:

```text
ICT 201 — Database Systems
CS 305 — Database Management
ICT 401 — Advanced Database Systems
```

## Filters

```text
Institution
Programme
Level
Course
Semester
Academic Year
Price
```

## Sorting

- Relevance
- Newest
- Most purchased
- Lowest price
- Highest price

---

# 9. Resource Metadata

Each resource should have structured information.

Example:

```text
Title:
ICT 201 — Database Systems Past Questions

Institution:
University XYZ

Programme:
BSc Information Technology

Level:
200

Course Code:
ICT 201

Course:
Database Systems

Semester:
First Semester

Academic Year:
2024/2025

Price:
GH₵10.00

File:
Private storage reference
```

Additional fields:

- Description
- Cover/thumbnail
- Number of pages
- Resource type
- Status
- Publication date
- Seller/owner where applicable

---

# 10. Resource Details Page

The resource page should contain:

- Resource title
- Institution
- Programme
- Level
- Course
- Semester
- Academic year
- Description
- Price
- Page count
- Preview/sample
- Purchase button

Example:

```text
ICT 201 — Database Systems

University XYZ
BSc Information Technology
Level 200
First Semester
2024/2025

Description
Past questions covering major topics in Database Systems.

GH₵10.00

[ BUY NOW ]
```

The complete document must not be exposed before purchase.

---

# 11. Payment System

The initial market is Ghana, so the payment architecture should support local payment methods.

Potential methods:

- Mobile Money
- Card payments
- Other supported payment gateways

The application should use a payment abstraction layer so the backend is not tightly coupled to a single provider.

## Payment Flow

```text
Student
   |
   v
Checkout
   |
   v
Create Pending Order
   |
   v
Initiate Payment
   |
   v
Payment Gateway
   |
   v
Payment Callback / Verification
   |
   v
Verify Transaction
   |
   v
Mark Order Paid
   |
   v
Create Entitlement
   |
   v
Add Resource to Library
```

## Important Rules

- Never trust the frontend payment-success state.
- Verify transactions server-side.
- Use unique transaction/order references.
- Make payment callbacks idempotent.
- Prevent duplicate entitlement creation.
- Record all payment events.

---

# 12. Student Library

The library is the student's personal collection.

Example:

```text
MY LIBRARY

ICT 201
Database Systems
2024/2025

[ OPEN ]

--------------------------------

STAT 202
Statistics II
2023/2024

[ OPEN ]
```

Library features:

- Purchased resources
- Recently opened
- Favourites
- Offline availability
- Search within owned resources

A resource should remain associated with the student's account even if it is later removed from public search, subject to the platform's terms and refund/access policy.

---

# 13. Secure Document Viewer

The document viewer is a core component.

## Objectives

- Allow legitimate purchasers to read resources.
- Avoid exposing original files publicly.
- Prevent casual downloading.
- Support mobile screens.
- Support future offline access.

## Viewer Features

- Page navigation
- Previous/next page
- Zoom
- Fullscreen
- Page counter
- Search where technically supported
- Responsive layout
- Watermark
- Loading states
- Error states

Example:

```text
+--------------------------------+
| <  Database Systems       4/28 |
+--------------------------------+
|                                |
|        DOCUMENT PAGE           |
|                                |
|        QUESTION 1              |
|        ................        |
|        ................        |
|                                |
+--------------------------------+
|  < Previous    4 / 28  Next >  |
+--------------------------------+
```

---

# 14. Document Security Model

The original PDF/document must be stored privately.

Do not use:

```text
/public/uploads/ICT201.pdf
```

Instead:

```text
Private Object Storage
        |
        v
Backend Authorization
        |
        +--> Authentication check
        |
        +--> Purchase/entitlement check
        |
        +--> Viewing session
        |
        v
Secure Viewer
```

The server should determine whether the current user is authorized to access the requested resource.

## Important Security Principle

Security must not depend on:

- Disabled right-click
- Hidden buttons
- Frontend-only checks
- Obfuscated URLs

Those measures can provide minor deterrence but are not true access control.

---

# 15. Personalized Watermarking

Purchased resources should ideally be displayed with a user-specific watermark.

Example:

```text
Purchased by: Student Name
Account ID: 48291
Order: PQ-10482
```

Watermarks can be positioned:

- Header
- Footer
- Diagonally
- Repeated across rendered pages

The objective is deterrence and traceability.

The system should not claim that screenshots or screen recording are impossible.

---

# 16. Online-First Strategy

The initial release should prioritize online access.

Advantages:

- Easier to implement
- Better access control
- Centralized entitlement checks
- Easier analytics
- Easier revocation/suspension
- Lower initial complexity

Initial model:

```text
Purchase
   |
   v
My Library
   |
   v
Online Viewer
```

No ordinary PDF download should be offered in the MVP.

---

# 17. PWA Architecture

The website should be installable as a Progressive Web App.

Required PWA components:

- Web App Manifest
- Service Worker
- App icons
- Installable experience
- Responsive design
- Offline application shell
- Caching strategy
- Network detection
- Update strategy

The PWA should behave like an application while remaining accessible through a normal browser.

---

# 18. Offline Reading

Offline reading should be implemented after the online experience is stable.

## Concept

```text
ONLINE
   |
   v
Authenticate
   |
   v
Verify Entitlement
   |
   v
Save Authorized Resource
   |
   v
Encrypted / Controlled Local Storage
```

When offline:

```text
Open PWA
   |
   v
Local Authentication State
   |
   v
Check Offline Entitlement
   |
   v
Open Controlled Viewer
```

## Offline Entitlement

The platform may require periodic reconnection.

Example:

```text
Offline access:
Available

Last verification:
September 6, 2026

Next verification:
September 13, 2026
```

The actual period should be configurable by the administrator.

## Important Limitation

A web/PWA application cannot guarantee perfect DRM. A determined user can potentially capture displayed content.

The security objective is therefore:

> Prevent direct file distribution and make unauthorized redistribution significantly harder, while maintaining a convenient legitimate experience.

---

# 19. Admin Dashboard

The administrator dashboard should provide an immediate overview.

Example:

```text
-----------------------------------------
TOTAL REVENUE       GH₵ 12,450
TOTAL SALES         1,248
STUDENTS            3,892
RESOURCES             746
-----------------------------------------
```

## Dashboard Sections

- Revenue overview
- Sales overview
- Recent orders
- Popular resources
- New students
- Payment status
- Resource activity

---

# 20. Resource Management

Admin actions:

- Create resource
- Upload document
- Edit metadata
- Set price
- Publish
- Unpublish
- Archive
- Delete
- Replace document
- Manage preview

Resource statuses:

```text
Draft
Published
Unpublished
Archived
```

---

# 21. Institution / Programme / Course Management

The admin should manage the academic hierarchy.

```text
Institution
    |
    +-- Programme
          |
          +-- Level
                |
                +-- Course
                      |
                      +-- Resources
```

This structure makes browsing and filtering easier.

---

# 22. Orders and Payments

Admin should be able to view:

- Order ID
- Student
- Items
- Amount
- Payment method
- Payment status
- Transaction reference
- Date
- Entitlement status

Payment states:

```text
Pending
Successful
Failed
Cancelled
Refunded
```

---

# 23. Analytics

Initial analytics:

- Total revenue
- Number of purchases
- Average order value
- Active students
- New students
- Most purchased resources
- Most popular courses
- Sales by date
- Sales by institution
- Sales by programme

Future analytics can include:

- Conversion rate
- Search-to-purchase rate
- Resource views
- Viewer engagement
- Offline usage

---

# 24. Notifications

The platform may provide:

- Purchase confirmation
- Payment confirmation
- New resource notifications
- Account notifications
- Offline access expiry reminders
- Promotional notifications

Notification channels can later include:

- In-app
- Email
- Push notifications
- WhatsApp where appropriate and legally/commercially supported

---

# 25. Database Design

Initial database entities:

```text
users
institutions
programmes
courses
past_questions
question_files
orders
order_items
payments
entitlements
viewing_sessions
access_logs
favourites
notifications
```

## Example Relationships

```text
users
  |
  +---- orders
  |       |
  |       +---- order_items
  |               |
  |               +---- past_questions
  |
  +---- entitlements
          |
          +---- past_questions

institutions
  |
  +---- programmes
          |
          +---- courses
                  |
                  +---- past_questions
```

---

# 26. Entitlement Model

The entitlement system is central to access control.

Example:

```text
user_id: 182
resource_id: 492
order_id: 10482
access_type: permanent
purchased_at: 2026-09-06
offline_until: 2026-09-13
status: active
```

When the student opens a resource:

```text
Authenticated?
     |
     +-- No --> Reject
     |
    Yes
     |
     v
Has entitlement?
     |
     +-- No --> Reject
     |
    Yes
     |
     v
Create viewing session
     |
     v
Allow viewer
```

---

# 27. Access Logs

The system should maintain access records for security and analytics.

Potential fields:

```text
id
user_id
resource_id
action
timestamp
IP address
user agent
session ID
```

Possible actions:

- Opened resource
- Started viewer
- Page viewed
- Offline access
- Access denied

Privacy requirements should be considered when collecting and retaining logs.

---

# 28. Technology Stack

Recommended architecture:

## Frontend

- Next.js
- TypeScript
- Responsive UI
- PWA support

## Backend

- Laravel
- REST API

## Database

- PostgreSQL

## File Storage

- Private S3-compatible object storage

## Authentication

- Secure session/token-based authentication
- Email verification where appropriate
- Password reset

## Payments

- Payment gateway abstraction supporting Ghanaian payment methods

---

# 29. Application Architecture

```text
                    STUDENT
                       |
                       v
             +-------------------+
             |       PWA         |
             |     Next.js       |
             +---------+---------+
                       |
                       v
             +-------------------+
             |    Laravel API    |
             +----+---------+----+
                  |         |
          +-------+         +--------+
          v                          v
   +-------------+            +-------------+
   | PostgreSQL  |            |   Payment   |
   |  Database   |            |   Gateway   |
   +-------------+            +-------------+
          |
          v
   +-------------+
   | Private     |
   | File Store  |
   +-------------+
```

---

# 30. API Design

Example endpoint structure:

```text
/api/auth/register
/api/auth/login
/api/auth/logout

/api/resources
/api/resources/{id}

/api/institutions
/api/programmes
/api/courses

/api/orders
/api/orders/{id}

/api/payments/initiate
/api/payments/verify

/api/library
/api/library/{resource}

/api/viewer/{resource}
/api/viewer/{resource}/session

/api/favourites
/api/notifications
```

Admin:

```text
/api/admin/dashboard
/api/admin/resources
/api/admin/students
/api/admin/orders
/api/admin/payments
/api/admin/analytics
```

---

# 31. Security Requirements

Minimum security requirements:

- HTTPS everywhere
- Secure authentication
- Password hashing
- Server-side authorization
- Private document storage
- Payment verification
- Rate limiting
- CSRF protection where applicable
- Input validation
- Output escaping
- SQL injection protection
- Secure cookies
- Session management
- Access logging
- Admin role protection
- File-type validation
- File-size limits
- Malware/security scanning where practical
- Secure secrets management
- Database backups

Never rely on frontend checks for authorization.

---

# 32. File Upload Security

Admin uploads should be validated.

Checks should include:

- Allowed file type
- MIME type
- File extension
- Maximum size
- File integrity
- Safe filename generation

Original filenames should not become public storage paths.

Example:

```text
Original:
ICT 201 Final Past Questions.pdf

Stored:
private/resources/8f/4c/93b1c2e8.bin
```

The database maintains the logical relationship.

---

# 33. UX Requirements

The interface should be:

- Clean
- Modern
- Professional
- Mobile-first
- Fast
- Accessible
- Consistent

Avoid unnecessary visual complexity.

## Navigation

Mobile:

```text
Home
Search
Library
Account
```

Desktop can use:

```text
Logo | Browse | Search | Library | Account
```

Admin navigation can use a sidebar.

---

# 34. Design System

The UI should define reusable:

- Buttons
- Inputs
- Cards
- Badges
- Modals
- Alerts
- Tables
- Pagination
- Tabs
- Navigation
- Empty states
- Loading states
- Error states

Typography, spacing, radius, shadows, and component behavior should be consistent across the application.

---

# 35. Empty and Error States

The application must not leave blank screens.

Examples:

### No Search Results

> No past questions found for your search.

Action:

**Clear Filters**

### Empty Library

> Your library is empty.

Action:

**Browse Past Questions**

### Payment Failed

> We could not confirm your payment.

Actions:

**Try Again**

### Resource Unavailable

> This resource is currently unavailable.

---

# 36. Performance Requirements

Target:

- Fast first load
- Optimized images
- Lazy loading
- Code splitting
- API pagination
- Efficient database queries
- CDN delivery for public assets
- Caching for appropriate public data
- Private caching strategy for authorized resources

The document viewer should load pages progressively rather than unnecessarily downloading the entire repository.

---

# 37. SEO

Public resource pages should be indexable where appropriate.

SEO targets include:

```text
University + course + past questions
Course code + past questions
Programme + past questions
Institution + academic year
```

Private purchased documents must never be indexed.

---

# 38. Accessibility

The application should aim for WCAG-aligned accessibility.

Consider:

- Keyboard navigation
- Adequate contrast
- Accessible labels
- Screen-reader support
- Focus states
- Semantic HTML
- Error messaging
- Scalable text
- Touch-friendly controls

---

# 39. MVP Scope

The first production version should contain:

### Student

- Registration
- Login
- Search
- Filtering
- Resource details
- Checkout
- Payment
- My Library
- Online viewer
- Watermarking
- Purchase history
- Account management

### Admin

- Dashboard
- Resource management
- Institution management
- Programme management
- Course management
- Student management
- Order management
- Payment management
- Basic analytics

### Platform

- PWA installation
- Responsive design
- Private file storage
- Secure authorization
- Access logging

---

# 40. Post-MVP Scope

After MVP validation:

### Phase 2

- Offline reading
- Push notifications
- Favourites
- Reviews
- Coupons
- Advanced analytics
- Improved search
- Promotional campaigns

### Phase 3

- Seller accounts
- Student uploads
- Seller approval
- Commission system
- Seller analytics
- Payouts
- Ratings
- Academic notes
- Study guides
- Practice quizzes

### Phase 4

- Native Android application
- Native iOS application if commercially justified
- Advanced document protection
- AI-powered search/recommendations
- Personalized study features

---

# 41. Future Marketplace Model

If third-party sellers are introduced:

```text
Seller
   |
   v
Upload Resource
   |
   v
Admin Moderation
   |
   v
Published
   |
   v
Student Purchases
   |
   v
Payment
   |
   +---- Platform Commission
   |
   +---- Seller Earnings
```

Example:

```text
Sale price: GH₵10
Platform commission: 20%
Seller share: 80%
```

The commission rate should be configurable.

---

# 42. Business Model

Initial model:

> Platform owner uploads and sells resources directly.

Future model:

> Multi-vendor academic marketplace.

Possible revenue streams:

- Direct resource sales
- Marketplace commission
- Featured resources
- Promotional placements
- Institutional partnerships
- Premium subscriptions, if validated

The initial MVP should avoid unnecessary monetization complexity.

---

# 43. Copyright and Content Policy

The platform should only host materials that the platform owner is legally permitted to distribute.

Before allowing third-party uploads, establish:

- Content ownership rules
- Upload terms
- Copyright complaint process
- Takedown process
- Seller verification
- Prohibited content policy

The platform should not assume that every past examination paper is automatically free to redistribute.

---

# 44. Privacy

The platform will potentially process:

- Names
- Email addresses
- Account credentials
- Purchase information
- Access logs
- Payment references

The implementation should follow applicable privacy and data-protection requirements.

Only necessary data should be collected.

---

# 45. Backup and Recovery

Production systems should have:

- Automated database backups
- File-storage redundancy
- Recovery procedures
- Backup retention policy
- Monitoring
- Error logging

Backups should be tested periodically rather than merely configured.

---

# 46. Testing Strategy

## Functional Testing

Test:

- Registration
- Login
- Search
- Filtering
- Checkout
- Payment
- Library
- Viewer
- Admin actions

## Security Testing

Test:

- Unauthorized resource access
- Expired sessions
- Direct file access
- Broken object-level authorization
- Payment manipulation
- Role escalation
- Malicious uploads

## PWA Testing

Test:

- Installation
- Online mode
- Offline mode
- Reconnection
- Service worker updates
- Cache invalidation

## Mobile Testing

Test common:

- Android browsers
- iOS browsers
- Small screens
- Tablets
- Desktop browsers

---

# 47. Development Roadmap

## Sprint 1 — Foundation

- Repository setup
- Project architecture
- Database
- Authentication
- Design system
- PWA foundation

## Sprint 2 — Academic Repository

- Institutions
- Programmes
- Courses
- Resource CRUD
- Search
- Filters

## Sprint 3 — Marketplace

- Product pages
- Orders
- Checkout
- Payment integration
- Payment verification
- Entitlements

## Sprint 4 — Secure Viewer

- Private storage
- Viewer
- Authorization
- Watermarking
- Viewing sessions
- Access logs

## Sprint 5 — Admin

- Dashboard
- Resource management
- Student management
- Order management
- Payment management
- Analytics

## Sprint 6 — PWA

- Installation
- Service worker
- Offline shell
- Caching
- Update handling

## Sprint 7 — Offline Reading

- Offline entitlement
- Controlled local storage
- Offline viewer
- Revalidation

## Sprint 8 — Testing & Launch

- Security testing
- Payment testing
- Performance optimization
- Mobile testing
- Production deployment
- Monitoring

---

# 48. Definition of Done — MVP

The MVP is considered ready when:

- Students can create accounts.
- Students can find resources.
- Students can filter resources.
- Students can view resource information.
- Students can purchase resources.
- Payments are verified server-side.
- Purchased resources appear in the student's library.
- Unauthorized users cannot access purchased documents.
- Original documents are stored privately.
- Documents can be viewed online.
- Watermarking is functional.
- Admin can manage resources.
- Admin can manage students.
- Admin can view orders and payments.
- PWA installation works.
- Core workflows work on mobile.
- Critical security tests pass.
- Database and file backups are configured.

---

# 49. Recommended Initial Product Positioning

The product should not be marketed simply as:

> "A website for downloading past questions."

Instead:

> **A digital academic library for students.**

Core message:

> **Find. Purchase. Study.**

The platform provides students with a centralized place to discover and access academic resources while giving the owner a controlled and scalable digital sales platform.

---

# 50. Final Architecture Direction

The final direction is:

```text
                         STUDENTS
                            |
                            v
                    +---------------+
                    |   PWA / Web   |
                    +-------+-------+
                            |
                            v
                    +---------------+
                    |  Application  |
                    |    Backend    |
                    +---+-------+---+
                        |       |
             +----------+       +----------+
             v                             v
       +-----------+                 +-----------+
       | PostgreSQL|                 |  Payment  |
       | Database  |                 |  Gateway  |
       +-----------+                 +-----------+
             |
             v
       +-----------+
       |  Private  |
       |  Storage  |
       +-----------+
             |
             v
       Secure Viewer
             |
             v
       Online Reading
             |
             v
    Controlled Offline Reading
          (Phase 2)
```

## Product Evolution

```text
PHASE 1
Past Question Marketplace
        |
        v
PHASE 2
PWA + Offline Reading
        |
        v
PHASE 3
Academic Resource Marketplace
        |
        v
PHASE 4
Multi-Seller Student Platform
        |
        v
PHASE 5
Full Digital Academic Ecosystem
```

---

## Document Status

**Current Version:** 1.0  
**Implementation Priority:** MVP  
**Primary Platform:** PWA  
**Primary Access Model:** Online-first  
**Offline Access:** Post-MVP  
**Marketplace Sellers:** Future phase  
**Original Document Downloads:** Not part of MVP  
**Architecture:** Designed for future expansion
