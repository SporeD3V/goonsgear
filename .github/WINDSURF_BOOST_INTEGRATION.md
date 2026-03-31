# Windsurf + Laravel Boost Integration Guide

This project is optimized for **Windsurf IDE** with Laravel Boost MCP server integration.

## Configuration

### boost.json
```json
{
    "agents": ["copilot", "windsurf"],
    "guidelines": true,
    "mcp": true,
    "skills": [
        "laravel-best-practices",
        "livewire-development"
    ]
}
```

**Key Settings:**
- `"agents": ["copilot", "windsurf"]` - Enables optimization for both Copilot and Windsurf
- `"mcp": true` - Enables MCP server for tool access
- `"guidelines": true` - Loads AGENTS.md into agent context automatically
- `"skills"` - Domain-specific knowledge modules that auto-activate

## How Windsurf Uses Boost

### 1. MCP Tools Available to Cascade
Windsurf's Cascade AI has direct access to Laravel Boost MCP tools:

- **`database-query`** - Run read-only SQL queries against the database
- **`database-schema`** - Inspect table structure before writing migrations
- **`search-docs`** - Search version-specific Laravel/package documentation
- **`get-absolute-url`** - Resolve correct URLs for the project
- **`browser-logs`** - Read browser console logs and errors

### 2. Automatic Context Loading
- **AGENTS.md** is automatically loaded into every Cascade conversation
- Contains Laravel 12, Livewire 4, PHPUnit 11 best practices
- Version-specific guidelines prevent outdated patterns

### 3. Domain-Triggered Skills
Skills activate automatically based on file type and context:

#### `laravel-best-practices`
**Auto-activates when:**
- Editing PHP files in `app/`, `routes/`, `config/`
- Working with Eloquent models, migrations, controllers
- Writing queries, jobs, policies, service classes

**Provides:**
- N+1 query detection and prevention
- Caching strategies
- Security patterns
- Validation best practices
- Performance optimization

#### `livewire-development`
**Auto-activates when:**
- Working with Livewire components
- Using `wire:` directives in Blade
- Questions about reactivity, state management

**Provides:**
- Livewire 4 patterns (SFC, MFC, class-based)
- Real-time validation
- Performance optimization
- Migration guides from v3

## Best Practices for Cascade Agents

### 1. Always Search Docs First
```
Before writing code, use search-docs to get version-specific examples
```
- Prevents deprecated patterns
- Shows latest Laravel 12 features
- Package-specific documentation

### 2. Use Database Tools Over Tinker
```
Prefer database-query and database-schema over php artisan tinker
```
- Faster execution
- Read-only safety
- Better for inspection

### 3. Let Skills Activate Automatically
```
Don't manually request skills - they load based on context
```
- Work naturally in files
- Skills appear when needed
- No overhead when not required

### 4. Leverage AGENTS.md Context
```
All Laravel/PHP conventions are pre-loaded
```
- No need to explain Laravel 12 structure
- Livewire 4 patterns known
- PHPUnit testing standards included

## Workflow Integration

### Development Flow
1. Cascade reads AGENTS.md automatically
2. Skills activate based on file/domain
3. MCP tools available for queries
4. Version-specific docs via `search-docs`

### Example: Creating a New Feature
```
User: "Add user notifications"

Cascade:
1. Activates laravel-best-practices skill
2. Uses search-docs for Laravel 12 notification patterns
3. Uses database-schema to check users table
4. Generates migration, model, notification class
5. Runs vendor/bin/pint for code formatting
```

## Advantages Over Copilot-Only Setup

| Feature | Copilot | Windsurf + Boost |
|---------|---------|------------------|
| **Context Awareness** | Limited | Full AGENTS.md + Skills |
| **Documentation Access** | Generic | Version-specific via MCP |
| **Database Inspection** | Manual | Direct via `database-schema` |
| **Multi-Agent** | Single | Cascade + Copilot |
| **Domain Skills** | No | Auto-activated |
| **Tool Access** | No | MCP server tools |

## Troubleshooting

### MCP Server Not Available
1. Check `boost.json` has `"mcp": true`
2. Restart Windsurf IDE
3. Verify Laravel Boost package is installed: `composer show laravel/boost`

### Skills Not Activating
- Skills activate automatically based on context
- Check `.github/skills/` directory exists
- Work in relevant files (e.g., PHP for laravel-best-practices)

### AGENTS.md Not Loaded
- AGENTS.md must be in project root
- Restart Cascade conversation
- Check `"guidelines": true` in boost.json

## Further Reading

- [Laravel Boost Documentation](https://laravel.com/docs/boost)
- [Windsurf MCP Integration](https://docs.codeium.com/windsurf/mcp)
- [Skills Development Guide](.github/skills/)
