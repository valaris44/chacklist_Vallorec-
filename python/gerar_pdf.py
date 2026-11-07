#!/usr/bin/env python3
"""Gera PDF final (reportlab + Pillow) e retorna uma linha final:
RESULT_JSON:{"status":"ok","pdf_path":"uploads/pdfs/xxx.pdf"} ou RESULT_JSON:{"status":"error","message":"..."}
Uso: python gerar_pdf.py /caminho/para/payload.json
"""
import sys, json, os, io, time
from datetime import datetime

def result_err(msg, code=2):
    print("RESULT_JSON:" + json.dumps({"status":"error","message":str(msg)}))
    sys.stdout.flush()
    sys.exit(code)

def result_ok(pdf_rel):
    print("RESULT_JSON:" + json.dumps({"status":"ok","pdf_path":pdf_rel}))
    sys.stdout.flush()
    sys.exit(0)

# check args
if len(sys.argv) < 2:
    result_err("Missing payload json argument")

payload_path = sys.argv[1]
if not os.path.exists(payload_path):
    result_err("Payload file not found: " + payload_path)

# check dependencies and try to auto-install when possible
missing = []
try:
    from reportlab.lib.pagesizes import A4
    from reportlab.lib.units import mm
    from reportlab.lib import colors
    from reportlab.pdfgen import canvas
except Exception as e:
    missing.append('reportlab')
try:
    from PIL import Image
except Exception as e:
    missing.append('Pillow')

if missing:
    # Try installing into the same Python environment we are running
    try:
        print('Missing packages detected:', missing, '— attempting to install via pip...')
        import subprocess
        subprocess.check_call([sys.executable, '-m', 'pip', 'install'] + missing)
        # attempt imports again
        from reportlab.lib.pagesizes import A4
        from reportlab.lib.units import mm
        from reportlab.lib import colors
        from reportlab.pdfgen import canvas
        from PIL import Image
    except Exception as e:
        result_err("missing dependency(s): " + ", ".join(missing) + ". Failed to auto-install: " + str(e))

# safe main
try:
    with open(payload_path,'r',encoding='utf-8') as f:
        payload = json.load(f)
    data = payload.get('data',{})
    checklist_id = payload.get('checklist_id', int(time.time()))
    timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
    pdf_name = f"checklist_{checklist_id}_{timestamp}.pdf"
    out_dir = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'uploads', 'pdfs')
    os.makedirs(out_dir, exist_ok=True)
    out_path = os.path.join(out_dir, pdf_name)

    c = canvas.Canvas(out_path, pagesize=A4)
    w,h = A4
    # header
    c.setFillColor(colors.HexColor('#002F57'))
    c.rect(20*mm, h - 30*mm, 40*mm, 10*mm, fill=1, stroke=0)
    c.setFillColor(colors.white)
    c.setFont("Helvetica-Bold", 12)
    c.drawString(22*mm, h - 27*mm, "Vallourec")
    c.setFillColor(colors.black)
    c.setFont("Helvetica-Bold", 14)
    c.drawString(65*mm, h - 26*mm, "Checklist Elétrica – Finishing")
    c.setFont("Helvetica", 9)
    c.drawString(65*mm, h - 32*mm, f"Enviado em: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")

    # minimal content (similar to previous implementation)
    y = h - 48*mm
    c.setFont("Helvetica-Bold", 10)
    c.drawString(20*mm, y, "Dados gerais:")
    c.setFont("Helvetica", 9)
    y -= 6*mm
    c.drawString(22*mm, y, f"Data: {data.get('date','')}")
    c.drawString(80*mm, y, f"Área: {data.get('area','')}")
    y -= 5*mm
    c.drawString(22*mm, y, f"Colaborador: {data.get('nome','')} (Mat.: {data.get('matricula','')})")

    # items (simple listing)
    y -= 12*mm
    c.setFont("Helvetica-Bold", 10)
    c.drawString(20*mm, y, "Itens:")
    y -= 6*mm
    c.setFont("Helvetica", 9)
    items = data.get('items',[])
    def draw_footer():
        c.setFont("Helvetica", 8)
        c.setFillColor(colors.HexColor('#666666'))
        c.drawRightString(w - 20*mm, 12*mm, f"Page {c.getPageNumber()}")

    for it in items:
        # ensure space for item plus possible image
        if y < 60*mm:
            draw_footer()
            c.showPage()
            y = h - 20*mm
        desc = it.get('description','')
        status = (it.get('status') or '').lower()
        obs = it.get('obs','')

        # status color box
        if status in ('verificado','s','sim'):
            st_color = colors.HexColor('#1FA05A')  # green
        elif status in ('na','n/a'):
            st_color = colors.HexColor('#FFD24A')  # yellow
        elif status in ('nao_verificado','n','não verificado','nao verificado'):
            st_color = colors.HexColor('#E84B4B')  # red
        else:
            st_color = colors.HexColor('#CCCCCC')  # neutral

        c.setFillColor(st_color)
        c.rect(20*mm, y - 3*mm, 3*mm, 3*mm, fill=1, stroke=0)

        c.setFillColor(colors.black)
        c.setFont("Helvetica-Bold", 9)
        c.drawString(24*mm, y - 1*mm, desc[:120])
        y -= 6*mm
        if obs:
            c.setFont("Helvetica", 8)
            c.drawString(24*mm, y, "Obs: " + obs[:200])
            y -= 6*mm

        # Photos: place first one to the right side of the content area
        photos = it.get('photos', []) or []
        if photos:
            for p_rel in photos[:2]:
                p_abs = p_rel if os.path.isabs(p_rel) else os.path.join(os.path.dirname(os.path.dirname(__file__)), p_rel)
                if os.path.exists(p_abs):
                    try:
                        img_pil = Image.open(p_abs)
                        # fit into 40mm width
                        target_w_pt = 40*mm
                        ratio = img_pil.width / float(img_pil.height)
                        target_h_pt = target_w_pt / ratio
                        x_img = w - 62*mm  # right area
                        y_img = y - (target_h_pt - 6*mm)
                        c.drawImage(p_abs, x_img, y_img, width=target_w_pt, height=target_h_pt, preserveAspectRatio=True, mask='auto')
                        # shift left for a second image if present
                        w -= 0
                    except Exception:
                        pass
        # small spacing after each item
        y -= 4*mm

    if y < 60*mm:
        draw_footer(); c.showPage(); y = h - 20*mm

    # signature
    if payload.get('signature_path'):
        try:
            sig = Image.open(payload['signature_path'])
            sig.thumbnail((260,80))
            sig_io = io.BytesIO()
            sig.save(sig_io, format='PNG')
            sig_io.seek(0)
            c.drawInlineImage(Image.open(sig_io), 22*mm, 30*mm, width=80*mm, height=30*mm)
            c.setFont("Helvetica", 8)
            c.drawString(22*mm, 25*mm, f"Assinatura: {data.get('nome','')}")
        except Exception as e:
            # ignore insertion errors
            pass

    # draw footer on the final page then save
    c.setFont("Helvetica", 8)
    c.setFillColor(colors.HexColor('#666666'))
    c.drawRightString(w - 20*mm, 12*mm, f"Page {c.getPageNumber()}")
    c.showPage()
    c.save()

    # Try to lock with PyPDF2 if available (optional)
    try:
        from PyPDF2 import PdfReader, PdfWriter
        reader = PdfReader(out_path)
        writer = PdfWriter()
        for p in reader.pages:
            writer.add_page(p)
        writer.encrypt(user_pwd="", owner_pwd="owner_secret", use_128bit=True)
        tmp = out_path + ".locked"
        with open(tmp, "wb") as fh:
            writer.write(fh)
        os.replace(tmp, out_path)
    except Exception:
        # not fatal, continue without lock
        pass

    # return relative path
    rel = os.path.join('uploads','pdfs', pdf_name)
    result_ok(rel)

except Exception as e:
    result_err(str(e))