# j_* (jay star)

**A path-based PHP framework where everything is a path.**

## Philosophy

jay_star treats URLs, data, state, and IDs as hierarchical paths - similar to a filesystem. Instead of traditional databases, it uses the filesystem itself as storage with intelligent encoding strategies.

## Core Concepts

### 1. Path-Based Architecture

URLs are directly mapped to filesystem paths:

```
URL: localhost/about-us/our-philosophy/~/0/1/4_gruppenfoto-mit-gruender.jpg

Structured Path: about-us/our-philosophy
Asset Path: 0/1/4
SEO Term: gruppenfoto-mit-gruender.jpg (stripped)

Flat Path: localhost⌇about-us⌇our-philosophy (/ → ⌇)

Filesystem: data/web/demo/domain/localhost/host/localhost/localhost⌇about-us⌇our-philosophy/media/0/1/4
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
// Generate user ID with shard
$user_id_shard = j_id('user');
// Returns: "user/0/003/039/000/000/019/5f8/321/7c0"

// Simple value
j_files_set($user_id_shard . '/name', 'John');
// Creates: data/user/0/003/039/000/000/019/5f8/321/7c0/name

// Nested structure
j_files_set($user_id_shard, ['name' => 'John', 'age' => 30]);
// Creates: data/user/0/003/039/.../name and .../age

// Meta-keys (value in filename)
j_files_set($user_id_shard . '/status=', 'active');
// Creates: data/user/0/003/039/.../status=active

j_files_get($user_id_shard . '/status='); // 'active'
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

### 6. Globbing & Pattern Matching

Find files and data using glob patterns with advanced filtering:

```php
// Find user by username (case-insensitive)
$matches = j_glob(
    'web/demo/' . j_id_wildcard('user') . '/auth/username=JoPhi',
    case_insensitive: true
);
// Returns: ["web/demo/user/0/abc/def/.../auth/username=JoPhi"]

// j_id_wildcard() generates pattern for all IDs of a section
j_id_wildcard('user')
// Returns: "user/*/*/*/*/*/*/*/*/*"

// Advanced: Clean results with trim_prefix, trim_suffix, meta_split, keys_slash_to_dash
$users = j_glob(
    'web/demo/' . j_id_wildcard('user') . '/auth/username=*',
    trim_prefix: 'web/demo/',              // Remove 'web/demo/' from results
    trim_suffix: 'auth/username',          // Remove 'auth/username' from results
    meta_split: true,                      // Split path and meta-value into key => value
    keys_slash_to_dash: true               // Convert '/' to '-' in keys
);
// Returns: ['user-1-013-ac1-000-000-001-882-9e1-b8b-95e-f54' => 'JoPhi']

// Pattern matching with wildcards
j_glob('web/*/domain/*/host/*/content/*');

// Extract user ID from glob match result
$match = "web/demo/user/0/abc/def/123/456/789/auth/username=JoPhi";
$user_path = j_reduce_path($match, 2);
// Returns: "web/demo/user/0/abc/def/123/456/789"
// (removes: auth/username=JoPhi)
```

**Advanced Parameters:**
- `trim_prefix: ''` - Remove prefix from beginning of paths
- `trim_suffix: ''` - Remove suffix from end of paths (applied after decoding)
- `meta_split: false` - Return flat array with path as key, meta-value as value
- `keys_slash_to_dash: false` - Convert `/` to `-` in keys (useful for cookies, JSON keys)

**Path Manipulation:**
- `j_reduce_path($path, $levels)` - Remove N path segments from the end
- Useful for extracting parent paths or IDs from glob results

**Case-Insensitive Search:** Automatically generates glob patterns like `[jJ][oO][pP][hH][iI]` for ASCII characters or `{j,J}{o,O}` for multi-byte characters, enabling efficient case-insensitive lookups without opening files.

### 7. Path Extraction

Extract specific components from structured paths:

```php
// Extract user ID from a path with sharded structure
$path = 'web/demo/user/1/013/ac1/000/000/001/882/9e1/b8b/95e/f54/auth/username';
$user_id = j_extract_from_path($path, 'user');
// Returns: '1/013/ac1/000/000/001/882/9e1/b8b/95e/f54'

// Extract multiple components
$info = j_extract_from_path('web/demo/domain/localhost/host/127.0.0.1/content/page');
// Returns: ['domain' => 'localhost', 'host' => '127.0.0.1', 'content' => 'page']

// Get specific section with default fallback
$domain = j_extract_from_path($path, 'domain', default: 'unknown');
```

**Smart Shard Detection:** Automatically recognizes sharded IDs like `user` and extracts the complete ID including all shard segments.

### 8. Encrypted Cookies

Secure cookie handling with AES-256-CBC encryption:

```php
// 1. Load cookie from HTTP (on request start)
j_cookie_get('__necessary', default: [
    'device-id' => null,
    'user-id' => null
]);

// 2. Read cookie values during request
$user_id = j_from_cookie('__necessary', 'user-id');
$device_id = j_from_cookie('__necessary', 'device-id', default: 'unknown');
$all_data = j_from_cookie('__necessary'); // Get entire cookie

// 3. Update cookie values during request
$user_id_shard = j_id('user');
j_update_cookie('__necessary', 'user-id', $user_id_shard);

// 4. Write cookie to HTTP (on response)
j_cookie_set('__necessary');
```

**Complete Cookie API:**
- `j_cookie_get()` - Load from HTTP, decrypt, store in memoizer
- `j_from_cookie()` - Read values from memoizer during request
- `j_update_cookie()` - Update values in memoizer
- `j_cookie_set()` - Encrypt and write to HTTP

All cookie data is JSON-encoded and encrypted with a unique key per installation.

## Request Flow

The main gateway (`index.php`) processes requests through labeled sections:

1. **J_INIT:** Auto-setup (`.htaccess`, encryption key, `.gitignore`)
2. **J_FUNCTION_LOADER:** Load all `*.active.php` functions
3. **J_CONFIGURATION:** Set defaults (output format, encryption key)
4. **J_REGISTRY:** Multi-tenant configuration (domain → tenant mapping)
5. **J_RESOLVE_TENANTS:** Match current domain to tenant
6. **J_RESOLVE_URL_PATH:** Parse URL with multi-phase detection:
   - **PHASE 1:** User Profile Mode (`@username` → lookup user)
   - **PHASE 2:** API Endpoint Detection (`jophi/v1` → set JSON output)
   - **PHASE 3:** Reserved for future path transformations
   - **FINAL PHASE:** Asset split (`~/`) and SEO term extraction
7. **J_SET_SYSTEM_WIDE_PATHES:** Generate all filesystem paths
8. **J_READ:** Gather all information (READ before CRUD)
   - **J_INPUT:** Process cookies, POST, GET parameters, detect run modes
9. **J_CREATE:** Create operations (based on READ context)
10. **J_UPDATE:** Update operations (based on READ context)
11. **J_DELETE:** Delete operations (based on READ context)
12. **J_EXIT:** Output response (HTML/JSON/Media)

## Function Categories

Functions are organized by priority (number prefix):

- **-1_string_functions:** String utilities (slash_to_wave, slug_find_problems)
- **0_array_or_kv:** Array/key-value operations (j_array_get/set, j_kv_get/set)
- **1_memo:** Memoizer functions (j_memo_get/set)
- **2_files:** File operations (j_files_get/set, j_file_get_contents)
- **3_ids_and_shards:** ID generation (j_id)
- **4_ids_and_shards_wildcards:** ID wildcards (j_id_wildcard, j_extract_from_path)
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
- User profile mode (`@username` paths with case-insensitive lookup)
- API endpoint detection (`jophi/v1` prefix)
- File-based storage with meta-keys
- Encrypted cookie handling (with helper functions)
- Memoizer state management
- Unique ID generation with sharding
- JSON output handler
- CRUD-structured gateway (READ before CREATE/UPDATE/DELETE)

**TODO:**
- HTML output handler (currently debug output only)
- Media output handler
- Authentication system (login/logout logic)
- Shutdown handler (j_shutdown)
- Real registry implementation (currently hardcoded demo)
- Access control enforcement (needs-access checking)

## License

MIT License (see LICENSE file)

## Author

Built by jophi
