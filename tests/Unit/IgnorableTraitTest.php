<?php

namespace Tests\Unit;

use App\Models\Domain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IgnorableTraitTest extends TestCase
{
    use RefreshDatabase;

    public function test_include_ignored_does_not_break_existing_filters(): void
    {
        $matchingUnignored = Domain::create([
            'tld' => 'match-unignored.com',
            'ignored_at' => null,
        ]);

        $matchingIgnored = Domain::create([
            'tld' => 'match-ignored.com',
            'ignored_at' => now(),
        ]);

        Domain::create([
            'tld' => 'other-ignored.com',
            'ignored_at' => now(),
        ]);

        $results = Domain::query()
            ->where('tld', 'like', 'match-%')
            ->includeIgnored()
            ->pluck('id')
            ->all();

        $this->assertEqualsCanonicalizing(
            [$matchingUnignored->id, $matchingIgnored->id],
            $results,
        );
    }

    public function test_exclude_ignored_only_returns_unignored_records(): void
    {
        $unignored = Domain::create([
            'tld' => 'visible.com',
            'ignored_at' => null,
        ]);

        Domain::create([
            'tld' => 'hidden.com',
            'ignored_at' => now(),
        ]);

        $results = Domain::query()
            ->excludeIgnored()
            ->pluck('id')
            ->all();

        $this->assertSame([$unignored->id], $results);
    }
}
