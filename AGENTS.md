# Gunpla Hangar Project Rules

## Mandatory Project Rule: Zero Live Database CRUD, DDL, or Query Execution by AI Agents

### Core Directive: Human-Triggered Database Actions Only
All AI agents, subagents, and automated workflows operating within the Gunpla Hangar project are strictly forbidden from performing ANY direct database operations (CRUD, DDL, DML, or queries) against the live, staging, or containerized MySQL / MariaDB databases.

**Only the human user possesses the authority and the trigger to execute queries, migrations, seeds, or inspection commands against the database.**

---

### Strict Prohibitions for ALL Agents:
1. **Zero CLI Execution Against Database Engine:**
   - NEVER execute interactive or batch CLI commands targeting the database (e.g., `docker compose exec db mysql ...`, `mysql -u ...`, `mariadb -u ...`, `mysqldump ...`).
   - NEVER execute or trigger database migration runners, seeders, or database restore scripts (`restore.ps1`, backup restore commands) via terminal/command tools.
2. **Zero Direct CRUD / DDL / DML Execution:**
   - Agents must never run `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE TABLE`, `ALTER TABLE`, `DROP TABLE`, `TRUNCATE`, or any SQL command directly on the active database engine.
3. **No Direct Ad-Hoc Scripts Connecting to Live DB:**
   - Agents must never invoke temporary scratch scripts, PHP CLI scripts, or Python scripts that open a live PDO/MySQL connection to manipulate or query live database records.

---

### Allowed Scope (Authoring & Review Only):
- **Authoring Code & Migrations:** Agents MAY write, refactor, and maintain SQL files in `db_init/` or migration folders (e.g., `up_*.sql`, `down_*.sql`), and write PHP PDO queries inside application files (`src/`).
- **Providing Instructions to the User:** When database changes, queries, migrations, or data inspections are required, the agent MUST format the exact SQL queries or terminal commands in a clean, copy-pasteable block and instruct the user to execute it.

---

### Protocol for Database Operations
Whenever any agent needs a database operation executed:
1. Formulate the clean, validated SQL script or migration file.
2. Present the SQL script and/or the exact terminal command in a copy-pasteable code block to the user.
3. Explicitly ask the user to review and run the command/script themselves.
4. Wait for the user's confirmation or output before proceeding.
