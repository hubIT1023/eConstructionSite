# Authentication Architecture & Session Security

```text
Status: Verified
Last Verified: 2026-09-08
Owner: eConstruction Supply Development Team
```

## Multi-Realm Session Management

- **Super Admin Session**: `$_SESSION['user']`
- **Supplier Staff Session**: `$_SESSION['supplier']`
- **Customer Session**: `$_SESSION['customer']`
- **Security Checkpoints**: Each protected page inspects the existence of the corresponding session key, redirecting unauthorized requests to the respective login page.
