<?php

namespace App\Services;

use App\Exceptions\InvalidPractiScorePage;
use DOMDocument;
use DOMElement;
use DOMXPath;

class PractiScoreResultsParser
{
    private const REQUIRED_COLUMNS = [
        '#', '%', 'Stg Pts', 'Pts', 'HF', 'Time', 'Div', 'Class',
        'A', 'C', 'D', 'M', 'NS', 'Proc',
    ];

    private const POINTS_PER_ROUND = 5;

    /**
     * Read a shooter's match results from a saved PractiScore results page.
     *
     * @return array<string, mixed>
     */
    public function parse(string $html): array
    {
        $xpath = $this->xpath($html);

        $rows = $this->stageRows($xpath);

        $stages = [];

        foreach ($rows as $uuid => $stageRows) {
            $stages[] = $this->stage(
                $uuid,
                $this->stageLabel($xpath, $uuid, $stageRows),
                $stageRows,
            );
        }

        usort($stages, fn (array $a, array $b) => $a['stage_number'] <=> $b['stage_number']);

        $firstRow = array_values($rows)[0];
        $firstRow = $firstRow['division'] ?? $firstRow['combined'];

        [$discipline, $date] = $this->disciplineAndDate($xpath);
        $url = $this->resultsUrl($html);

        return [
            'shooter' => $this->text($xpath, '//*[@id="individualShooterName"]'),
            'match' => [
                'name' => $this->matchName($xpath),
                'club_name' => $this->text($xpath, '//div[@id="resultsBaseHolder"]//h4//a[contains(@href, "/clubs/")]'),
                'match_date' => $date,
                'discipline' => $discipline,
                'division' => $firstRow['Div'] ?: null,
                'classification' => $firstRow['Class'] ?: null,
                'practiscore_url' => $url,
                'practiscore_match_id' => $this->matchId($url),
            ],
            'stages' => $stages,
        ];
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);

        $document->loadHTML(
            '<?xml encoding="UTF-8">'.$html,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    private function stageRows(DOMXPath $xpath): array
    {
        $columns = [];

        foreach ($this->elements($xpath, '//table[@id="individualTable"]/thead/tr/th') as $index => $heading) {
            $columns[trim($heading->textContent)] = $index;
        }

        $rows = [];

        foreach ($this->elements($xpath, '//table[@id="individualTable"]/tbody/tr[@stageid]') as $row) {
            if (array_diff(self::REQUIRED_COLUMNS, array_keys($columns))) {
                throw new InvalidPractiScorePage(
                    'This results page has a layout we do not recognize.'
                );
            }

            $cells = $this->elements($xpath, './td', $row);

            $values = [];

            foreach ($columns as $name => $index) {
                $values[$name] = trim($cells[$index]->textContent ?? '');
            }

            $type = str_contains($row->getAttribute('class'), 'combinedRow')
                ? 'combined'
                : 'division';

            $rows[$row->getAttribute('stageid')][$type] = $values;
        }

        if ($rows === []) {
            throw new InvalidPractiScorePage(
                'No stage results were found. Select your name on the results page before saving it.'
            );
        }

        return $rows;
    }

    /**
     * @param  array<string, array<string, string>>  $rows
     */
    private function stageLabel(DOMXPath $xpath, string $uuid, array $rows): string
    {
        $option = $this->elements($xpath, sprintf('//option[@stageuuid="%s"]', $uuid))[0] ?? null;

        // The results table cuts long stage names short, so prefer the filter list.
        return $option?->getAttribute('key')
            ?: ($rows['division']['Stage'] ?? $rows['combined']['Stage'] ?? '');
    }

    /**
     * @param  array<string, array<string, string>>  $rows
     * @return array<string, mixed>
     */
    private function stage(string $uuid, string $label, array $rows): array
    {
        if (! preg_match('/^Stage\s+(\d+):?\s*(.*)$/', trim($label), $matches)) {
            throw new InvalidPractiScorePage("Could not read the stage \"{$label}\".");
        }

        $name = preg_replace('/\s+/', ' ', $matches[2]) ?? $matches[2];
        $classifierCode = null;

        if (preg_match('/^(.*?)\s*\((\d{2}-\d{2})\)$/', $name, $classifier)) {
            $name = $classifier[1];
            $classifierCode = $classifier[2];
        }

        // Match scoring is by division, so its row holds the official numbers.
        $row = $rows['division'] ?? $rows['combined'];

        $points = (int) $row['Pts'];
        $hitFactor = (float) $row['HF'];
        $percentage = (float) rtrim($row['%'], '%');
        $stagePoints = (float) $row['Stg Pts'];

        // PractiScore can leave the time blank, but hit factor is points / time.
        $timeCalculated = $row['Time'] === '' && $hitFactor > 0;
        $time = $timeCalculated
            ? round($points / $hitFactor, 2)
            : ($row['Time'] === '' ? null : (float) $row['Time']);

        $maximumPoints = $percentage > 0
            ? (int) round($stagePoints / ($percentage / 100))
            : null;

        return [
            'stage_number' => (int) $matches[1],
            'name' => $name !== '' ? $name : null,
            'is_classifier' => $classifierCode !== null,
            'classifier_code' => $classifierCode,
            'maximum_points' => $maximumPoints,
            'minimum_rounds' => $maximumPoints !== null
                ? (int) ceil($maximumPoints / self::POINTS_PER_ROUND)
                : null,
            'result' => [
                'time' => $time,
                'alpha' => (int) $row['A'],
                'charlie' => (int) $row['C'],
                'delta' => (int) $row['D'],
                'mike' => (int) $row['M'],
                'no_shoot' => (int) $row['NS'],
                'procedural' => (int) $row['Proc'],
                'points' => $points,
                'hit_factor' => $hitFactor,
                'stage_percentage' => $percentage,
                'stage_points' => $stagePoints,
                'overall_place' => isset($rows['combined']) ? (int) $rows['combined']['#'] : null,
                'division_place' => isset($rows['division']) ? (int) $rows['division']['#'] : null,
                'source' => 'practiscore',
                'source_data' => [
                    'stage_uuid' => $uuid,
                    'time_calculated' => $timeCalculated,
                    'no_penalty_miss' => (int) ($row['NPM'] ?? 0),
                    'additional_penalty' => (int) ($row['Apen'] ?? 0),
                ],
            ],
        ];
    }

    private function matchName(DOMXPath $xpath): string
    {
        $heading = $this->elements($xpath, '//div[@id="resultsBaseHolder"]//h4')[0] ?? null;

        $name = $heading?->firstChild?->textContent;

        if ($name === null || trim($name) === '') {
            throw new InvalidPractiScorePage('Could not read the match name.');
        }

        return trim($name);
    }

    /**
     * @return array{string, ?string}
     */
    private function disciplineAndDate(DOMXPath $xpath): array
    {
        $subtitle = $this->text($xpath, '//div[@id="resultsBaseHolder"]//h4/small[1]') ?? '';

        if (preg_match('/^(.*?)\s*(\d{4}-\d{2}-\d{2})$/', $subtitle, $matches)) {
            return [$matches[1] ?: 'USPSA', $matches[2]];
        }

        return ['USPSA', null];
    }

    private function resultsUrl(string $html): ?string
    {
        $pattern = '#https://(?:www\.)?practiscore\.com/results/[a-z]+/[0-9a-f-]{36}(?:\?[\w=&-]*)?#i';

        return preg_match($pattern, $html, $matches) ? $matches[0] : null;
    }

    private function matchId(?string $url): ?string
    {
        return $url && preg_match('#/([0-9a-f-]{36})#i', $url, $matches)
            ? strtolower($matches[1])
            : null;
    }

    private function text(DOMXPath $xpath, string $query): ?string
    {
        $text = trim($this->elements($xpath, $query)[0]->textContent ?? '');

        return $text !== '' ? (preg_replace('/\s+/', ' ', $text) ?? $text) : null;
    }

    /**
     * @return list<DOMElement>
     */
    private function elements(DOMXPath $xpath, string $query, ?DOMElement $context = null): array
    {
        $elements = [];

        foreach ($xpath->query($query, $context) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }
}
