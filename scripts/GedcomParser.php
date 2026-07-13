<?php
/**
 * Parser mínimo de GEDCOM (5.5 / 5.5.1 / 7.0 — subconjunto comum).
 * Lê registros INDI (pessoas) e FAM (famílias/uniões), extraindo os campos
 * que o sistema usa: nome, sexo, nascimento, falecimento, cônjuges e filhos.
 *
 * Não pretende cobrir 100% da especificação GEDCOM (que é muito ampla) — cobre
 * o que praticamente todo exportador (MyHeritage, Ancestry, Gramps, FamilySearch...) gera.
 */
class GedcomParser
{
    private const MESES = [
        'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6,
        'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12,
    ];

    /** @var array<string, array> */
    public array $individuos = [];
    /** @var array<string, array> */
    public array $familias = [];

    public function parse(string $caminhoArquivo): void
    {
        $conteudo = file_get_contents($caminhoArquivo);
        if ($conteudo === false) {
            throw new RuntimeException("Não foi possível ler o arquivo: {$caminhoArquivo}");
        }

        // GEDCOM pode vir em UTF-8, UTF-8 com BOM, ANSI ou UTF-16. Normaliza pra UTF-8.
        $conteudo = $this->normalizarCodificacao($conteudo);
        $linhas = preg_split('/\r\n|\r|\n/', $conteudo);

        $registroAtual = null;
        $tipoAtual = null;
        $contextoTag = null; // para saber se um DATE/PLAC pertence a BIRT, DEAT ou MARR

        foreach ($linhas as $linhaBruta) {
            $linha = trim($linhaBruta);
            if ($linha === '') continue;

            // Formato de uma linha GEDCOM: NIVEL [@XREF@] TAG [VALOR]
            if (!preg_match('/^(\d+)\s+(@[^@]+@\s+)?(\S+)(\s+(.*))?$/', $linha, $m)) {
                continue;
            }
            $nivel = (int) $m[1];
            $xref = isset($m[2]) ? trim($m[2], "@ \t") : null;
            $tag = strtoupper($m[3]);
            $valor = $m[5] ?? '';

            if ($nivel === 0) {
                $registroAtual = null;
                $tipoAtual = null;
                $contextoTag = null;

                if ($tag === 'INDI' && $xref) {
                    $tipoAtual = 'INDI';
                    $registroAtual = $xref;
                    $this->individuos[$xref] = [
                        'id' => $xref,
                        'nomes' => [],
                        'sexo' => null,
                        'nascimento_data' => null,
                        'nascimento_local' => null,
                        'falecimento_data' => null,
                        'falecimento_local' => null,
                        'falecido' => false,
                    ];
                } elseif ($tag === 'FAM' && $xref) {
                    $tipoAtual = 'FAM';
                    $registroAtual = $xref;
                    $this->familias[$xref] = [
                        'id' => $xref,
                        'marido' => null,
                        'esposa' => null,
                        'filhos' => [],
                        'casamento_data' => null,
                        'casamento_local' => null,
                    ];
                }
                continue;
            }

            if ($registroAtual === null) continue;

            if ($tipoAtual === 'INDI') {
                $this->processarLinhaIndividuo($registroAtual, $nivel, $tag, $valor, $contextoTag);
                if ($nivel === 1) $contextoTag = $tag;
            } elseif ($tipoAtual === 'FAM') {
                $this->processarLinhaFamilia($registroAtual, $nivel, $tag, $valor, $contextoTag);
                if ($nivel === 1) $contextoTag = $tag;
            }
        }
    }

    private function processarLinhaIndividuo(string $id, int $nivel, string $tag, string $valor, ?string $contexto): void
    {
        $ind = &$this->individuos[$id];

        if ($nivel === 1 && $tag === 'NAME') {
            $ind['nomes'][] = $this->interpretarNomeGedcom($valor);
        } elseif ($nivel === 1 && $tag === 'SEX') {
            $ind['sexo'] = strtoupper(trim($valor)) === 'F' ? 'F' : (strtoupper(trim($valor)) === 'M' ? 'M' : null);
        } elseif ($nivel === 2 && $tag === 'DATE' && $contexto === 'BIRT') {
            $ind['nascimento_data'] = $this->interpretarDataGedcom($valor);
        } elseif ($nivel === 2 && $tag === 'PLAC' && $contexto === 'BIRT') {
            $ind['nascimento_local'] = $this->limparLocal($valor);
        } elseif ($nivel === 2 && $tag === 'DATE' && $contexto === 'DEAT') {
            $ind['falecimento_data'] = $this->interpretarDataGedcom($valor);
            $ind['falecido'] = true;
        } elseif ($nivel === 2 && $tag === 'PLAC' && $contexto === 'DEAT') {
            $ind['falecimento_local'] = $this->limparLocal($valor);
        } elseif ($nivel === 1 && $tag === 'DEAT') {
            $ind['falecido'] = true; // tag DEAT presente mesmo sem data conhecida
        }
    }

    private function processarLinhaFamilia(string $id, int $nivel, string $tag, string $valor, ?string $contexto): void
    {
        $fam = &$this->familias[$id];

        if ($nivel === 1 && $tag === 'HUSB') {
            $fam['marido'] = trim($valor, '@ ');
        } elseif ($nivel === 1 && $tag === 'WIFE') {
            $fam['esposa'] = trim($valor, '@ ');
        } elseif ($nivel === 1 && $tag === 'CHIL') {
            $fam['filhos'][] = trim($valor, '@ ');
        } elseif ($nivel === 2 && $tag === 'DATE' && $contexto === 'MARR') {
            $fam['casamento_data'] = $this->interpretarDataGedcom($valor);
        } elseif ($nivel === 2 && $tag === 'PLAC' && $contexto === 'MARR') {
            $fam['casamento_local'] = $this->limparLocal($valor);
        }
    }

    // Nome GEDCOM vem como "Nome /Sobrenome/" — extrai as duas partes
    private function interpretarNomeGedcom(string $bruto): array
    {
        $bruto = trim($bruto);
        if (preg_match('/^(.*?)\/(.*)\/\s*(.*)$/', $bruto, $m)) {
            $primeiro = trim($m[1]);
            $sobrenome = trim($m[2]);
            $completo = trim($primeiro . ' ' . $sobrenome);
        } else {
            $completo = str_replace('/', '', $bruto);
        }
        return ['completo' => $completo];
    }

    private function limparLocal(string $valor): ?string
    {
        $valor = trim($valor);
        return $valor !== '' ? $valor : null;
    }

    /**
     * Interpreta datas GEDCOM (ex: "12 JUN 1950", "JUN 1950", "1950", "ABT 1950",
     * "BEF 1900", "BET 1950 AND 1955") e retorna ['data' => 'Y-m-d'|null, 'aproximada' => bool, 'original' => string].
     */
    public function interpretarDataGedcom(string $bruto): array
    {
        $original = trim($bruto);
        $texto = strtoupper($original);
        $aproximada = false;

        foreach (['ABT', 'EST', 'CAL', 'BEF', 'AFT'] as $qualificador) {
            if (str_starts_with($texto, $qualificador . ' ')) {
                $aproximada = true;
                $texto = trim(substr($texto, strlen($qualificador)));
                break;
            }
        }
        if (str_starts_with($texto, 'BET ')) {
            $aproximada = true;
            $texto = trim(substr($texto, 4));
            if (str_contains($texto, ' AND ')) {
                $texto = trim(explode(' AND ', $texto)[0]);
            }
        }

        if (!preg_match('/^(?:(\d{1,2})\s+)?(?:([A-Z]{3,4})\s+)?(\d{3,4})$/', $texto, $m)) {
            return ['data' => null, 'aproximada' => true, 'original' => $original];
        }

        $dia = $m[1] ?? null;
        $mesTexto = $m[2] ?? null;
        $ano = $m[3];

        if (!$dia || !$mesTexto) $aproximada = true;

        $mes = $mesTexto ? (self::MESES[substr($mesTexto, 0, 3)] ?? 1) : 1;
        $dia = $dia ? (int) $dia : 1;

        $dataFormatada = sprintf('%04d-%02d-%02d', (int) $ano, $mes, $dia);

        return ['data' => $dataFormatada, 'aproximada' => $aproximada, 'original' => $original];
    }

    private function normalizarCodificacao(string $conteudo): string
    {
        // Remove BOM UTF-8, se houver
        if (substr($conteudo, 0, 3) === "\xEF\xBB\xBF") {
            $conteudo = substr($conteudo, 3);
        }
        // Detecta UTF-16 (comum em exports do MyHeritage/Ancestry)
        if (substr($conteudo, 0, 2) === "\xFF\xFE" || substr($conteudo, 0, 2) === "\xFE\xFF") {
            $convertido = @mb_convert_encoding($conteudo, 'UTF-8', 'UTF-16');
            if ($convertido !== false) return $convertido;
        }
        if (!mb_check_encoding($conteudo, 'UTF-8')) {
            $convertido = @mb_convert_encoding($conteudo, 'UTF-8', 'ISO-8859-1');
            if ($convertido !== false) return $convertido;
        }
        return $conteudo;
    }
}
