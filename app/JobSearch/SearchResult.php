<?php

namespace App\JobSearch;

final readonly class SearchResult
{
    /**
     * @param  list<JobPosting>  $postings
     * @param  array<string, array{label: string, status: string, found: int, error?: string}>  $providers
     */
    public function __construct(
        public array $postings,
        public array $providers,
    ) {}
}
