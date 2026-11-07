# Checklist Elétrica - Vallourec (POC)

POC para digitalização do checklist elétrico. Tecnologias: HTML/CSS/JS (vanilla), PHP (API), MySQL, Python (openpyxl, smtplib).

Estrutura:
- public/: frontend
- api/: endpoints PHP
- python/: scripts para gerar XLS e enviar e-mail
- uploads/: assinaturas e planilhas
- config/db.php: ajuste conexão ao banco
- data/checklist_items.json: itens carregados no formulário (simula o XLS)

Instalação (Windows, XAMPP):
1. Coloque a pasta `checklist-vallourec` em `htdocs`.
2. Crie o banco e tabelas (veja `schema.sql`).
3. Ajuste `config/db.php` se necessário.
4. (Opcional) Rode o script CLI para importar schema e dados de exemplo:
   - `php scripts/import_schema.php` (executar no diretório do projeto)
5. Ajustes necessários para os scripts Python:
   - Use the bundled Python located at `py\python.exe` (the app runs Python scripts using this absolute path). Use `scripts\install_python_deps.ps1` to install required packages into the bundled interpreter.
   - Use o script PowerShell para instalar dependências: `PowerShell -ExecutionPolicy Bypass -File scripts\install_python_deps.ps1`.
   - Se o Apache não encontrar `python`, defina a variável de ambiente `PYTHON_PATH` para o caminho completo (ou edite `config/app.php`).
6. Rodar migração para armazenar o nome do arquivo da planilha (opcional):
   - `php scripts/migrate_add_planilha.php`
7. Configure variáveis SMTP no ambiente ou edite `python/enviar_email.py`.
8. Acesse http://localhost/checklist-vallourec/public/

Observações:
- Este é um POC; verifique permissões e segurança antes de usar em produção.
- Para reproduzir fielmente o XLS original, disponibilizar o arquivo de referência permite ajustar formatação da planilha gerada.
- Coloque o arquivo original (XLSM com macros, se houver) em `python/templates/checklist_template.xlsm`. O gerador usará esse template e preservará macros ao salvar como `.xlsm`. Veja `python/templates/README.md` para detalhes.
