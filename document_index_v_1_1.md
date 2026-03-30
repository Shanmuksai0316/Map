# Document Index v1.1

**Release:** MAP-HMS v1.0  
**Date:** January 2026  
**Owner:** MAP Co-Pilot  
**Tenancy Model:** Single database with tenant_id scoping (migrated from database-per-tenant)  
**Stack:** Laravel 12 + Filament v3 + Livewire 3, PostgreSQL 16, React Native 0.72, Redis 7, Docker + Coolify, FCM, MSG91, SendGrid, Hostinger VPS, Cloudflare

---

## Versioning & Change Control
- **SemVer per file** (e.g., `PRD_v1.1.md`). Minor bumps record doc-only refinements; major bumps reflect scope decisions.  
- **Changelog footer** in each file summarises deltas since previous version.  
- Source of truth remains this **Document Index**.

---

## Root-Level Specification Documents

| File | Description |
|------|-------------|
| **README.md** | Main project overview and quick start guide |
| **prd_v_1_1.md** | Product Requirements Document - comprehensive requirements |
| **user_stories_v_1_1.md** | Role/module stories with measurable ACs |
| **api_spec_v_1_1.md** | API specification (endpoints, auth, errors) |
| **erd_v_1_1.md** | Entity Relationship Diagram (Mermaid) |
| **data_dictionary_v_1_1.md** | Fields, types, indexes, constraints |
| **design_system_v_1_0.md** | Visual & UX guidelines |
| **coding_standards_v_1_0.md** | PHP/TypeScript conventions |
| **SECURITY.md** | Security practices and MASVS mapping |
| **CONTRIBUTING.md** | Contribution guidelines |

---

## Documentation (`/docs`)

### Getting Started
| File | Description |
|------|-------------|
| `README.md` | Documentation index and navigation |
| `QUICK_START.md` | Prerequisites and setup guide |
| `ONBOARDING_QuickStart.md` | First 60 minutes with demo credentials |
| `ARCHITECTURE_Overview.md` | System architecture overview |
| `DEVELOPMENT_Guide.md` | Development workflow and conventions |

### Backend Development
| File | Description |
|------|-------------|
| `BACKEND_Modules.md` | Guide to all Laravel modules |
| `SECURITY_Practices.md` | Security guidelines and auth/roles |
| `TESTING_Strategy.md` | Testing approach and coverage |
| `DATABASE_MIGRATION_GUIDE.md` | Database schema and migrations |

### Mobile Development
| File | Description |
|------|-------------|
| `MOBILE_Guide.md` | React Native app structure and offline handling |

### Deployment & Operations
| File | Description |
|------|-------------|
| `deployment/README.md` | Deployment overview (Hostinger VPS) |
| `deployment/INFRASTRUCTURE.md` | Complete infrastructure guide |
| `deployment/DEPLOYMENT_CHECKLIST.md` | Pre/post deployment verification |
| `ops/RUNBOOK.md` | Operational procedures |
| `ops/INCIDENT_PLAYBOOK.md` | Incident response guide |
| `ops/ObservabilityGuide_v1.0.md` | Monitoring and logging |
| `RELEASE_Checklist.md` | Release procedures |

### Configuration
| File | Description |
|------|-------------|
| `INTEGRATIONS_Credentials.md` | External service setup (MSG91, SendGrid, FCM) |
| `TENANT_SUBDOMAIN_SETUP.md` | Multi-tenant subdomain configuration |
| `Backups_and_Restore.md` | Database backup procedures |

### Knowledge Base
| File | Description |
|------|-------------|
| `KB/FAQ.md` | Frequently asked questions |
| `KB/Troubleshooting.md` | Common issues and solutions |
| `KB/Glossary.md` | Terminology and definitions |

### History
| File | Description |
|------|-------------|
| `CHANGELOG.md` | Version history and updates |

---

## Infrastructure (Hostinger VPS)

| Component | Technology |
|-----------|------------|
| **VPS** | Hostinger (8 vCPU, 32GB RAM, 400GB NVMe) |
| **IP Address** | 72.62.79.173 |
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

---

## Index Maintenance
- When any file changes, update its entry here and bump its version.  
- Keep **IDs/role names** consistent across all docs and code.

---

*Last Updated: January 2026*
