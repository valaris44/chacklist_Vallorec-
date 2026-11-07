-- Dados de exemplo para POC
INSERT INTO checklists (date, area, nome, matricula, setor, responsavel, obs_geral, signature_path, created_at) VALUES
('2025-12-30', 'Sala Elétrica', 'João Silva', '12345', 'Operação', 'Supervisor A', 'Teste de importação de dados', NULL, NOW());

SET @cid = LAST_INSERT_ID();

INSERT INTO checklist_items (checklist_id, description, status, obs) VALUES
(@cid, 'Verificar temperatura e ventilação dos painéis', 'verificado', 'Dentro do limite'),
(@cid, 'Conferir posição e sinais de desgaste nos disjuntores', 'nao_verificado', 'Disjuntor D-3 com folga'),
(@cid, 'Inspeção visual de cabos, terminais e conexões', 'verificado', 'Ok'),
(@cid, 'Checar rota de emergência e sinalização elétrica', 'na', 'Não aplicável');
