#!/usr/bin/env python3
"""
Enviar email simples com anexo PDF.
Uso: python enviar_email.py <relative_pdf_path> [recipient]
Retorna uma linha RESULT_JSON:{"status":"ok"} ou {"status":"error","message":"..."}
"""
import sys, os, smtplib, json
from email.message import EmailMessage

def result_err(msg, code=2):
    print("RESULT_JSON:" + json.dumps({"status":"error","message":str(msg)}))
    sys.stdout.flush()
    sys.exit(code)

def result_ok():
    print("RESULT_JSON:" + json.dumps({"status":"ok"}))
    sys.stdout.flush()
    sys.exit(0)

if len(sys.argv) < 2:
    result_err("Missing pdf path argument")

pdf_rel = sys.argv[1]
DEFAULT_TO = os.getenv('DEFAULT_TO', '')
recipient = sys.argv[2] if len(sys.argv) > 2 else DEFAULT_TO

base = os.path.dirname(os.path.dirname(__file__))
pdf_path = os.path.join(base, pdf_rel) if not os.path.isabs(pdf_rel) else pdf_rel
if not os.path.exists(pdf_path):
    result_err("PDF not found: " + pdf_path)

SMTP_HOST = os.getenv('SMTP_HOST', '')
SMTP_PORT = int(os.getenv('SMTP_PORT', 25))
SMTP_USER = os.getenv('SMTP_USER', '')
SMTP_PASS = os.getenv('SMTP_PASS', '')

# If SMTP is not configured, return a non-fatal skipped status so caller can continue
if not SMTP_HOST or not recipient:
    print("RESULT_JSON:" + json.dumps({"status":"skipped","message":"SMTP not configured (SMTP_HOST or DEFAULT_TO missing). Skipping email."}))
    sys.stdout.flush()
    sys.exit(0)

msg = EmailMessage()
msg['Subject'] = 'Checklist Elétrica – Vallourec'
msg['From'] = SMTP_USER or 'no-reply@vallourec.local'
msg['To'] = recipient
msg.set_content('O checklist foi gerado automaticamente. O PDF em anexo contém o relatório final para auditoria.')

with open(pdf_path, 'rb') as f:
    data = f.read()
msg.add_attachment(data, maintype='application', subtype='pdf', filename=os.path.basename(pdf_path))

try:
    if SMTP_USER and SMTP_PASS:
        with smtplib.SMTP(SMTP_HOST, SMTP_PORT, timeout=15) as s:
            s.starttls()
            s.login(SMTP_USER, SMTP_PASS)
            s.send_message(msg)
    else:
        # try local SMTP (may fail if not configured)
        with smtplib.SMTP(SMTP_HOST, SMTP_PORT, timeout=15) as s:
            s.send_message(msg)
    result_ok()
except Exception as e:
    result_err("email_error:" + str(e))
