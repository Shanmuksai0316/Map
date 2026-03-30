#!/bin/zsh
set -u

DEVICE=${DEVICE:-emulator-5554}
PHONE="$1"
OTP="${2:-123456}"

function dump_ui() {
  adb -s "$DEVICE" shell uiautomator dump /sdcard/uidump.xml >/dev/null
  adb -s "$DEVICE" exec-out cat /sdcard/uidump.xml > /tmp/uidump.xml
}

function tap_line() {
  local line="$1"
  local coords
  coords=$(echo "$line" | perl -ne 'if (/bounds="\[(\d+),(\d+)\]\[(\d+),(\d+)\]"/) { $x=int(($1+$3)/2); $y=int(($2+$4)/2); print "$x $y"; }')
  if [ -n "$coords" ]; then
    adb -s "$DEVICE" shell input tap $coords
  fi
}

function tap_by_pattern() {
  local pattern="$1"
  local line
  line=$(sed 's/</\n</g' /tmp/uidump.xml | rg -m 1 "$pattern" || true)
  if [ -n "$line" ]; then
    tap_line "$line"
    return 0
  fi
  return 1
}

# Launch app
adb -s "$DEVICE" shell monkey -p com.mapmars.hmsstaff -c android.intent.category.LAUNCHER 1 >/dev/null
sleep 1

# Tap Get Started (static coords)
adb -s "$DEVICE" shell input tap 721 2853
sleep 1

# Focus phone input and type phone
adb -s "$DEVICE" shell input tap 720 1831
adb -s "$DEVICE" shell input text "$PHONE"

# Tap Get OTP (use bounds)
dump_ui
tap_by_pattern 'send-otp-button'

# Wait for success alert and tap OK
for i in {1..40}; do
  dump_ui
  if tap_by_pattern 'android:id/button1'; then
    break
  fi
  sleep 1
done

# Wait for OTP input, then type OTP
for i in {1..40}; do
  dump_ui
  if sed 's/</\n</g' /tmp/uidump.xml | rg -q 'otp-input'; then
    adb -s "$DEVICE" shell input text "$OTP"
    break
  fi
  sleep 1
done

# Tap Verify & Login
for i in {1..5}; do
  dump_ui
  if tap_by_pattern 'verify-otp-button'; then
    break
  fi
  sleep 1
done

# Wait for dashboard (Actions or Checklist)
for i in {1..40}; do
  dump_ui
  if sed 's/</\n</g' /tmp/uidump.xml | rg -q 'Actions|Checklist'; then
    exit 0
  fi
  sleep 1
done

exit 0
