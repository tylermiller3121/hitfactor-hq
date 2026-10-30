<?php

namespace App\Actions;

use App\Models\MatchResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ImportPractiScoreMatch
{
    /**
     * Create a match with its stages and results from parsed PractiScore data.
     *
     * @param  array<string, mixed>  $parsed
     */
    public function handle(User $user, array $parsed): MatchResult
    {
        return DB::transaction(function () use ($user, $parsed) {
            $match = $user->matchResults()->create($parsed['match']);

            foreach ($parsed['stages'] as $stage) {
                $result = $stage['result'];
                unset($stage['result']);

                $match->stages()
                    ->create([...$stage, 'sequence' => $stage['stage_number']])
                    ->result()
                    ->create($result);
            }

            return $match;
        });
    }
}
