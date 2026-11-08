<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the SMS transport refused a message an ESME submitted over SMPP.
 *
 * The queue retries it; the receipt that finally reaches the ESME is `UNDELIV`.
 */
class SmppDeliveryRejected extends RuntimeException {}
