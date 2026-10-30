<?php

use App\Exceptions\InvalidPractiScorePage;
use App\Models\MatchResult;
use App\Models\Stage;
use App\Models\User;
use App\Services\PractiScoreResultsParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function practiScorePage(): string
{
    return (string) file_get_contents(base_path('tests/Fixtures/practiscore/individual-results.html'));
}

function practiScoreUpload(?string $content = null): UploadedFile
{
    return UploadedFile::fake()->createWithContent('results.html', $content ?? practiScorePage());
}

beforeEach(function () {
    Storage::fake('local');

    $this->user = User::factory()->create();
});

it('reads match details from a results page', function () {
    $parsed = (new PractiScoreResultsParser)->parse(practiScorePage());

    expect($parsed['shooter'])->toBe('Doe, Jane')
        ->and($parsed['match'])->toMatchArray([
            'name' => 'Test Club Match',
            'club_name' => 'Test Club',
            'match_date' => '2026-01-15',
            'discipline' => 'USPSA',
            'division' => 'Production',
            'classification' => 'B',
            'practiscore_match_id' => '99999999-9999-4999-8999-999999999999',
        ])
        ->and($parsed['match']['practiscore_url'])
        ->toStartWith('https://practiscore.com/results/all/99999999');
});

it('uses division numbers and both placements for a stage', function () {
    $stage = (new PractiScoreResultsParser)->parse(practiScorePage())['stages'][0];

    expect($stage)->toMatchArray([
        'stage_number' => 1,
        'name' => 'Warehouse',
        'is_classifier' => false,
        'maximum_points' => 60,
        'minimum_rounds' => 12,
    ])->and($stage['result'])->toMatchArray([
        'time' => 10.0,
        'alpha' => 10,
        'points' => 50,
        'hit_factor' => 5.0,
        'stage_percentage' => 90.0,
        'stage_points' => 54.0,
        'overall_place' => 5,
        'division_place' => 2,
        'source' => 'practiscore',
    ]);
});

it('reads the full stage name and classifier code', function () {
    $stage = (new PractiScoreResultsParser)->parse(practiScorePage())['stages'][1];

    expect($stage['name'])->toBe('Quick Draw Classifier')
        ->and($stage['is_classifier'])->toBeTrue()
        ->and($stage['classifier_code'])->toBe('99-01');
});

it('calculates a blank time from points and hit factor', function () {
    $result = (new PractiScoreResultsParser)->parse(practiScorePage())['stages'][1]['result'];

    expect($result['time'])->toBe(10.0)
        ->and($result['source_data']['time_calculated'])->toBeTrue();
});

it('reads hits and penalties', function () {
    $result = (new PractiScoreResultsParser)->parse(practiScorePage())['stages'][2]['result'];

    expect($result)->toMatchArray([
        'alpha' => 13,
        'charlie' => 3,
        'delta' => 1,
        'mike' => 2,
        'no_shoot' => 1,
        'procedural' => 1,
    ])->and($result['source_data']['time_calculated'])->toBeFalse();
});

it('uses the combined row when there is no division row', function () {
    $html = preg_replace('/<tr[^>]*divisionRow.*?<\/tr>/s', '', practiScorePage());

    $result = (new PractiScoreResultsParser)->parse($html)['stages'][0]['result'];

    expect($result['stage_percentage'])->toBe(80.0)
        ->and($result['overall_place'])->toBe(5)
        ->and($result['division_place'])->toBeNull();
});

it('rejects pages without stage results', function () {
    (new PractiScoreResultsParser)->parse('<html><body><p>Hello</p></body></html>');
})->throws(InvalidPractiScorePage::class, 'Select your name');

it('rejects a changed table layout', function () {
    $html = str_replace('<th>HF</th>', '<th>Factor</th>', practiScorePage());

    (new PractiScoreResultsParser)->parse($html);
})->throws(InvalidPractiScorePage::class, 'layout');

it('ignores links that are not PractiScore results', function () {
    $html = str_replace('https://practiscore.com/results/all/', 'https://example.com/results/all/', practiScorePage());

    $match = (new PractiScoreResultsParser)->parse($html)['match'];

    expect($match['practiscore_url'])->toBeNull()
        ->and($match['practiscore_match_id'])->toBeNull();
});

it('imports a match after review', function () {
    Livewire::actingAs($this->user)
        ->test('matches.import')
        ->set('page', practiScoreUpload())
        ->call('preview')
        ->assertHasNoErrors()
        ->assertSee('Doe, Jane')
        ->assertSee('calculated')
        ->tap(fn () => expect(MatchResult::count())->toBe(0))
        ->call('save')
        ->assertRedirect(route('matches.show', MatchResult::sole()));

    $match = MatchResult::sole();

    expect($match->user_id)->toBe($this->user->id)
        ->and($match->stages)->toHaveCount(3)
        ->and($match->stages[1]->classifier_code)->toBe('99-01')
        ->and($match->stages[1]->result->source)->toBe('practiscore')
        ->and((float) $match->stages[1]->result->time)->toBe(10.0)
        ->and($match->stages[1]->result->source_data['time_calculated'])->toBeTrue();
});

it('does not import the same match twice', function () {
    MatchResult::create([
        'user_id' => $this->user->id,
        'name' => 'Existing',
        'practiscore_match_id' => '99999999-9999-4999-8999-999999999999',
    ]);

    Livewire::actingAs($this->user)
        ->test('matches.import')
        ->set('page', practiScoreUpload())
        ->call('preview')
        ->assertSee('You already imported this match')
        ->assertDontSee('Import Match')
        ->call('save');

    expect(MatchResult::count())->toBe(1)
        ->and(Stage::count())->toBe(0);
});

it('lets different users import the same match', function () {
    MatchResult::create([
        'user_id' => User::factory()->create()->id,
        'name' => 'Theirs',
        'practiscore_match_id' => '99999999-9999-4999-8999-999999999999',
    ]);

    Livewire::actingAs($this->user)
        ->test('matches.import')
        ->set('page', practiScoreUpload())
        ->call('preview')
        ->call('save');

    expect($this->user->matchResults()->count())->toBe(1);
});

it('shows an error for a page without results', function () {
    Livewire::actingAs($this->user)
        ->test('matches.import')
        ->set('page', practiScoreUpload('<!DOCTYPE html><html><body>Index</body></html>'))
        ->call('preview')
        ->assertHasErrors('page');

    expect(MatchResult::count())->toBe(0);
});

it('rejects files that are not html', function () {
    Livewire::actingAs($this->user)
        ->test('matches.import')
        ->set('page', UploadedFile::fake()->create('results.pdf', 10, 'application/pdf'))
        ->call('preview')
        ->assertHasErrors('page');
});

it('cannot save without a reviewed file', function () {
    Livewire::actingAs($this->user)
        ->test('matches.import')
        ->call('save')
        ->assertStatus(422);
});
