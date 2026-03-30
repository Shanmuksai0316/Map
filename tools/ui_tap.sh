#!/bin/zsh
set -u
DEVICE=${DEVICE:-emulator-5554}
TYPE=${1:-desc}
PATTERN=${2:-}

if [ -z "$PATTERN" ]; then
  echo "usage: ui_tap.sh <desc|text> <pattern>" >&2
  exit 1
fi

adb -s "$DEVICE" shell uiautomator dump /sdcard/uidump.xml >/dev/null
adb -s "$DEVICE" exec-out cat /sdcard/uidump.xml > /tmp/uidump.xml

if [ "$TYPE" = "desc" ]; then
  line=$(sed 's/</\n</g' /tmp/uidump.xml | rg -m 1 "content-desc=\"$PATTERN\"" || true)
else
  line=$(sed 's/</\n</g' /tmp/uidump.xml | rg -m 1 "text=\"$PATTERN\"" || true)
fi

if [ -z "$line" ]; then
  echo "not found: $TYPE=$PATTERN" >&2
  exit 2
fi

coords=$(echo "$line" | perl -ne 'if (/bounds="\[(\d+),(\d+)\]\[(\d+),(\d+)\]"/) { $x=int(($1+$3)/2); $y=int(($2+$4)/2); print "$x $y"; }')
if [ -z "$coords" ]; then
  echo "no bounds" >&2
  exit 3
fi

adb -s "$DEVICE" shell input tap $coords
