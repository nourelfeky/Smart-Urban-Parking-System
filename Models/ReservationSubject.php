<?php

declare(strict_types=1);

/**
 * Reservation subject — notifies observers after persistence succeeds (Observer pattern).
 */
final class ReservationSubject
{
    private static ?self $instance = null;

    /** @var list<ReservationObserver> */
    private array $observers = [];

    private function __construct()
    {
        // Availability first so spot state matches subsequent notification-side effects.
        $this->observers[] = new ParkingAvailabilityTracker();
        $this->observers[] = new NotificationService();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function attach(ReservationObserver $observer): void
    {
        $this->observers[] = $observer;
    }

    public function notifyObservers(ReservationEvent $event): void
    {
        foreach ($this->observers as $observer) {
            $observer->update($event);
        }
    }

    /** @alias notifyObservers — textbook Observer "notify" naming */
    public function notify(ReservationEvent $event): void
    {
        $this->notifyObservers($event);
    }
}
