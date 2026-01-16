<?php
/**
 * Report Fields Configuration
 *
 * Centralized definition of all available report fields with metadata
 *
 * @package FortaleceePSE
 * @subpackage Config
 */

return [
    // Personal Information
    'nome_completo' => [
        'label' => 'Nome Completo',
        'type' => 'text',
        'required' => true,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],
    'cpf' => [
        'label' => 'CPF',
        'type' => 'text',
        'required' => true,
        'searchable' => true,
        'sensitive' => true,
        'auto_filled' => false,
    ],
    'email' => [
        'label' => 'E-mail',
        'type' => 'email',
        'required' => true,
        'searchable' => true,
        'sensitive' => true,
        'auto_filled' => false,
    ],
    'email_login' => [
        'label' => 'E-mail de Login',
        'type' => 'email',
        'required' => true,
        'searchable' => true,
        'sensitive' => true,
        'auto_filled' => false,
    ],
    'telefone' => [
        'label' => 'Telefone',
        'type' => 'text',
        'required' => true,
        'searchable' => false,
        'sensitive' => false,
        'auto_filled' => false,
    ],
    'data_nascimento' => [
        'label' => 'Data de Nascimento',
        'type' => 'date',
        'required' => true,
        'searchable' => false,
        'sensitive' => false,
        'auto_filled' => false,
    ],
    'genero' => [
        'label' => 'Gênero',
        'type' => 'enum',
        'required' => true,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],
    'raca_cor' => [
        'label' => 'Raça/Cor',
        'type' => 'enum',
        'required' => true,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],

    // Institutional Information
    'perfil_usuario' => [
        'label' => 'Perfil de Usuário',
        'type' => 'enum',
        'required' => true,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],
    'vinculo_institucional' => [
        'label' => 'Vínculo Institucional',
        'type' => 'enum',
        'required' => true,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],

    // Location
    'estado' => [
        'label' => 'Estado',
        'type' => 'enum',
        'required' => true,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],
    'municipio' => [
        'label' => 'Município',
        'type' => 'text',
        'required' => true,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => true,
    ],
    'logradouro' => [
        'label' => 'Logradouro',
        'type' => 'text',
        'required' => true,
        'searchable' => false,
        'sensitive' => false,
        'auto_filled' => true,
    ],
    'cep' => [
        'label' => 'CEP',
        'type' => 'text',
        'required' => true,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],
    'complemento' => [
        'label' => 'Complemento',
        'type' => 'text',
        'required' => false,
        'searchable' => false,
        'sensitive' => false,
        'auto_filled' => false,
    ],
    'numero' => [
        'label' => 'Número',
        'type' => 'text',
        'required' => true,
        'searchable' => false,
        'sensitive' => false,
        'auto_filled' => false,
    ],
    'bairro' => [
        'label' => 'Bairro',
        'type' => 'text',
        'required' => true,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],

    // Accessibility
    'acessibilidade' => [
        'label' => 'Necessidades de Acessibilidade',
        'type' => 'boolean',
        'required' => true,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],
    'descricao_acessibilidade' => [
        'label' => 'Descrição da Acessibilidade',
        'type' => 'text',
        'required' => false,
        'searchable' => false,
        'sensitive' => false,
        'auto_filled' => false,
    ],

    // EAA Specific Fields
    'rede_escola' => [
        'label' => 'Rede Escolar',
        'type' => 'enum',
        'required' => false,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],
    'escola_nome' => [
        'label' => 'Nome da Escola',
        'type' => 'text',
        'required' => false,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],
    'funcao_eaa' => [
        'label' => 'Função na EAA',
        'type' => 'enum',
        'required' => false,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],

    // IES Specific Fields
    'instituicao_nome' => [
        'label' => 'Nome da Instituição',
        'type' => 'text',
        'required' => false,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],
    'curso_nome' => [
        'label' => 'Nome do Curso',
        'type' => 'text',
        'required' => false,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],
    'area_pesquisa' => [
        'label' => 'Área de Pesquisa',
        'type' => 'text',
        'required' => false,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],

    // NAP Specific Fields
    'nap_nome' => [
        'label' => 'Nome do NAP',
        'type' => 'text',
        'required' => false,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],
    'especialidade' => [
        'label' => 'Especialidade',
        'type' => 'text',
        'required' => false,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],

    // GTI Specific Fields
    'setor_gti' => [
        'label' => 'Setor GTI',
        'type' => 'text',
        'required' => false,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],
    'sistema_responsavel' => [
        'label' => 'Sistema Responsável',
        'type' => 'text',
        'required' => false,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],

    // Common Fields
    'matricula' => [
        'label' => 'Matrícula',
        'type' => 'text',
        'required' => false,
        'searchable' => true,
        'sensitive' => true,
        'auto_filled' => false,
    ],
    'funcao_administrativa' => [
        'label' => 'Função Administrativa',
        'type' => 'text',
        'required' => false,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],
    'departamento' => [
        'label' => 'Departamento',
        'type' => 'text',
        'required' => false,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],
    'projeto_nome' => [
        'label' => 'Nome do Projeto',
        'type' => 'text',
        'required' => false,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],
    'regiao_responsavel' => [
        'label' => 'Região Responsável',
        'type' => 'text',
        'required' => false,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => false,
    ],

    // Audit Fields
    'created_at' => [
        'label' => 'Data de Criação',
        'type' => 'datetime',
        'required' => true,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => true,
    ],
    'updated_at' => [
        'label' => 'Data de Atualização',
        'type' => 'datetime',
        'required' => false,
        'searchable' => true,
        'sensitive' => false,
        'auto_filled' => true,
    ],
];
