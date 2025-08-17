<?php

declare(strict_types=1);

namespace App\Message;

use DateTimeImmutable;

final readonly class NotifyScheduledCourseMessage
{
    public function __construct(
        public string $reminder,
        public string $courseName,
        public DateTimeImmutable $courseDateTime,
    ) {}
}
