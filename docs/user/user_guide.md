# User guide

This guide is for authorised Abhipraya administrators who use the Home page, QR Center, Social Audit, Analytics, Reports, and Profile. Public respondents do not need an account; see [Public feedback](#public-feedback) below.

## Before you begin

- Use a supported modern browser and the official Abhipraya URL supplied by your programme administrator.
- Sign in only with your own account. Do not share passwords.
- A **Facility Administrator** sees only the facility assigned to that account. Facility filtering is enforced by the system, not just hidden in the screen.
- Data is anonymous. Do not try to identify a beneficiary from their feedback.

## Sign in

1. Open the Abhipraya administrator URL.
2. Enter your username and password.
3. Complete the displayed verification step, if requested.
4. Select **Sign in**.

After successful sign-in, the system opens **Home**. Use the profile menu to update your profile, change password, or sign out.

## Home dashboard

The Home page answers four practical questions:

1. How much feedback was received?
2. How are beneficiaries rating services?
3. Which service indicators need attention?
4. Which department or facility should be reviewed first?

### Use the filters

At the top of Home, select one or more filters and choose **Apply**.

| Filter | Use it for |
| --- | --- |
| **Facility** | Limit the view to one permitted facility. For Facility Administrator accounts, the assigned facility is selected and cannot be changed. |
| **Department** | Review one department, such as OPD, Pharmacy, Labour Room, or IPD. |
| **Survey version** | Compare or review data collected with a particular published survey version. Use this when questions have changed over time. |
| **From date** and **To date** | Review a specific reporting period. Leave both blank to view all available data. |

The system only returns data that your account is permitted to see.

### Read the KPI cards

| Card | Meaning |
| --- | --- |
| **Feedback received** | Number of submitted feedback responses in the selected period. |
| **Average rating** | Average of rating-type indicators, shown as a score out of five and stars. It does not include binary or text questions. |
| **Facilities reporting** | Number of facilities with feedback in the selected period. |
| **Priority issues** | Indicators below the configured performance threshold, plus unfavourable configured binary, availability, category, or severity results. |

### Understand colours and icons

- **Green** means a favourable or higher-performing result.
- **Amber** means a result that should be monitored.
- **Red** means a low score, adverse result, or high-priority issue that needs review.
- A star is used only for `rating` questions. It is not used for yes/no, availability, category, numeric, or text questions.

For example, a question configured as “Were medicines available?” is shown as an available/unavailable percentage, not as a star rating. A question configured as “Did you have to pay extra money?” can treat **No** as favourable when the survey JSON explicitly marks it as positive.

### Review priority alerts

The **Priority alerts** panel lists the most important low-performing indicators in the selected scope. Each alert identifies the indicator, related facility or department, and the number of relevant responses.

Use **Open analytics** to investigate an alert before taking action. Check:

1. The selected period and number of responses.
2. The facility and department affected.
3. The survey version used for those responses.
4. Whether the issue is recurring or a recent change.

### Review trends and departments

- **Feedback trend** shows response volume over the last six months. A rise in responses does not automatically mean services are worse; review rating or distribution data in Analytics.
- **Department performance** compares average rating-type indicators by department. Use it to identify which department needs a closer review.
- **Recent feedback** shows the latest anonymous submissions in your permitted scope. It never displays the respondent's identity.

### Use quick actions

| Action | Opens |
| --- | --- |
| **QR Center** | Generate, preview, download, or print a department feedback QR poster. |
| **Explore analytics** | Detailed facility, department, survey-version, and indicator analysis. |
| **Download reports** | Report preview and export options for your permitted data. |

## QR Center

1. Open **QR Center**.
2. Select a permitted facility and department.
3. Select the active survey where applicable.
4. Choose **Generate**.
5. Preview the QR poster, then download or print it for display at the department.

Use the QR poster only at the selected department. The QR link resolves the facility and department before loading the public survey.

## Social Audit

Use **Social Audit** to generate a community-feedback QR link for a facility and survey date. QR links expire after four hours, except the non-expiring test link for NIN `1234567890`.

Participants select English or Hindi, can play Hindi question audio, and answer one question at a time. Selecting an answer advances the survey after a short pause. For all non-test facilities, one browser device can submit once per QR token. The system stores response context, a device-derived key, and IP address for audit and duplicate prevention; do not use this technical data to identify beneficiaries.

See the dedicated [Social Audit QR survey guide](social_audit.md) for the complete workflow.

## Analytics

Use **Analytics** to understand why a result appears on Home.

1. Select facility, department, survey version, indicator, and date filters as needed.
2. Select **Apply**.
3. Review the trend, response count, facility coverage, and result appropriate to the question type.

### Choose the right interpretation

| Question type | Main result to read |
| --- | --- |
| Rating | Average score and coloured stars. |
| Binary / availability | Favourable and unfavourable percentage. |
| Category | Count and percentage for each option. |
| Numeric / duration | Average and range or bands. |
| Severity | Low, medium, high, and critical distribution. |
| Text | Number of comments; review only through authorised, governed processes. |

Use **Survey version** when comparing results across changed surveys. A new version may have different questions, options, or scales; do not compare unlike indicators as if they were the same measure.

## Reports

1. Open **Reports**.
2. Choose the report filters and date range.
3. Generate the report preview.
4. Review the summary, average score with stars where applicable, facility count, and detailed table.
5. Download the approved format, such as CSV, Excel, PDF, or print output, when available.

Reports obey the same scope as Home and Analytics. A facility account receives only the assigned facility's report data.

## CAPA: corrective and preventive action

When a low-performing indicator needs action, record a **Corrective and Preventive Action (CAPA)** through the authorised CAPA workflow.

Good CAPA records include:

- Facility and department.
- The affected indicator and survey version.
- The observed evidence, period, and response count.
- The corrective action, responsible person, target date, and progress status.
- Follow-up evidence or verification outcome.

Do not enter beneficiary names or other personal details in a CAPA record. When survey questions are revised, keep the survey version with the action so future reviewers understand the evidence that triggered it.

## Profile and password

Select **My Profile** in the header to review or update the profile fields that your account is permitted to change. Use **Change password** to set a new password.

Choose a unique password and sign out when using a shared computer.

## Accessibility features

Abhipraya supports keyboard navigation and provides a **Skip to main content** link at the start of the page. Use the browser zoom control when larger text is needed. Controls use visible labels, keyboard focus, and colour with text/icons so that meaning is not conveyed by colour alone.

If a dropdown is long, use its search option to find the facility, department, indicator, or survey version.

## Public feedback

### For beneficiaries

1. Scan the facility department QR code.
2. Confirm the facility and department shown by the link.
3. Select a language.
4. Allow location access when the survey requests it. This is used only to validate the configured facility-radius rule.
5. Answer the questions and submit feedback.

The public survey is anonymous. Do not enter a name, phone number, Aadhaar number, medical record number, or other personal or medical information in a response.

## Getting help

If you cannot sign in, cannot see your assigned facility, or believe your role scope is incorrect, contact the programme system administrator. Include your username, time of the issue, and a screenshot that does not contain passwords or beneficiary data.
