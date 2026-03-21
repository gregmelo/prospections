<?php

namespace App\DTO;

class SearchResult
{
    /**
     * @param Company[] $companies
     */
    public function __construct(
        public readonly array $companies,
        public readonly int $totalResults,
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $totalPages,
        public readonly bool $hasError = false,
        public readonly ?string $errorMessage = null,
        public readonly bool $isTruncated = false,
    ) {}

    public static function empty(): self
    {
        return new self(
            companies: [],
            totalResults: 0,
            page: 1,
            perPage: 25,
            totalPages: 0,
            hasError: false,
            errorMessage: null,
            isTruncated: false,
        );
    }

    public static function error(string $message): self
    {
        return new self(
            companies: [],
            totalResults: 0,
            page: 1,
            perPage: 25,
            totalPages: 0,
            hasError: true,
            errorMessage: $message,
            isTruncated: false,
        );
    }
}
