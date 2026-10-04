<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use GraphQL\Type\Definition\Type as GraphQLType;
use GraphQL\Utils\SchemaPrinter;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\DisableIntrospection;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\ScopedGraphQL;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\ScopesSchemaTypes;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeDefinition;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use Rebing\GraphQL\GraphQL as RebingGraphQL;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\Type as RebingType;
use Tests\Fixtures\RebingGraphQL\Enums\Orphan;
use Tests\Fixtures\RebingGraphQL\Factories\AcmeCompany;
use Tests\Fixtures\RebingGraphQL\Factories\AcmeCompanyFields;
use Tests\Fixtures\RebingGraphQL\Factories\AcmeFactoryQueries;
use Tests\Fixtures\RebingGraphQL\Factories\AcmeLookup;
use Tests\Fixtures\RebingGraphQL\Providers\AcmeConfigurableProvider;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeUser;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeUserQuery;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeUserWithBilling;
use Tests\Fixtures\RebingGraphQL\Schemas\AcmeAdminQueries;
use Tests\Fixtures\RebingGraphQL\Schemas\AcmeAuditLevel;
use Tests\Fixtures\RebingGraphQL\Schemas\AcmeDefaultPamphletQuery;
use Tests\Fixtures\RebingGraphQL\Schemas\AcmeLedgerQuery;
use Tests\Fixtures\RebingGraphQL\Schemas\AcmeNode;
use Tests\Fixtures\RebingGraphQL\Schemas\AcmeNodeQueries;
use Tests\Fixtures\RebingGraphQL\Schemas\AcmePublicQueries;
use Tests\Fixtures\RebingGraphQL\Schemas\AcmeReport;
use Tests\Fixtures\RebingGraphQL\Schemas\AcmeReportExtras;
use Tests\Fixtures\RebingGraphQL\Schemas\AcmeReportFilter;
use Tests\Fixtures\RebingGraphQL\Schemas\AcmeReportLine;
use Tests\Fixtures\RebingGraphQL\Schemas\AcmeWidget;
use Tests\Fixtures\RebingGraphQL\Schemas\Invalid\AcmeAdminNote;
use Tests\Fixtures\RebingGraphQL\Schemas\Invalid\AcmeLooseBoard;
use Tests\Fixtures\RebingGraphQL\Schemas\Invalid\AcmeNoteQuery;
use Tests\Fixtures\RebingGraphQL\Types\PamphletQuery;
use Tests\Fixtures\RebingGraphQL\Types\PamphletType;

const SCOPED_SOURCES = [AcmeAdminQueries::class, AcmePublicQueries::class, AcmeReport::class, AcmeReportLine::class, AcmeReportFilter::class, Orphan::class];

const ADMIN_SCHEMA = ['admin' => ['query' => []]];

const ORPHAN_SDL = <<<'GRAPHQL'
    enum Orphan {
      Alone
    }
    GRAPHQL;

const REPORT_LINE_SDL = <<<'GRAPHQL'
    type AcmeReportLine {
      text: String!
    }
    GRAPHQL;

const PUBLIC_QUERY_SDL = <<<'GRAPHQL'
    type Query {
      line: AcmeReportLine!
    }
    GRAPHQL;

const ADMIN_SDL = [
    <<<'GRAPHQL'
    enum Mood {
      Calm
      Cheerful
      Gloomy
    }
    GRAPHQL,
    <<<'GRAPHQL'
    input AcmeReportFilterInput {
      severity: Mood!
    }
    GRAPHQL,
    <<<'GRAPHQL'
    type AcmeReport {
      title: String!
      severity: Mood!
      lines: [AcmeReportLine!]!
    }
    GRAPHQL,
    REPORT_LINE_SDL,
    <<<'GRAPHQL'
    type Query {
      report(filter: AcmeReportFilterInput!): AcmeReport!
      adminLine: AcmeReportLine!
    }
    GRAPHQL,
    ORPHAN_SDL,
];

/**
 * Discover and apply the sources on an isolated setup, with schema-scoped types on or off.
 *
 * @param  list<class-string|DiscoveredType>  $sources
 * @param  array<string, mixed>  $schemas  graphql.schemas to start from
 */
function scopeSchemas(array $sources, bool $scoped = true, array $schemas = []): void
{
    config()->set('discovery.graphql.scoped_schemas', $scoped);
    isolateGraphQL();
    config()->set('graphql.schemas', $schemas);
    discoverGraphQL(...$sources)->apply();
}

/**
 * The sorted top-level definitions of a schema.
 *
 * @return list<string>
 */
function scopedDefinitions(string $schema): array
{
    return sdlDefinitions(SchemaPrinter::doPrint(GraphQL::schema($schema)));
}

/**
 * @param  list<string>  $definitions
 * @return list<string>
 */
function sortedDefinitions(array $definitions): array
{
    sort($definitions);

    return $definitions;
}

/**
 * @param  list<TypeDefinition>  $definitions
 * @param  list<class-string>  $sources
 */
function provideScoped(array $definitions, array $sources = SCOPED_SOURCES, bool $scoped = true): void
{
    app()->instance(AcmeConfigurableProvider::class, new AcmeConfigurableProvider($definitions));
    scopeSchemas([...$sources, AcmeConfigurableProvider::class], $scoped);
}

/**
 * @param  string|list<string>|null  $schema
 */
function acmeProvided(string $name, string|array|null $schema = null): TypeDefinition
{
    return new TypeDefinition($name, Position::Output, static fn(): array => [new Field(name: 'entry', type: 'string')], schema: $schema);
}

function handWrittenType(string $name, string $field): RebingType
{
    return new class ($name, $field) extends RebingType {
        public function __construct(string $name, private readonly string $field)
        {
            $this->attributes = ['name' => $name];
        }

        public function fields(): array
        {
            return [$this->field => ['type' => GraphQLType::nonNull(GraphQLType::string())]];
        }
    };
}

/**
 * @return array<string, mixed>
 */
function queryScoped(string $query, string $schema = 'default'): array
{
    app(RebingGraphQL::class);
    $introspection = DocumentValidator::getRule(DisableIntrospection::class);
    $introspection?->setEnabled(DisableIntrospection::DISABLED);

    try {
        return GraphQL::query($query, null, ['schema' => $schema]);
    } finally {
        $introspection?->setEnabled(DisableIntrospection::ENABLED);
    }
}

describe('assignment', function () {
    it('keeps a type out of every schema whose actions do not reach it, whichever schema is built first', function (string $first, string $printed, array $definitions) {
        scopeSchemas(SCOPED_SOURCES);

        GraphQL::schema($first);

        expect(scopedDefinitions($printed))->toBe(sortedDefinitions($definitions));
    })->with([
        'default after admin' => ['admin', 'default', [ORPHAN_SDL, REPORT_LINE_SDL, PUBLIC_QUERY_SDL]],
        'admin after default' => ['default', 'admin', ADMIN_SDL],
    ]);

    it('shares the instances of a type both schemas use, whichever schema is built first', function (string $first, string $second) {
        scopeSchemas(SCOPED_SOURCES);

        GraphQL::schema($first);
        GraphQL::schema($second);

        expect(scopedDefinitions('default'))->toBe(sortedDefinitions([ORPHAN_SDL, REPORT_LINE_SDL, PUBLIC_QUERY_SDL]))
            ->and(scopedDefinitions('admin'))->toBe(sortedDefinitions(ADMIN_SDL))
            ->and(queryScoped('{ line { text } }'))->toBe(['data' => ['line' => ['text' => 'public line']]])
            ->and(queryScoped('{ adminLine { text } }', 'admin'))->toBe(['data' => ['adminLine' => ['text' => 'admin line']]]);
    })->with([
        'default first' => ['default', 'admin'],
        'admin first' => ['admin', 'default'],
    ]);

    it('lists every registered type in every schema while scoping is off', function () {
        scopeSchemas(SCOPED_SOURCES, scoped: false);

        expect(scopedDefinitions('default'))->toContain(ADMIN_SDL[2])->toContain(ADMIN_SDL[1]);
    });

    it('prints a single schema the same with scoping on and off', function () {
        $sources = [AcmePublicQueries::class, AcmeReportLine::class, AcmeReport::class, Orphan::class, AcmeReportExtras::class];

        scopeSchemas($sources, scoped: false);
        $off = SchemaPrinter::doPrint(GraphQL::schema());
        scopeSchemas($sources);

        expect(SchemaPrinter::doPrint(GraphQL::schema()))->toBe($off)->and($off)->toContain('type AcmeReport {');
    });

    it('follows the fields an #[TypeExtension] contributor adds', function () {
        scopeSchemas([...SCOPED_SOURCES, AcmeReportExtras::class]);

        expect(scopedDefinitions('default'))->not->toContain(ORPHAN_SDL)
            ->and(scopedDefinitions('admin'))->toContain(ORPHAN_SDL);
    });

    it('places a type that no action reaches in the schemas schema: names', function () {
        scopeSchemas([...SCOPED_SOURCES, AcmeAuditLevel::class]);
        $level = "enum AcmeAuditLevel {\n  Full\n}";

        expect(scopedDefinitions('default'))->not->toContain($level)
            ->and(scopedDefinitions('admin'))->toContain($level);
    });

    it('keeps the placement of a replaced type for its replacement', function () {
        scopeSchemas([...SCOPED_SOURCES, new DiscoveredType('AcmeUser', AcmeUser::class, schemas: ['admin']), AcmeUserWithBilling::class]);

        $admin = implode("\n\n", scopedDefinitions('admin'));

        expect($admin)->toContain('type AcmeUser {')->toContain('  billingReference: String')
            ->and(implode("\n\n", scopedDefinitions('default')))->not->toContain('AcmeUser');
    });

    it('resolves a query of a scoped schema', function () {
        scopeSchemas(SCOPED_SOURCES);

        expect(queryScoped('{ report(filter: {severity: Gloomy}) { title severity lines { text } } }', 'admin'))
            ->toBe(['data' => ['report' => ['title' => 'Q3', 'severity' => 'Gloomy', 'lines' => [['text' => 'admin line']]]]])
            ->and(queryScoped('{ line { text } }'))->toBe(['data' => ['line' => ['text' => 'public line']]]);
    });

    it('keeps the scopes when the configuration is cached', function () {
        scopeSchemas(SCOPED_SOURCES);
        $schemas = config('graphql.schemas');
        $types = config('graphql.types');

        withCachedConfig(function () use ($schemas, $types) {
            config()->set('graphql.schemas', $schemas);
            config()->set('graphql.types', $types);
            discoverGraphQL(...SCOPED_SOURCES)->apply();

            expect(scopedDefinitions('default'))->toBe(sortedDefinitions([ORPHAN_SDL, REPORT_LINE_SDL, PUBLIC_QUERY_SDL]));
        });
    });

    it('keeps schema: through the discovery cache', function () {
        $types = array_values(array_filter([...cachedGraphQLItems(AcmeAuditLevel::class)], static fn(object $item): bool => $item instanceof DiscoveredType));

        expect($types)->toHaveCount(1)->and($types[0]->schemas)->toBe(['admin']);
    });
});

describe('hand-written Rebing types', function () {
    it('lists a type in graphql.types in every schema', function () {
        config()->set('discovery.graphql.scoped_schemas', true);
        isolateGraphQL();
        config()->set('graphql.types', ['Pamphlet' => PamphletType::class]);
        discoverGraphQL(...SCOPED_SOURCES)->apply();
        $pamphlet = "type Pamphlet {\n  title: String!\n}";

        expect(scopedDefinitions('default'))->toContain($pamphlet)
            ->and(scopedDefinitions('admin'))->toContain($pamphlet);
    });

    it('keeps a type of a schema types key in that schema, whichever schema is built first', function (string $first) {
        scopeSchemas([AcmePublicQueries::class, AcmeReportLine::class, PamphletQuery::class], schemas: ['pamphlets' => ['types' => ['Pamphlet' => PamphletType::class]]]);
        GraphQL::schema($first);

        expect(queryScoped('{ __type(name: "Pamphlet") { name } }'))->toBe(['data' => ['__type' => null]])
            ->and(scopedDefinitions('default'))->toBe(sortedDefinitions([REPORT_LINE_SDL, PUBLIC_QUERY_SDL]))
            ->and(queryScoped('{ pamphlet { title } }', 'pamphlets'))->toBe(['data' => ['pamphlet' => ['title' => 'Common Sense']]]);
    })->with([
        'default first' => 'default',
        'pamphlets first' => 'pamphlets',
    ]);
});

describe('type loader', function () {
    it('does not know a type of another schema by name', function () {
        scopeSchemas(SCOPED_SOURCES);

        expect(queryScoped('{ __type(name: "AcmeReport") { name } }'))->toBe(['data' => ['__type' => null]])
            ->and(queryScoped('{ __type(name: "AcmeReport") { name } }', 'admin'))->toBe(['data' => ['__type' => ['name' => 'AcmeReport']]]);

        $result = queryScoped('{ line { text } ... on AcmeReport { title } }');

        expect($result)->not->toHaveKey('data')
            ->and($result['errors'])->toHaveCount(1)
            ->and($result['errors'][0]['message'])->toStartWith('Unknown type "AcmeReport".');
    });

    it('still finds a type the schema reaches through a field discovery cannot see', function () {
        app()->instance(AcmeCompanyFields::class, new AcmeCompanyFields([new Field(name: 'report', type: 'AcmeReport', nullable: true, resolve: static fn(): null => null)]));
        scopeSchemas([...SCOPED_SOURCES, AcmeFactoryQueries::class, AcmeCompany::class, AcmeLookup::class]);

        expect(queryScoped('{ __type(name: "AcmeReport") { name } }'))->toBe(['data' => ['__type' => ['name' => 'AcmeReport']]])
            ->and(queryScoped('{ company { name report { title } } }'))->toBe(['data' => ['company' => ['name' => 'Acme', 'report' => null]]]);
    });
});

describe('validation', function () {
    it('rejects a placed type that another schema reaches', function () {
        expect(fn() => scopeSchemas([AcmeNoteQuery::class, AcmeLooseBoard::class, AcmeAdminNote::class], schemas: ADMIN_SCHEMA))->toThrow(\LogicException::class, sprintf(
            "Field AcmeLooseBoard.pinned references %1\$s, which #[Type(schema: ...)] on %1\$s places in schema [admin], but schema [default] reaches it through %2\$s::board. Add 'default' to schema:, or remove schema: so the type follows the actions that use it.",
            AcmeAdminNote::class,
            AcmeNoteQuery::class,
        ));
    });

    it('rejects a placed replacement that another schema reaches through the class it replaces', function () {
        expect(fn() => scopeSchemas([AcmeUserQuery::class, new DiscoveredType('AcmeUser', AcmeUser::class, schemas: ['admin']), AcmeUserWithBilling::class], schemas: ADMIN_SCHEMA))
            ->toThrow(\LogicException::class, sprintf('on %s places in schema [admin], but schema [default] reaches it through %s::', AcmeUserWithBilling::class, AcmeUserQuery::class));
    });

    it('rejects a placed type that a type in every schema references', function () {
        expect(fn() => scopeSchemas([AcmePublicQueries::class, AcmeReportLine::class, AcmeLooseBoard::class, AcmeAdminNote::class], schemas: ADMIN_SCHEMA))->toThrow(\LogicException::class, sprintf(
            'Field AcmeLooseBoard.pinned references %1$s, which #[Type(schema: ...)] on %1$s places in schema [admin], but AcmeLooseBoard is in every schema: no action reaches it and it sets no schema:. Place AcmeLooseBoard with schema: too, or remove schema: from %1$s.',
            AcmeAdminNote::class,
        ));
    });

    it('rejects schema: naming a schema that does not exist', function () {
        expect(fn() => scopeSchemas([AcmePublicQueries::class, AcmeReportLine::class, new DiscoveredType('Orphan', Orphan::class, TypeKind::Enum, schemas: ['admn'])]))
            ->toThrow(\LogicException::class, sprintf('#[Enum(schema: ...)] on %s names schema [admn], which is not in graphql.schemas and no action uses it. Fix the name, or add the schema.', Orphan::class));
    });

    it('reports a misspelt schema before checking the placement against the schemas that reach the type', function () {
        expect(fn() => scopeSchemas([AcmePublicQueries::class, new DiscoveredType('AcmeReportLine', AcmeReportLine::class, schemas: ['admn'])]))
            ->toThrow(\LogicException::class, sprintf('#[Type(schema: ...)] on %s names schema [admn], which is not in graphql.schemas and no action uses it.', AcmeReportLine::class));
    });

    it('rejects schema: while scoping is off', function () {
        expect(fn() => scopeSchemas([AcmePublicQueries::class, AcmeReportLine::class, AcmeAuditLevel::class], scoped: false))
            ->toThrow(\LogicException::class, sprintf('#[Enum(schema: ...)] on %s needs schema-scoped types. Set discovery.graphql.scoped_schemas to true, or remove schema:.', AcmeAuditLevel::class));
    });
});

describe('binding', function () {
    it('swaps in the scoped GraphQL only while scoping is on', function () {
        scopeSchemas(SCOPED_SOURCES, scoped: false);
        $off = app(RebingGraphQL::class);
        scopeSchemas(SCOPED_SOURCES);

        expect($off::class)->toBe(RebingGraphQL::class)->and(app(RebingGraphQL::class))->toBeInstanceOf(ScopedGraphQL::class);
    });

    it('rejects another subclass of Rebing\'s GraphQL', function () {
        scopeSchemas(SCOPED_SOURCES);
        app()->singleton(RebingGraphQL::class, static fn($app) => new class ($app, $app['config']) extends RebingGraphQL {});

        expect(fn() => app(RebingGraphQL::class))
            ->toThrow(\LogicException::class, sprintf('which schema-scoped types cannot extend. Use the %s trait in it, or set discovery.graphql.scoped_schemas to false.', ScopesSchemaTypes::class));
    });

    it('scopes a subclass that uses the trait', function () {
        scopeSchemas(SCOPED_SOURCES);
        app()->singleton(RebingGraphQL::class, static fn($app) => new class ($app, $app['config']) extends RebingGraphQL {
            use ScopesSchemaTypes;
        });
        GraphQL::clearResolvedInstance(RebingGraphQL::class);

        expect(app(RebingGraphQL::class))->not->toBeInstanceOf(ScopedGraphQL::class)
            ->and(scopedDefinitions('default'))->toBe(sortedDefinitions([ORPHAN_SDL, REPORT_LINE_SDL, PUBLIC_QUERY_SDL]));
    });
});

describe('provided types', function () {
    it('keeps a provided type in the schemas that reach it', function () {
        provideScoped([acmeProvided('AcmeLedger')], [...SCOPED_SOURCES, AcmeLedgerQuery::class]);
        $ledger = "type AcmeLedger {\n  entry: String!\n}";

        expect(scopedDefinitions('default'))->not->toContain($ledger)
            ->and(scopedDefinitions('admin'))->toContain($ledger)
            ->and(queryScoped('{ ledger { entry } }', 'admin'))->toBe(['data' => ['ledger' => ['entry' => 'E1']]]);
    });

    it('lists a provided type nothing reaches in every schema, unless schema: places it', function () {
        provideScoped([acmeProvided('AcmeVault'), acmeProvided('AcmeSafe', 'admin')]);
        $vault = "type AcmeVault {\n  entry: String!\n}";
        $safe = "type AcmeSafe {\n  entry: String!\n}";

        expect(scopedDefinitions('default'))->toContain($vault)->not->toContain($safe)
            ->and(scopedDefinitions('admin'))->toContain($vault)->toContain($safe);
    });

    it('rejects a placed provided type that another schema reaches, every time GraphQL resolves', function () {
        provideScoped([acmeProvided('AcmeLedger', 'default')], [...SCOPED_SOURCES, AcmeLedgerQuery::class]);
        $message = sprintf(
            "The type provider %s yields [AcmeLedger] with schema: [default], but schema [admin] reaches it through Method %s::ledger. Add 'admin' to schema:, or drop schema: so the type follows the actions that use it.",
            AcmeConfigurableProvider::class,
            AcmeLedgerQuery::class,
        );

        expect(fn() => GraphQL::schema('admin'))->toThrow(\LogicException::class, $message)
            ->and(fn() => GraphQL::schema('admin'))->toThrow(\LogicException::class, $message);
    });

    it('rejects a provided schema: naming a schema that does not exist', function () {
        provideScoped([acmeProvided('AcmeVault', 'admn')]);

        expect(fn() => GraphQL::schema())->toThrow(\LogicException::class, sprintf('The type provider %s yields [AcmeVault] with schema: [admn], which is not in graphql.schemas and no action uses it.', AcmeConfigurableProvider::class));
    });

    it('rejects a provided schema: while scoping is off', function () {
        provideScoped([acmeProvided('AcmeVault', 'admin')], scoped: false);

        expect(fn() => GraphQL::schema())->toThrow(\LogicException::class, sprintf('The type provider %s yields [AcmeVault] with schema:, which needs schema-scoped types. Set discovery.graphql.scoped_schemas to true, or remove schema:.', AcmeConfigurableProvider::class));
    });
});

describe('interfaces', function () {
    it('lists an implementation of an interface the schema lists, even when only another schema names it', function () {
        config()->set('discovery.graphql.scoped_schemas', true);
        isolateGraphQL();
        config()->set('graphql.types', ['AcmeNode' => AcmeNode::class, 'AcmeWidget' => AcmeWidget::class]);
        discoverGraphQL(AcmeNodeQueries::class)->apply();

        expect(queryScoped('{ node { id } }'))->toBe(['data' => ['node' => ['id' => 'W1']]])
            ->and(queryScoped('{ widget { id } }', 'admin'))->toBe(['data' => ['widget' => ['id' => 'W1']]]);
    });
});

describe('rebuilding', function () {
    it('serves a type that is cleared and added again once its schema is rebuilt', function () {
        config()->set('discovery.graphql.scoped_schemas', true);
        isolateGraphQL();
        config()->set('graphql.types', ['Pamphlet' => PamphletType::class]);
        discoverGraphQL(PamphletQuery::class)->apply();

        expect(scopedDefinitions('pamphlets'))->toContain("type Pamphlet {\n  title: String!\n}");

        GraphQL::clearType('Pamphlet');
        GraphQL::addType(handWrittenType('Pamphlet', 'subtitle'), 'Pamphlet');
        GraphQL::clearSchema('pamphlets');

        expect(scopedDefinitions('pamphlets'))->toContain("type Pamphlet {\n  subtitle: String!\n}");
    });

    it('serves the type a types key now names once the schemas are cleared', function () {
        scopeSchemas([AcmePublicQueries::class, AcmeReportLine::class, PamphletQuery::class], schemas: ['pamphlets' => ['types' => ['Pamphlet' => PamphletType::class]]]);

        expect(scopedDefinitions('pamphlets'))->toContain("type Pamphlet {\n  title: String!\n}");

        config()->set('graphql.schemas.pamphlets.types', ['Pamphlet' => handWrittenType('Pamphlet', 'subtitle')]);
        GraphQL::clearSchemas();

        expect(scopedDefinitions('pamphlets'))->toContain("type Pamphlet {\n  subtitle: String!\n}");
    });
});

describe('types keys', function () {
    it('shares the instance of a type two schemas list in their types key, whichever schema is built first', function (string $first, string $second, string $query, array $result) {
        scopeSchemas([AcmeDefaultPamphletQuery::class, PamphletQuery::class], schemas: [
            'default' => ['types' => [PamphletType::class]],
            'pamphlets' => ['types' => [PamphletType::class]],
        ]);

        scopedDefinitions($first);
        scopedDefinitions($second);
        GraphQL::schema($first)->assertValid();

        expect(queryScoped($query, $first))->toBe(['data' => $result]);
    })->with([
        'default first' => ['default', 'pamphlets', '{ defaultPamphlet { title } }', ['defaultPamphlet' => ['title' => 'Default']]],
        'pamphlets first' => ['pamphlets', 'default', '{ pamphlet { title } }', ['pamphlet' => ['title' => 'Common Sense']]],
    ]);

    it('scopes a type a types key gains once the schemas are cleared', function () {
        scopeSchemas([AcmePublicQueries::class, AcmeReportLine::class, PamphletQuery::class], schemas: ['pamphlets' => ['types' => ['Pamphlet' => PamphletType::class]]]);
        scopedDefinitions('pamphlets');

        config()->set('graphql.schemas.pamphlets.types', ['Pamphlet' => PamphletType::class, 'Leaflet' => handWrittenType('Leaflet', 'fold')]);
        GraphQL::clearSchemas();
        $leaflet = "type Leaflet {\n  fold: String!\n}";

        expect(scopedDefinitions('pamphlets'))->toContain($leaflet)
            ->and(scopedDefinitions('default'))->not->toContain($leaflet);
    });

    it('scopes a type a types key gains once its schema is cleared', function () {
        scopeSchemas([AcmePublicQueries::class, AcmeReportLine::class, PamphletQuery::class], schemas: ['pamphlets' => ['types' => ['Pamphlet' => PamphletType::class]]]);
        scopedDefinitions('pamphlets');

        config()->set('graphql.schemas.pamphlets.types', ['Pamphlet' => PamphletType::class, 'Leaflet' => handWrittenType('Leaflet', 'fold')]);
        GraphQL::clearSchema('pamphlets');
        $leaflet = "type Leaflet {\n  fold: String!\n}";

        expect(scopedDefinitions('pamphlets'))->toContain($leaflet)
            ->and(scopedDefinitions('default'))->not->toContain($leaflet);
    });
});
