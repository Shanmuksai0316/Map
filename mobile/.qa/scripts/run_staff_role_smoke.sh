#!/usr/bin/env bash
set -euo pipefail

MAESTRO_BIN="${HOME}/.maestro/bin/maestro"
FLOW_FILE="/Users/nagrajyr/Downloads/mapmars/mobile/.qa/flows/staff_role_smoke.yaml"
OUT_BASE="/Users/nagrajyr/Downloads/mapmars/mobile/.qa/staff-emulator-2026-03-06/full-run/maestro"
DEVICE_ID="emulator-5554"

mkdir -p "${OUT_BASE}"
SUMMARY_FILE="${OUT_BASE}/summary.csv"
echo "role,status,phone,expected_card,artifacts_dir" > "${SUMMARY_FILE}"

roles=(
  "rm_supervisor:7200658181:Requests"
  "sports_manager:7676000129:Active Requests"
  "guard:9739143498:Outpass"
  "hk_supervisor:8555903456:Requests"
  "laundry_manager:9538678739:Active Requests"
  "warden:9739557963:Requests"
  "campus_manager:7975452363:Requests"
  "rector:9663275871:Outpass"
)

for entry in "${roles[@]}"; do
  IFS=':' read -r role phone expected_card <<< "${entry}"
  run_dir="${OUT_BASE}/${role}"
  mkdir -p "${run_dir}"
  echo "==> Running role=${role} phone=${phone} expected=${expected_card}"

  if "${MAESTRO_BIN}" test "${FLOW_FILE}" \
    --udid "${DEVICE_ID}" \
    --test-output-dir "${run_dir}" \
    --debug-output "${run_dir}/debug" \
    --flatten-debug-output \
    --format NOOP \
    -e PHONE="${phone}" \
    -e ROLE="${role}" \
    -e EXPECT_CARD="${expected_card}" \
    > "${run_dir}/run.log" 2>&1; then
    echo "${role},pass,${phone},${expected_card},${run_dir}" >> "${SUMMARY_FILE}"
  else
    echo "${role},fail,${phone},${expected_card},${run_dir}" >> "${SUMMARY_FILE}"
  fi
done

echo
echo "Smoke run complete. Summary: ${SUMMARY_FILE}"
