# AI Database & SQL Development Rules

```text
Status: Verified
Last Verified: 2026-09-08
Owner: eConstruction Supply Development Team
```

1. **PostgreSQL 15 Dialect**: Use valid PostgreSQL syntax. E.g., use `LIMIT n OFFSET m` (not MySQL `LIMIT m, n`), use `CURRENT_TIMESTAMP` (not `NOW()`), use double quotes or standard column names.
2. **Parameterized Statements**: Always use PDO prepared statements (`$statement = $pdo->prepare(...); $statement->execute([...]);`). Never interpolate unsanitized `$_GET` or `$_POST` variables into SQL strings.
3. **Primary Key Sequences**: PostgreSQL sequences generate IDs on `INSERT`. Use `$pdo->lastInsertId()` to retrieve generated primary keys.
