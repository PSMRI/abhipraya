# Troubleshooting and FAQ

## A facility user sees another facility in a filter

Sign out and sign in again, then refresh the page. The API must enforce the facility NIN assigned to the user session. If another facility remains visible or data is returned, stop using the account and contact the system administrator.

## The Home page shows no data

Check the selected facility, department, survey version, and date range. Leaving dates empty shows all available data. Confirm that public feedback has been submitted for the selected scope.

## A report or analytical score looks unexpected

Check the indicator `report_type` and survey version. Only rating questions produce average score and stars. Binary, availability, category, severity, numeric, duration, and text questions each have different outputs.

## QR poster does not generate

Confirm that a permitted facility and department are selected and that an active survey package is available. Check browser console/network errors and server logs; do not expose a QR poster preview if generation fails.

## Documentation page still shows old content

Recycle the IIS application pool after documentation or PHP renderer updates. Browser refresh alone may not refresh a cached PHP process.

## Public survey rejects location

Check that device location permission is enabled, the facility location/radius configuration is correct, and the device is within the configured radius. Do not weaken location checks without programme approval.
