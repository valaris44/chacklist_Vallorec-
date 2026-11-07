Place your original checklist template file(s) here to enable template-aware generation.

Expected filenames (choose one):
- checklist_template.xlsm  # Recommended if you want to preserve macros (VBA).
- checklist_template.xlsx  # If you only have an XLSX without macros.

Notes:
- If a .xlsm file is present, the generator will open it with keep_vba=True and will save the generated file as .xlsm (preserving macros).
- The script heuristically finds labels like "Data", "Área", "Nome", "Matrícula", "Seção", "Descrição", "Status", "Observações", and "Assinatura" and fills values to the adjacent cells. Adjust the template labels if necessary.
- After placing the template, re-run the tests (scripts/run_generate_test.php) or submit a checklist via the UI to generate an output file that follows the template layout.
