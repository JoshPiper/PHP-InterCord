<?php

declare(strict_types=1);

namespace Internet\InterCord;

/**
 * A part of a message that Payload::clear() can remove when editing.
 */
enum PayloadField
{
	case Content;
	case Embeds;
}
