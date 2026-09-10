# Security Findings & Vulnerability Audit

```text
Status: Verified
Last Verified: 2026-09-08
Source: Application Security Review
Owner: eConstruction Supply Development Team
```

## Discovered Security Findings

### SEC-01: Legacy Password Hashing Algorithm
- **Severity**: `HIGH`
- **Description**: Authentication routines compute `md5($password)` for verification.
- **Remediation**: Migrate to `password_hash($password, PASSWORD_ARGON2ID)` with seamless re-hash on login.

### SEC-02: CSRF Token Coverage
- **Severity**: `MEDIUM`
- **Description**: While `CSRF_Protect.php` exists, certain AJAX endpoints rely solely on session authentication without verifying anti-CSRF request tokens.
- **Remediation**: Standardize `X-CSRF-Token` header validation across all JSON endpoints.

### SEC-03: SQL Parameter Binding
- **Severity**: `INFORMATIONAL` (Clean)
- **Description**: All core SQL queries use PDO prepared statements with positional or named parameters, effectively mitigating SQL injection vulnerabilities.
