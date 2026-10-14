<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use Identity\Domain\Document;
use Identity\Domain\DocumentType;
use Identity\Domain\InvalidDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DocumentTest extends TestCase
{
    // -----------------------------------------------------------------
    // CPF
    // -----------------------------------------------------------------

    /**
     * Números públicos de teste, amplamente citados como válidos — servem de
     * conferência EXTERNA do algoritmo. Se a implementação estiver errada,
     * ela falha aqui, e não só contra si mesma.
     *
     * @return list<array{string}>
     */
    public static function cpfsValidos(): array
    {
        return [['529.982.247-25'], ['52998224725'], ['111.444.777-35'], ['11144477735']];
    }

    #[DataProvider('cpfsValidos')]
    public function test_aceita_cpf_com_digito_verificador_correto(string $cpf): void
    {
        $documento = Document::fromString(DocumentType::Cpf, $cpf);

        $this->assertSame(DocumentType::Cpf, $documento->type);
    }

    public function test_guarda_o_cpf_so_com_digitos(): void
    {
        // O formato é da tela; o banco guarda o valor. Máscara gravada faz o
        // mesmo CPF virar duas linhas diferentes.
        $this->assertSame('52998224725', Document::fromString(DocumentType::Cpf, '529.982.247-25')->value);
    }

    public function test_recusa_cpf_com_digito_verificador_errado(): void
    {
        $this->expectException(InvalidDocument::class);

        Document::fromString(DocumentType::Cpf, '529.982.247-26');
    }

    public function test_recusa_cpf_com_tamanho_errado(): void
    {
        $this->expectException(InvalidDocument::class);

        Document::fromString(DocumentType::Cpf, '5299822472');
    }

    /** @return list<array{string}> */
    public static function cpfsRepetidos(): array
    {
        return [['00000000000'], ['11111111111'], ['22222222222'], ['99999999999']];
    }

    #[DataProvider('cpfsRepetidos')]
    public function test_recusa_cpf_de_digitos_repetidos(string $cpf): void
    {
        // 111.111.111-11 PASSA no cálculo do dígito verificador. É por isso
        // que a checagem existe em separado — sem ela, onze uns viram um CPF
        // "válido", e é o primeiro palpite de quem quer burlar o cadastro.
        $this->expectException(InvalidDocument::class);

        Document::fromString(DocumentType::Cpf, $cpf);
    }

    // -----------------------------------------------------------------
    // CNH
    // -----------------------------------------------------------------

    public function test_aceita_cnh_com_digitos_verificadores_corretos(): void
    {
        $this->assertSame(
            DocumentType::Cnh,
            Document::fromString(DocumentType::Cnh, Document::cnhValidaParaTeste('527988023'))->type,
        );
    }

    public function test_recusa_cnh_com_digito_alterado(): void
    {
        // Mexer em QUALQUER dígito precisa quebrar — é o que prova que os dois
        // verificadores estão sendo conferidos de verdade.
        $valida = Document::cnhValidaParaTeste('527988023');

        for ($posicao = 0; $posicao < 11; $posicao++) {
            $adulterada = $valida;
            $adulterada[$posicao] = (string) (((int) $valida[$posicao] + 1) % 10);

            try {
                Document::fromString(DocumentType::Cnh, $adulterada);
                $this->fail("Aceitou CNH adulterada na posicao {$posicao}: {$adulterada}");
            } catch (InvalidDocument) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_recusa_cnh_de_digitos_repetidos(): void
    {
        $this->expectException(InvalidDocument::class);

        Document::fromString(DocumentType::Cnh, '11111111111');
    }

    public function test_recusa_cnh_com_tamanho_errado(): void
    {
        $this->expectException(InvalidDocument::class);

        Document::fromString(DocumentType::Cnh, '1234567890');
    }

    // -----------------------------------------------------------------
    // CNPJ — formato alfanumérico (IN RFB 2.229/2024)
    // -----------------------------------------------------------------

    public function test_aceita_cnpj_alfanumerico(): void
    {
        // Exemplo publicado com os DVs já calculados (83). Conferência
        // EXTERNA: se a conversão ASCII-48 ou os pesos estiverem errados,
        // falha aqui, e não só contra a própria implementação.
        $documento = Document::fromString(DocumentType::Cnpj, 'AB.12C.D34/EFGH-83');

        $this->assertSame('AB12CD34EFGH83', $documento->value);
    }

    public function test_aceita_cnpj_numerico_antigo(): void
    {
        // O formato alfanumérico é SUPERCONJUNTO do antigo: para dígitos,
        // ASCII-48 devolve o próprio valor, então o mesmo algoritmo vale para
        // os dois. Nenhum CNPJ já emitido deixa de valer.
        $this->assertSame(
            '11222333000181',
            Document::fromString(DocumentType::Cnpj, '11.222.333/0001-81')->value,
        );
    }

    public function test_normaliza_o_cnpj_para_maiusculas(): void
    {
        $this->assertSame('AB12CD34EFGH83', Document::fromString(DocumentType::Cnpj, 'ab12cd34efgh83')->value);
    }

    public function test_recusa_cnpj_com_digito_verificador_errado(): void
    {
        $this->expectException(InvalidDocument::class);

        Document::fromString(DocumentType::Cnpj, 'AB12CD34EFGH84');
    }

    public function test_recusa_cnpj_com_letra_no_digito_verificador(): void
    {
        // Os dois últimos são SEMPRE numéricos, mesmo no formato novo.
        $this->expectException(InvalidDocument::class);

        Document::fromString(DocumentType::Cnpj, 'AB12CD34EFGH8A');
    }

    public function test_recusa_cnpj_com_tamanho_errado(): void
    {
        $this->expectException(InvalidDocument::class);

        Document::fromString(DocumentType::Cnpj, 'AB12CD34EFGH8');
    }

    public function test_recusa_cnpj_zerado(): void
    {
        // 14 zeros PASSAM no módulo 11 — a soma dá zero e os dois DVs saem 0.
        // Sem a guarda de caracteres repetidos, viraria um CNPJ "válido".
        $this->expectException(InvalidDocument::class);

        Document::fromString(DocumentType::Cnpj, '00000000000000');
    }

    public function test_recusa_caractere_fora_do_alfabeto_do_cnpj(): void
    {
        $this->expectException(InvalidDocument::class);

        Document::fromString(DocumentType::Cnpj, 'AB12CD34EFG#83');
    }

    public function test_recusa_cnpj_com_qualquer_caractere_alterado(): void
    {
        $valido = 'AB12CD34EFGH83';

        for ($posicao = 0; $posicao < 12; $posicao++) {
            $adulterado = $valido;
            // Troca por outro caractere do mesmo alfabeto, mantendo a forma.
            $adulterado[$posicao] = $valido[$posicao] === 'A' ? 'B' : 'A';

            try {
                Document::fromString(DocumentType::Cnpj, $adulterado);
                $this->fail("Aceitou CNPJ adulterado na posicao {$posicao}: {$adulterado}");
            } catch (InvalidDocument) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // -----------------------------------------------------------------

    public function test_compara_por_tipo_e_valor(): void
    {
        $cpf = Document::fromString(DocumentType::Cpf, '529.982.247-25');

        $this->assertTrue($cpf->equals(Document::fromString(DocumentType::Cpf, '52998224725')));
    }

    public function test_nao_expoe_o_documento_ao_serializar(): void
    {
        // CPF e CNPJ sao dado pessoal (LGPD). Sem isto, um
        // Log::error(..., ['perfil' => $perfil]) grava o numero inteiro num
        // arquivo que nao tem rotacao e vai para backup.
        $documento = Document::fromString(DocumentType::Cpf, '529.982.247-25');

        $this->assertStringNotContainsString('52998224725', (string) json_encode($documento));
        $this->assertStringNotContainsString('52998224725', print_r($documento, true));
    }

    public function test_a_mascara_mantem_as_pontas_para_conferencia(): void
    {
        // O que se quer saber num log de suporte e "e este documento mesmo?".
        // As pontas respondem isso; o miolo nao precisa aparecer.
        $this->assertSame('529******25', Document::fromString(DocumentType::Cpf, '529.982.247-25')->masked());
        $this->assertSame('AB1*********83', Document::fromString(DocumentType::Cnpj, 'AB12CD34EFGH83')->masked());
    }

    public function test_a_api_continua_devolvendo_o_documento_inteiro_ao_titular(): void
    {
        // A mascara e para log, nao para a resposta: o dono precisa conferir
        // o que esta gravado. O Resource le a propriedade direto.
        $this->assertSame('52998224725', Document::fromString(DocumentType::Cpf, '529.982.247-25')->value);
    }

    public function test_recusa_valor_vazio(): void
    {
        $this->expectException(InvalidDocument::class);

        Document::fromString(DocumentType::Cpf, '   ');
    }
}
