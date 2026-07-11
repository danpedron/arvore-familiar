-- Migração: adiciona coordenadas (lat/lng) para local de nascimento e falecimento,
-- usadas pela busca de locais via OpenStreetMap/Nominatim.
-- Rode este script apenas se você já tinha uma instalação anterior.

USE arvore_familiar;

ALTER TABLE pessoas
    ADD COLUMN IF NOT EXISTS local_nascimento_lat DECIMAL(10,7) NULL AFTER local_nascimento,
    ADD COLUMN IF NOT EXISTS local_nascimento_lng DECIMAL(10,7) NULL AFTER local_nascimento_lat,
    ADD COLUMN IF NOT EXISTS local_falecimento_lat DECIMAL(10,7) NULL AFTER local_falecimento,
    ADD COLUMN IF NOT EXISTS local_falecimento_lng DECIMAL(10,7) NULL AFTER local_falecimento_lat;
