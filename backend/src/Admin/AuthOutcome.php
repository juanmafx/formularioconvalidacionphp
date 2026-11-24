<?php

declare(strict_types=1);

namespace ContactForm\Admin;

enum AuthOutcome
{
    case Succeeded;
    case Rejected;
    case RateLimited;
    case Expired;
}
