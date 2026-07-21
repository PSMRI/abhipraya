import { FileBlob, SpreadsheetFile } from '@oai/artifact-tool';
import fs from 'node:fs/promises';
const wb = await SpreadsheetFile.importXlsx(await FileBlob.load('C:/Users/manish_k/Downloads/abhipyara_vapt_tes_update.xlsx'));
console.log((await wb.inspect({kind:'workbook,sheet,table',maxChars:12000,tableMaxRows:30,tableMaxCols:12,tableMaxCellChars:200})).ndjson);
for (const s of wb.worksheets.items) {
  const used=s.getUsedRange();
  console.log('SHEET',s.name, used?.address);
  if (used) console.log((await wb.inspect({kind:'region',sheetId:s.name,range:used.address,maxChars:12000})).ndjson);
  const p=await wb.render({sheetName:s.name,autoCrop:'all',scale:1,format:'png'});
  await fs.writeFile(`preview-${s.name.replace(/[^a-z0-9]/gi,'_')}.png`,new Uint8Array(await p.arrayBuffer()));
}
