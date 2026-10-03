<?php

declare(strict_types=1);

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
];
