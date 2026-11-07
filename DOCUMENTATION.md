# Checklist Elétrica — Vallourec

Documentação técnica do projeto. Objetivo: descrever funcionamento, arquitetura, requisitos e procedimentos de instalação e diagnóstico para uso e manutenção.

---

## 1. Visão geral

Aplicação para digitalização de um checklist elétrico. O fluxo principal consiste em: coleta de dados via formulário web, persistência em banco, geração de planilha (XLSX/XLSM) e opção de envio por e‑mail.

Público-alvo: desenvolvedores, operadores e administradores responsáveis por instalação, manutenção e execução do sistema.

---

## 2. Arquitetura e componentes

- Frontend: `public/` (HTML/CSS/JS).
- API: `api/` (PHP com PDO para MySQL).
- Processamento offline: `python/` (geração de planilhas e envio de e‑mail).
- Dados e configurações: `config/`, `data/`.
- Armazenamento de artefatos: `uploads/` (assinaturas e planilhas).
- Logs: `logs/`.
- Banco de dados: MySQL (definições em `schema.sql`).

---

## 3. Fluxo de dados

1. O frontend envia um POST JSON para `api/checklist_submit.php`.
2. O endpoint valida o payload e grava registros nas tabelas `checklists` e `checklist_items`.
3. Se presente, a assinatura em data URL (PNG) é decodificada e salva em `uploads/assinaturas/`.
4. O servidor gera um arquivo JSON temporário e invoca `python/gerar_planilha.py`, que cria a planilha em `uploads/planilhas/`.
5. Em caso de sucesso, o caminho da planilha é gravado em `checklists.planilha_path` e o endpoint retorna a URL de download.
6. Opcionalmente, `python/enviar_email.py` anexa e envia a planilha por SMTP, conforme variáveis de ambiente configuradas.

---

## 4. API — Endpoints

### POST /api/checklist_submit.php

- Content-Type: `application/json`.
- Campos principais (no objeto `data`):
  - `date` (YYYY-MM-DD), `area`, `nome`, `matricula`, `items`, `signature` (data URL ou `null`).
  - `items`: array de objetos com `section`, `description`, `status` (`conforme|atencao|nao_conforme`), `obs`, e `photos` (array de data URLs de imagens).
- Comportamento: valida campos, grava no banco, persiste assinatura, grava as fotos em disco (em `uploads/evidencias/<checklist_id>/<item_id>/`), executa gerador de planilha e gerador de PDF (`python/gerar_pdf.py`) e atualiza `planilha_path` e `pdf_path`.
- Respostas:
  - Sucesso: JSON com `success: true`, nomes dos arquivos gerados e URLs (`planilha_download`, `pdf_download`).
  - Erro: HTTP 400 para dados inválidos; HTTP 500 para falhas internas (logs em `logs/submit.log`).

### GET /api/checklist_history.php

- Retorna histórico de checklists com links XLS e PDF quando disponíveis.

### GET /api/checklist_detail.php?id=<id>

- Retorna itens do checklist e metadados, incluindo links de download para fotos (miniaturas no frontend). Use este endpoint para apresentação no histórico.

### GET /api/checklist_download.php?file=<relative_path_or_basename>

- Serve planilhas, PDFs e imagens. Para evidências, passe o caminho relativo de `uploads/evidencias` (ex.: `123/456/photo_1.jpg`). O endpoint valida o caminho e evita traversal.

Exemplo mínimo (veja `test_payload.json`):

```json
{
  "data": {
    "date": "2025-12-30",
    "area": "Sala Elétrica",
    "nome": "Teste CLI",
    "matricula": "99999",
    "items": [ { "section": "Painéis", "description": "Verificar temp", "status": "verificado", "obs": "OK" } ],
    "signature": null
  }
}
```

### GET /api/checklist_history.php

Retorna os registros recentes (até 100) com metadados e URL de download quando a planilha existir (`planilha_download`).

### GET /api/checklist_download.php?file=<basename>

Serve o arquivo indicado (somente por `basename`) a partir da pasta configurada em `config/app.php` (`planilhas_folder`). Retorna 404 se não existir.

### POST /api/debug_json.php

Endpoint para inspeção de payloads: grava o JSON bruto em `logs/` e retorna informações sobre seu parse.

---

## 5. Banco de dados

Arquivo: `schema.sql` — tabelas principais:

- `checklists`:
  - `id`, `date`, `area`, `nome`, `matricula`, `setor`, `responsavel`, `obs_geral`, `signature_path`, `planilha_path`, `created_at`.
- `checklist_items`:
  - `id`, `checklist_id` (FK), `description`, `status`, `obs`.

Script auxiliar: `scripts/migrate_add_planilha.php` adiciona a coluna `planilha_path` caso necessário. Use `php scripts/migrate_add_pdf.php` para adicionar a coluna `pdf_path` (se necessário) para armazenar caminho do PDF gerado.

---

## 6. Scripts Python

### gerar_planilha.py

- Entrada: JSON temporário com `data`, `signature_path`, `planilha_path` e `checklist_id`.
- Produz: arquivo XLSX ou XLSM (quando um template `.xlsm` é usado), preenchendo campos e itens.
- Dependências: `openpyxl`. Insere imagens (assinaturas) se `Pillow` estiver disponível.
- Local de saída: `uploads/planilhas/`.
### gerar_pdf.py (novo)

- Cria o RELATÓRIO FINAL EM PDF que é o documento oficial para auditoria.
- Entrada: mesmo JSON usado pelo gerador de planilha (`data`, `signature_path`, `planilha_path`, caminhos de fotos por item em `photo_paths`).
- Produz: PDF em `uploads/pdfs/` com layout corporativo, status coloridos, observações, miniaturas das fotos e assinatura.
- Dependências: `reportlab`, `Pillow` (para manipulação de imagens). Opcional: `PyPDF2` para travar/encriptar o PDF.
### enviar_email.py

- Envia a planilha por SMTP para destinatários definidos em `DEFAULT_TO`.
- Variáveis de ambiente: `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `DEFAULT_TO`.
- Se não configurado, o envio é ignorado e registrado em log.

---

## 7. Instalação e configuração (resumo para Windows / XAMPP)

1. Copie a pasta `checklist-vallourec` para `htdocs` (ex.: `C:\xampp\htdocs\checklist-vallourec`).
2. Importe `schema.sql` no MySQL (phpMyAdmin ou CLI).
3. Ajuste `config/db.php` ou use variáveis de ambiente (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`).
4. Ajuste `config/app.php` se for necessário apontar para um executável Python específico (`python_path`) ou defina `PYTHON_PATH` no ambiente.
5. Instale dependências Python no interpretador embutido: `openpyxl`, `Pillow`, `reportlab` e `PyPDF2` (opcional para travamento de PDF). LibreOffice não é mais necessário — os PDFs são gerados diretamente com ReportLab.

```powershell
PowerShell -ExecutionPolicy Bypass -File scripts\install_python_deps.ps1
# Instale manualmente PyPDF2 se desejar travar PDFs:
# C:\> py\python.exe -m pip install PyPDF2
```

6. Garanta permissões de escrita para as pastas `uploads/` e `logs/` pelo usuário do servidor web.
7. Configure variáveis SMTP quando desejar envio automático de planilhas.

---

## 8. Estrutura de arquivos

- `public/` — front-end (formulário e assets)
- `api/` — endpoints PHP
- `python/` — scripts e `templates/`
- `config/` — configuração da aplicação
- `data/` — itens padrão (`checklist_items.json`)
- `uploads/` — `assinaturas/` e `planilhas/`
- `logs/` — logs e arquivos de diagnóstico
- `scripts/` — utilitários de instalação e migração

---

## 9. Diagnóstico rápido e resolução de problemas

- JSON inválido: `checklist_submit.php` retorna HTTP 400 e grava o payload em `logs/raw_request_*.log`. Use `api/debug_json.php` para analisar o conteúdo.
- Falha na geração da planilha: consulte `logs/submit.log` para saída do gerador Python e código de retorno; verifique `config/app.php` (`python_path`) e instalação das dependências Python.
- Assinatura não inserida: `Pillow` pode não estar presente; o gerador registra aviso e continua sem imagem.
- Execução de comandos bloqueada: funções `exec`/`shell_exec` podem estar desabilitadas no `php.ini` — verifique `disable_functions`.
- Permissões: confirme que o usuário do servidor web tem permissão de escrita em `uploads/` e `logs/`.
- SMTP: verifique as variáveis de ambiente e as credenciais de autenticação.

---

## 10. Segurança e recomendações

- Revisar antes de uso em produção: autenticação/autorizações, validação dos inputs, proteção CSRF e CORS, controle de acesso e logging apropriado.
- Evitar exposição de logs sensíveis em ambiente público.
- Tratar uploads e nomes de arquivos com sanitização adicional quando necessário.

---

## 11. Extensões e melhorias sugeridas

- Implementar autenticação e permissões (ex.: tokens JWT, sessions) para os endpoints.
- Adicionar testes automatizados (integração e unidade) para validação do fluxo de ponta a ponta.
- Fornecer uma interface administrativa para visualizar e baixar planilhas geradas.

---

## 12. Testes rápidos

- Arquivo de exemplo: `test_payload.json`.
- Exemplo de teste via `curl`:

```bash
curl -X POST -H "Content-Type: application/json" --data @test_payload.json http://localhost/checklist-vallourec/api/checklist_submit.php
```

- Verifique: `uploads/planilhas/` para o arquivo gerado e `logs/submit.log` para detalhes do processo.

---

## Referências

Arquivos principais: `api/checklist_submit.php`, `python/gerar_planilha.py`, `config/app.php`, `schema.sql`, `data/checklist_items.json`.

