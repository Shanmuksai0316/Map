# Student Bulk Upload via Google Form

Use this flow when you want to collect student records in Google Form and then upload the exported file in `Bulk Upload Students`.

## 1. Create the Google Form

Create form questions using these exact titles:

1. `Full Name` (required)
2. `Email Address` (required)
3. `Mobile Number` (required)
4. `Gender` (required)
5. `Date of Birth` (optional)
6. `Student UID` (optional)
7. `MAP ID` (optional)
8. `ERP Number` (optional)
9. `Department` (optional)
10. `Year of Study` (optional)
11. `Father Name` (optional)
12. `Father Mobile Number` (optional)
13. `Mother Name` (optional)
14. `Mother Mobile Number` (optional)
15. `Local Guardian Name` (optional)
16. `Local Guardian Contact` (optional)
17. `Local Relationship` (optional)
18. `Local Address` (optional)
19. `Blood Group` (optional)
20. `Medical Information` (optional)

## 2. Recommended Google Form field types

1. `Full Name`: Short answer
2. `Email Address`: Short answer (Response validation: Email)
3. `Mobile Number`: Short answer
4. `Gender`: Multiple choice (`male`, `female`, `other`)
5. `Date of Birth`: Date
6. `Year of Study`: Multiple choice (`1`, `2`, `3`, `4`, `5`)
7. `Blood Group`: Multiple choice (`A+`, `A-`, `B+`, `B-`, `O+`, `O-`, `AB+`, `AB-`)

## 3. Export and upload

1. Open form `Responses`.
2. Click `View in Sheets`.
3. In Google Sheets: `File` -> `Download` -> `Microsoft Excel (.xlsx)` or `Comma Separated Values (.csv)`.
4. In MAP Campus Manager panel: `Bulk Upload Students` -> `Upload CSV` -> upload this file.
5. Run `Dry-Run`, fix any errors, then `Commit`.

## Notes

1. Google Form `Timestamp` column is ignored by importer.
2. Required import fields are `Full Name`, `Email Address`, `Mobile Number`, `Gender`.
3. If `Student UID` or `MAP ID` is blank, system auto-generates values.
