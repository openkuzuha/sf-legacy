<?php

namespace App\Twig;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class DateTimeExtension extends AbstractExtension
{
    private readonly DateTimeZone $timezone;

    public function __construct(string $appTimezone)
    {
        $this->timezone = new DateTimeZone($appTimezone);
    }

    /** @return list<TwigFilter> */
    public function getFilters(): array
    {
        return [
            new TwigFilter('bbs_datetime', $this->format(...)),
        ];
    }

    public function format(string $timestamp, string $format = 'YYYY/MM/DD(ddd) HH:mm:ss'): string
    {
        return $this->date($timestamp)->isoFormat($format);
    }

    private function date(string $timestamp): CarbonImmutable
    {
        $date = CarbonImmutable::parse($timestamp)->setTimezone($this->timezone);
        $date->locale('ja');

        return $date;
    }
}
