<?php

declare(strict_types=1);

/**
 * Observer interface (class diagram: ReservationObserver).
 */
interface ReservationObserver
{
    public function update(ReservationEvent $event): void;
}
