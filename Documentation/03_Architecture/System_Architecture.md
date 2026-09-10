# High-Level System Architecture

```text
Status: Verified
Last Verified: 2026-09-08
Source: Docker & Infrastructure Audit
Owner: eConstruction Supply Development Team
```

## 1. Multi-Tier Application Architecture

The platform follows a modular Multi-Tier Web Architecture running in containerized Docker services:

```mermaid
flowchart TD
    subgraph ClientTier["Client Tier"]
        Desktop["Desktop Browsers (Chrome / Firefox / Edge)"]
        Tablet["POS Touch Terminals / Tablets"]
        Mobile["Mobile Web Browsers"]
    end

    subgraph PresentationTier["Presentation & Routing Tier"]
        WebContainer["econstructionsite-web (Apache 2.4 / PHP 7.4)"]
        Router["Path-based Routing Engine"]
    end

    subgraph ServiceTier["Application Logic Tier"]
        AuthModule["Authentication & RBAC Middleware"]
        CatalogModule["3-Tier Catalog & Spec Engine"]
        CartModule["Cart & Pricing Calculation Engine"]
        POSModule["POS Terminal & Checkout Processor"]
        RestockModule["Inter-Store Restock & Transfer Engine"]
        AuditModule["Order Activity Logging Engine"]
    end

    subgraph DataTier["Data Persistence Tier"]
        DBContainer["econstructionsite-db (PostgreSQL 15 Container)"]
        Storage["Local Assets & Image File Storage (/assets/uploads/)"]
    end

    ClientTier --> WebContainer
    WebContainer --> Router
    Router --> ServiceTier
    ServiceTier --> DBContainer
    ServiceTier --> Storage
```
