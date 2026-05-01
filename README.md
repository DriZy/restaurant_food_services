# Restaurant Food Services Plugin

Restaurant Food Services is a modular WooCommerce extension for restaurants and food brands that need meal planning, recurring subscriptions, catering request management, and delivery scheduling in one plugin. It combines storefront features with admin workflows so teams can manage orders, subscriptions, and catering approvals without stitching together multiple tools. The codebase is organized with a Loader-based architecture for maintainability, predictable hooks, and safe extensibility.

## Key Features

- **Meals module**: add meal-plan product settings like meals per week, meal selection rules, and meal metadata.
- **Frontend entry pages**: shortcode-based public containers for `[restaurant_order_meals]`, `[restaurant_meal_plans]`, and `[restaurant_catering]`.
- **Account authentication UI**: a signup-first `Sign up / Sign in` tab switcher for the frontend account page and registration shortcode.
- **Subscriptions module**: create subscription records from checkout orders, support customer pause/resume/cancel actions, and process renewal scheduling.
- **Catering module**: capture catering requests via shortcode form, manage requests in admin, and convert approved requests into WooCommerce orders.
- **Delivery module**: collect delivery date/time-slot checkout fields and manage delivery schedules from the admin dashboard.
- **WooCommerce emails**: includes custom customer emails for subscription created, catering request submitted, and catering approved events.
- **Security and structure**: nonce checks, capability guards, sanitized inputs, escaped outputs, and module-driven hook registration via `Loader`.

## Structure

- `restaurant-food-services.php` - Main plugin bootstrap file.
- `includes/class-bootstrap.php` - Startup lifecycle and WooCommerce dependency check.
- `includes/class-loader.php` - Hook registry for actions and filters.
- `includes/class-plugin.php` - Main plugin bootstrap class.
- `includes/class-activator.php` - Activation routines.
- `includes/class-deactivator.php` - Deactivation routines.
- `includes/modules/class-module-interface.php` - Module contract.
- `includes/modules/class-abstract-module.php` - Shared module base class.
- `includes/modules/class-public-module.php` - Frontend shortcodes module hooks.
- `includes/modules/class-account-module.php` - Frontend account hub, signup/login switcher, and account endpoint rendering.
- `includes/modules/class-meals-module.php` - Meals module hooks.
- `includes/modules/class-subscriptions-module.php` - Subscriptions module hooks.
- `includes/modules/class-catering-module.php` - Catering module hooks.
- `includes/modules/class-delivery-module.php` - Delivery module hooks.
- `includes/modules/class-emails-module.php` - WooCommerce custom email hooks.
- `includes/public/class-order-meals-page.php` - `[restaurant_order_meals]` renderer.
- `includes/public/class-meal-plans-page.php` - `[restaurant_meal_plans]` renderer.
- `includes/public/class-catering-page.php` - `[restaurant_catering]` renderer.
- `scripts/integration-smoke-check.sh` - Loader and registration smoke checks.
- `index.php` - Direct access protection.

## Frontend Account Authentication

The account module adds a signup-first authentication experience for logged-out users:

- `[restaurant_signup]` renders a registration-first page with a secondary **Sign in** tab.
- `[restaurant_account]` renders the same logged-out auth switcher, then shows the Restaurant Hub when the visitor is signed in.
- The WooCommerce my-account endpoint also uses the same logged-out switcher when registration is enabled.

### Behavior

- **Sign up** is the default tab so new customers can register immediately.
- **Sign in** remains available as the secondary tab for returning customers.
- If WooCommerce registration is disabled, the UI falls back to the login form only.
- The switcher respects WooCommerce settings such as `woocommerce_enable_myaccount_registration`, `woocommerce_registration_generate_username`, and `woocommerce_registration_generate_password`.

### Optional tab links

You can deep-link to a specific tab with the `auth` query parameter:

- `?auth=signup` opens the Sign up tab.
- `?auth=signin` opens the Sign in tab.

## Loader Architecture

- Module hooks are registered through `Loader` in each module `register_hooks( Loader $loader )` method.
- Startup is handled via `Bootstrap::init()` and `Bootstrap::on_plugins_loaded()`.
- Direct `add_action`/`add_filter` registrations are limited to bootstrap/loader internals.

## Quick Verification

Run the integration smoke check:

```bash
/Users/idristabi/Projects/wordpress/brenssmallchops/wp-content/plugins/restaurant-food-services/scripts/integration-smoke-check.sh
```

Manual flow checks in wp-admin/storefront:

1. Meal order flow
   - Mark a product as meal plan (`is_meal_plan=yes`) and set meals per week.
   - Place a WooCommerce order for that product.
   - Confirm delivery fields save on order and no checkout validation errors.

2. Subscription flow
   - Complete checkout with a meal-plan product.
   - Verify row is created in `wp_restaurant_subscriptions`.
   - Verify account endpoint `my-subscriptions` renders and pause/resume/cancel actions work.
   - Verify "Subscription Created" email is present in WooCommerce > Settings > Emails and sends.

3. Catering flow
   - Submit `[restaurant_catering_form]` with valid nonce/fields.
   - Verify `catering_request` post is created with pending status.
   - Approve via admin status change or Convert to Order.
   - Verify "Catering Request Submitted" and "Catering Approved" emails trigger once.

4. Account auth flow
   - Visit a page containing `[restaurant_signup]` or `[restaurant_account]` while logged out.
   - Confirm **Sign up** is shown first and **Sign in** can be switched to instantly.
   - Try to access `your-site.com/wp-login.php` as a guest; confirm you are redirected to the page with `[restaurant_signup]`.
   - Use `[restaurant_login_logout]` in a widget or menu and verify it toggles correctly between Log In/Log Out.
   - Disable WooCommerce registration to confirm the UI falls back to the sign-in form only.

## Notes

This scaffold is ready for additional restaurant and food service features, admin screens, API integrations, and custom post types.


 - Verify "Catering Request Submitted" and "Catering Approved" emails trigger once.

4. Account auth flow
   - Visit a page containing `[restaurant_signup]` or `[restaurant_account]` while logged out.
   - Confirm **Sign up** is shown first and **Sign in** can be switched to instantly.
   - Disable WooCommerce registration to confirm the UI falls back to the sign-in form only.

## Notes

This scaffold is ready for additional restaurant and food service features, admin screens, API integrations, and custom post types.


