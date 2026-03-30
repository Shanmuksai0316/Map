# Suggested Reply to App Review – MAP Vidyarthi (Student App)

Use this in **App Store Connect** → your app → **App Review** → **Reply to App Review** (for the relevant submission). Adjust the wording if you have already made technical changes (e.g. iPhone-only, or a deletion link).

---

## Draft reply

**Subject (if required):** Re: Guideline 2.1 and 5.1.1(v) – MAP Vidyarthi Version 1.0

Thank you for your feedback on MAP Vidyarthi.

**Guideline 2.1 – App Completeness (errors when tapping features)**  
We have restricted this app to **iPhone only**. It is no longer offered for iPad in the App Store. The version we are submitting (or have submitted) is built and configured for iPhone only, so it will not be available or tested on iPad. We have also addressed the underlying issues so that features work as expected on iPhone. We request that the app be reviewed on an iPhone device. If you need any test credentials or steps for iPhone, we are happy to provide them.

**Guideline 5.1.1(v) – Account deletion**  
MAP Vidyarthi is an **institutional app**: student accounts are not created by the user in the app. They are **provisioned by the educational institution** (college/hostel) that uses our platform. Students sign in with their registered phone number (OTP); they do not self-register or “create” an account in the consumer sense.

Our **account lifecycle** is as follows:

- Student accounts are created and managed by the institution’s administrator.
- When a student **completes their tenure** (e.g. after their course or academic year, typically around one year), their account is **automatically deactivated** by the system as part of our institutional workflow. They can no longer sign in or access data.
- Data retention and permanent deletion are handled according to our privacy policy and the institution’s requirements (e.g. retention for compliance, then deletion or anonymisation).

We do not offer in-app “create account” in the sense of user self-registration; the equivalent of “account creation” is done by the institution. We do, however, want to align with your guideline.

**Implemented solution:**  
We have added (or will add) in the app an **“Request account deletion”** (or “Delete my account”) option in the student Profile/Settings. This will either:

- Open a **direct link** to a dedicated web page where the user can submit a permanent account-deletion request and we process it in line with our policy, or  
- Send a **deletion request** from within the app (e.g. via an API) that we process and then confirm to the user.

In both cases, the user **initiates** account deletion from the app, and we then process the request (including permanent deletion of data as per our policy). We believe this satisfies the requirement to offer users an option to initiate account deletion while respecting our institutional workflow and data-handling obligations.

We would be grateful if you could confirm that this approach is acceptable, or let us know if you need any additional information or documentation.

Thank you again for your time.

[Your name / OMAP Services Management Pvt Ltd]

---

## Notes for you

1. **Before you send**  
   - If you have **not** yet made the app iPhone-only or added the deletion option, either (a) do those changes and submit a new build, then send this reply, or (b) shorten the reply to say you are addressing both points and will submit an update shortly, and use the above as the explanation of your workflow and proposed solution.

2. **Deletion in the app**  
   To minimise rejection risk, **add in the student app** one of the following:
   - **Option A:** A “Delete my account” / “Request account deletion” row in Profile that opens a **URL** (e.g. `https://mapservices.in/account/delete` or `/privacy#deletion`) where the user can submit a deletion request.
   - **Option B:** The same row but triggering an in-app **API call** that submits a deletion request; after success, show a message and log the user out.

   The reply above says you have added (or will add) this; make sure the build you submit actually includes it.

3. **iPhone-only**  
   In Xcode, set the app to **iPhone only** (e.g. **TARGETED_DEVICE_FAMILY = 1**). In App Store Connect, ensure the app is only available for **iPhone** so reviewers are not asked to test on iPad.

4. **If Apple insists on “full” in-app deletion**  
   If they reply that a link or “request” is not enough, you may need to implement a full in-app flow (e.g. “Delete my account” → confirm → API that permanently deletes/anonymises the account → logout). The same reply can be sent first to explain your workflow and suggest the link/request approach; you can then add the full flow if App Review requires it.
