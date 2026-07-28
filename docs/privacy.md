# Privacy and applicable-law implementation guide

## Application privacy baseline

Abhipraya collects anonymous survey answers, facility/department reference, a location-validation result, response timestamp and limited security telemetry. Public beneficiaries do not create accounts and the survey must not request name, phone number, email, government ID or exact home address.

GPS is used only to confirm that the survey is being completed within the configured facility radius. Store the validation outcome and permitted facility reference; do not retain exact coordinates in public exports.

## Retention schedule

The deploying authority must approve the final periods. The recommended baseline is:

- survey answers and aggregated reports: retain for the approved programme period, then delete or irreversibly aggregate;
- session, IP and rate-limit security logs: retain for the minimum period needed for fraud/security investigation, then delete;
- CAPTCHA and authentication events: retain only for security monitoring and incident response;
- exported files: retain under the authority’s records schedule and delete from temporary locations after transfer.

Record the approved period, owner, deletion method and last deletion date in the release evidence pack.

## Rights and grievance route

Publish the deploying authority’s privacy contact and grievance route on the survey and administrator pages. Requests should be logged, acknowledged, identity-verified where required by law, assigned an owner and closed with a written outcome. Anonymous survey responses may not be attributable to a person; explain this limitation in the notice.

Complete a legal review for every deployment state/country before production. Record notice version, approval date, retention schedule, deletion process and grievance response owner. This repository provides the implementation baseline; it is not legal advice and does not replace review under applicable health, privacy, records and public-sector laws.
