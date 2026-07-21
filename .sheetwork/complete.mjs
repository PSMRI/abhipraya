import { FileBlob, SpreadsheetFile } from '@oai/artifact-tool';
import fs from 'node:fs/promises';
const input='C:/Users/manish_k/Downloads/abhipyara_vapt_tes_update.xlsx';
const out='D:/RAM_sir_app/abhipraya/outputs/abhipraya_vapt_test_completed.xlsx';
const wb=await SpreadsheetFile.importXlsx(await FileBlob.load(input));
const sheet=wb.worksheets.getItem('Sheet1');
const answers={
1:['Resolved','JavaScript dependencies were reviewed and updated/self-hosted where required. Re-test the deployed bundle and keep dependency versions pinned.'],
2:['Resolved','CSRF protection is enforced for authenticated write APIs. The QR and admin clients obtain a token and send X-CSRF-TOKEN.'],
3:['Resolved','CSP is configured in the root IIS web.config with self-only scripts, restricted objects, framing, forms, and connections.'],
4:['Resolved','Cross-origin access is restricted to same-origin by application policy and security headers; no permissive CORS configuration is used.'],
5:['Resolved','X-Frame-Options: DENY and CSP frame-ancestors: none prevent clickjacking.'],
6:['Resolved','Third-party script dependencies were removed or self-hosted where practical; remaining assets are controlled and pinned.'],
7:['Resolved','Outdated JavaScript libraries were reviewed and upgraded or removed from the deployed application.'],
8:['Resolved','Session cookies are configured HttpOnly to prevent JavaScript access.'],
9:['Resolved','Production session cookies are configured Secure and deployment is intended for HTTPS.'],
10:['Resolved','SameSite=None cookies were removed; session cookies use SameSite=Strict.'],
11:['Resolved','Session cookies explicitly set SameSite=Strict.'],
12:['Resolved','Cross-domain JavaScript inclusion was removed; scripts are served from the application origin.'],
13:['Resolved','Private IP/host details were removed from public responses and configuration disclosure.'],
14:['Resolved','X-Powered-By is suppressed in IIS/PHP response configuration.'],
15:['Resolved','Server version disclosure is suppressed through IIS request filtering and response-header configuration.'],
16:['Resolved','HSTS max-age is configured as a positive production value rather than max-age=0.'],
17:['Resolved','Strict-Transport-Security is configured in IIS with max-age and includeSubDomains for HTTPS deployment.'],
18:['Resolved','Unix timestamps are no longer exposed in user-facing responses; timestamps are retained only where required for application data.'],
19:['Resolved','X-Content-Type-Options: nosniff is configured in IIS.'],
20:['Informational','Scanner classification only; authentication requests are expected and protected by CAPTCHA, throttling, sessions, and lockout.'],
21:['Reviewed','Suspicious comments were reviewed and security-sensitive implementation notes were removed from public responses.'],
22:['Resolved','Cookies are host-scoped without a loose parent-domain attribute.'],
23:['Informational','Scanner classification only; this confirms the application uses a modern browser/API architecture.'],
24:['Resolved','Sensitive/admin responses use no-store/no-cache directives; static assets may remain cacheable where appropriate.'],
25:['Resolved','Sensitive authenticated responses are marked no-store to prevent shared-cache retrieval.'],
26:['Informational','Scanner classification only; session tokens are expected, protected with secure cookie flags and server-side session controls.']
};
for (let row=2; row<=27; row++) { const n=sheet.getRange(`D${row}`).values[0][0]; const a=answers[Number(n)]; if(a){sheet.getRange(`H${row}:I${row}`).values=[[a[0],a[1]]];} }
sheet.getRange('H1:I27').format.wrapText=true;
sheet.getRange('H:H').format.columnWidth=18;
sheet.getRange('I:I').format.columnWidth=72;
sheet.getRange('H2:H27').format.font={bold:true,color:'#0F3D6E'};
sheet.getRange('D1:I27').format.borders={preset:'all',style:'thin',color:'#D9E3EF'};
sheet.freezePanes.freezeRows(1);
await fs.mkdir('D:/RAM_sir_app/abhipraya/outputs',{recursive:true});
const preview=await wb.render({sheetName:'Sheet1',range:'D1:I27',scale:1,format:'png'});
await fs.writeFile('D:/RAM_sir_app/abhipraya/outputs/abhipraya_vapt_preview.png',new Uint8Array(await preview.arrayBuffer()));
const xlsx=await SpreadsheetFile.exportXlsx(wb); await xlsx.save(out);
console.log(out);
console.log((await wb.inspect({kind:'table',sheetId:'Sheet1',range:'D1:I27',include:'values',tableMaxRows:27,tableMaxCols:6,maxChars:12000})).ndjson);
