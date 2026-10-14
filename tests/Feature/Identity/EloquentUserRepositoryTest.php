<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Identity\Application\DocumentAlreadyInUse;
use Identity\Application\EmailAlreadyRegistered;
use Identity\Application\UserRepository;
use Identity\Domain\Document;
use Identity\Domain\DocumentType;
use Identity\Domain\EmailAddress;
use Identity\Domain\HashedPassword;
use Identity\Domain\NewUser;
use Identity\Domain\PersonName;
use Identity\Domain\PhoneNumber;
use Identity\Domain\PlatformRole;
use Identity\Domain\ProfileChanges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * O adapter contra o Postgres real. O que se prova aqui é o que o fake do
 * teste unitário não consegue provar: que a query e o mapeamento traduzem a
 * linha certo, e que a constraint do banco faz o que o contrato da porta diz.
 */
final class EloquentUserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private UserRepository $repositorio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositorio = $this->app->make(UserRepository::class);
    }

    public function test_grava_o_usuario_e_devolve_ele_com_id_e_data(): void
    {
        $registrado = $this->repositorio->add($this->novoUsuario());

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $registrado->id,
            'O id deveria ser um UUID versao 7.',
        );

        $linha = DB::table('users')->where('id', $registrado->id)->first();

        $this->assertSame('Joao Pedro', $linha->name);
        $this->assertSame('joao@shopmaster.test', $linha->email);
    }

    public function test_grava_o_papel_buyer_na_tabela_de_papeis(): void
    {
        $registrado = $this->repositorio->add($this->novoUsuario());

        $papeis = DB::table('user_platform_roles')
            ->where('user_id', $registrado->id)
            ->pluck('role')
            ->all();

        $this->assertSame(['buyer'], $papeis);
    }

    public function test_nao_grava_a_senha_em_texto_puro(): void
    {
        $registrado = $this->repositorio->add($this->novoUsuario());

        $hash = DB::table('users')->where('id', $registrado->id)->value('password_hash');

        $this->assertNotSame('senha-forte-123', $hash);
        $this->assertTrue(password_verify('senha-forte-123', $hash));
    }

    public function test_exists_by_email_encontra_quem_ja_esta_cadastrado(): void
    {
        $this->repositorio->add($this->novoUsuario());

        $this->assertTrue($this->repositorio->existsByEmail(EmailAddress::fromString('joao@shopmaster.test')));
        $this->assertFalse($this->repositorio->existsByEmail(EmailAddress::fromString('outra@shopmaster.test')));
    }

    public function test_exists_by_email_ignora_a_caixa_porque_a_coluna_e_citext(): void
    {
        // Gravado em minusculas pelo VO; a consulta vem de um caminho que nao
        // normalizou. E o `citext` que segura isto — nao ha LOWER() na query.
        DB::table('users')->insert([
            'id' => '01a07e4a-a020-73a1-bd0e-6bac810e4a44',
            'name' => 'Joao Pedro',
            'email' => 'joao@shopmaster.test',
            'password_hash' => 'irrelevante',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($this->repositorio->existsByEmail(
            EmailAddress::fromString('joao@shopmaster.test')
        ));

        $duplicado = DB::table('users')->where('email', 'JOAO@SHOPMASTER.TEST')->exists();
        $this->assertTrue($duplicado, 'A coluna citext deveria casar sem diferenciar caixa.');
    }

    public function test_a_constraint_do_banco_fecha_a_corrida_entre_verificacao_e_insert(): void
    {
        // Este é o cenário que o existsByEmail() do use case NÃO cobre: dois
        // cadastros simultâneos do mesmo e-mail passam os dois pela
        // verificação, e só a constraint UNIQUE impede a segunda linha.
        // O contrato da porta diz que isso vira EmailAlreadyRegistered.
        $this->repositorio->add($this->novoUsuario());

        $this->expectException(EmailAlreadyRegistered::class);

        $this->repositorio->add($this->novoUsuario());
    }

    public function test_a_corrida_e_barrada_mesmo_com_caixa_diferente(): void
    {
        $this->repositorio->add($this->novoUsuario());

        $this->expectException(EmailAlreadyRegistered::class);

        $this->repositorio->add($this->novoUsuario('JOAO@SHOPMASTER.TEST'));
    }

    public function test_nao_deixa_usuario_orfao_quando_a_gravacao_falha(): void
    {
        $this->repositorio->add($this->novoUsuario());

        try {
            $this->repositorio->add($this->novoUsuario());
        } catch (EmailAlreadyRegistered) {
            // esperado
        }

        $this->assertSame(1, DB::table('users')->count());
        $this->assertSame(1, DB::table('user_platform_roles')->count());
    }

    // -----------------------------------------------------------------
    // RF-003 / RF-004 — leitura para autenticação
    // -----------------------------------------------------------------

    public function test_find_credentials_by_email_traz_a_identidade_e_o_hash(): void
    {
        $registrado = $this->repositorio->add($this->novoUsuario());

        $credenciais = $this->repositorio->findCredentialsByEmail(
            EmailAddress::fromString('joao@shopmaster.test')
        );

        $this->assertNotNull($credenciais);
        $this->assertSame($registrado->id, $credenciais->user->id);
        $this->assertSame('Joao Pedro', $credenciais->user->name->value());
        $this->assertSame([PlatformRole::Buyer], $credenciais->user->roles);
        $this->assertTrue(password_verify('senha-forte-123', $credenciais->password->value()));
    }

    public function test_find_credentials_by_email_ignora_a_caixa(): void
    {
        $this->repositorio->add($this->novoUsuario());

        $this->assertNotNull($this->repositorio->findCredentialsByEmail(
            EmailAddress::fromString('JOAO@SHOPMASTER.TEST')
        ));
    }

    public function test_find_credentials_by_email_devolve_null_para_quem_nao_existe(): void
    {
        $this->assertNull($this->repositorio->findCredentialsByEmail(
            EmailAddress::fromString('ninguem@shopmaster.test')
        ));
    }

    public function test_find_identity_by_id_nao_carrega_hash_algum(): void
    {
        // RULE 4: o caminho do refresh não precisa da senha, então não a
        // busca. O que não é lido não vaza.
        $registrado = $this->repositorio->add($this->novoUsuario());

        $identidade = $this->repositorio->findIdentityById($registrado->id);

        $this->assertNotNull($identidade);
        $this->assertSame('joao@shopmaster.test', $identidade->email->value());
        $this->assertStringNotContainsString('$2y$', json_encode($identidade) ?: '');
    }

    public function test_find_identity_by_id_devolve_null_para_id_inexistente(): void
    {
        $this->assertNull($this->repositorio->findIdentityById('01a07e4a-a020-73a1-bd0e-000000000000'));
    }

    public function test_find_identity_by_id_nao_estoura_com_id_que_nao_e_uuid(): void
    {
        // O id vem do `sub` de um token, ou seja, de fora. A coluna é `uuid`,
        // e o Postgres estouraria com "invalid input syntax" — transformando
        // um token forjado em 500 em vez do 401 que ele merece.
        $this->assertNull($this->repositorio->findIdentityById('nao-sou-um-uuid'));
        $this->assertNull($this->repositorio->findIdentityById("'; drop table users; --"));
    }

    public function test_carrega_todos_os_papeis_de_plataforma(): void
    {
        $registrado = $this->repositorio->add($this->novoUsuario());

        DB::table('user_platform_roles')->insert([
            'user_id' => $registrado->id,
            'role' => PlatformRole::Seller->value,
            'granted_at' => now(),
        ]);

        $identidade = $this->repositorio->findIdentityById($registrado->id);

        $this->assertSame([PlatformRole::Buyer, PlatformRole::Seller], $identidade->roles);
    }

    public function test_descarta_papel_desconhecido_em_vez_de_estourar(): void
    {
        // A coluna não tem CHECK de propósito (papel novo é valor novo no
        // enum, sem migration), então um INSERT manual pode gravar lixo.
        // Descartar falha FECHADO: perde-se um papel ininteligível em vez de
        // derrubar a requisição.
        $registrado = $this->repositorio->add($this->novoUsuario());

        DB::table('user_platform_roles')->insert([
            'user_id' => $registrado->id,
            'role' => 'imperador',
            'granted_at' => now(),
        ]);

        $this->assertSame([PlatformRole::Buyer], $this->repositorio->findIdentityById($registrado->id)->roles);
    }

    public function test_tolera_tipo_de_documento_que_o_enum_nao_conhece_mais(): void
    {
        // `passport` foi um tipo valido e deixou de ser. Com `from()` isto
        // lancaria ValueError -> 500, e a pessoa nao conseguiria nem abrir o
        // proprio perfil para corrigir.
        $registrado = $this->repositorio->add($this->novoUsuario());

        DB::table('users')->where('id', $registrado->id)->update([
            'document' => 'FZ123456',
            'document_type' => 'passport',
        ]);

        $perfil = $this->repositorio->findProfileById($registrado->id);

        $this->assertNotNull($perfil, 'O perfil precisa continuar legivel.');
        $this->assertNull($perfil->document, 'O documento ininteligivel e descartado, falhando fechado.');
        $this->assertSame('Joao Pedro', $perfil->name->value());
    }

    public function test_a_constraint_do_banco_fecha_a_corrida_do_documento(): void
    {
        // A verificacao previa do use case nao cobre a corrida: duas contas
        // gravando o mesmo documento ao mesmo tempo passam as duas por ela.
        // Este teste chama o repositorio DIRETO, pulando o use case, que e o
        // unico jeito de exercitar o catch da violacao de unicidade.
        $primeiro = $this->repositorio->add($this->novoUsuario());
        $segundo = $this->repositorio->add($this->novoUsuario('maria@shopmaster.test'));

        $documento = Document::fromString(DocumentType::Cpf, '529.982.247-25');

        $this->repositorio->updateProfile($primeiro->id, new ProfileChanges(document: $documento));

        $this->expectException(DocumentAlreadyInUse::class);

        $this->repositorio->updateProfile($segundo->id, new ProfileChanges(document: $documento));
    }

    public function test_remove_o_telefone_quando_a_mudanca_pede_remocao(): void
    {
        $registrado = $this->repositorio->add($this->novoUsuario());
        $this->repositorio->updateProfile($registrado->id, new ProfileChanges(
            phone: PhoneNumber::fromString('11988887777'),
        ));

        $this->repositorio->updateProfile($registrado->id, new ProfileChanges(removesPhone: true));

        $this->assertNull($this->repositorio->findProfileById($registrado->id)?->phone);
    }

    private function novoUsuario(string $email = 'joao@shopmaster.test'): NewUser
    {
        return NewUser::register(
            PersonName::fromString('Joao Pedro'),
            EmailAddress::fromString($email),
            HashedPassword::fromHash(password_hash('senha-forte-123', PASSWORD_BCRYPT, ['cost' => 4])),
        );
    }
}
