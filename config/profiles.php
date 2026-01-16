<?php
/**
 * User Profiles Configuration
 *
 * Defines all available user profiles and their specific required fields
 *
 * @package FortaleceePSE
 * @subpackage Config
 */

return [
    // EAA Profiles
    'estudante-eaa' => [
        'label' => 'Estudante - EAA',
        'category' => 'EAA',
        'description' => 'Estudante da Educação de Adolescentes e Adultos',
        'specific_fields' => ['rede_escola', 'escola_nome', 'funcao_eaa'],
    ],
    'profissional-saude-eaa' => [
        'label' => 'Profissional Saúde - EAA',
        'category' => 'EAA',
        'description' => 'Profissional de Saúde da Educação de Adolescentes e Adultos',
        'specific_fields' => ['rede_escola', 'escola_nome'],
    ],
    'profissional-educacao-eaa' => [
        'label' => 'Profissional Educação - EAA',
        'category' => 'EAA',
        'description' => 'Profissional de Educação da Educação de Adolescentes e Adultos',
        'specific_fields' => ['rede_escola', 'escola_nome'],
    ],
    'professor-eaa' => [
        'label' => 'Professor - EAA',
        'category' => 'EAA',
        'description' => 'Professor da Educação de Adolescentes e Adultos',
        'specific_fields' => ['rede_escola', 'escola_nome', 'matricula'],
    ],
    'gestor-eaa' => [
        'label' => 'Gestor - EAA',
        'category' => 'EAA',
        'description' => 'Gestor da Educação de Adolescentes e Adultos',
        'specific_fields' => ['rede_escola', 'escola_nome', 'matricula', 'funcao_administrativa'],
    ],

    // IES Profiles
    'estudante-ies' => [
        'label' => 'Estudante - IES',
        'category' => 'IES',
        'description' => 'Estudante de Instituição de Ensino Superior',
        'specific_fields' => ['instituicao_nome', 'curso_nome', 'matricula'],
    ],
    'bolsista-ies' => [
        'label' => 'Bolsista - IES',
        'category' => 'IES',
        'description' => 'Bolsista de Instituição de Ensino Superior',
        'specific_fields' => ['instituicao_nome'], // curso_nome é opcional
    ],
    'voluntario-ies' => [
        'label' => 'Voluntário - IES',
        'category' => 'IES',
        'description' => 'Voluntário de Instituição de Ensino Superior',
        'specific_fields' => ['instituicao_nome'], // curso_nome é opcional
    ],
    'coordenador-ies' => [
        'label' => 'Coordenador - IES',
        'category' => 'IES',
        'description' => 'Coordenador de Instituição de Ensino Superior',
        'specific_fields' => ['instituicao_nome', 'departamento'],
    ],
    'professor-ies' => [
        'label' => 'Professor - IES',
        'category' => 'IES',
        'description' => 'Professor de Instituição de Ensino Superior',
        'specific_fields' => ['instituicao_nome', 'departamento', 'matricula'],
    ],
    'pesquisador' => [
        'label' => 'Pesquisador',
        'category' => 'IES',
        'description' => 'Pesquisador vinculado a Instituição de Ensino Superior',
        'specific_fields' => ['instituicao_nome', 'area_pesquisa', 'projeto_nome'],
    ],

    // NAP Profiles
    'jovem-mobilizador-nap' => [
        'label' => 'Jovem Mobilizador - NAP',
        'category' => 'NAP',
        'description' => 'Jovem Mobilizador do Núcleo de Acessibilidade Pedagógica',
        'specific_fields' => ['nap_nome'],
    ],
    'apoiador-pedagogico-nap' => [
        'label' => 'Apoiador Pedagógico - NAP',
        'category' => 'NAP',
        'description' => 'Apoiador Pedagógico do Núcleo de Acessibilidade Pedagógica',
        'specific_fields' => ['nap_nome'],
    ],
    'coordenacao-nap' => [
        'label' => 'Coordenação - NAP',
        'category' => 'NAP',
        'description' => 'Coordenação do Núcleo de Acessibilidade Pedagógica',
        'specific_fields' => ['nap_nome', 'funcao_administrativa'],
    ],
    'gestor-nap' => [
        'label' => 'Gestor - NAP',
        'category' => 'NAP',
        'description' => 'Gestor do Núcleo de Acessibilidade Pedagógica',
        'specific_fields' => ['nap_nome', 'rede_escola', 'funcao_administrativa'],
    ],
    'assistente-nap' => [
        'label' => 'Assistente - NAP',
        'category' => 'NAP',
        'description' => 'Assistente do Núcleo de Acessibilidade Pedagógica',
        'specific_fields' => ['nap_nome', 'especialidade', 'matricula'],
    ],

    // GTI Profiles
    'gti-m' => [
        'label' => 'GTI-M',
        'category' => 'GTI',
        'description' => 'Gestão Tecnológica Inclusiva - Municipal',
        'specific_fields' => ['setor_gti', 'sistema_responsavel'],
    ],
    'gti-e' => [
        'label' => 'GTI-E',
        'category' => 'GTI',
        'description' => 'Gestão Tecnológica Inclusiva - Estadual',
        'specific_fields' => ['setor_gti', 'sistema_responsavel'], // secretariaOrigem é mapeado para regiao_responsavel mas não é obrigatório
    ],
    'gestor-gti' => [
        'label' => 'Gestor - GTI',
        'category' => 'GTI',
        'description' => 'Gestor de Gestão Tecnológica Inclusiva',
        'specific_fields' => ['setor_gti', 'sistema_responsavel', 'matricula'],
    ],
    'tecnico-gti' => [
        'label' => 'Técnico - GTI',
        'category' => 'GTI',
        'description' => 'Técnico de Gestão Tecnológica Inclusiva',
        'specific_fields' => ['setor_gti', 'especialidade', 'matricula'],
    ],

    // Governance
    'coordenacao-fortalece-pse' => [
        'label' => 'Coordenação Fortalece PSE',
        'category' => 'Governance',
        'description' => 'Coordenação do programa Fortalece PSE',
        'specific_fields' => ['regiao_responsavel'],
    ],
    'representante-ms-mec' => [
        'label' => 'Representante MS/MEC',
        'category' => 'Governance',
        'description' => 'Representante do Ministério da Saúde e Educação',
        'specific_fields' => ['departamento'],
    ],
    'coordenador-institucional' => [
        'label' => 'Coordenador Institucional',
        'category' => 'Governance',
        'description' => 'Coordenador Institucional do Fortalece PSE',
        'specific_fields' => ['instituicao_nome', 'funcao_administrativa', 'matricula'],
    ],
    'monitor-programa' => [
        'label' => 'Monitor do Programa',
        'category' => 'Governance',
        'description' => 'Monitor responsável pelo programa Fortalece PSE',
        'specific_fields' => ['regiao_responsavel', 'matricula'],
    ],
];
