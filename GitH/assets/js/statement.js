/* Printed billing statement in the IIRGP form layout (ISAT U seal top left).
   printBillingStatement(code): asks the server for the statement (which also counts the print for Rev. No.),
   builds the form in a hidden frame and opens the print dialog. Needs api.js (apiPost, esc, toast). */

const BS_MIN_ROWS = 22;

function bsMoney(n) {
  return Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function billingStatementHtml(s, sealUrl) {
  const term = `${s.semester}, AY ${s.academic_year}`;
  const filled = s.items.map(i => `<tr>
      <td class="c1">${esc(s.date)}</td>
      <td class="c2">${esc(s.student_name)}</td>
      <td class="detail">${esc(i.title)}<small>Subscription · ${esc(term)}</small></td>
      <td class="c4">${bsMoney(i.price)}</td></tr>`).join('');
  const blanks = '<tr><td></td><td></td><td></td><td></td></tr>'.repeat(Math.max(0, BS_MIN_ROWS - s.items.length));
  return `<!doctype html><html lang="en"><head><meta charset="utf-8">
<title>Billing Statement ${esc(s.billing_code)}</title>
<style>
@page { size: A4; margin: 12mm 14mm; }
* { box-sizing: border-box; }
body { margin: 0; font-family: Arial, "Liberation Sans", Helvetica, sans-serif; color: #000; font-size: 10pt; }
.page { width: 182mm; margin: 0 auto; }
table { border-collapse: collapse; width: 100%; }
.hdr td { border: 1px solid #000; padding: 2px 6px; font-size: 8.5pt; }
.hdr .logo { width: 30mm; text-align: center; vertical-align: middle; padding: 4px; }
.hdr .logo img { width: 25mm; display: block; margin: 0 auto; }
.hdr .uni { text-align: center; line-height: 1.5; }
.hdr .uni b { font-size: 9pt; }
.hdr .title { text-align: center; font-weight: bold; font-size: 14pt; padding: 6px; }
.hdr .k { width: 26mm; }
.hdr .v { width: 38mm; text-align: center; font-weight: bold; height: 5.2mm; white-space: nowrap; }
.hdr .v.code { font-size: 8pt; }
.sub { text-align: center; margin: 14px 0 0; }
.sub .name { font-weight: bold; font-size: 12pt; text-decoration: underline; }
.sub .op { font-size: 8.5pt; margin-top: 1px; }
.billno { text-align: right; font-size: 8.5pt; font-weight: bold; margin: 14px 0 8px; }
.billno span { display: inline-block; width: 56mm; border-bottom: 1px solid #000; text-align: left; padding-left: 6px; font-size: 10pt; }
.client td { padding: 3px 0; font-size: 9.5pt; }
.client .lbl { width: 30mm; }
.client .val { border-bottom: 1px solid #000; padding-left: 4px; }
.client .pad { width: 30mm; }
.items { margin-top: 12px; border: 2px solid #000; }
.items th { border: 1px solid #000; border-bottom: 2px solid #000; padding: 6px 4px; font-size: 9.5pt; }
.items td { border: 1px solid #000; height: 5.6mm; padding: 2px 5px; font-size: 9pt; vertical-align: top; }
.items .c1 { width: 25mm; text-align: center; }
.items .c2 { width: 40mm; }
.items .c4 { width: 28mm; text-align: right; }
.items .detail small { display: block; font-size: 7.5pt; color: #333; }
.items tfoot td { font-weight: bold; text-align: center; border-top: 2px solid #000; padding: 5px; height: auto; font-size: 9.5pt; }
.items tfoot td.c4 { text-align: right; }
tr { page-break-inside: avoid; }
.sig { margin-top: 16px; font-size: 9.5pt; page-break-inside: avoid; }
.sig td { vertical-align: top; }
.line { border-top: 1px solid #000; margin-top: 34px; text-align: center; padding-top: 2px; }
.signed { text-align: center; font-weight: bold; margin-top: 18px; }
.signed + .line { margin-top: 2px; }
.pay { margin-top: 20px; font-size: 8pt; font-weight: bold; page-break-inside: avoid; }
.pay td { padding-top: 12px; white-space: nowrap; }
.pay .blank { border-bottom: 1px solid #000; }
</style></head><body><div class="page">
<table class="hdr">
  <tr><td class="logo" rowspan="4"><img src="${sealUrl}" alt="Iloilo Science and Technology University seal"></td>
      <td class="uni" rowspan="2">Republic of the Philippines<br><b>ILOILO SCIENCE AND TECHNOLOGY UNIVERSITY</b><br>La Paz, Iloilo City</td>
      <td class="k">Department:</td><td class="v">IIRGP</td></tr>
  <tr><td class="k">Document Code:</td><td class="v code">${esc(s.document_code)}</td></tr>
  <tr><td class="title" rowspan="2">BILLING STATEMENT</td><td class="k">Rev. No.:</td><td class="v">${Number(s.rev_no)}</td></tr>
  <tr><td class="k">Effective Date:</td><td class="v">${esc(s.effective_date)}</td></tr>
</table>
<div class="sub"><div class="name">Learning Asset Subscription &amp; Sales Operation</div>
<div class="op">IIRGP Project / Operation</div></div>
<div class="billno">BILLING NO.: <span>${esc(s.billing_code)}</span></div>
<table class="client">
  <tr><td class="lbl">Client/Student:</td><td class="val">${esc(s.student_name)} &nbsp;(${esc(s.student_id_number)})</td><td class="pad"></td></tr>
  <tr><td class="lbl">Program/Year:</td><td class="val">${esc(s.program)}, ${esc(s.year_level)} – Section ${esc(s.section)}</td><td class="pad"></td></tr>
</table>
<table class="items">
  <thead><tr><th class="c1">Date of<br>Transaction</th><th class="c2">Responsible Person /<br>Requesting Officer</th><th>Event / Transaction Details</th><th class="c4">Amount Due</th></tr></thead>
  <tbody>${filled}${blanks}</tbody>
  <tfoot><tr><td colspan="3">TOTAL AMOUNT DUE (PhP)</td><td class="c4">${bsMoney(s.total)}</td></tr></tfoot>
</table>
<table class="sig">
  <tr><td style="width:40%">Prepared by:<div class="line">Project In-Charge/Manager</div></td><td style="width:20%"></td>
      <td style="width:40%">Verified/Reviewed by:<div class="line">Director</div></td></tr>
  <tr><td><div class="line" style="margin-top:28px">Date</div></td>
      <td style="text-align:center; padding-top:8px">Conformed/Received:<div class="signed">${esc(s.student_name)}</div><div class="line">Client/Student</div></td>
      <td><div class="line" style="margin-top:28px">Date</div></td></tr>
</table>
<table class="pay"><tr>
  <td style="width:1%">Amount Paid:&nbsp;</td><td class="blank" style="width:30%"></td>
  <td style="width:1%; padding-left:14px">O.R. No.:&nbsp;</td><td class="blank" style="width:38%"></td>
  <td style="width:1%; padding-left:14px">Date:&nbsp;</td><td class="blank" style="width:20%"></td>
</tr></table>
</div></body></html>`;
}

async function printBillingStatement(code, btn) {
  if (btn) btn.disabled = true;
  try {
    const r = await apiPost('/cart/statement_print.php', { billing_code: code });
    if (!r.success) { toast(r.message, 'error'); return; }
    document.getElementById('bsPrintFrame')?.remove();
    const frame = document.createElement('iframe');
    frame.id = 'bsPrintFrame';
    frame.setAttribute('aria-hidden', 'true');
    frame.style.cssText = 'position:fixed; right:0; bottom:0; width:0; height:0; border:0; visibility:hidden;';
    document.body.appendChild(frame);
    const doc = frame.contentDocument;
    doc.open(); doc.write(billingStatementHtml(r.statement, location.origin + '/assets/img/isatu-seal.png')); doc.close();
    const img = doc.querySelector('img');
    if (img && !img.complete) await new Promise(res => { img.onload = img.onerror = res; });
    frame.contentWindow.focus();
    frame.contentWindow.print();
  } finally {
    if (btn) btn.disabled = false;
  }
}
