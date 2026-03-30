fastlane documentation
----

# Installation

Make sure you have the latest version of the Xcode command line tools installed:

```sh
xcode-select --install
```

For _fastlane_ installation instructions, see [Installing _fastlane_](https://docs.fastlane.tools/#installing-fastlane)

# Available Actions

### test

```sh
[bundle exec] fastlane test
```

Run all tests

### bump_version

```sh
[bundle exec] fastlane bump_version
```

Increment version for both platforms

----


## Android

### android build_staff_debug

```sh
[bundle exec] fastlane android build_staff_debug
```

Build Staff App Debug APK

### android build_staff_release

```sh
[bundle exec] fastlane android build_staff_release
```

Build Staff App Release APK

### android build_staff_bundle

```sh
[bundle exec] fastlane android build_staff_bundle
```

Build Staff App Release Bundle (AAB)

### android deploy_staff_internal

```sh
[bundle exec] fastlane android deploy_staff_internal
```

Deploy Staff App to Play Store Internal Track

### android deploy_staff_production

```sh
[bundle exec] fastlane android deploy_staff_production
```

Deploy Staff App to Play Store Production

### android build_student_debug

```sh
[bundle exec] fastlane android build_student_debug
```

Build Student App Debug APK

### android build_student_release

```sh
[bundle exec] fastlane android build_student_release
```

Build Student App Release APK

### android build_student_bundle

```sh
[bundle exec] fastlane android build_student_bundle
```

Build Student App Release Bundle (AAB)

### android deploy_student_internal

```sh
[bundle exec] fastlane android deploy_student_internal
```

Deploy Student App to Play Store Internal Track

### android deploy_student_production

```sh
[bundle exec] fastlane android deploy_student_production
```

Deploy Student App to Play Store Production

### android check_student_playstore

```sh
[bundle exec] fastlane android check_student_playstore
```

Check Student app submission status on Play Store (production track)

### android check_staff_playstore

```sh
[bundle exec] fastlane android check_staff_playstore
```

Check Staff app submission status on Play Store (production track)

### android check_playstore_status

```sh
[bundle exec] fastlane android check_playstore_status
```

Check both Student and Staff app submission status on Play Store

### android check_student_release

```sh
[bundle exec] fastlane android check_student_release
```

Check if Student app is released on Play Store (production track release status)

### android check_staff_release

```sh
[bundle exec] fastlane android check_staff_release
```

Check if Staff app is released on Play Store (production track release status)

### android check_playstore_release

```sh
[bundle exec] fastlane android check_playstore_release
```

Check if both apps are released to Play Store (release status, not just submission)

### android deploy_both_internal

```sh
[bundle exec] fastlane android deploy_both_internal
```

Deploy Both Apps to Internal Testing

----


## iOS

### ios build_staff_dev

```sh
[bundle exec] fastlane ios build_staff_dev
```

Build Staff App for Development

### ios build_staff_release

```sh
[bundle exec] fastlane ios build_staff_release
```

Build Staff App for Release

### ios deploy_staff_testflight

```sh
[bundle exec] fastlane ios deploy_staff_testflight
```

Deploy Staff App to TestFlight

### ios deploy_staff_appstore

```sh
[bundle exec] fastlane ios deploy_staff_appstore
```

Deploy Staff App to App Store

### ios build_student_release

```sh
[bundle exec] fastlane ios build_student_release
```

Build Student App for Release

### ios deploy_student_testflight

```sh
[bundle exec] fastlane ios deploy_student_testflight
```

Deploy Student App to TestFlight

### ios deploy_student_appstore

```sh
[bundle exec] fastlane ios deploy_student_appstore
```

Deploy Student App to App Store

----

This README.md is auto-generated and will be re-generated every time [_fastlane_](https://fastlane.tools) is run.

More information about _fastlane_ can be found on [fastlane.tools](https://fastlane.tools).

The documentation of _fastlane_ can be found on [docs.fastlane.tools](https://docs.fastlane.tools).
