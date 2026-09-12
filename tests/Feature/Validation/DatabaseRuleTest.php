<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Exists as LaravelExists;
use Illuminate\Validation\Rules\Unique as LaravelUnique;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use Tests\Fixtures\Validation\Database\ExistsEmail;
use Tests\Fixtures\Validation\Database\ExistsWhereArray;
use Tests\Fixtures\Validation\Database\ExistsWhereClosure;
use Tests\Fixtures\Validation\Database\IgnoreLiteralId;
use Tests\Fixtures\Validation\Database\IgnoreOwnId;
use Tests\Fixtures\Validation\Database\IgnoreOwnModel;
use Tests\Fixtures\Validation\Database\UniqueEmail;
use Tests\Fixtures\Validation\Database\UniqueWithoutTrashed;
use Workbench\App\Models\User;

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password')->nullable();
        $table->string('remember_token')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    User::unguarded(static function (): void {
        User::create(['id' => 5, 'name' => 'Ada', 'email' => 'ada@example.com']);
        User::create(['id' => 6, 'name' => 'Bob', 'email' => 'bob@example.com']);
    });
});

afterEach(function () {
    Schema::dropIfExists('users');
});

describe('#[Unique]', function () {
    it('builds Laravel\'s unique rule object', function () {
        $rules = app(RuleCompiler::class)->forObject(new UniqueEmail())->rules;

        expect($rules['email'][1])->toBeInstanceOf(LaravelUnique::class);
    });

    it('passes for a value no row holds', function () {
        expect(Validator::makeFromObject(new UniqueEmail())->passes())->toBeTrue();
    });

    it('fails for a value an existing row holds', function () {
        $form = new UniqueEmail();
        $form->email = 'ada@example.com';

        expect(Validator::makeFromObject($form)->fails())->toBeTrue();
    });

    it('surfaces the message the attribute carries', function () {
        $form = new UniqueEmail();
        $form->email = 'ada@example.com';

        expect(Validator::makeFromObject($form)->errors()->first('email'))->toBe('That email is taken.');
    });

    it('keys that message under the unique rule', function () {
        expect(app(RuleCompiler::class)->forObject(new UniqueEmail())->messages)
            ->toBe(['email.unique' => 'That email is taken.']);
    });
});

describe('#[Unique] ignore', function () {
    it('lets the ignored row keep its own value', function () {
        $form = new IgnoreLiteralId();
        $form->email = 'ada@example.com';

        expect(Validator::makeFromObject($form)->passes())->toBeTrue();
    });

    it('still fails for another row', function () {
        $form = new IgnoreLiteralId();
        $form->email = 'bob@example.com';

        expect(Validator::makeFromObject($form)->fails())->toBeTrue();
    });

    it('reads the key off the object under validation', function () {
        $form = new IgnoreOwnId();
        $form->id = 5;
        $form->email = 'ada@example.com';

        expect(Validator::makeFromObject($form)->passes())->toBeTrue();
    });

    it('resolves the closure per call, so a different key is a different check', function () {
        $form = new IgnoreOwnId();
        $form->id = 6;
        $form->email = 'ada@example.com';

        expect(Validator::makeFromObject($form)->fails())->toBeTrue();
    });

    it('ignores a returned model by its own key', function () {
        $form = new IgnoreOwnModel();
        $form->user = User::find(5);
        $form->email = 'ada@example.com';

        expect(Validator::makeFromObject($form)->passes())->toBeTrue();
    });

    it('checks every row when the closure returns nothing to ignore', function () {
        $form = new IgnoreOwnId();
        $form->email = 'ada@example.com';

        expect(Validator::makeFromObject($form)->fails())->toBeTrue();
    });
});

describe('#[Unique] trashed rows', function () {
    it('lets a soft-deleted row release its value', function () {
        User::query()->where('id', 5)->update(['deleted_at' => now()]);

        $form = new UniqueWithoutTrashed();
        $form->email = 'ada@example.com';

        expect(Validator::makeFromObject($form)->passes())->toBeTrue();
    });

    it('still fails against a row that is not trashed', function () {
        $form = new UniqueWithoutTrashed();
        $form->email = 'ada@example.com';

        expect(Validator::makeFromObject($form)->fails())->toBeTrue();
    });
});

describe('#[Exists]', function () {
    it('builds Laravel\'s exists rule object', function () {
        $rules = app(RuleCompiler::class)->forObject(new ExistsEmail())->rules;

        expect($rules['email'][1])->toBeInstanceOf(LaravelExists::class);
    });

    it('passes for a value a row holds', function () {
        expect(Validator::makeFromObject(new ExistsEmail())->passes())->toBeTrue();
    });

    it('fails for a value no row holds', function () {
        $form = new ExistsEmail();
        $form->email = 'nobody@example.com';

        expect(Validator::makeFromObject($form)->fails())->toBeTrue();
    });

    it('surfaces the message the attribute carries', function () {
        $form = new ExistsEmail();
        $form->email = 'nobody@example.com';

        expect(Validator::makeFromObject($form)->errors()->first('email'))->toBe('No such account.');
    });
});

describe('#[Exists] where constraints', function () {
    it('accepts a row matching every constraint in the array', function () {
        expect(Validator::makeFromObject(new ExistsWhereArray())->passes())->toBeTrue();
    });

    it('rejects a row outside the whereIn list', function () {
        User::query()->where('id', 5)->update(['name' => 'Carol']);

        expect(Validator::makeFromObject(new ExistsWhereArray())->fails())->toBeTrue();
    });

    it('rejects a row where the null constraint does not hold', function () {
        User::query()->where('id', 5)->update(['remember_token' => 'abc']);

        expect(Validator::makeFromObject(new ExistsWhereArray())->fails())->toBeTrue();
    });

    it('hands the query builder and the context to a where closure', function () {
        expect(Validator::makeFromObject(new ExistsWhereClosure())->passes())->toBeTrue();
    });

    it('re-reads the context, so a sibling value narrows the query', function () {
        $form = new ExistsWhereClosure();
        $form->name = 'Bob';

        expect(Validator::makeFromObject($form)->fails())->toBeTrue();
    });
});
