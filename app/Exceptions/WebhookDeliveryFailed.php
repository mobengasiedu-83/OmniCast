<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a subscriber did not accept a webhook delivery.
 *
 * The queue treats it as a retryable failure; the delivery row already holds
 * what the subscriber answered.
 */
class WebhookDeliveryFailed extends RuntimeException {}
