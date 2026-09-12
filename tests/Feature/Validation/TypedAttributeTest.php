<?php

declare(strict_types=1);

use Illuminate\Validation\Rules\AnyOf;
use Illuminate\Validation\Rules\Can;
use Illuminate\Validation\Rules\Contains;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\DoesntContain;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\NotIn;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use Tests\Fixtures\Validation\Typed\AuthorizationRules;
use Tests\Fixtures\Validation\Typed\ComparisonRules;
use Tests\Fixtures\Validation\Typed\ConditionalRules;
use Tests\Fixtures\Validation\Typed\DateRules;
use Tests\Fixtures\Validation\Typed\FileRules;
use Tests\Fixtures\Validation\Typed\PresenceRules;
use Tests\Fixtures\Validation\Typed\SetRules;
use Tests\Fixtures\Validation\Typed\SizeRules;
use Tests\Fixtures\Validation\Typed\StringShapeRules;
use Tests\Fixtures\Validation\Typed\TypeRules;

/**
 * The fixture properties are `mixed`, which is nullable, so every member carries an inferred
 * `nullable` ahead of the attribute under test. Drop it so each expectation shows the attribute's
 * own output and nothing else.
 *
 * @return array<string, list<mixed>>
 */
function attributeRules(object $fixture): array
{
    return array_map(
        static fn(array $rules): array => array_values(array_filter(
            $rules,
            static fn(mixed $rule): bool => $rule !== 'nullable',
        )),
        app(RuleCompiler::class)->forObject($fixture)->rules,
    );
}

describe('type rules', function () {
    it('writes the type rules out by name', function () {
        $rules = attributeRules(new TypeRules());

        expect($rules['text'])->toBe(['string']);
        expect($rules['count'])->toBe(['integer']);
        expect($rules['amount'])->toBe(['numeric']);
        expect($rules['flag'])->toBe(['boolean']);
        expect($rules['bag'])->toBe(['array']);
        expect($rules['items'])->toBe(['list']);
    });

    it('turns a flag into the token Laravel reads', function () {
        $rules = attributeRules(new TypeRules());

        expect($rules['strictCount'])->toBe(['integer:strict']);
        expect($rules['strictFlag'])->toBe(['boolean:strict']);
    });

    it('lists the allowed keys of an array rule', function () {
        expect(attributeRules(new TypeRules())['keyed'])->toBe(['array:name,email']);
    });
});

describe('size rules', function () {
    it('writes a single bound', function () {
        $rules = attributeRules(new SizeRules());

        expect($rules['min'])->toBe(['min:5']);
        expect($rules['max'])->toBe(['max:10.5']);
        expect($rules['size'])->toBe(['size:6']);
    });

    it('writes a pair of bounds in order', function () {
        $rules = attributeRules(new SizeRules());

        expect($rules['between'])->toBe(['between:1,10']);
        expect($rules['digitsBetween'])->toBe(['digits_between:2,8']);
    });

    it('writes the digit rules', function () {
        $rules = attributeRules(new SizeRules());

        expect($rules['digits'])->toBe(['digits:4']);
        expect($rules['minDigits'])->toBe(['min_digits:2']);
        expect($rules['maxDigits'])->toBe(['max_digits:8']);
    });

    it('drops an optional argument that was not given', function () {
        $rules = attributeRules(new SizeRules());

        expect($rules['decimalFixed'])->toBe(['decimal:2']);
        expect($rules['decimalRange'])->toBe(['decimal:2,4']);
    });

    it('keeps a float argument intact', function () {
        expect(attributeRules(new SizeRules())['multipleOf'])->toBe(['multiple_of:0.25']);
    });
});

describe('comparison rules', function () {
    it('names the other field', function () {
        $rules = attributeRules(new ComparisonRules());

        expect($rules['same'])->toBe(['same:password']);
        expect($rules['different'])->toBe(['different:username']);
        expect($rules['gt'])->toBe(['gt:floor']);
        expect($rules['lt'])->toBe(['lt:ceiling']);
    });

    it('takes a literal value just as well as a field name', function () {
        $rules = attributeRules(new ComparisonRules());

        expect($rules['gte'])->toBe(['gte:10']);
        expect($rules['lte'])->toBe(['lte:99.5']);
    });

    it('writes the array-shaped comparisons', function () {
        $rules = attributeRules(new ComparisonRules());

        expect($rules['inArray'])->toBe(['in_array:allowed.*']);
        expect($rules['inArrayKeys'])->toBe(['in_array_keys:nl,be']);
        expect($rules['requiredArrayKeys'])->toBe(['required_array_keys:street,city']);
    });
});

describe('string shape rules', function () {
    it('passes a pattern through untouched', function () {
        $rules = attributeRules(new StringShapeRules());

        expect($rules['regex'])->toBe(['regex:/^[a-z]+$/']);
        expect($rules['notRegex'])->toBe(['not_regex:/\d/']);
    });

    it('joins the affix lists', function () {
        $rules = attributeRules(new StringShapeRules());

        expect($rules['startsWith'])->toBe(['starts_with:nl-,be-']);
        expect($rules['doesntStartWith'])->toBe(['doesnt_start_with:de-']);
        expect($rules['endsWith'])->toBe(['ends_with:.nl']);
        expect($rules['doesntEndWith'])->toBe(['doesnt_end_with:.test']);
    });

    it('turns flags into tokens rather than booleans', function () {
        $rules = attributeRules(new StringShapeRules());

        expect($rules['alpha'])->toBe(['alpha:ascii']);
        expect($rules['alphaNum'])->toBe(['alpha_num:ascii']);
        expect($rules['distinct'])->toBe(['distinct:strict,ignore_case']);
    });

    it('leaves an unset flag off the rule entirely', function () {
        $rules = attributeRules(new StringShapeRules());

        expect($rules['alphaDash'])->toBe(['alpha_dash']);
        expect($rules['ascii'])->toBe(['ascii']);
        expect($rules['confirmed'])->toBe(['confirmed']);
        expect($rules['hexColor'])->toBe(['hex_color']);
        expect($rules['ip'])->toBe(['ip']);
    });

    it('writes the optional arguments when they are given', function () {
        $rules = attributeRules(new StringShapeRules());

        expect($rules['confirmedField'])->toBe(['confirmed:repeat_password']);
        expect($rules['uuid'])->toBe(['uuid:4']);
        expect($rules['timezone'])->toBe(['timezone:per_country,NL']);
        expect($rules['url'])->toBe(['url:https']);
    });

    it('writes an encoding by name', function () {
        expect(attributeRules(new StringShapeRules())['encoding'])->toBe(['encoding:UTF-8']);
    });
});

describe('set rules', function () {
    it('builds Laravel rule objects rather than rule strings', function () {
        $rules = attributeRules(new SetRules());

        expect($rules['in'][0])->toBeInstanceOf(In::class);
        expect($rules['notIn'][0])->toBeInstanceOf(NotIn::class);
        expect($rules['contains'][0])->toBeInstanceOf(Contains::class);
        expect($rules['doesntContain'][0])->toBeInstanceOf(DoesntContain::class);
    });

    it('quotes the values, so one holding a comma survives', function () {
        $rules = attributeRules(new SetRules());

        expect((string) $rules['in'][0])->toBe('in:"nl","be"');
        expect((string) $rules['quoted'][0])->toBe('in:"one, two","three"');
    });

    it('writes a backed enum as its value', function () {
        expect((string) attributeRules(new SetRules())['enumValues'][0])->toBe('in:"draft","open"');
    });

    it('writes the negative forms', function () {
        $rules = attributeRules(new SetRules());

        expect((string) $rules['notIn'][0])->toBe('not_in:"de"');
        expect((string) $rules['doesntContain'][0])->toBe('doesnt_contain:"de"');
    });
});

describe('date rules', function () {
    it('writes a DateTimeInterface in the format Laravel parses back', function () {
        expect(attributeRules(new DateRules())['after'])->toBe(['after:2026-01-02 03:04:05']);
    });

    it('takes a relative word or a field name as well', function () {
        $rules = attributeRules(new DateRules());

        expect($rules['afterOrEqual'])->toBe(['after_or_equal:today']);
        expect($rules['before'])->toBe(['before:ends_at']);
        expect($rules['beforeOrEqual'])->toBe(['before_or_equal:tomorrow']);
        expect($rules['equals'])->toBe(['date_equals:starts_at']);
    });

    it('joins several accepted formats', function () {
        $rules = attributeRules(new DateRules());

        expect($rules['date'])->toBe(['date']);
        expect($rules['format'])->toBe(['date_format:Y-m-d,d-m-Y']);
    });
});

describe('presence rules', function () {
    it('writes the argument-less forms', function () {
        $rules = attributeRules(new PresenceRules());

        expect($rules['required'])->toBe(['required']);
        expect($rules['present'])->toBe(['present']);
        expect($rules['filled'])->toBe(['filled']);
    });

    it('joins the field lists', function () {
        $rules = attributeRules(new PresenceRules());

        expect($rules['requiredWith'])->toBe(['required_with:first,last']);
        expect($rules['requiredWithAll'])->toBe(['required_with_all:first,last']);
        expect($rules['requiredWithout'])->toBe(['required_without:first']);
        expect($rules['requiredWithoutAll'])->toBe(['required_without_all:first,last']);
        expect($rules['presentWithAll'])->toBe(['present_with_all:first,last']);
        expect($rules['missingWith'])->toBe(['missing_with:legacy']);
        expect($rules['excludeWithout'])->toBe(['exclude_without:parent']);
        expect($rules['prohibits'])->toBe(['prohibits:other']);
    });

    it('writes a field with the values that trigger it', function () {
        $rules = attributeRules(new PresenceRules());

        expect($rules['presentIf'])->toBe(['present_if:plan,pro,team']);
        expect($rules['missingIf'])->toBe(['missing_if:plan,free']);
        expect($rules['requiredIfAccepted'])->toBe(['required_if_accepted:terms']);
    });
});

describe('conditional rules', function () {
    it('writes the field form as the rule Laravel names', function () {
        $rules = attributeRules(new ConditionalRules());

        expect($rules['requiredIf'])->toBe(['required_if:plan,pro']);
        expect($rules['requiredUnless'])->toBe(['required_unless:subscribe,1']);
        expect($rules['prohibitedIf'])->toBe(['prohibited_if:plan,free']);
        expect($rules['prohibitedUnless'])->toBe(['prohibited_unless:plan,pro']);
        expect($rules['excludeIf'])->toBe(['exclude_if:plan,free']);
        expect($rules['excludeUnless'])->toBe(['exclude_unless:plan,pro']);
        expect($rules['acceptedIf'])->toBe(['accepted_if:plan,pro']);
        expect($rules['declinedIf'])->toBe(['declined_if:plan,free']);
    });

    it('lists every value that triggers the condition', function () {
        expect(attributeRules(new ConditionalRules())['requiredIfAny'])->toBe(['required_if:plan,pro,team']);
    });
});

describe('file rules', function () {
    it('writes the file rules by name', function () {
        $rules = attributeRules(new FileRules());

        expect($rules['file'])->toBe(['file']);
        expect($rules['mimes'])->toBe(['mimes:jpg,png']);
        expect($rules['mimetypes'])->toBe(['mimetypes:image/jpeg']);
        expect($rules['extensions'])->toBe(['extensions:jpg']);
    });

    it('adds the svg token only when svg is allowed', function () {
        $rules = attributeRules(new FileRules());

        expect($rules['image'])->toBe(['image']);
        expect($rules['svg'])->toBe(['image:allow_svg']);
    });

    it('writes dimensions as the constraints that were given', function () {
        $rules = attributeRules(new FileRules());

        expect($rules['dimensions'][0])->toBeInstanceOf(Dimensions::class);
        expect((string) $rules['dimensions'][0])->toBe('dimensions:min_width=100,max_height=200,ratio=3/2');
    });

    it('spreads a ratio range over the two ratio constraints', function () {
        expect((string) attributeRules(new FileRules())['ratioBetween'][0])
            ->toBe('dimensions:min_ratio=1/2,max_ratio=3/2');
    });
});

describe('authorization rules', function () {
    it('builds a Can rule object', function () {
        $rules = attributeRules(new AuthorizationRules());

        expect($rules['post'][0])->toBeInstanceOf(Can::class);
        expect($rules['withArguments'][0])->toBeInstanceOf(Can::class);
    });

    it('builds an AnyOf rule object', function () {
        expect(attributeRules(new AuthorizationRules())['anyOf'][0])->toBeInstanceOf(AnyOf::class);
    });
});
