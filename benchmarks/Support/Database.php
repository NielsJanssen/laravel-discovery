<?php

declare(strict_types=1);

namespace Benchmarks\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;

final class Database
{
    public const int AUTHORS = 50;

    public const int BOOKS_PER_AUTHOR = 4;

    /** Creates and seeds the in-memory tables the batch loading query reads. */
    public static function seed(Application $app): void
    {
        $db = $app->make('db')->connection();
        $schema = $db->getSchemaBuilder();

        $schema->create('bench_authors', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        $schema->create('bench_books', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id');
            $table->string('title');
            $table->integer('pages');
        });

        $authors = [];
        $books = [];

        for ($author = 1; $author <= self::AUTHORS; $author++) {
            $authors[] = ['id' => $author, 'name' => "Acme Author {$author}"];

            for ($book = 1; $book <= self::BOOKS_PER_AUTHOR; $book++) {
                $books[] = ['author_id' => $author, 'title' => "Acme Book {$author}.{$book}", 'pages' => 100 + $author + $book];
            }
        }

        $db->table('bench_authors')->insert($authors);
        $db->table('bench_books')->insert($books);
    }

    /** Counts the queries one closure runs. */
    public static function countQueries(Application $app, callable $run): int
    {
        $db = $app->make('db')->connection();
        $db->flushQueryLog();
        $db->enableQueryLog();
        $run();
        $count = count($db->getQueryLog());
        $db->disableQueryLog();

        return $count;
    }
}
