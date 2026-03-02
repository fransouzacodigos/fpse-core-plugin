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
        'label' => 'Estudante (Equipe de Aprendizagem Ativa)',
        'category' => 'EAA',
        'description' => 'Estudante da Educação de Adolescentes e Adultos',
        'specific_fields' => ['rede_escola', 'escola_nome', 'escola_inep'],
    ],
    'profissional-saude-eaa' => [
        'label' => 'Profissional de Saúde (Equipe de Aprendizagem Ativa)',
        'category' => 'EAA',
        'description' => 'Profissional de Saúde da Educação de Adolescentes e Adultos',
        'specific_fields' => ['rede_escola', 'escola_nome', 'escola_inep'],
    ],
    'profissional-educacao-eaa' => [
        'label' => 'Profissional de Educação (Equipe de Aprendizagem Ativa)',
        'category' => 'EAA',
        'description' => 'Profissional de Educação da Educação de Adolescentes e Adultos',
        'specific_fields' => ['rede_escola', 'escola_nome', 'escola_inep', 'funcao_eaa'],
    ],
    'outro-membro-eaa' => [
        'label' => 'Outro membro da Equipe de Aprendizagem Ativa',
        'category' => 'EAA',
        'description' => 'Outro membro da Equipe de Aprendizagem Ativa',
        'specific_fields' => ['rede_escola', 'escola_nome', 'escola_inep'],
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
        'label' => 'Bolsista da Instituição Formadora',
        'category' => 'IES',
        'description' => 'Bolsista de Instituição de Ensino Superior',
        'specific_fields' => ['instituicao_nome', 'curso_nome'],
    ],
    'voluntario-ies' => [
        'label' => 'Voluntário(a) da Instituição Formadora',
        'category' => 'IES',
        'description' => 'Voluntário de Instituição de Ensino Superior',
        'specific_fields' => ['instituicao_nome', 'curso_nome'],
    ],
    'coordenador-ies' => [
        'label' => 'Docente da Instituição Formadora',
        'category' => 'IES',
        'description' => 'Coordenador de Instituição de Ensino Superior',
        'specific_fields' => ['instituicao_nome'],
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
        'label' => 'Jovem Mobilizador(a) do Núcleo de Apoio Pedagógico',
        'category' => 'NAP',
        'description' => 'Jovem Mobilizador do Núcleo de Acessibilidade Pedagógica',
        'specific_fields' => ['nap_nome', 'ufs_acompanhadas'],
    ],
    'apoiador-pedagogico-nap' => [
        'label' => 'Apoiador(a) do Núcleo de Apoio Pedagógico',
        'category' => 'NAP',
        'description' => 'Apoiador Pedagógico do Núcleo de Acessibilidade Pedagógica',
        'specific_fields' => ['nap_nome', 'ufs_acompanhadas'],
    ],
    'coordenacao-nap' => [
        'label' => 'Coordenador(a) do Núcleo de Apoio Pedagógico',
        'category' => 'NAP',
        'description' => 'Coordenação do Núcleo de Acessibilidade Pedagógica',
        'specific_fields' => ['nap_nome', 'ufs_acompanhadas'],
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
        'label' => 'Representante do Grupo de Trabalho Intersetorial Municipal',
        'category' => 'GTI',
        'description' => 'Gestão Tecnológica Inclusiva - Municipal',
        'specific_fields' => ['setor_gti', 'sistema_responsavel'],
    ],
    'gti-e' => [
        'label' => 'Representante do Grupo de Trabalho Intersetorial Estadual',
        'category' => 'GTI',
        'description' => 'Gestão Tecnológica Inclusiva - Estadual',
        'specific_fields' => ['setor_gti', 'sistema_responsavel', 'regiao_responsavel'],
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
        'label' => 'Membro da Coordenação do Fortalece PSE',
        'category' => 'Governance',
        'description' => 'Coordenação do programa Fortalece PSE',
        'specific_fields' => [],
    ],
    'representante-mec' => [
        'label' => 'Representante do Ministério da Educação',
        'category' => 'Governance',
        'description' => 'Representante do Ministério da Educação',
        'specific_fields' => ['departamento'],
    ],
    'representante-ms' => [
        'label' => 'Representante do Ministério da Saúde',
        'category' => 'Governance',
        'description' => 'Representante do Ministério da Saúde',
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
