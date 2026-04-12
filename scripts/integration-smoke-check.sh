#!/usr/bin/env zsh
set -euo pipefail

PLUGIN_DIR="/Users/idristabi/Projects/wordpress/brenssmallchops/wp-content/plugins/restaurant-food-services"

echo "[1/4] Checking module files are loaded by main plugin bootstrap..."
required_includes=(
  "includes/modules/class-meals-module.php"
  "includes/modules/class-subscriptions-module.php"
  "includes/modules/class-catering-module.php"
  "includes/modules/class-delivery-module.php"
  "includes/modules/class-emails-module.php"
)
for inc in "${required_includes[@]}"; do
  if ! grep -q "$inc" "$PLUGIN_DIR/restaurant-food-services.php"; then
    echo "ERROR: Missing require_once for $inc"
    exit 1
  fi
done

echo "[2/4] Checking all modules are instantiated in Plugin::load_modules()..."
required_modules=(
  "new Emails_Module()"
  "new Meals_Module()"
  "new Subscriptions_Module()"
  "new Catering_Module()"
  "new Delivery_Module()"
)
for mod in "${required_modules[@]}"; do
  if ! grep -q "$mod" "$PLUGIN_DIR/includes/class-plugin.php"; then
    echo "ERROR: Missing module registration: $mod"
    exit 1
  fi
done

echo "[3/4] Checking each module uses Loader in register_hooks()..."
module_files=(
  "$PLUGIN_DIR/includes/modules/class-meals-module.php"
  "$PLUGIN_DIR/includes/modules/class-subscriptions-module.php"
  "$PLUGIN_DIR/includes/modules/class-catering-module.php"
  "$PLUGIN_DIR/includes/modules/class-delivery-module.php"
  "$PLUGIN_DIR/includes/modules/class-emails-module.php"
)
for file in "${module_files[@]}"; do
  if ! grep -q "function register_hooks( Loader \\\$loader )" "$file"; then
    echo "ERROR: Loader register_hooks signature missing in $file"
    exit 1
  fi
  if ! grep -q "\$loader->add_" "$file"; then
    echo "ERROR: No loader hook registration found in $file"
    exit 1
  fi
done

echo "[4/4] Checking direct WordPress hook registration appears only in bootstrap/loader..."
violations=$(grep -R "add_action(\|add_filter(" "$PLUGIN_DIR" --include='*.php' | grep -v "includes/class-loader.php" | grep -v "includes/class-bootstrap.php" | grep -v -- "->add_" || true)
if [[ -n "$violations" ]]; then
  echo "ERROR: Found direct add_action/add_filter outside Loader-managed classes:"
  echo "$violations"
  exit 1
fi

echo "OK: Loader integration smoke checks passed."



