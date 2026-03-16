# BeTA Bring Integration — GitHub Copilot Instructions

## Project overview

**BeTA Bring Integration** is a WooCommerce plugin (PHP 8.1+, WP 6.3+, WC 8+) that
integrates with the Mybring API to enable shipment booking, label generation, pickup-point
lookup, and real-time rate queries directly from the WooCommerce admin.

Key directories:
```
beta-bring-integration/
├── beta-bring-integration.php   # Plugin entry point
├── includes/
│   ├── API/        # Bring API service classes
│   ├── Admin/      # WP/WC admin UI (settings, meta boxes, order columns)
│   ├── Model/      # Value objects & data-access
│   ├── Util/       # Helpers
│   └── Woo/        # WooCommerce-specific helpers
└── assets/
    ├── css/admin.css
    └── js/admin.js
```

Namespace: `BeTA\Bring\`  
Text domain: `bbi`  
Plugin version constant: `BBI_VER`

---

## Specialist agents — when to call them

All code changes in this repository **must** pass through the relevant specialist agents
before being considered complete. Use them by `@`-mentioning their name in Copilot Chat.

| Agent | `@`-handle | Call when… |
|---|---|---|
| Lead Plugin Architect | `@lead-plugin-architect` | Designing new features, refactoring architecture, bootstrap changes, adding new classes or namespaces |
| WooCommerce Integration | `@woocommerce-integration` | Order meta, HPOS, admin meta boxes, settings pages, bulk actions, WC hooks |
| Bring API Integration | `@bring-api-integration` | Any `includes/API/` work: new endpoints, request/response parsing, authentication |
| Admin UX & Settings | `@admin-ux-settings` | Admin settings fields, WC settings sections, order-list columns, admin notices |
| Frontend UX | `@frontend-ux` | `assets/js/admin.js`, `assets/css/admin.css`, AJAX handlers, DOM interactions |
| Code Reviewer | `@code-reviewer` | Before merging any PR; reviewing logic, style, and correctness |
| Testing | `@testing` | Writing PHPUnit tests, simulation scripts, test fixtures |
| Documentation | `@documentation` | README, CHANGELOG, inline DocBlocks, `readme.txt` |
| Security | `@security` | **Always** — every change must be reviewed by the Security agent |

---

## Mandatory security gate

> **Every pull request and every code change — no exceptions — must be reviewed by
> `@security` before it is merged.**

The Security agent checks for:
- Unsanitised user input reaching the database, filesystem, or output
- Missing nonce verification on AJAX / form handlers
- Insufficient capability checks (`current_user_can`)
- Sensitive data (API keys, credentials) exposed in logs, responses, or source
- SSRF vectors in HTTP client calls
- Insecure direct object references on order IDs
- Dependency and supply-chain risks

If the Security agent raises a finding, the finding **must** be resolved before merge.

---

## Coding standards

- PHP 8.1+ strict types; every file starts with `declare(strict_types=1);` when
  introducing new files.
- Follow WordPress Coding Standards (tabs, Yoda conditions, `snake_case` for functions
  and variables, `PascalCase` for classes).
- All user-facing strings wrapped in `__()` / `esc_html__()` with text domain `bbi`.
- Outputs escaped: `esc_html()`, `esc_attr()`, `esc_url()`.
- No direct `$_GET`/`$_POST` without `sanitize_*` + `wp_unslash`.
- Use `$order->get_meta()` / `$order->update_meta_data()` + `$order->save()` for HPOS
  compatibility — **never** `get_post_meta()` / `update_post_meta()` directly on orders.
- Never log or return raw API keys or credentials.

---

## REST API conventions

Namespace: `bbi/v1`  
All routes require `permission_callback` checking `manage_woocommerce`.  
Sanitise every route parameter before use.

---

## Bring API endpoints in use

| Service | Base URL |
|---|---|
| Booking | `https://www.mybring.com/booking/api/create` |
| Shipping Guide v2 | `https://api.bring.com/shippingguide/v2/products` |
| Pickup Point | `https://api.bring.com/pickuppoint/api/pickuppoint` |
| Address / Postal Code | `https://api.bring.com/address/api` |
| Customer / User Settings | `https://www.mybring.com/api/mybring/usersettings` |
