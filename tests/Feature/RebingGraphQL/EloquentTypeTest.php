<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use Tests\Fixtures\RebingGraphQL\Types\Eloquent\Article;
use Tests\Fixtures\RebingGraphQL\Types\Eloquent\ArticleQueries;
use Tests\Fixtures\RebingGraphQL\Types\Eloquent\ArticleStatus;
use Tests\Fixtures\RebingGraphQL\Types\Eloquent\Invalid\AuthorizedFrameworkProperty;
use Tests\Fixtures\RebingGraphQL\Types\Eloquent\Invalid\AuthorizedTraitProperty;
use Tests\Fixtures\RebingGraphQL\Types\Eloquent\Invalid\FieldOnPlainModelProperty;
use Tests\Fixtures\RebingGraphQL\Types\Eloquent\Invalid\ShadowingArticle;
use Workbench\App\Models\User;

const ARTICLE_SDL = <<<'GRAPHQL'
    "An article stored in the database"
    type Article {
      id: ID!
      title: String!
      publishedAt: String
      wordCount: Int!
      status: ArticleStatus!
      copiesSold: Int
      slug: String!

      "The title in capitals"
      headline: String!
    }

    enum ArticleStatus {
      Draft
      Published
    }

    type Query {
      article(id: ID!): Article!
    }

    type Mutation {
      retitleArticle(title: String!, id: ID!): Article!
    }

    GRAPHQL;

describe('discovering a #[Type] model', function () {
    it('prints only the hooked properties and #[Field] methods, never the framework members', function () {
        expect(schemaSdl(ArticleQueries::class, Article::class))->toBe(ARTICLE_SDL);

        buildAllSchemas();
    });

    it('leaves an #[Ignore]d plain public property alone', function () {
        [$type] = discoveredTypesOf(TypeKind::Object, Article::class);

        expect(array_column($type->fields, 'phpName'))->not->toContain('previewing');
    });

    it('infers the #[Type] from a model return while the parameter binds the same model by ID', function () {
        $actions = discoveredActions(ArticleQueries::class, Article::class);

        expect($actions['article']->returnType)->toEqual(TypeRef::class(Article::class))
            ->and($actions['retitleArticle']->returnType)->toEqual(TypeRef::class(Article::class));
    });

    it('decides everything from reflection, without instantiating the model', function () {
        Article::$constructed = 0;

        isolateGraphQL();
        discoverGraphQL(ArticleQueries::class, Article::class)->apply();

        expect(Article::$constructed)->toBe(0);
    });

    it('survives a serialize round trip', function () {
        $items = iterator_to_array(discoverGraphQL(ArticleQueries::class, Article::class)->getItems(), false);

        expect($items)->not->toBeEmpty();

        foreach ($items as $item) {
            expect(unserialize(serialize($item)))->toEqual($item);
        }

        $type = array_values(array_filter($items, static fn(mixed $item): bool => $item instanceof DiscoveredType))[0];

        expect(array_map(static fn($field): string => $field->name, $type->fields))
            ->toBe(['id', 'title', 'publishedAt', 'wordCount', 'status', 'copiesSold', 'slug', 'headline']);
    });

    it('rejects a plain public property that would shadow an attribute', function (string $class) {
        expect(fn() => discoverGraphQL($class))->toThrow(
            \LogicException::class,
            sprintf(
                'Property %s::$title is a plain public property on an Eloquent model, so it shadows the attribute \'title\'. Make it a virtual hooked property, as in `public string $title { get => $this->getAttribute(\'title\'); }`, or add #[Ignore] to keep it out of the type.',
                $class,
            ),
        );
    })->with([
        'without #[Field]' => [ShadowingArticle::class],
        'with #[Field]' => [FieldOnPlainModelProperty::class],
    ]);

    it('still reports a field decorator on a property it skips', function (string $class, string $property) {
        expect(fn() => discoverGraphQL($class))->toThrow(
            \LogicException::class,
            "Property {$class}::\${$property} has #[Authorize] but is not a field",
        );
    })->with([
        'a redeclared framework property' => [AuthorizedFrameworkProperty::class, 'timestamps'],
        'a plain property from a trait' => [AuthorizedTraitProperty::class, 'mediaConversions'],
    ]);
});

describe('the Eloquent hook contract (canary)', function () {
    beforeEach(function () {
        loadGraphQLMigrations();
    });

    it('hooks keep dirty tracking and save() working', function () {
        $article = Article::create([
            'title' => 'Hooks',
            'published_at' => '2026-01-02 03:04:05',
            'word_count' => '120',
        ]);

        expect($article->title)->toBe('Hooks')
            ->and($article->publishedAt)->toBeInstanceOf(CarbonImmutable::class)
            ->and($article->wordCount)->toBe(120)
            ->and($article->status)->toBe(ArticleStatus::Draft)
            ->and($article->isDirty())->toBeFalse();

        $article->title = 'Property hooks';

        expect($article->isDirty('title'))->toBeTrue()
            ->and($article->getAttribute('title'))->toBe('Property hooks')
            ->and($article->toArray())->toMatchArray(['title' => 'Property hooks', 'word_count' => 120]);

        $article->save();

        expect($article->isDirty())->toBeFalse()
            ->and(Article::findOrFail($article->id)->title)->toBe('Property hooks');
    });
});

describe('resolving a #[Type] model', function () {
    beforeEach(function () {
        loadGraphQLMigrations();
    });

    it('resolves a model returned by a query end to end', function () {
        $article = Article::create([
            'title' => 'Hooks',
            'published_at' => '2026-01-02 03:04:05',
            'word_count' => 120,
            'status' => ArticleStatus::Published,
        ]);
        Article::create(['title' => 'Drafts']);

        schemaSdl(ArticleQueries::class, Article::class);

        $this->postJson('/graphql', ['query' => "{ article(id: {$article->id}) { id title publishedAt wordCount status headline } }"])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => ['article' => [
                'id' => (string) $article->id,
                'title' => 'Hooks',
                'publishedAt' => '2026-01-02 03:04:05',
                'wordCount' => 120,
                'status' => 'Published',
                'headline' => 'HOOKS',
            ]]]);
    });

    it('authorizes a hooked property against the model', function (bool $allowed, ?int $copiesSold) {
        Gate::define('viewSales', fn(?User $user, Article $article) => $allowed && $article->title === 'Hooks');
        $article = Article::create(['title' => 'Hooks', 'copies_sold' => 42]);

        schemaSdl(ArticleQueries::class, Article::class);

        $this->postJson('/graphql', ['query' => "{ article(id: {$article->id}) { title copiesSold } }"])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => ['article' => ['title' => 'Hooks', 'copiesSold' => $copiesSold]]]);
    })->with([
        'denied' => [false, null],
        'allowed' => [true, 42],
    ]);

    it('returns null for a cast attribute that is null', function () {
        $article = Article::create(['title' => 'Drafts']);

        schemaSdl(ArticleQueries::class, Article::class);

        $this->postJson('/graphql', ['query' => "{ article(id: {$article->id}) { publishedAt wordCount } }"])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => ['article' => ['publishedAt' => null, 'wordCount' => 0]]]);
    });

    it('writes through a set hook in a mutation and persists it', function () {
        $article = Article::create(['title' => 'Hooks']);

        schemaSdl(ArticleQueries::class, Article::class);

        $this->postJson('/graphql', ['query' => "mutation { retitleArticle(id: {$article->id}, title: \"Virtual\") { id title } }"])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertExactJson(['data' => ['retitleArticle' => ['id' => (string) $article->id, 'title' => 'Virtual']]]);

        expect($article->fresh()?->title)->toBe('Virtual');
    });
});
