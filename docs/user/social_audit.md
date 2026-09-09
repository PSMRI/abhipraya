# Social Audit QR survey

## Purpose

Social Audit collects community feedback about regular Health and Wellness Centre services. It is separate from the department feedback QR workflow.

## Create a QR link

1. Sign in and open **Social Audit** from the administrator navigation.
2. Select a facility and survey date.
3. Select **Generate QR**.
4. Display, share, or test the generated QR link.

QR links for ordinary facilities are valid for four hours. The configured test facility, NIN `1234567890`, uses a non-expiring QR link for testing only.

## Participant journey

1. Scan the Social Audit QR code.
2. Select English or Hindi. The Hindi flow provides a language prompt, question audio, and a thank-you audio prompt.
3. Select an answer. The survey briefly confirms the selection, stops any current audio, and advances automatically to the next question.
4. Submit the final answer.

The survey displays one question at a time, progress, colour-coded answers (green for Yes, red for No, and blue for Don't know), and an audio button. A participant can use Back to change an earlier answer.

## Duplicate-submission rule

For all facilities except test NIN `1234567890`, one browser device can submit only once for a Social Audit QR token. The server records a device-derived key in `social_audit_submissions` before saving answers. This database rule prevents duplicate or simultaneous repeat submissions from the same browser device.

The test facility is exempt so that repeat testing is possible. Clearing browser storage, changing browsers, or using another device is treated as a different device; use a login or verified contact method if stricter person-level uniqueness is required.

## Privacy and audit data

Social Audit does not ask for names, phone numbers, Aadhaar numbers, or medical-record details. The application stores the selected answer, survey context, language, device-derived key, timestamp, and the network IP address for abuse prevention and audit. Access to this technical data must be limited to authorised administrators and handled according to the deployment's privacy policy.
