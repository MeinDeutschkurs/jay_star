# j_* (jay star)

**A path-based PHP framework where everything is a path.**

## Philosophy

jay_star treats URLs, data, state, and IDs as hierarchical paths - similar to a filesystem. Instead of traditional databases, it uses the filesystem itself as storage with intelligent encoding strategies.

## Core Concepts

### 1. Path-Based Architecture

URLs are directly mapped to filesystem paths:

```
URL: localhost/foo/bar~/media/123_product-image

Structured Path: localhost/foo/bar
Asset Path: media/123
SEO Term: product-image (stripped)

Flat Path: localhost⌇foo⌇bar (/ → ⌇)

Filesystem: data/web/demo/domain/localhost/host/localhost⌇foo⌇bar/
```

**Special Syntax:**
- `~/` separates structured content from assets/media
- `⌇` (wave) replaces `/` in flat paths to avoid filesystem conflicts
- `key=value` in filenames for metadata storage

### 2. Multi-Tenancy

The framework supports multiple domains/hosts sharing the same codebase:

```
data/
  web/{web}/
    domain/{domain}/
      host/{host}/
        {flat_path}/
          meta/       # Metadata
          content/    # Main content
          media/      # Media files
          needs-access/  # Access control flags
```

Each tenant (domain/host combination) gets its own isolated data space.

### 3. Memoizer (In-Memory State)

Central state management using slash-separated keypaths:

```php
j_memo_set('analysis/tenant/domain', 'localhost');
$domain = j_memo_get('analysis/tenant/domain'); // 'localhost'

// Batch mode - arrays with / in keys are auto-expanded
j_memo_set('users', [
    'john/name' => 'John Doe',
    'john/age' => 30
]);
```

All state is stored in `$GLOBALS['_MEMOIZER']` and accessible throughout the request lifecycle.

### 4. File-Based Storage

Data persistence using the filesystem:

```php
// Simple value
j_files_set('users/123/name', 'John');
// Creates: data/users/123/name

// Nested structure
j_files_set('users/123', ['name' => 'John', 'age' => 30]);
// Creates: data/users/123/name and data/users/123/age

// Meta-keys (value in filename)
j_files_set('users/123/status=', 'active');
// Creates: data/users/123/status=active

j_files_get('users/123/status='); // 'active'
```

**Meta-Keys:** Keys ending with `=` store the value as part of the filename, enabling fast queries without opening files.

### 5. Unique ID Generation

Path-based IDs with sharding support:

```php
j_id('device')
// Returns: "device/0/003/039/000/000/019/5f8/321/7c0"
//          section/shard/pid/pid/timestamp in hex (3-char groups)
```

Components:
- **Section:** Logical grouping (user, device, session, etc.)
- **Shard:** Random shard number (0-1, expandable)
- **PID:** Process ID in hex (for multi-process safety)
- **Timestamp:** Nanosecond precision timestamp in hex

### 6. Encrypted Cookies

Secure cookie handling with AES-256-CBC encryption:

```php
j_cookie_get('__necessary', default: [
    'device-id' => null,
    'user-id' => null
]);
// Reads, decrypts, stores in memoizer

// Modify cookie data in memoizer
j_memo_set('var/cookies/__necessary/user-id', 'user/0/123/456/...');

j_cookie_set('__necessary');
// Reads from memoizer, encrypts, sets cookie
```

All cookie data is JSON-encoded and encrypted with a unique key per installation.

## Request Flow

The main gateway (`index.php`) processes requests through labeled sections:

1. **J_INIT:** Auto-setup (`.htaccess`, encryption key, `.gitignore`)
2. **J_FUNCTION_LOADER:** Load all `*.active.php` functions
3. **J_CONFIGURATION:** Set defaults (output format, encryption key)
4. **J_REGISTRY:** Multi-tenant configuration (domain → tenant mapping)
5. **J_RESOLVE_TENANTS:** Match current domain to tenant
6. **J_RESOLVE_URL_PATH:** Parse URL into structured/asset paths
7. **J_SET_SYSTEM_WIDE_PATHES:** Generate all filesystem paths
8. **J_INPUT:** Process cookies, POST, GET parameters
9. **J_EXIT:** Output response (HTML/JSON/Media)

## Function Categories

Functions are organized by priority (number prefix):

- **-1_string_functions:** String utilities (slash_to_wave, slug_find_problems)
- **0_array_or_kv:** Array/key-value operations (j_array_get/set, j_kv_get/set)
- **1_memo:** Memoizer functions (j_memo_get/set)
- **2_files:** File operations (j_files_get/set, j_file_get_contents)
- **3_ids_and_shards:** ID generation (j_id)
- **4_ids_and_shards_wildcards:** ID wildcards (j_id_wildcard)
- **5_globbing:** Pattern matching (j_glob)
- **6_cookies_and_encryption_decryption:** Cookie & crypto (j_cookie_get/set, j_encrypt/decrypt_aes)

## Installation

1. Clone or copy files to your web server
2. Ensure PHP 8.0+ is installed
3. Point your web server to `index.php`
4. On first run, the framework auto-generates:
   - `.htaccess` (URL rewriting)
   - `secrets/encryption.key` (AES-256 key)
   - `.gitignore`

## Current State (v0.1-alpha)

**What works:**
- Multi-tenant routing and path resolution
- File-based storage with meta-keys
- Encrypted cookie handling
- Memoizer state management
- Unique ID generation with sharding

**TODO:**
- User profile mode (paths starting with `@username`)
- Output handlers (HTML/JSON/Media)
- Authentication system
- Shutdown handler (j_shutdown)
- Real registry implementation (currently hardcoded demo)

## License

MIT License (see LICENSE file)

## Author

Built by jophi
