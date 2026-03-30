# MAP-HMS Glossary

Definitions and terminology used throughout the MAP-HMS system.

## Core Terms

### Tenant
**Definition**: A university or organization using the MAP-HMS system
**Context**: Multi-tenant architecture where each tenant has isolated data
**Example**: "MIT University" is a tenant with its own campuses and hostels

### Campus
**Definition**: A physical location or branch of a tenant organization
**Context**: Universities may have multiple campuses
**Example**: "Main Campus", "North Campus", "South Campus"

### Hostel
**Definition**: A residence building within a campus
**Context**: Where students live and are managed
**Example**: "Boys Hostel A", "Girls Hostel B", "International Hostel"

### Room Block
**Definition**: A section or floor within a hostel
**Context**: Organizational unit for room management
**Example**: "Ground Floor", "First Floor", "Block A", "Block B"

### Room
**Definition**: An individual living space within a room block
**Context**: Contains beds for student allocation
**Example**: "Room 101", "Room 205", "Dormitory A"

### Bed
**Definition**: An individual sleeping space within a room
**Context**: The actual allocation unit for students
**Example**: "Bed 1", "Bed 2", "Bunk A"

## User Roles

### Student
**Definition**: A resident student in the hostel
**Permissions**: Can request outpasses, view notices, submit tickets
**Context**: Primary end-user of the mobile app

### Guard
**Definition**: Security personnel managing gate operations
**Permissions**: Can record gate entries, manage visitors, view outpasses
**Context**: Uses mobile app for gate operations

### Campus Manager
**Definition**: Administrative staff managing campus operations
**Permissions**: Can manage rooms, students, outpasses, notices
**Context**: Uses Filament admin panel

### Rector
**Definition**: Senior administrative authority
**Permissions**: Can approve high-priority requests, view reports
**Context**: Has oversight permissions across the system

### Admin
**Definition**: System administrator with full access
**Permissions**: Can manage tenants, users, system settings
**Context**: Technical administrator role

## System Features

### Out-Pass
**Definition**: Permission for a student to leave the hostel premises
**Context**: Time-bound exit permission with approval workflow
**Example**: "Medical outpass", "Emergency outpass", "Planned outpass"

### Gate Entry
**Definition**: Record of student entering or exiting through the gate
**Context**: Audit trail of all gate movements
**Example**: "Student going OUT at 10:00 AM", "Student coming IN at 6:00 PM"

### Late Minutes
**Definition**: Number of minutes a student is late returning to hostel
**Context**: Used for attendance and discipline tracking
**Example**: "Student is 30 minutes late"

### Visitor
**Definition**: A guest visiting a student in the hostel
**Context**: Requires approval and gate management
**Example**: "Parent visit", "Friend visit", "Official visitor"

### Attendance Session
**Definition**: A daily period for marking student attendance
**Context**: Typically morning or evening roll call
**Example**: "Morning attendance", "Evening attendance"

### Checklist
**Definition**: Daily/weekly maintenance and compliance tasks
**Context**: Assigned to staff for completion and approval
**Example**: "Daily cleaning checklist", "Weekly safety inspection"

### Ticket
**Definition**: A support request for hostel-related issues
**Context**: Student can report problems, staff can track resolution
**Example**: "Plumbing issue", "Electrical problem", "Room maintenance"

### Notice
**Definition**: An announcement or communication to students/staff
**Context**: Can be targeted to specific audiences
**Example**: "Hostel rules update", "Event announcement", "Emergency notice"

## Technical Terms

### Multi-Tenancy
**Definition**: Architecture where multiple tenants share the same application
**Context**: Data isolation through tenant_id in all business tables
**Implementation**: Global scopes, policies, middleware

### Feature Flag
**Definition**: A configuration to enable/disable system features
**Context**: Allows gradual rollout and A/B testing
**Example**: "laundry_module", "sports_module", "checklists_module"

### Policy
**Definition**: Laravel authorization class defining user permissions
**Context**: Controls access to resources based on user roles and context
**Example**: "OutPassPolicy", "RoomPolicy", "TicketPolicy"

### Global Scope
**Definition**: Automatic query filtering applied to all model queries
**Context**: Ensures tenant isolation in all database operations
**Implementation**: Applied automatically to filter by tenant_id

### Audit Log
**Definition**: Record of all system activities for compliance
**Context**: Tracks who did what when for security and debugging
**Example**: "User login", "Outpass approved", "Room allocated"

### Offline Queue
**Definition**: System for handling actions when mobile app is offline
**Context**: Queues actions locally and syncs when connection restored
**Implementation**: AsyncStorage on mobile, background sync

### Webhook
**Definition**: HTTP callback for external service notifications
**Context**: Receives real-time updates from payment/SMS/email services
**Example**: "Payment success", "SMS delivery status", "Email bounce"

## Data Types

### Status Enums
**Definition**: Predefined values for entity states
**Context**: Standardized status tracking across the system

#### OutPass Status
- `pending`: Awaiting approval
- `approved`: Approved and active
- `rejected`: Denied by authority
- `expired`: Past validity period
- `completed`: Student returned

#### Ticket Status
- `open`: Newly created
- `in_progress`: Being worked on
- `resolved`: Issue fixed
- `closed`: Ticket completed

#### Attendance Status
- `present`: Student marked present
- `absent`: Student not present
- `late`: Student arrived late

### Priority Levels
**Definition**: Importance classification for requests and notifications
**Context**: Determines processing order and escalation

- `low`: Normal priority
- `medium`: Standard priority
- `high`: Important priority
- `urgent`: Critical priority

## Integration Terms

### MSG91
**Definition**: SMS service provider for notifications
**Context**: Sends SMS alerts for outpasses, attendance, emergencies
**Features**: DLT registration, delivery tracking

### SendGrid
**Definition**: Email service provider for notifications
**Context**: Sends email alerts and reports
**Features**: Template management, delivery tracking

### FCM (Firebase Cloud Messaging)
**Definition**: Push notification service for mobile apps
**Context**: Real-time notifications to student and guard apps
**Features**: Topic subscriptions, rich notifications

### Razorpay
**Definition**: Payment gateway for fee collection
**Context**: Handles online payments for hostel fees
**Features**: Multiple payment methods, webhook notifications

### AWS S3
**Definition**: Cloud storage for file uploads
**Context**: Stores documents, images, reports
**Features**: Presigned URLs, CDN integration

## Security Terms

### JWT Token
**Definition**: JSON Web Token for API authentication
**Context**: Stateless authentication for mobile and web clients
**Features**: Expiration, refresh tokens

### HMAC
**Definition**: Hash-based Message Authentication Code
**Context**: Verifies webhook authenticity from external services
**Implementation**: Shared secret for signature validation

### PII (Personally Identifiable Information)
**Definition**: Data that can identify individuals
**Context**: Requires special handling and encryption
**Examples**: Phone numbers, addresses, ID numbers

### Audit Trail
**Definition**: Complete record of system activities
**Context**: Required for compliance and security monitoring
**Implementation**: Comprehensive logging of all operations

## Performance Terms

### N+1 Query Problem
**Definition**: Inefficient database queries causing performance issues
**Context**: Loading relationships one by one instead of bulk loading
**Solution**: Eager loading with `with()` method

### Eager Loading
**Definition**: Loading related models in a single query
**Context**: Prevents N+1 queries and improves performance
**Implementation**: `Model::with(['relation1', 'relation2'])->get()`

### Caching
**Definition**: Storing frequently accessed data in memory
**Context**: Reduces database load and improves response times
**Implementation**: Redis for sessions, application cache for computed data

### Rate Limiting
**Definition**: Restricting number of requests per user/time period
**Context**: Prevents abuse and ensures fair resource usage
**Implementation**: Laravel rate limiting middleware

## Mobile Terms

### Offline-First
**Definition**: App design that works without internet connection
**Context**: Queues actions locally and syncs when online
**Implementation**: Local storage, background sync, conflict resolution

### Background Sync
**Definition**: Automatic synchronization when connection restored
**Context**: Processes queued actions and updates local data
**Implementation**: Network state monitoring, retry logic

### Push Notification
**Definition**: Real-time message delivery to mobile devices
**Context**: Alerts for outpasses, notices, ticket updates
**Implementation**: FCM integration with topic subscriptions

### Deep Linking
**Definition**: Direct navigation to specific app screens via URL
**Context**: Allows external links to open specific app content
**Implementation**: URL scheme handling in React Navigation

## Business Logic Terms

### Business Hours
**Definition**: Allowed times for certain operations
**Context**: Outpass validity, gate operations, visitor hours
**Example**: "Outpasses valid 6 AM to 10 PM"

### Grace Period
**Definition**: Additional time allowed beyond normal limits
**Context**: Late returns, deadline extensions
**Example**: "15-minute grace period for late returns"

### Escalation
**Definition**: Automatic advancement of unresolved issues
**Context**: Tickets, checklists, approvals moved to higher authority
**Implementation**: Time-based triggers, notification chains

### Idempotency
**Definition**: Operation that can be repeated safely
**Context**: Prevents duplicate processing of requests
**Implementation**: Unique request IDs, database constraints

---

*Glossary version: v1.0*
*Owner: MAP Co-Pilot*
