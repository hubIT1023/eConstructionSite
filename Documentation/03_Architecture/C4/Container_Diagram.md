# C4 Level 2 - Container Diagram

```text
Status: Verified
Last Verified: 2026-09-08
Source: C4 Architectural Model
Owner: eConstruction Supply Development Team
```

```mermaid
flowchart TD
    subgraph Client_Applications["Client Applications"]
        WebBrowser["Web Browser (Storefront & Admin Portals)"]
        POSTerminal["POS Touch Terminal / Barcode Scanner"]
    end

    subgraph Docker_Host["Docker Host (eConstructionSite)"]
        WebServer["Web Server Container (econstructionsite-web)\nApache 2.4 + PHP 7.4\nServes HTML, processes business logic, REST/AJAX APIs"]
        DatabaseServer["Database Container (econstructionsite-db)\nPostgreSQL 15\nStores relational data, catalog, orders, and audits"]
        VolumeStorage["Mounted File Storage\n/assets/uploads/\nStores product images, receipts, sliders"]
    end

    WebBrowser -->|HTTPS / REST| WebServer
    POSTerminal -->|HTTPS / AJAX| WebServer
    WebServer -->|PDO PostgreSQL (Port 5432)| DatabaseServer
    WebServer -->|Read / Write| VolumeStorage
```
