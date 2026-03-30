# 🏢 MAP-HMS - Multi-Tenant Hostel Management System

[![Laravel](https://img.shields.io/badge/Laravel-12.33-red.svg)](https://laravel.com/)
[![React Native](https://img.shields.io/badge/React_Native-0.72-blue.svg)](https://reactnative.dev/)
[![TypeScript](https://img.shields.io/badge/TypeScript-5.8-blue.svg)](https://www.typescriptlang.org/)
[![Filament](https://img.shields.io/badge/Filament-3.x-orange.svg)](https://filamentphp.com/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-14-blue.svg)](https://www.postgresql.org/)
[![License](https://img.shields.io/badge/License-Proprietary-red.svg)]()

> **Production-Ready** | Multi-tenant hostel management system for educational institutions across India

**MAP-HMS** (Management and Access Portal - Hostel Management System) is a comprehensive multi-tenant platform featuring database-per-tenant architecture, complete offline-first mobile applications, and role-based access control for managing hostel operations in educational institutions.

---

## 📋 Table of Contents

- [Key Features](#-key-features)
- [Architecture](#-architecture)
- [Technology Stack](#-technology-stack)
- [Quick Start](#-quick-start)
- [Project Structure](#-project-structure)
- [Core Modules](#-core-modules)
- [Mobile Applications](#-mobile-applications)
- [Web Admin Panels](#-web-admin-panels)
- [Security & Compliance](#-security--compliance)
- [Testing](#-testing)
- [Deployment](#-deployment)
- [Documentation](#-documentation)
- [Contributing](#-contributing)
- [Support](#-support)

---

## ✨ Key Features

### 🏗️ Multi-Tenancy
- **Database-Per-Tenant**: Complete data isolation via separate PostgreSQL databases
- **Subdomain Routing**: Automatic tenant detection via subdomains
- **Tenant Onboarding**: Guided wizard for tenant setup (Campus → Hostel → Rooms → Staff)
- **Cross-Tenant Staff**: Staff members can be assigned to multiple tenants

### 📱 Mobile Applications
- **Dual Apps**: Separate Student and Staff applications
- **Offline-First**: Full offline functionality with automatic sync
- **QR Code Integration**: Student QR scanning for gate operations
- **Push Notifications**: Real-time alerts via FCM
- **Deep Linking**: `maphms://` scheme for direct navigation
- **Storage Management**: Intelligent storage warnings at 75%/90% thresholds

### 🌐 Web Admin Panels
- **Super Admin**: System-wide tenant and staff management
- **Campus Manager**: Complete CRUD operations for all tenant resources
- **Rector Panel**: College oversight and gate pass approvals
- **College Management**: Read-only analytics dashboard

### 🔐 Security
- **MASVS L2 Compliance**: Mobile Application Security Verification Standard
- **Step-Up Authentication**: OTP verification for sensitive operations
- **PII Protection**: Tap-to-reveal for sensitive information
- **Audit Logging**: Comprehensive activity tracking
- **Tenant Isolation**: Complete database separation

### 📊 Core Modules
- ✅ **Onboarding**: Tenant/campus/hostel setup wizard
- ✅ **Student Management**: Import, allocation, activation workflows
- ✅ **Out-Pass System**: Leave requests with Rector approval
- ✅ **Gate Management**: QR scanning, visitor logs, duty handover
- ✅ **Attendance (V2)**: Session-based, room-wise marking
- ✅ **Tickets**: Multi-category support with SLA tracking
- ✅ **Checklists**: Daily auto-generation with photo evidence
- ✅ **Notices**: Role-based targeting with push notifications
- ✅ **Laundry**: Request → Pickup → Wash → Deliver workflow
- ✅ **Sports**: Facility booking and equipment tracking
- ✅ **Reports & Exports**: Async jobs with presigned downloads

---

## 🏗️ Architecture

### System Components

```
MAP-HMS System
├── 🌐 Web Layer (Laravel 12 + Filament 3)
│   ├── Super Admin Panel (/admin)
│   ├── Campus Manager Panel (/campus-manager)
│   ├── Rector Panel (/rector)
│   └── College Management Panel (/college-management)
│
├── 📱 Mobile Layer (React Native 0.72)
│   ├── Student App (Self-service features)
│   └── Staff App (Role-based dashboards)
│
├── 🔌 API Layer (Laravel REST API)
│   ├── Authentication & Authorization
│   ├── Multi-tenant Middleware
│   ├── Rate Limiting
│   └── Background Jobs (Queues)
│
└── 🗄️ Data Layer
    ├── Central Database (Users, Tenants, System Data)
    └── Tenant Databases (Per-tenant PostgreSQL databases)
```

### Multi-Tenant Architecture

```
Tenant (University/Organization)
├── Campus (Physical location)
│   ├── Hostel (Residence building)
│   │   ├── Room Block (Floor/Section)
│   │   │   ├── Room (Individual space)
│   │   │   │   └── Bed (Student allocation)
│   │   └── Staff (Warden, Guard, Supervisors)
│   └── Students
```

### Data Isolation

- **Database Level**: Separate PostgreSQL database per tenant
- **Application Level**: Global scopes enforce tenant isolation
- **Policy Level**: Authorization policies check tenant membership
- **API Level**: Middleware validates tenant access

---

## 🛠️ Technology Stack

### Backend
- **Framework**: Laravel 12.33.0
- **Admin Panel**: Filament 3.x
- **Database**: PostgreSQL 14+ (database-per-tenant)
- **Multi-tenancy**: Stancl Tenancy v3.9
- **Queue**: Redis + Laravel Horizon
- **Storage**: AWS S3 (presigned URLs)
- **Cache**: Redis 7

### Mobile
- **Framework**: React Native 0.72.10
- **Language**: TypeScript 5.8
- **State Management**: Zustand
- **Forms**: React Hook Form + Zod
- **Storage**: MMKV (secure local storage)
- **Navigation**: React Navigation v7
- **Push Notifications**: FCM (Firebase Cloud Messaging)

### Infrastructure (Hostinger VPS)
- **Hosting**: Hostinger VPS (8 vCPU, 32GB RAM, 400GB NVMe)
- **IP Address**: 72.62.79.173
- **Containerization**: Docker + Coolify
- **Reverse Proxy**: Traefik (automatic SSL)
- **CI/CD**: GitHub Actions (manual deployment via SSH)
- **Monitoring**: Laravel Horizon + Docker logs
- **SMS**: MSG91 (STPL)
- **Email**: SendGrid
- **CDN/DNS**: Cloudflare (DNS + SSL)

---

## 🚀 Quick Start

### Prerequisites

- **PHP**: 8.2+ with required extensions
- **Node.js**: 18+
- **PostgreSQL**: 14+
- **Redis**: 6+
- **Composer**: Latest version
- **Docker**: Optional (for containerized setup)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/paragmasteh/mapmars.git
   cd mapmars
   ```

2. **Backend Setup**
   ```bash
   cd api
   composer install
   cp .env.example .env
   php artisan key:generate
   
   # Configure database connection
   # Edit .env with your PostgreSQL credentials
   
   php artisan migrate
   php artisan db:seed  # Optional: seed demo data
   php artisan serve
   ```

3. **Mobile Setup**
   ```bash
   cd mobile
   npm install
   
   # For Android
   npx react-native run-android --variant=studentDebug
   npx react-native run-android --variant=staffDebug
   
   # For iOS (Mac only)
   cd ios && pod install && cd ..
   npx react-native run-ios --scheme=StudentApp
   npx react-native run-ios --scheme=StaffApp
   ```

4. **Access Applications**
   - **Super Admin**: `http://localhost:8000/admin`
   - **Campus Manager**: `http://demo-college.localhost:8000/campus-manager`
   - **Rector**: `http://demo-college.localhost:8000/rector`
   - **API Documentation**: `http://localhost:8000/api/documentation`

### First-Time Setup

1. **Create Super Admin User**
   ```bash
   php artisan tinker
   User::create([
       'name' => 'Super Admin',
       'email' => 'admin@example.com',
       'phone' => '+919876543210',
       'password' => Hash::make('password'),
       'role' => 'super_admin'
   ]);
   ```

2. **Create Tenant via Onboarding Wizard**
   - Login to Super Admin panel
   - Navigate to Tenants → Create
   - Follow the onboarding wizard
   - Complete setup: Tenant → Campus → Hostel → Rooms → Staff

---

## 📁 Project Structure

```
MAP-HMS/
├── api/                    # Laravel backend application
│   ├── app/
│   │   ├── Domain/         # Business logic modules
│   │   ├── Http/           # Controllers, Requests, Resources
│   │   ├── Models/         # Eloquent models
│   │   ├── Policies/       # Authorization policies
│   │   ├── Jobs/           # Background jobs
│   │   ├── Services/       # Business services
│   │   └── Filament/       # Admin panel resources
│   ├── database/           # Migrations, seeders, factories
│   ├── routes/             # API routes
│   ├── tests/              # Test suites
│   └── config/             # Configuration files
│
├── mobile/                 # React Native mobile applications
│   ├── src/
│   │   ├── app/            # App entry point
│   │   ├── features/       # Feature modules
│   │   ├── components/     # Reusable components
│   │   ├── services/       # API services
│   │   ├── stores/         # Zustand stores
│   │   └── utils/          # Utilities
│   ├── android/            # Android native code
│   └── ios/                # iOS native code
│
├── docs/                   # Documentation
│   ├── deployment/         # Deployment guides (Hostinger VPS)
│   ├── KB/                 # Knowledge base (FAQ, Troubleshooting)
│   └── ops/                # Operations runbooks
│
└── scripts/                # Utility scripts
```

---

## 🎯 Core Modules

| Module | Purpose | Status | Feature Flag |
|--------|---------|--------|---------------|
| **Onboarding** | Tenant/campus/hostel setup | ✅ Complete | Always enabled |
| **Student Management** | Import, allocation, activation | ✅ Complete | Always enabled |
| **Out-Pass** | Leave requests with approvals | ✅ Complete | Always enabled |
| **Gate Management** | QR scanning, visitor management | ✅ Complete | Security add-on |
| **Attendance (V2)** | Session-based attendance marking | ✅ Complete | Always enabled |
| **Tickets** | Support ticket system | ✅ Complete | Always enabled |
| **Checklists** | Daily maintenance tasks | ✅ Complete | Always enabled |
| **Notices** | Announcements and communications | ✅ Complete | Always enabled |
| **Laundry** | Laundry service management | ✅ Complete | Laundry add-on |
| **Sports** | Sports facility booking | ✅ Complete | Sports add-on |
| **Reports** | Analytics and exports | ✅ Complete | Always enabled |

---

## 📱 Mobile Applications

### Student App

**Features:**
- 🚪 **Gate Pass Management**: Create, track, and manage leave requests
- 📊 **Attendance Tracking**: View attendance records and statistics
- 📝 **Complaints System**: Submit and track complaints with photo evidence
- 🎫 **Support Tickets**: Create and manage support requests
- 👤 **Profile Management**: Update personal information
- 📢 **Notices**: View campus notices and announcements
- 💳 **Payment Status**: Track fee payments and dues
- ⚽ **Sports Booking**: Book sports facilities and equipment (if enabled)
- 🧺 **Laundry Requests**: Submit laundry service requests (if enabled)
- 📴 **Offline Support**: Work offline with automatic sync

### Staff App

**Features:**
- 🎯 **Role-Based Dashboards**: Customized interfaces for each role
- 🚪 **Gate Operations**: QR scanning, entry/exit management, visitor handling
- 📊 **Attendance Management**: Mark and manage student attendance
- ✅ **Approval Workflows**: Gate pass approvals with step-up authentication
- 🎫 **Ticket Management**: Create, assign, and track maintenance tickets
- 🚨 **Security Incidents**: Report and manage security issues
- 👥 **Visitor Management**: Handle visitor approvals and tracking
- 🆘 **Emergency Procedures**: Quick access to emergency exit procedures
- 📈 **Reports & Analytics**: Comprehensive reporting dashboards
- 📴 **Offline Support**: Full offline functionality with automatic sync

### Staff Roles

1. **Campus Manager**: Full administrative control
2. **Rector**: College oversight and gate pass approvals
3. **Warden**: Hostel management and attendance marking
4. **Guard**: Gate operations and security management
5. **HK Supervisor**: Housekeeping operations
6. **RM Supervisor**: Room allocation and management
7. **Laundry Manager**: Laundry service operations
8. **Sports Manager**: Sports facility management

### Mobile Technical Features

- **Offline Queue**: Actions queued when offline, sync when online
- **Optimistic Updates**: UI updates immediately for better UX
- **Conflict Resolution**: Smart handling of sync conflicts
- **Storage Management**: 5MB storage guard with warnings
- **OTP Authentication**: Phone number + OTP verification
- **Step-Up Authentication**: Additional verification for sensitive actions
- **PII Protection**: Tap-to-reveal for sensitive information
- **Screenshot Blocking**: Prevents screenshots on sensitive screens
- **Secure Storage**: Encrypted local storage (MMKV)

---

## 🌐 Web Admin Panels

### Super Admin Panel

**Access**: `http://localhost:8000/admin`

**Features:**
- Tenant management and creation
- Staff assignment across tenants
- System monitoring and analytics
- Onboarding wizard for new tenants
- Tenant switching (impersonation)

### Campus Manager Panel

**Access**: `http://{tenant-subdomain}.localhost:8000/campus-manager`

**Features:**
- Student management (import, activate, deactivate)
- Room allocation and management
- Gate pass approvals/rejections
- Attendance oversight
- Reports and exports (CSV/PDF)
- Bulk operations

### Rector Panel

**Access**: `http://{tenant-subdomain}.localhost:8000/rector`

**Features:**
- College oversight and monitoring
- Analytics dashboard (KPIs)
- Gate pass approvals with step-up authentication
- Student performance insights
- Attendance analytics

### College Management Panel

**Access**: `http://{tenant-subdomain}.localhost:8000/college-management`

**Features:**
- Read-only analytics dashboard
- Performance metrics (occupancy, attendance)
- Historical data and trends
- Export capabilities

---

## 🔐 Security & Compliance

### Authentication & Authorization

- **OTP-Based Login**: Phone number + OTP verification
- **Step-Up Authentication**: Additional verification for sensitive operations
- **Role-Based Access Control**: Granular permissions per role (11 roles)
- **Multi-Device Support**: One active device per user per app
- **Session Management**: Secure token-based sessions

### Data Protection

- **Tenant Isolation**: Complete database separation per tenant
- **PII Protection**: Tap-to-reveal for sensitive information
- **Audit Logging**: Comprehensive activity tracking
- **Secure Storage**: Encrypted local storage on mobile devices
- **File Upload Security**: Presigned S3 URLs, MIME allowlist, size caps

### Compliance

- **MASVS L2**: Mobile Application Security Verification Standard Level 2
- **Data Retention**: Configurable retention policies
- **Privacy Controls**: User consent and data management
- **Export Restrictions**: Controlled data exports with audit trails

---

## 🧪 Testing

### Test Coverage

- **Backend Tests**: 124+ Laravel tests (Pest/PHPUnit)
- **Mobile Tests**: 50+ React Native tests (Jest)
- **E2E Tests**: Complete user workflows (Detox)
- **Integration Tests**: API endpoints and database operations
- **Security Tests**: Authentication and authorization flows

### Test Commands

```bash
# Backend tests
cd api && php artisan test

# Mobile tests
cd mobile && npm test

# E2E tests (mobile)
cd mobile && npm run test:e2e

# Run all tests
make test
```

### Test Users

All test users use OTP: `123456`

- **Student**: `+91 98765 43210`
- **Guard**: `+91 98765 43211`
- **Warden**: `+91 98765 43212`
- **HK Supervisor**: `+91 98765 43213`
- **RM Supervisor**: `+91 98765 43214`
- **Campus Manager**: `+91 98765 43215`
- **Rector**: `+91 98765 43216`

---

## 🚀 Deployment

### Environment Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                    LOCAL → STAGING → PRODUCTION                     │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  LOCAL (Docker)          STAGING                    PRODUCTION      │
│  *.localhost:8000        *.staging.mapservices.in   *.mapservices.in│
│                                                                     │
│  Branch: feature/*       Branch: staging            Branch: main    │
│  Auto: No                Auto: Yes (on push)        Auto: Manual    │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Production Deployment (Hostinger VPS)

- **VPS**: Hostinger VPS (8 vCPU, 32GB RAM, 400GB NVMe)
- **Container Management**: Docker + Coolify
- **Database**: PostgreSQL 16 (local container)
- **Cache/Queue**: Redis 7 (local container)
- **Reverse Proxy**: Traefik with automatic SSL
- **CDN/SSL**: Cloudflare (proxied)
- **Storage**: Local NVMe (400GB available)

### Environment Setup

1. **Configure Environment Variables**
   ```bash
   cp .env.example .env
   # Edit .env with production values
   ```

2. **Run Migrations**
   ```bash
   php artisan migrate --force
   ```

3. **Optimize Application**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

4. **Start Queue Workers**
   ```bash
   php artisan horizon
   ```

### Docker Deployment (Local)

```bash
cd api
docker-compose up -d
```

### Production URLs

| Environment | URL | Purpose |
|-------------|-----|---------|
| **Production** | https://admin.mapservices.in | Live system |
| **Staging** | https://admin.staging.mapservices.in | Testing/QA |
| **Local** | http://admin.localhost:8000 | Development |

### Health Checks

```bash
# Production
curl https://api.mapservices.in/healthz

# Staging
curl https://api.staging.mapservices.in/healthz
```

See [Deployment Guide](docs/deployment/README.md) for detailed instructions.

---

## 📚 Documentation

### Core Documentation

- **[PRD v1.1](prd_v_1_1.md)**: Product Requirements Document
- **[API Specification](api_spec_v_1_1.md)**: Complete API documentation (OpenAPI)
- **[Data Dictionary](data_dictionary_v_1_1.md)**: Database schema and relationships
- **[ERD](erd_v_1_1.md)**: Entity Relationship Diagram
- **[Security Plan](SECURITY.md)**: Security controls and threat model
- **[Design System](design_system_v_1_0.md)**: UI/UX guidelines
- **[Coding Standards](coding_standards_v_1_0.md)**: PHP/TypeScript conventions

### Implementation Guides

- **[Quick Start Guide](docs/QUICK_START.md)**: Getting started quickly
- **[Development Guide](docs/DEVELOPMENT_Guide.md)**: Development workflow
- **[Mobile Guide](docs/MOBILE_Guide.md)**: Mobile app development
- **[Testing Strategy](docs/TESTING_Strategy.md)**: Comprehensive testing approach
- **[Deployment Guide](docs/deployment/README.md)**: Production deployment
- **[Architecture Overview](docs/ARCHITECTURE_Overview.md)**: System architecture

### Additional Resources

- **[Changelog](docs/CHANGELOG.md)**: Version history and updates
- **[Document Index](document_index_v_1_1.md)**: Complete documentation index
- **[Integrations Credentials](docs/INTEGRATIONS_Credentials.md)**: Third-party service setup
- **[User Stories](user_stories_v_1_1.md)**: User stories and acceptance criteria

---

## 🤝 Contributing

### Development Workflow

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass (`make test`)
6. Commit your changes (`git commit -m 'feat: add amazing feature'`)
7. Push to the branch (`git push origin feature/amazing-feature`)
8. Open a Pull Request

### Code Standards

- **PHP**: Follow PSR-12 coding standards (enforced by Pint)
- **TypeScript**: Use strict mode and proper typing
- **Commits**: Use [Conventional Commits](https://www.conventionalcommits.org/)
- **Documentation**: Update docs for new features
- **Tests**: All new features must include tests

See [CONTRIBUTING.md](CONTRIBUTING.md) for detailed guidelines.

---

## 📞 Support

### Getting Help

- **Documentation**: Comprehensive guides in `/docs` folder
- **GitHub Issues**: [Report bugs and request features](https://github.com/paragmasteh/mapmars/issues)
- **API Documentation**: OpenAPI specification at `/api/documentation`
- **Knowledge Base**: [Troubleshooting Guide](docs/KB/Troubleshooting.md)

### Contact

- **Repository**: [GitHub - paragmasteh/mapmars](https://github.com/paragmasteh/mapmars)
- **Issues**: [GitHub Issues](https://github.com/paragmasteh/mapmars/issues)
- **Discussions**: [GitHub Discussions](https://github.com/paragmasteh/mapmars/discussions)

---

## 📄 License

This project is proprietary software developed for MAP HMS system. All rights reserved.

---

## 🎉 Project Status

**Version**: v1.0  
**Status**: ✅ Production Ready  
**Last Updated**: November 2025

### ✅ Completed Features

- [x] Multi-tenant architecture with database isolation
- [x] Complete web admin panels (4 roles)
- [x] Mobile applications (Student + Staff)
- [x] Role-based access control (11 roles)
- [x] OTP authentication system
- [x] Offline-first mobile design
- [x] QR code integration
- [x] Comprehensive testing suite (200+ tests)
- [x] Production deployment configuration
- [x] Complete documentation

### 🚧 In Progress

- [ ] iOS App Store submission
- [ ] Android Play Store submission
- [ ] Advanced analytics dashboard
- [ ] Multi-language support
- [ ] Performance testing at scale

### 📋 Roadmap

- [ ] Biometric authentication
- [ ] Advanced offline capabilities
- [ ] Mobile app performance optimization
- [ ] Advanced security features
- [ ] Integration with external systems

---

**MAP-HMS** is a complete, production-ready hostel management system designed for modern educational institutions. 🎓
