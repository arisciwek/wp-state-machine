# Data Directory

This directory contains data files and defaults for the WP State Machine plugin.

## Directory Structure

```
/src/Data/
├── defaults/            Production-ready default workflow configurations (future use)
├── backups/             Automatic backups before workflow resets
└── README.md            This file
```

## Subdirectories

### `/defaults/`
Reserved for production-ready default workflow YML files.

**Current Status:** Reserved for future use

**Future Purpose:**
- Exported user workflows
- Custom templates
- Site-specific defaults
- Production overrides

**Note:** Default workflow YML files are currently located in `/examples/` directory for better visibility and educational purposes.

### `/backups/`
Automatic backups created before workflow reset operations.

**Purpose:**
- Backup workflows before reset
- Recovery from accidental resets
- Audit trail

**Auto-cleanup:** Backups older than 90 days are automatically removed

## Usage

### YML Workflow Resolution Order

The `WorkflowSeeder` class looks for YML files in this order:

1. `/src/Data/defaults/` - Production defaults (priority)
2. `/examples/` - Educational examples (fallback)

This allows you to override example workflows with production versions without modifying the examples.

### Database Schema Updates

Schema changes are handled directly in table definition files:
- `/src/Database/Tables/StateMachinesDB.php`
- `/src/Database/Tables/WorkflowGroupsDB.php`
- `/src/Database/Tables/StatesDB.php`
- `/src/Database/Tables/TransitionsDB.php`

**Development Mode:**
- Enable "Clear data on deactivate" in Settings > Database
- Deactivating plugin will drop all tables
- Reactivating will recreate tables with latest schema

**Production Mode:**
- Data preserved on deactivation
- Manual schema updates via phpMyAdmin or SQL queries

## Development Notes

### Why not put YML files here?

YML files remain in `/examples/` because:
1. **Visibility** - Easier for developers to find and learn from
2. **Documentation** - Examples include extensive comments
3. **Education** - Examples serve as learning resources
4. **Separation** - Clear distinction between code examples and data

### When to use `/defaults/`

Use this directory when you need to:
- Override example workflows for production
- Store site-specific workflow configurations
- Export custom workflows for deployment
- Maintain production-ready templates

## Related Documentation

- **Examples:** `/examples/` - Educational YML workflow examples
- **TODO-6106:** YML Seeder Implementation guide
- **Seeders:** `/src/Seeders/` - Workflow seeding classes

## Version History

- **v1.0.0** (2025-11-08) - Initial structure
  - Created migrations directory
  - Created defaults directory (reserved)
  - Created backups directory
  - Added README documentation

---

**Maintained by:** WPPM Development Team
**Last Updated:** 2025-11-08
