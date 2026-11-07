#!/usr/bin/env python3
"""Gera planilha XLSX a partir do template oficial e gera PDF travado para auditoria.

Regras importantes:
- Nunca modifica o template original. Sempre copia antes de preencher.
- Mantém a estrutura e formatos do template.
- Usa 'python/templates/checklist_eletrica_finishing.xlsx' como fonte (procura cópias alternativas se necessário).
"""
import sys, json, os, shutil, subprocess, tempfile, uuid
from datetime import datetime

# Attempt to import openpyxl and Pillow; if missing try to install automatically
try:
    from openpyxl import load_workbook
    from openpyxl.drawing.image import Image as XLImage
    from openpyxl.cell import MergedCell
    from openpyxl.utils.cell import get_column_letter, coordinate_from_string, column_index_from_string
except Exception as e:
    # Try to auto-install the required packages into this Python environment
    try:
        print('openpyxl or Pillow missing, attempting to install...')
        subprocess.check_call([sys.executable, '-m', 'pip', 'install', 'openpyxl', 'pillow'], stdout=subprocess.DEVNULL)
        from openpyxl import load_workbook
        from openpyxl.drawing.image import Image as XLImage
        from openpyxl.cell import MergedCell
        from openpyxl.utils.cell import get_column_letter, coordinate_from_string, column_index_from_string
    except Exception as e2:
        print('Erro: openpyxl (e Pillow para imagens) são necessários e não puderam ser instalados:', e2)
        sys.exit(2)

# Optional: PyPDF2 para 'travar' PDF
try:
    from PyPDF2 import PdfReader, PdfWriter
    PYPDF2_AVAILABLE = True
except Exception:
    PYPDF2_AVAILABLE = False

if len(sys.argv) < 2:
    print('Usage: gerar_planilha.py <input_json>')
    sys.exit(1)

inp = sys.argv[1]
with open(inp,'r',encoding='utf-8') as f:
    payload = json.load(f)

# Extrai campos
payload_data = payload.get('data', {})
planilha_path = payload.get('planilha_path')
signature_path = payload.get('signature_path')

# Configurações locais
BASE_DIR = os.path.dirname(__file__)
TEMPLATE_DIR = os.path.join(BASE_DIR, 'templates')
EXPECTED_TEMPLATE = os.path.join(TEMPLATE_DIR, 'checklist_eletrica_finishing.xlsx')

# If expected template doesn't exist, try to find a close match and copy it to the expected name
if not os.path.exists(EXPECTED_TEMPLATE):
    print('Template exato não encontrado em:', EXPECTED_TEMPLATE)
    # search for probable template files
    candidates = [f for f in os.listdir(TEMPLATE_DIR) if os.path.isfile(os.path.join(TEMPLATE_DIR, f))]
    match = None
    for c in candidates:
        low = c.lower()
        if 'eletric' in low or 'elétrica' in low or 'finishing' in low or ('check' in low and 'ele' in low):
            match = c
            break
    if match:
        src = os.path.join(TEMPLATE_DIR, match)
        try:
            shutil.copy2(src, EXPECTED_TEMPLATE)
            print(f'Arquivo de template encontrado ({match}) copiado para {os.path.basename(EXPECTED_TEMPLATE)}')
        except Exception as e:
            print('Erro ao copiar template candidato:', e)
            sys.exit(3)
    else:
        print('Nenhum template plausível encontrado. Por favor coloque o arquivo oficial em:', EXPECTED_TEMPLATE)
        sys.exit(4)

# If planilha_path not provided, generate a standardized filename inside uploads/planilhas
if not planilha_path:
    out_dir = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'uploads', 'planilhas')
    os.makedirs(out_dir, exist_ok=True)
    cid = payload.get('checklist_id') or payload_data.get('matricula') or 'unknown'
    date_str = (payload_data.get('date') or datetime.now().strftime('%Y%m%d')).replace(' ','_').replace('/','-')
    planilha_path = os.path.join(out_dir, f"checklist_eletrica_{cid}_{date_str}.xlsx")

os.makedirs(os.path.dirname(planilha_path), exist_ok=True)

# Create a temp copy path then save final workbook to planilha_path
tpl_copy = tempfile.NamedTemporaryFile(prefix='tpl_', suffix='.xlsx', delete=False)
tpl_copy.close()
shutil.copy2(EXPECTED_TEMPLATE, tpl_copy.name)

# Load workbook from the temp copy
try:
    wb = load_workbook(tpl_copy.name)
    ws = wb.active
except Exception as e:
    print('Erro ao abrir cópia do template:', e)
    sys.exit(6)

# Helpers: normalize coordinates to the top-left cell in a merged range
from openpyxl.utils import get_column_letter
from openpyxl.utils.cell import coordinate_from_string, column_index_from_string

def normalized_coord(coord):
    try:
        col, row = coordinate_from_string(coord)
        col_idx = column_index_from_string(col)
        for mr in ws.merged_cells.ranges:
            min_col, min_row, max_col, max_row = mr.bounds
            if min_col <= col_idx <= max_col and min_row <= row <= max_row:
                return f"{get_column_letter(min_col)}{min_row}"
    except Exception:
        pass
    return coord


def set_cell(coord, value):
    coord = normalized_coord(coord)
    try:
        ws[coord] = value
    except Exception as e:
        print(f'Erro ao escrever em {coord}:', e)

# Try to map visible label cells (date, area, nome, matricula, setor, responsavel, assinatura)
label_map = {}
search_keys = ['date','data','area','local','nome','matricula','setor','responsavel','responsável','assinatura']
for row in ws.iter_rows():
    for cell in row:
        if cell.value and isinstance(cell.value, str):
            vl = cell.value.strip().lower()
            for k in search_keys:
                if k in vl and k not in label_map:
                    label_map[k] = cell

# Fill mapped cells: prefer writing to the cell right of the label if available
def write_label(key, value):
    if key in label_map:
        cl = label_map[key]
        target_col = cl.column + 1
        target_row = cl.row
        target = f"{get_column_letter(target_col)}{target_row}"
        try:
            set_cell(target, value)
            return
        except Exception:
            pass
        # fallback: write into the label cell itself (normalized)
        set_cell(cl.coordinate, value)
    else:
        print(f'Label nao encontrado para {key}')

# Fill fixed mapped fields
set_cell('A1', 'ROTA ELÉTRICA')
write_label('local', f"LOCAL: {payload_data.get('area') or 'FÁBRICA DE LUVAS'}")
write_label('data', f"DATA: {payload_data.get('date','')}")
write_label('responsável', f"RESPONSÁVEL: {payload_data.get('responsavel','')}")

# Cabeçalho padrão do template (linha 7)
set_cell('A7', 'ITEM A VERIFICAR')
set_cell('F7', 'S')
set_cell('G7', 'N')
set_cell('H7', 'NA')

# Items: start at row 9
start_row = 9
items = payload_data.get('items', [])
for idx, it in enumerate(items, start=1):
    r = start_row + idx - 1
    # A -> item number
    set_cell(f'A{r}', idx)
    # B -> description
    set_cell(f'B{r}', it.get('description',''))
    # Clear F,G,H before setting
    set_cell(f'F{r}', '')
    set_cell(f'G{r}', '')
    set_cell(f'H{r}', '')
    status = (it.get('status') or '').lower()
    if status in ('verificado','s','sim'):
        set_cell(f'F{r}', 'X')
    elif status in ('nao_verificado','n','não verificado','nao verificado','n'):
        set_cell(f'G{r}', 'X')
    elif status in ('na','n/a'):
        set_cell(f'H{r}', 'X')

    # Insert up to two photos for each item (if available), into columns I and K
    photos = it.get('photos', []) or []
    for i_photo, rel in enumerate(photos[:2]):
        abs_path = rel if os.path.isabs(rel) else os.path.join(os.path.dirname(os.path.dirname(__file__)), rel)
        if os.path.exists(abs_path):
            try:
                pimg = XLImage(abs_path)
                pimg.width = 100
                pimg.height = 60
                col = 9 + (i_photo * 2)  # I=9, K=11
                target_coord = f"{get_column_letter(col)}{r}"
                ws.add_image(pimg, target_coord)
            except Exception as e:
                print('Erro ao inserir foto', abs_path, e)

# Observações: título em A15, texto em A16-A17 (preserva múltiplas linhas)
obs = payload_data.get('obs_geral','') or ''
set_cell('A15', 'OBSERVAÇÕES')
if obs:
    lines = str(obs).splitlines()
    if len(lines) == 1:
        set_cell('A16', lines[0])
    else:
        set_cell('A16', lines[0])
        set_cell('A17', '\n'.join(lines[1:]))

# Legenda: A18 (título) e D18 (texto)
set_cell('A18', 'LEGENDA')
set_cell('D18', payload_data.get('legenda','S = Sim  •  N = Não  •  NA = Não aplicável'))

# Assinatura: procura por célula que contenha 'assinatura' e insere imagem abaixo
if signature_path and os.path.exists(signature_path):
    sign_cell = None
    for row in ws.iter_rows():
        for cell in row:
            if cell.value and isinstance(cell.value, str) and 'assinatura' in cell.value.lower():
                sign_cell = cell
                break
        if sign_cell:
            break
    try:
        img = XLImage(signature_path)
        img.width = 360
        img.height = 120
        if sign_cell:
            target = ws.cell(row=sign_cell.row+1, column=sign_cell.column)
            ws.add_image(img, target.coordinate)
            print('Assinatura inserida em', target.coordinate)
        else:
            # fallback: insert near bottom (two rows after last item)
            fallback_row = start_row + len(items) + 2
            target = ws.cell(row=fallback_row, column=2)
            ws.add_image(img, target.coordinate)
            print('Assinatura inserida em fallback', target.coordinate)
    except Exception as e:
        print('Erro ao inserir assinatura (Pillow/OpenPyXL):', e)

# Save filled workbook to desired path (never overwrite template)
try:
    wb.save(planilha_path)
    print('Planilha salva em:', planilha_path)
except Exception as e:
    print('Erro ao salvar planilha final:', e)
    sys.exit(7)

# We do not convert XLSX to PDF here (LibreOffice is not used). PDFs are generated separately by gerar_pdf.py which uses reportlab.
pdf_path = None

# Saída final em JSON para o chamador PHP inspecionar
out = {
    'planilha_path': planilha_path,
    'pdf_path': None
}
print(json.dumps(out))
