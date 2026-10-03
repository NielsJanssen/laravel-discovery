<?php

declare(strict_types=1);

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\FieldCase;

return [
    /*
     * GraphQL scalar or type names for PHP classes, matched with is_a(), so an interface covers its
     * implementations: [CarbonInterface::class => 'DateTime']. A named type must be registered with Rebing.
     */
    'scalars' => [],

    /*
     * Namespace prefixes whose members never become fields: anything a class or trait under one of them declares,
     * also when an application class redeclares it. Add a framework or package that your types extend.
     */
    'skip_namespaces' => ['Illuminate\\'],

    /*
     * How PHP names become GraphQL names: a FieldCase (Preserve, Camel, Snake) or a NamingStrategy class-string.
     * fields: type and input fields; arguments: query, mutation, field and #[AsArgs] args; operations: query and mutation names.
     * An explicit `name:` always wins. Run discovery:clear after changing these.
     */
    'naming' => [
        'fields' => FieldCase::Preserve,
        'arguments' => FieldCase::Preserve,
        'operations' => FieldCase::Preserve,
    ],
];
