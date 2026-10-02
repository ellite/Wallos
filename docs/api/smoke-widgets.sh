#!/usr/bin/env bash
# Smoke-test Wallos widget APIs against a running instance.
# Usage:
#   BASE=http://localhost:8282 KEY=your-api-key ./docs/api/smoke-widgets.sh
set -euo pipefail

BASE="${BASE:-http://localhost:8282}"
KEY="${KEY:?Set KEY to your Wallos API key}"

echo "== list_widgets =="
LIST=$(curl -sS "${BASE}/api/widgets/list_widgets.php?api_key=${KEY}")
echo "$LIST" | jq '{schema_version, count:(.widgets|length), pmb:(.widgets|map(select(.widget_id=="payment_method_budget"))|[.[]|{instance_id,title,display_mode,payment_method_ids}])}'

INSTANCE=$(echo "$LIST" | jq -r '[.widgets[]|select(.widget_id=="payment_method_budget")][0].instance_id // empty')
if [[ -z "$INSTANCE" ]]; then
  echo "No payment_method_budget instance found; trying period_budget only."
else
  echo
  echo "== get_widget payment_method_budget instance_id=${INSTANCE} =="
  curl -sS "${BASE}/api/widgets/get_widget.php?api_key=${KEY}&widget_id=payment_method_budget&instance_id=${INSTANCE}" \
    | jq '{success, schema_version, instance_id, display_mode, title, notes, methods, methods_breakdown}'
fi

echo
echo "== get_widget period_budget =="
curl -sS "${BASE}/api/widgets/get_widget.php?api_key=${KEY}&widget_id=period_budget" \
  | jq '{success, schema_version, period_budget, amount_needed, remaining, over_budget, period}'

echo
echo "== get_widget category_cost =="
curl -sS "${BASE}/api/widgets/get_widget.php?api_key=${KEY}&widget_id=category_cost&category_limit=5" \
  | jq '{success, schema_version, limit, categories}'
