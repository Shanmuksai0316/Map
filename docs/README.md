# MAP-HMS Developer Knowledge Base

Welcome to the MAP-HMS (Management and Access Portal - Hostel Management System) developer documentation. This knowledge base provides comprehensive guides for developers working on this Laravel API + Filament + React Native mono-repo.

## 🚀 Quick Navigation

### Getting Started
- **[QUICK_START.md](./QUICK_START.md)** - Prerequisites, setup, and getting running
- **[ONBOARDING_QuickStart.md](./ONBOARDING_QuickStart.md)** - First 60 minutes with demo credentials
- **[ARCHITECTURE_Overview.md](./ARCHITECTURE_Overview.md)** - High-level system architecture and component relationships
- **[DEVELOPMENT_Guide.md](./DEVELOPMENT_Guide.md)** - Repository layout, conventions, commands, testing

### Backend Development
- **[BACKEND_Modules.md](./BACKEND_Modules.md)** - Complete guide to all Laravel modules (Onboarding, Rooms, Out-Pass, Gate, etc.)
- **[SECURITY_Practices.md](./SECURITY_Practices.md)** - Security guidelines, MASVS mapping, auth/roles
- **[TESTING_Strategy.md](./TESTING_Strategy.md)** - Pest tests, coverage, how to add new tests
- **[DATABASE_MIGRATION_GUIDE.md](./DATABASE_MIGRATION_GUIDE.md)** - Database schema and migrations

### Mobile Development
- **[MOBILE_Guide.md](./MOBILE_Guide.md)** - React Native app structure, navigation, stores, offline handling

### Operations & Deployment
- **[deployment/README.md](./deployment/README.md)** - Deployment overview ⭐ **START HERE**
- **[deployment/INFRASTRUCTURE.md](./deployment/INFRASTRUCTURE.md)** - Hostinger VPS architecture and configuration
- **[deployment/DEPLOYMENT_CHECKLIST.md](./deployment/DEPLOYMENT_CHECKLIST.md)** - Pre/post deployment verification
- **[ops/RUNBOOK.md](./ops/RUNBOOK.md)** - Operational runbooks
- **[ops/INCIDENT_PLAYBOOK.md](./ops/INCIDENT_PLAYBOOK.md)** - Incident response procedures
- **[RELEASE_Checklist.md](./RELEASE_Checklist.md)** - Release procedures

### Configuration
- **[INTEGRATIONS_Credentials.md](./INTEGRATIONS_Credentials.md)** - External service credentials (MSG91, SendGrid, FCM)
- **[TENANT_SUBDOMAIN_SETUP.md](./TENANT_SUBDOMAIN_SETUP.md)** - Multi-tenant subdomain configuration
- **[Backups_and_Restore.md](./Backups_and_Restore.md)** - Database backup procedures

### Knowledge Base
- **[KB/FAQ.md](./KB/FAQ.md)** - Frequently asked questions
- **[KB/Troubleshooting.md](./KB/Troubleshooting.md)** - Common issues and solutions
- **[KB/Glossary.md](./KB/Glossary.md)** - Terminology and definitions

## 📋 System Overview

MAP-HMS is a multi-tenant hostel management system with the following key components:

- **Laravel API** - RESTful backend with Filament admin panel
- **React Native Mobile** - Student and Staff mobile applications
- **Multi-tenant Architecture** - Campus/Hostel isolation with role-based access
- **Feature Flags** - Toggle modules like Laundry, Sports, Checklists
- **Offline Support** - Mobile apps work offline with sync capabilities

## 🏗️ Infrastructure

MAP-HMS is deployed on **Hostinger VPS** with the following stack:

| Component | Technology |
|-----------|------------|
| **VPS** | Hostinger (8 vCPU, 32GB RAM, 400GB NVMe) |
| **Container Management** | Docker + Coolify |
| **Database** | PostgreSQL 16 |
| **Cache/Queue** | Redis 7 |
| **Reverse Proxy** | Traefik (automatic SSL) |
| **CDN/DNS** | Cloudflare |

### Environment URLs

| Environment | URL | Branch |
|-------------|-----|--------|
| **Production** | https://admin.mapservices.in | `main` |
| **Staging** | https://admin.staging.mapservices.in | `staging` |
| **Local** | http://admin.localhost:8000 | `feature/*` |

## 🎯 Core Modules

| Module | Purpose | Status |
|--------|---------|--------|
| **Onboarding** | Tenant/campus/hostel setup | ✅ Complete |
| **Imports** | Bulk student/room data import | ✅ Complete |
| **Rooms** | Room allocation and management | ✅ Complete |
| **Out-Pass** | Student exit/entry permissions | ✅ Complete |
| **Gate** | Guard operations and gate control | ✅ Complete |
| **Visitors** | Guest visit management | ✅ Complete |
| **Attendance** | Daily attendance tracking | ✅ Complete |
| **Checklists** | Daily/weekly maintenance tasks | ✅ Complete |
| **Tickets** | Support ticket system | ✅ Complete |
| **Notices** | Announcements and communications | ✅ Complete |
| **Laundry** | Laundry cycle management | 🔧 Feature Flag |
| **Sports** | Sports equipment and events | 🔧 Feature Flag |
| **Dashboards** | Analytics and reporting | ✅ Complete |

## 🔧 Development Workflow

1. **Setup**: Follow [QuickStart](./QUICK_START.md)
2. **Development**: Use [Development Guide](./DEVELOPMENT_Guide.md)
3. **Testing**: Run tests before commits (see [Testing Strategy](./TESTING_Strategy.md))
4. **Security**: Review [Security Practices](./SECURITY_Practices.md)
5. **Deployment**: Use [Release Checklist](./RELEASE_Checklist.md) and [Deployment Guide](./deployment/README.md)

## 📞 Support

- **Code Issues**: Check [Troubleshooting](./KB/Troubleshooting.md) first
- **Questions**: See [FAQ](./KB/FAQ.md)
- **Security**: Follow [Security Practices](./SECURITY_Practices.md)
- **Contributing**: Read [CONTRIBUTING.md](../CONTRIBUTING.md)

---

*Last updated: January 2026*
