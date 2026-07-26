# Use cases

## UC-01: Anonymous department feedback

**Actor:** Beneficiary

1. The beneficiary scans a QR poster at a facility department.
2. Abhipraya resolves the facility, department, and active survey version.
3. The beneficiary chooses a language, answers the questions, and submits feedback.
4. The system stores the response without asking for an account or identity.

**Outcome:** The programme receives structured, department-specific feedback.

## UC-02: Facility service review

**Actor:** Facility Administrator

1. The administrator signs in.
2. Home automatically limits data to the assigned facility.
3. The administrator reviews response count, average rating, priority indicators, department performance, and recent feedback.
4. The administrator opens Analytics or Reports for deeper review.

**Outcome:** The facility team can focus on its own service gaps without viewing another facility's data.

## UC-03: QR poster management

**Actor:** Authorised administrator

1. Select a permitted facility and department.
2. Generate or preview the QR link for the active survey.
3. Download or print the poster.
4. Display it at the relevant department.

**Outcome:** Beneficiaries reach the correct feedback survey with one scan.

## UC-04: Indicator analysis

**Actor:** Data Analyst or authorised administrator

1. Select facility, department, indicator, survey version, and period.
2. Review the type-appropriate result: stars, percentage, distribution, numeric summary, or duration band.
3. Compare facilities or departments only within the permitted scope.

**Outcome:** Decisions are based on the question’s meaning, rather than treating every answer as a rating.

## UC-05: Corrective and preventive action

**Actor:** Facility Administrator

1. Identify a low-performing indicator from Home or Analytics.
2. Record the CAPA action with facility, department, indicator, survey version, owner, due date, and status.
3. Update progress and verification evidence.
4. Recheck later survey data to assess improvement.

**Outcome:** Feedback is connected to a documented improvement process.

## UC-06: Survey change without losing history

**Actor:** Survey Manager

1. Prepare a candidate survey JSON package.
2. Validate it and review the difference from the active version.
3. Publish a new version with a schema hash.
4. Use version filters in analysis and reports to interpret old and new responses correctly.

**Outcome:** New questions can be introduced without corrupting historical analysis.
