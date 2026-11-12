#!/usr/bin/env bash
# Runs every local check for the NAB Member Portal plugin, then stops the test server.
# Usage (from the repo root):  bash dev/harness/run-tests.sh
set -u
cd "$(dirname "$0")"
mkdir -p shots
rm -f meta.json server.log && rm -rf uploads   # fresh member data from seed-meta.json

echo "== PHP lint"
fail=0
for f in ../../nab-member-portal/*.php ../../nab-member-portal/inc/*.php ../../nab-member-portal/templates/*.php; do
  php -l "$f" >/dev/null || { echo "LINT FAIL $f"; fail=1; }
done
for f in ../../nab-member-portal/assets/js/nab-*.js; do node --check "$f" || { echo "JS FAIL $f"; fail=1; }; done

echo "== Member access unit tests"
php member-access-test.php | grep -v '^PASS' || true

php -S 127.0.0.1:8092 router.php > server.log 2>&1 &
SRV=$!
trap 'kill $SRV 2>/dev/null' EXIT
for i in $(seq 1 30); do curl -s -o /dev/null http://127.0.0.1:8092/page/nab-dashboard && break; sleep 0.3; done

echo "== Every portal page renders"
for t in nab-dashboard nab-lfp nab-simulator nab-utilization nab-credit-report nab-dispute-center nab-loan nab-card-match nab-pad nab-chatbot nab-support nab-learning nab-plans nab-emergency-fund nab-roadmap nab-budget nab-payments nab-networth nab-loan-calculator nab-collections; do
  out=$(curl -s "http://127.0.0.1:8092/page/$t")
  if [ ${#out} -lt 20000 ] || echo "$out" | grep -q 'Fatal error\|Warning</b>\|Notice</b>'; then echo "PAGE FAIL $t"; fail=1; fi
done
grep -i "fatal\|warning\|notice\|deprecated" server.log && fail=1

echo "== Browser flows"
for s in flows.js loancalc.js cancelflow.js dispute2.js collections.js; do
  rm -f meta.json
  echo "-- $s"; timeout 240 node "$s" 2>&1 | tail -3
done
rm -f meta.json

echo "== Member Access screen (list upload)"
rm -f admin-state.json
php -S 127.0.0.1:8093 admin-router.php > admin.log 2>&1 &
ADM=$!
trap 'kill $SRV $ADM 2>/dev/null' EXIT
for i in $(seq 1 30); do curl -s -o /dev/null http://127.0.0.1:8093/admin && break; sleep 0.3; done
timeout 120 node import-flow.js 2>&1 | grep -E "no file|xlsx|preview:|result:|scrollWidth|ERRORS"
grep -iE "fatal|warning|deprecated" admin.log && fail=1
rm -f admin-state.json

[ $fail = 0 ] && echo "== DONE: no lint/page errors (read the flow output above for ERRORS: [])" || echo "== SOME CHECKS FAILED"
